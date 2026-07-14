<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class AuditoriaE4logController extends Controller
{
    /**
     * Regras de Faturamento da E4LOG (Mínimo e % sobre a Nota Fiscal)
     */
    private const REGRAS_REGIAO = [
        'Região 1' => ['min' => 200.00, 'pct' => 0.02],
        'Região 2' => ['min' => 250.00, 'pct' => 0.03],
        'Região 3' => ['min' => 350.00, 'pct' => 0.03],
        'Região 4' => ['min' => 420.00, 'pct' => 0.04],
    ];

    public function processar(Request $request)
    {
        $request->validate(['files.*' => 'required|file|mimes:xml']);

        $batchId = $request->input('batch_id', Str::uuid()->toString());
        $resultadosAtuais = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                
                $data = $this->parseXml($file);
                if (!$data) continue; // Pula arquivos corrompidos

                $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
                
                // Extrações do XML
                $localDestino         = $this->extractCityAndUf($data);
                $cidadeDestino        = $localDestino['cidade'];
                $ufDestino            = $localDestino['uf'];
                
                $valorCarga           = $this->extractInvoiceValue($data);
                $valorCobradoOriginal = $this->extractFreightValue($data);
                $observacoesTexto     = $this->extractObs($data);
                $tipoCTe              = $this->extractTipoCTe($data);
                
                $temTde               = $this->verificarTde($data, $observacoesTexto, $tipoCTe);
                $tipoOperacao         = $this->extractTipoOperacao($observacoesTexto, $tipoCTe);
                $chaveCte             = $this->extractChaveCTe($data, $nomeArquivo);
                $chaveOriginal        = $this->extractChaveOriginal($data);
                $chaveNfe             = $this->extractChaveNFe($data); // Chave para a DRE
                
                $regiaoFaturadaData   = $this->descobrirRegiaoFaturada($observacoesTexto, $valorCarga, $valorCobradoOriginal, $temTde, $tipoOperacao);

                // 1. VERIFICAÇÃO DE UF E BUSCA DA TABELA OFICIAL E4LOG
                if ($ufDestino !== 'SP' && $ufDestino !== '') {
                    // Impede falso positivo se a tabela do estado não estiver parametrizada
                    $nomeRegiao = "Tabela " . $ufDestino . " Ausente";
                    $percentual = 0;
                    $minimo = 0;
                } else {
                    $nomeRegiao = $this->getRegiaoPorCidade($cidadeDestino);

                    // Fallback Inteligente (Caso a cidade esteja muito fora do padrão)
                    if ($nomeRegiao === '-') {
                        $regFat = strtolower($regiaoFaturadaData['nome']);
                        if (str_contains($regFat, '1')) $nomeRegiao = 'Região 1';
                        elseif (str_contains($regFat, '2')) $nomeRegiao = 'Região 2';
                        elseif (str_contains($regFat, '3')) $nomeRegiao = 'Região 3';
                        elseif (str_contains($regFat, '4')) $nomeRegiao = 'Região 4';
                        else $nomeRegiao = 'Indefinida/SP'; 
                        
                        if ($nomeRegiao !== 'Indefinida/SP') $nomeRegiao .= ' (Auto)';
                    }
                    
                    $regiaoBase = str_replace(' (Auto)', '', $nomeRegiao);
                    $percentual = self::REGRAS_REGIAO[$regiaoBase]['pct'] ?? 0;
                    $minimo     = self::REGRAS_REGIAO[$regiaoBase]['min'] ?? 0;
                }

                // 2. MATEMÁTICA DE AUDITORIA
                $freteBase = 0;
                $valorSlaCorreto = 0;
                $diferenca = 0;

                if ($tipoOperacao === 'Complemento') {
                    // Complemento é auditado apenas no agrupamento final com o pai
                    $freteBase = 0; 
                } else if ($nomeRegiao !== "Tabela {$ufDestino} Ausente" && $nomeRegiao !== 'Indefinida/SP') {
                    // Frete Base = Maior valor entre o frete Mínimo da região e a % sobre a NFe
                    $freteCalculado = $valorCarga * $percentual;
                    $freteBase = max($minimo, $freteCalculado);
                    
                    // Regra TDE: Mínimo R$160 ou 20% do frete base
                    $valorTde = $temTde ? max(160.00, $freteBase * 0.20) : 0;
                    
                    // Frete Final Matriz
                    $valorSlaCorreto = $freteBase + $valorTde;
                    $diferenca       = $valorSlaCorreto - $valorCobradoOriginal;
                }

                // 3. DEFINIÇÃO DO MOTIVO / STATUS
                if (str_contains($nomeRegiao, 'Ausente') || $nomeRegiao === 'Indefinida/SP') {
                    $status = 'Alerta';
                    $motivo = "Requer revisão manual (Tabela de valores não parametrizada para UF/Região).";
                    $diferenca = 0; // Zera para não poluir o balanço geral com falsos positivos
                } else {
                    if (round($diferenca, 2) > 0) {
                        $status = 'Divergente';
                        $motivo = "Cobrado a MENOS. E4LOG perdeu R$ " . number_format(abs($diferenca), 2, ',', '.');
                    } elseif (round($diferenca, 2) < 0) {
                        $status = 'Divergente';
                        $motivo = "Cobrado a MAIS. BWT pagou R$ " . number_format(abs($diferenca), 2, ',', '.') . " indevidamente.";
                    } else {
                        $status = 'Validado';
                        $motivo = "Validação 100% Exata com a Matriz.";
                    }
                }

                $resultadosAtuais[] = [
                    'chave_cte'           => $chaveCte,
                    'chave_nfe'           => $chaveNfe, // Fundamental para o Dashboard DRE
                    'arquivo'             => $nomeArquivo,
                    'cidade_destino'      => $cidadeDestino . ($ufDestino ? " - {$ufDestino}" : ''),
                    'regiao_sistema'      => $nomeRegiao,
                    'percentual_sistema'  => ($percentual > 0) ? ($percentual * 100) . '%' : '-',
                    'regiao_faturada'     => $regiaoFaturadaData['nome'],
                    'percentual_faturado' => $regiaoFaturadaData['pct'],
                    'tem_tde'             => $temTde ? 'Sim' : 'Não',
                    'tipo_operacao'       => $tipoOperacao,
                    'chave_original'      => $chaveOriginal,
                    'valor_carga'         => (float) $valorCarga,
                    
                    // --- SEPARAÇÃO FRETE E TDE ---
                    'valor_cobrado'       => (float) $valorCobradoOriginal,
                    'valor_frete_cobrado' => $tipoOperacao === 'Complemento' ? 0 : (float) $valorCobradoOriginal,
                    'valor_tde_cobrado'   => $tipoOperacao === 'Complemento' ? (float) $valorCobradoOriginal : 0,
                    // -----------------------------
                    
                    'valor_sla'           => (float) $valorSlaCorreto,
                    'diferenca'           => (float) $diferenca,
                    'status'              => $status,
                    'motivo'              => $motivo 
                ];
            }
        }

        $dadosEmCache = Cache::get('auditoria_e4log_' . $batchId, []);
        $dadosAtualizados = array_merge($dadosEmCache, $resultadosAtuais);
        Cache::put('auditoria_e4log_' . $batchId, $dadosAtualizados, now()->addHours(2));

        return response()->json([
            'batch_id' => $batchId,
            'data'     => $this->agruparResultados($dadosAtualizados)
        ]);
    }

    public function exportarPdf($batchId)
    {
        ini_set('max_execution_time', 0); 
        ini_set('memory_limit', '2G');    
        
        $dadosFlat = Cache::get('auditoria_e4log_' . $batchId);
        if (!$dadosFlat) abort(404, 'Sessão de auditoria expirada.');

        $dadosAgrupados = $this->agruparResultados($dadosFlat);
        $resumo = $this->gerarResumoExecutivo($dadosAgrupados);

        $pdf = Pdf::loadView('pdf.auditoria_e4log_report', [
            'dados'          => $dadosAgrupados,
            'resumo'         => $resumo,
            'data_auditoria' => now()->format('d/m/Y H:i')
        ]);

        $pdf->setPaper('A4', 'landscape'); 
        return $pdf->stream('auditoria_e4log_' . now()->format('YmdHi') . '.pdf');
    }

    private function agruparResultados($dadosFlat) {
        $agrupados = [];

        // 1. Mapeia CTEs Originais
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] !== 'Complemento') {
                $chave = $item['chave_cte'];
                $item['arquivos_complemento'] = []; 
                $agrupados[$chave] = $item;
            }
        }

        // 2. Associa Complementos
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] === 'Complemento') {
                $chavePai = $item['chave_original'];

                if ($chavePai && isset($agrupados[$chavePai])) {
                    $agrupados[$chavePai]['valor_cobrado'] += $item['valor_cobrado'];
                    
                    // --- SOMA DE VALORES SEPARADOS ---
                    $agrupados[$chavePai]['valor_frete_cobrado'] += $item['valor_frete_cobrado'];
                    $agrupados[$chavePai]['valor_tde_cobrado']   += $item['valor_tde_cobrado'];
                    // ---------------------------------
                    
                    // Recalcula a diferença pós-soma do complemento
                    if(!str_contains($agrupados[$chavePai]['regiao_sistema'], 'Ausente')){
                        $agrupados[$chavePai]['diferenca'] = $agrupados[$chavePai]['valor_sla'] - $agrupados[$chavePai]['valor_cobrado'];
                        
                        $diff = $agrupados[$chavePai]['diferenca'];
                        $agrupados[$chavePai]['status'] = round($diff, 2) == 0 ? 'Validado' : 'Divergente';
                    }
                    
                    $agrupados[$chavePai]['tem_tde'] = 'Sim';
                    $agrupados[$chavePai]['arquivos_complemento'][] = $item['arquivo'];
                } else {
                    $chave = $item['chave_cte'];
                    $item['arquivos_complemento'] = [];
                    $agrupados[$chave] = $item;
                }
            }
        }

        $resultadoFinal = array_values($agrupados);

        // Ordenação inteligente: Erros críticos primeiro, Exatos por último.
        usort($resultadoFinal, function($a, $b) {
            $diffA = round($a['diferenca'], 2);
            $diffB = round($b['diferenca'], 2);
            
            // Prioridade para Alertas de UF
            if ($a['status'] === 'Alerta' && $b['status'] !== 'Alerta') return -1;
            if ($a['status'] !== 'Alerta' && $b['status'] === 'Alerta') return 1;

            if ($diffA != 0 && $diffB == 0) return -1;
            if ($diffA == 0 && $diffB != 0) return 1;
            
            return strcmp($a['cidade_destino'] . $a['arquivo'], $b['cidade_destino'] . $b['arquivo']);
        });

        return $resultadoFinal;
    }

    private function descobrirRegiaoFaturada($obs, $valorCarga, $valorCobrado, $temTde, $tipoOperacao) {
        if ($tipoOperacao === 'Complemento') return ['nome' => 'Complemento', 'pct' => '-'];
        if ($valorCobrado <= 0) return ['nome' => 'Não Faturado', 'pct' => '-'];

        foreach (self::REGRAS_REGIAO as $nome => $regra) {
            $freteBase = max($regra['min'], $valorCarga * $regra['pct']);
            $tde = $temTde ? max(160.00, $freteBase * 0.20) : 0;
            
            if (abs(($freteBase + $tde) - $valorCobrado) <= 1.50) {
                return ['nome' => $nome . ' (Calc)', 'pct' => ($regra['pct'] * 100) . '%'];
            }
        }

        if (preg_match('/REGI[AÃ]O\s*(\d)/i', $obs, $matches)) {
            $num = $matches[1];
            $pct = '-';
            if ($num == '1') $pct = '2%';
            elseif ($num == '2') $pct = '3%';
            elseif ($num == '3') $pct = '3%';
            elseif ($num == '4') $pct = '4%';
            return ['nome' => 'Região ' . $num, 'pct' => $pct];
        }

        return ['nome' => 'Indefinida', 'pct' => '-'];
    }

    private function gerarResumoExecutivo($dados) {
        $f_menos = 0; $f_mais = 0;  
        $total_cobrado = 0; $total_sla = 0;

        foreach ($dados as $item) {
            $total_cobrado += $item['valor_cobrado'];
            // Apenas soma o SLA se estiver dentro das Regiões Válidas (evita distorcer com UFs de fora)
            if ($item['status'] !== 'Alerta') {
                $total_sla += $item['valor_sla'];
                if ($item['diferenca'] > 0) $f_menos += $item['diferenca'];
                elseif ($item['diferenca'] < 0) $f_mais += abs($item['diferenca']);
            }
        }

        return [
            'total_documentos' => count($dados),
            'total_cobrado'    => $total_cobrado,
            'total_sla'        => $total_sla,
            'balanco_geral'    => $f_mais - $f_menos, // Positivo = Prejuízo BWT; Negativo = E4LOG perdeu
            'faturado_a_menos' => $f_menos, // Economia
            'faturado_a_mais'  => $f_mais,  // Prejuízo
        ];
    }

    // --- Matriz Completa Oficial de Municípios ---
    private function getRegiaoPorCidade($cidade) {
        // Limpeza extrema para evitar bugs de dois espaços e acentos mal formatados
        $cidadeLpa = preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($cidade));
        $cidadeLpa = strtoupper(preg_replace('/\s+/', ' ', trim($cidadeLpa)));

        $cidadesPorRegiao = [
            'Região 1' => [
                'AMERICANA', 'CAMPINAS', 'COSMOPOLIS', 'ELIAS FAUSTO', 'HOLAMBRA', 'HORTOLANDIA', 'INDAIATUBA',
                'JAGUARIUNA', 'MONTE MOR', 'NOVA ODESSA', 'PAULINIA', 'PEDREIRA', 'SANTA BARBARA DOESTE', 'SUMARE',
                'VALINHOS', 'VINHEDO', 'CAMPO LIMPO PAULISTA', 'ITUPEVA', 'JUNDIAI', 'LOUVEIRA', 'VARZEA PAULISTA',
                'ATIBAIA', 'BOM JESUS DOS PERDOES', 'BRAGANCA PAULISTA', 'ITATIBA', 'JARINU', 'JOANOPOLIS',
                'MORUNGABA', 'NAZARE PAULISTA', 'PIRACAIA', 'TUIUTI', 'VARGEM'
            ],
            'Região 2' => [
                'LIMEIRA', 'ARARAS', 'CONCHAL', 'CORDEIROPOLIS', 'IRACEMAPOLIS', 'LEME', 'SANTA CRUZ DA CONCEICAO', 'SANTA GERTRUDES',
                'PIRACICABA', 'AGUAS DE SAO PEDRO', 'CAPIVARI', 'CHARQUEADA', 'JUMIRIM', 'MOMBUCA', 'RAFARD', 'RIO DAS PEDRAS', 'SALTINHO', 'SANTA MARIA DA SERRA', 'SAO PEDRO', 'TIETE',
                'PIRASSUNUNGA', 'AGUAI', 'PORTO FERREIRA', 'SANTA CRUZ DAS PALMEIRAS',
                'MOGI MIRIM', 'ARTUR NOGUEIRA', 'ENGENHEIRO COELHO', 'ESTIVA GERBI', 'ITAPIRA', 'MOGI GUACU', 'SANTO ANTONIO DE POSSE',
                'AMPARO', 'AGUAS DE LINDOIA', 'LINDOIA', 'MONTE ALEGRE DO SUL', 'PEDRA BELA', 'PINHALZINHO', 'SERRA NEGRA', 'SOCORRO',
                'OSASCO', 'BARUERI', 'CAJAMAR', 'CARAPICUIBA', 'ITAPEVI', 'JANDIRA', 'PIRAPORA DO BOM JESUS', 'SANTANA DE PARNAIBA',
                'FRANCO DA ROCHA', 'CAIEIRAS', 'FRANCISCO MORATO', 'MAIRIPORA',
                'GUARULHOS', 'ARUJA', 'SANTA ISABEL',
                'ITAPECERICA DA SERRA', 'COTIA', 'EMBU DAS ARTES', 'EMBUGUACU', 'JUQUITIBA', 'SAO LOURENCO DA SERRA', 'TABOAO DA SERRA', 'VARGEM GRANDE PAULISTA',
                'SAO PAULO', 'DIADEMA', 'MAUA', 'RIBEIRAO PIRES', 'RIO GRANDE DA SERRA', 'SANTO ANDRE', 'SAO BERNARDO DO CAMPO', 'SAO CAETANO DO SUL',
                'MOGI DAS CRUZES', 'BIRITIBA MIRIM', 'FERRAZ DE VASCONCELOS', 'GUARAREMA', 'ITAQUAQUECETUBA', 'POA', 'SALESOPOLIS', 'SUZANO',
                'SOROCABA', 'ALUMINIO', 'ARACARIGUAMA', 'ARACOIABA DA SERRA', 'CABREUVA', 'CAPELA DO ALTO', 'IPERO', 'ITU', 'MAIRINQUE', 'PORTO FELIZ', 'SALTO', 'SALTO DE PIRAPORA', 'SAO ROQUE', 'SARAPUI', 'VOTORANTIM', 'CERQUILHO', 'TATUI', 'BOITUVA'
            ],
            'Região 3' => [
                'AURIFLAMA', 'FLOREAL', 'GASTAO VIDIGAL', 'GENERAL SALGADO', 'GUZOLANDIA', 'MAGDA', 'NOVA CASTILHO', 'NOVA LUZITANIA', 'SAO JOAO DE IRACEMA',
                'NHANDEARA', 'MACAUBAL', 'MONCOES', 'MONTE APRAZIVEL', 'NEVES PAULISTA', 'NIPOA', 'POLONI', 'SEBASTIANOPOLIS DO SUL', 'UNIAO PAULISTA',
                'NOVO HORIZONTE', 'IRAPUA', 'ITAJOBI', 'MARAPOAMA', 'SALES', 'URUPES',
                'BURITAMA', 'ALTO ALEGRE', 'BARBOSA', 'BILAC', 'BRAUNA', 'BREJO ALEGRE', 'CLEMENTINA', 'COROADOS', 'GABRIEL MONTEIRO', 'LOURDES', 'LUIZIANIA', 'PIACATU', 'SANTOPOLIS DO AGUAPEI', 'TURIUBA',
                'LINS', 'CAFELANDIA', 'GETULINA', 'GUAICARA', 'GUAIMBE', 'JULIO MESQUITA', 'SABINO',
                'BAURU', 'AREALVA', 'AREIOPOLIS', 'AVAI', 'BALBINOS', 'BOREBI', 'CABRALIA PAULISTA', 'DUARTINA', 'GUARANTA', 'IACANGA',
                'PIRATININGA', 'LUCIANOPOLIS', 'PAULISTANIA', 'PIRAJUI', 'PONGAI', 'PRESIDENTE ALVES', 'REGINOPOLIS', 'UBIRAJARA', 'URU',
                'DRACENA', 'JUNQUEIROPOLIS', 'MONTE CASTELO', 'NOVA GUATAPORANGA', 'OURO VERDE', 'PANORAMA', 'PAULICEIA', 'SANTA MERCEDES', 'TUPI PAULISTA',
                'ADAMANTINA', 'FLORA RICA', 'FLORIDA PAULISTA', 'INUBIA PAULISTA', 'IRAPURU', 'LUCELIA', 'MARIAPOLIS', 'OSVALDO CRUZ', 'PACAEMBU', 'PARAPUA', 'PRACINHA', 'RINOPOLIS', 'SAGRES', 'SALMOURAO',
                'SAO JOSE DO RIO PRETO', 'CATANDUVA', 'ELISIARIO', 'SANTA ADELIA', 'SAO CARLOS', 'ARARAQUARA', 'MATAO', 'IBATE', 'RIO CLARO', 'SANTA SALETE', 'JALES', 'SANTA FE DO SUL', 'TRES FRONTEIRAS', 'FERNANDOPOLIS', 'MERIDIANO', 'COSMORAMA', 'VOTUPORANGA', 'BALSAMO', 'URANIA', 'CEDRAL', 'TANABI', 'ESTRELA DOESTE', 'MIRASSOL',
                'SAO JOAO DA BOA VISTA', 'AGUAS DA PRATA', 'CACONDE', 'CASA BRANCA', 'DIVINOLANDIA', 'ESPIRITO SANTO DO PINHAL', 'ITOBI', 'MOCOCA', 'SANTO ANTONIO DO JARDIM', 'SAO JOSE DO RIO PARDO', 'SAO SEBASTIAO DA GRAMA', 'TAMBAU', 'TAPIRATIBA', 'VARGEM GRANDE DO SUL',
                'SAO JOSE DOS CAMPOS', 'CACAPAVA', 'IGARATA', 'JACAREI', 'PINDAMONHANGABA', 'SANTA BRANCA', 'TAUBATE', 'TREMEMBE',
                'TUPA', 'ARCOIRIS', 'BASTOS', 'HERCULANDIA', 'IACRI', 'QUEIROZ', 'QUINTANA',
                'MARILIA', 'ALVARO DE CARVALHO', 'ALVINLANDIA', 'ECHAPORA', 'FERNAO', 'GALIA', 'GARCA', 'LUPERCIO', 'OCAUCU', 'ORIENTE', 'OSCAR BRESSANE', 'POMPEIA', 'VERA CRUZ',
                'ADOLFO', 'ALTAIR', 'BADY BASSITT', 'CARDOSO', 'GUAPIACU', 'PARISI', 'PONTES GESTAL', 'RIOLANDIA', 'VALENTIM GENTIL',
                'NOVA GRANADA', 'GUARACI', 'IBIRA', 'ICEM', 'IPIGUA', 'JACI', 'JOSE BONIFACIO', 'MENDONCA', 'ZACARIAS', 'MIRASSOLANDIA', 'NOVA ALIANCA', 'ONDA VERDE', 'ORINDIUVA', 'PALESTINA', 'PAULO DE FARIA', 'PLANALTO', 'POTIRENDABA', 'UBARANA', 'UCHOA',
                'ARACATUBA', 'ANDRADINA', 'AVANHANDAVA', 'BIRIGUI', 'CASTILHO', 'GLICERIO', 'GUARARAPES', 'MURUTINGA DO SUL', 'PENAPOLIS', 'PROMISSAO', 'VALPARAISO',
                'JAU', 'AGUDOS', 'BARRA BONITA', 'DOIS CORREGOS', 'IGARACU DO TIETE', 'LENCOIS PAULISTA', 'MINEIROS DO TIETE', 'BROTAS', 'TORRINHA', 'PEDERNEIRAS', 'SAO MANUEL',
                'RIBEIRAO PRETO', 'BARRINHA', 'BRODOWSKI', 'CRAVINHOS', 'DUMONT', 'GUATAPARA', 'JARDINOPOLIS', 'LUIZ ANTONIO', 'PONTAL', 'PRADOPOLIS', 'SANTA RITA DO PASSA QUATRO', 'SANTA ROSA DE VITERBO', 'SAO SIMAO',
                'FRANCA', 'SERRA AZUL', 'SERRANA', 'SERTAOZINHO', 'ALTINOPOLIS', 'BATATAIS', 'CAJURU', 'CASSIA DOS COQUEIROS', 'SANTA CRUZ DA ESPERANCA', 'SANTO ANTONIO DA ALEGRIA',
                'BARRETOS', 'OLIMPIA', 'COLINA', 'TERRA ROXA',
                'JABOTICABAL', 'MONTE ALTO', 'BEBEDOURO', 'COLOMBIA', 'GUAIRA', 'IPUA', 'JABORANDI', 'MIGUELOPOLIS', 'MORRO AGUDO',
                'SAO JOAQUIM DA BARRA', 'BURITIZAL', 'GUARA', 'IGARAPAVA', 'ITUVERAVA', 'CRISTAIS PAULISTA', 'ITIRAPUA', 'JERIQUARA', 'PATROCINIO PAULISTA', 'PEDREGULHO',
                'CANDIDO RODRIGUES', 'FERNANDO PRESTES', 'GUARIBA', 'MONTE AZUL PAULISTA', 'PIRANGI', 'PITANGUEIRAS', 'TAIACU', 'TAQUARAL', 'TAQUARITINGA', 'VIRADOURO', 'VISTA ALEGRE DO ALTO',
                'OUROESTE', 'ALVARES FLORENCE', 'AMERICO DE CAMPOS', 'GUARANI DOESTE', 'INDIAPORA', 'MACEDONIA', 'MIRA ESTRELA', 'PEDRANOPOLIS', 'SAO JOAO DAS DUAS PONTES', 'TURMALINA',
                'PONTALINDA', 'ASPASIA', 'DIRCE REIS', 'DOLCINOPOLIS', 'JAMBEIRO', 'MARINOPOLIS', 'MESOPOLIS', 'NOVA CANAA PAULISTA', 'PALMEIRA DOESTE', 'PARANAPUA', 'POPULINA', 'RUBINEIA', 'SANTA ALBERTINA', 'SANTA CLARA DOESTE', 'SANTA RITA DOESTE', 'SANTANA DA PONTE PENSA', 'SAO FRANCISCO', 'VITORIA BRASIL'
            ],
            'Região 4' => [
                'CAMPOS DO JORDAO', 'MONTEIRO LOBATO', 'SANTO ANTONIO DO PINHAL', 'SAO BENTO DO SAPUCAI',
                'GUARATINGUETA', 'APARECIDA', 'CACHOEIRA PAULISTA', 'CANAS', 'CRUZEIRO', 'LAVRINHAS', 'LORENA', 'PIQUETE', 'POTIM', 'QUELUZ', 'ROSEIRA',
                'PARAIBUNA', 'PARAITINGA', 'ARAPEI', 'AREIAS', 'BANANAL', 'CUNHA', 'SAO JOSE DO BARREIRO', 'SILVEIRAS',
                'CARAGUATATUBA', 'ILHABELA', 'SAO SEBASTIAO', 'UBATUBA',
                'PRESIDENTE PRUDENTE', 'ALFREDO MARCONDES', 'ALVARES MACHADO', 'ANHUMAS', 'CAIABU', 'CAIUA', 'EMILIANOPOLIS', 'ESTRELA DO NORTE', 'EUCLIDES DA CUNHA PAULISTA', 'INDIANA', 'JOAO RAMALHO', 'MARABA PAULISTA', 'MARTINOPOLIS', 'MIRANTE DO PARANAPANEMA', 'NARANDIBA', 'PIQUEROBI', 'PIRAPOZINHO', 'PRESIDENTE BERNARDES', 'PRESIDENTE EPITACIO', 'PRESIDENTE VENCESLAU', 'RANCHARIA', 'REGENTE FEIJO', 'RIBEIRAO DOS INDIOS', 'SANDOVALINA', 'SANTO ANASTACIO', 'SANTO EXPEDITO', 'TACIBA', 'TARABAI', 'TEODORO SAMPAIO', 'ROSANA',
                'CAPAO BONITO', 'APIAI', 'BARRA DO CHAPEU', 'GUAPIARA', 'IPORANGA', 'ITAOCA', 'ITAPIRAPUA PAULISTA', 'RIBEIRA', 'RIBEIRAO BRANCO', 'RIBEIRAO GRANDE',
                'REGISTRO', 'BARRA DO TURVO', 'CAJATI', 'CANANEIA', 'ELDORADO', 'IGUAPE', 'ILHA COMPRIDA', 'JACUPIRANGA', 'JUQUIA', 'MIRACATU', 'PARIQUERAACU', 'SETE BARRAS',
                'ITAPETININGA', 'ALAMBARI', 'ANGATUBA', 'CAMPINA DO MONTE ALEGRE', 'GUAREI',
                'BOCAINA', 'BARIRI', 'BORACEIA', 'ITAJU', 'ITAPUI', 'MACATUBA',
                'AVARE', 'AGUAS DE SANTA BARBARA', 'ARANDU', 'CERQUEIRA CESAR', 'IARAS', 'ITAI', 'ITATINGA', 'PARANAPANEMA',
                'PEREIRA BARRETO', 'BENTO DE ABREU', 'GUARACAI', 'ILHA SOLTEIRA', 'ITAPURA', 'LAVINIA', 'MIRANDOPOLIS', 'NOVA INDEPENDENCIA', 'RUBIACEA', 'SAO JOAO DO PAUDALHO', 'SUD MENNUCCI', 'SUZANAPOLIS',
                'ITAPEVA', 'BARAO DE ANTONINA', 'BOM SUCESSO DE ITARARE', 'BURI', 'CORONEL MACEDO', 'ITABERA', 'ITAPORANGA', 'ITARARE', 'NOVA CAMPINA', 'RIVERSUL', 'STO ANT DO ARACANGUA', 'TAQUARITUBA', 'TAQUARIVAI',
                'ITANHAEM', 'BERTIOGA', 'CUBATAO', 'GUARUJA', 'PRAIA GRANDE', 'SANTOS', 'SAO VICENTE',
                'ITARIRI', 'MONGAGUA', 'PEDRO DE TOLEDO', 'PERUIBE',
                'ASSIS', 'BORA', 'CAMPOS NOVOS PAULISTA', 'CANDIDO MOTA', 'CRUZALIA', 'FLORINEA', 'IBIRAREMA', 'IEPE', 'LUTECIA', 'MARACAI', 'NANTES', 'PALMITAL', 'PARAGUACU PAULISTA', 'PEDRINHAS PAULISTA', 'PLATINA', 'QUATA', 'TARUMA',
                'OURINHOS', 'BERNARDINO DE CAMPOS', 'CANITAR', 'CHAVANTES', 'ESPIRITO SANTO DO TURVO', 'FARTURA', 'IPAUSSU', 'MANDURI', 'OLEO', 'PIRAJU', 'RIBEIRAO DO SUL', 'SALTO GRANDE', 'SANTA CRUZ DO RIO PARDO', 'SAO PEDRO DO TURVO', 'SARUTAIA', 'TAGUAI', 'TEJUPA', 'TIMBURI',
                'LARANJA PAULISTA', 'CESARIO LANGE', 'LARANJAL PAULISTA', 'PEREIRAS', 'PORANGABA', 'QUADRA', 'TORRE DE PEDRA',
                'IBIUNA', 'PIEDADE', 'PILAR DO SUL', 'SAO MIGUEL ARCANJO', 'TAPIRAI',
                'BOTUCATU', 'ANHEMBI', 'BOFETE', 'CONCHAS', 'PARDINHO', 'PRATANIA'
            ]
        ];

        foreach ($cidadesPorRegiao as $regiao => $cidades) {
            if (in_array($cidadeLpa, $cidades)) return $regiao;
        }

        foreach ($cidadesPorRegiao as $regiao => $cidades) {
            foreach ($cidades as $c) {
                if (str_contains($cidadeLpa, $c) || str_contains($c, $cidadeLpa)) return $regiao;
            }
        }

        return '-';
    }

    // --- Auxiliares de Leitura de XML ---
    private function parseXml($file) {
        try {
            $xmlContent = file_get_contents($file->getPathname());
            $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
            $xmlObj = simplexml_load_string($xmlContent);
            return json_decode(json_encode($xmlObj), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractChaveCTe($data, $nomeArquivo) {
        if (isset($data['protCTe']['infProt']['chCTe'])) return (string) $data['protCTe']['infProt']['chCTe'];
        $base = $this->getBaseNode($data);
        if ($base && isset($base['@attributes']['Id'])) return str_replace('CTe', '', $base['@attributes']['Id']);
        if (preg_match('/\\d{44}/', $nomeArquivo, $matches)) return $matches[0];
        return Str::uuid()->toString();
    }
    
    // --- CORREÇÃO 1: Rastreio de NFe em TDEs (Extrai da OBS se não for CT-e normal) ---
    private function extractChaveNFe($data) {
        $base = $this->getBaseNode($data);
        if (isset($base['infCTeNorm']['infDoc']['infNFe']['chave'])) {
            return (string) $base['infCTeNorm']['infDoc']['infNFe']['chave'];
        }
        
        $obs = $this->extractObs($data);
        if (preg_match('/NF\s*[:\-]?\s*(\d+)/i', $obs, $matches)) {
            return 'NF_EXTRAIDA_' . $matches[1]; 
        }

        return 'SEM_NFE_' . Str::uuid()->toString(); 
    }

    // --- CORREÇÃO 2: A tag XML V4 da Sefaz para complementos se chama 'chCTe' ---
    private function extractChaveOriginal($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCteComp']['chCTe'])) return (string) $base['infCteComp']['chCTe'];
        if ($base && isset($base['infCteComp']['chave'])) return (string) $base['infCteComp']['chave'];
        return null;
    }
    
    private function extractTipoOperacao($observacoes, $tipoCTe) { 
        $obs = strtoupper($observacoes);
        if (str_contains($obs, 'DEVOLUCAO') || str_contains($obs, 'RETORNO')) return 'Devolução'; 
        if (str_contains($obs, 'REENTREGA')) return 'Reentrega'; 
        if ($tipoCTe == '1' || str_contains($obs, 'COMPL')) return 'Complemento'; 
        return 'Entrega'; 
    }
    
    private function verificarTde($data, $obs, $tipoCTe) {
        if (str_contains(strtoupper($obs), 'TDE') || str_contains(strtoupper($obs), 'RURAL')) return true;
        if ($tipoCTe === '0') {
            $base = $this->getBaseNode($data);
            if ($base && isset($base['vPrest']['Comp'])) {
                $comps = $base['vPrest']['Comp'];
                if (isset($comps['xNome'])) $comps = [$comps];
                foreach ($comps as $c) {
                    $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                    if (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE')) return true;
                }
            }
        }
        return false;
    }
    
    private function getBaseNode($data) { 
        if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; 
        if (isset($data['infCte'])) return $data['infCte']; 
        return null; 
    }
    
    private function extractCityAndUf($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['dest']['enderDest']['xMun'])) {
            return [
                'cidade' => strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')),
                'uf'     => strtoupper((string) ($base['dest']['enderDest']['UF'] ?? ''))
            ];
        }
        return ['cidade' => 'Desconhecida', 'uf' => '']; 
    }
    
    private function extractInvoiceValue($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; 
        if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga']; 
        return 0.00; 
    }
    
    private function extractFreightValue($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest']; 
        return 0.00; 
    }
    
    private function extractObs($data) { 
        $base = $this->getBaseNode($data); 
        $obs = '';
        if ($base && isset($base['compl']['xObs'])) $obs .= (string) $base['compl']['xObs']; 
        return strtoupper($obs);
    }
    
    private function extractTipoCTe($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; 
        return '0'; 
    }
}
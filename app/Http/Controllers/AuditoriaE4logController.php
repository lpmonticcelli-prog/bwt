<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class AuditoriaE4logController extends Controller
{
    /**
     * Nomenclaturas Oficiais da Matriz
     */
    private const NOMENCLATURA_R1 = 'REGIÃO 1 - REGIÃO DE SP E CAMPINAS';
    private const NOMENCLATURA_R2 = 'REGIÃO 2 - CENTRO DO ESTADO';
    private const NOMENCLATURA_R3 = 'REGIÃO 3 - REGIÕES DISTANTES';
    private const NOMENCLATURA_R4 = 'REGIÃO 4 - BAIXA DEMANDA';

    /**
     * Regras de Faturamento da E4LOG (Mínimo e % sobre a Nota Fiscal)
     */
    private const REGRAS_REGIAO = [
        self::NOMENCLATURA_R1 => ['min' => 200.00, 'pct' => 0.02],
        self::NOMENCLATURA_R2 => ['min' => 250.00, 'pct' => 0.03],
        self::NOMENCLATURA_R3 => ['min' => 350.00, 'pct' => 0.03],
        self::NOMENCLATURA_R4 => ['min' => 420.00, 'pct' => 0.04],
    ];

    /**
     * Matriz Completa Oficial de Municípios e Regiões de SP (Tabela E4LOG 2026)
     */
    private const CIDADES_POR_REGIAO = [
        self::NOMENCLATURA_R1 => [
            'AMERICANA', 'CAMPINAS', 'COSMOPOLIS', 'ELIAS FAUSTO', 'HOLAMBRA', 'HORTOLANDIA', 'INDAIATUBA', 'JAGUARIUNA', 'MONTE MOR', 'NOVA ODESSA', 'PAULINIA', 'PEDREIRA', 'SANTA BARBARA DOESTE', 'SUMARE', 'VALINHOS', 'VINHEDO',
            'CAMPO LIMPO PAULISTA', 'ITUPEVA', 'JUNDIAI', 'LOUVEIRA', 'VARZEA PAULISTA',
            'ATIBAIA', 'BOM JESUS DOS PERDOES', 'BRAGANCA PAULISTA', 'ITATIBA', 'JARINU', 'JOANOPOLIS', 'MORUNGABA', 'NAZARE PAULISTA', 'PIRACAIA', 'TUIUTI', 'VARGEM'
        ],
        self::NOMENCLATURA_R2 => [
            'ARARAS', 'CONCHAL', 'CORDEIROPOLIS', 'IRACEMAPOLIS', 'LEME', 'LIMEIRA', 'SANTA CRUZ DA CONCEICAO', 'SANTA GERTRUDES',
            'AGUAS DE SAO PEDRO', 'CAPIVARI', 'CHARQUEADA', 'JUMIRIM', 'MOMBUCA', 'PIRACICABA', 'RAFARD', 'RIO DAS PEDRAS', 'SALTINHO', 'SANTA MARIA DA SERRA', 'SAO PEDRO', 'TIETE',
            'PIRASSUNUNGA', 'PORTO FERREIRA', 'SANTA CRUZ DAS PALMEIRAS', 'AGUAI',
            'ARTUR NOGUEIRA', 'ENGENHEIRO COELHO', 'ESTIVA GERBI', 'ITAPIRA', 'MOGI GUACU', 'MOGI MIRIM', 'SANTO ANTONIO DE POSSE',
            'AGUAS DE LINDOIA', 'AMPARO', 'LINDOIA', 'MONTE ALEGRE DO SUL', 'PEDRA BELA', 'PINHALZINHO', 'SERRA NEGRA', 'SOCORRO',
            'ALUMINIO', 'ARACARIGUAMA', 'ARACOIABA DA SERRA', 'CABREUVA', 'CAPELA DO ALTO', 'IPERO', 'ITU', 'MAIRINQUE', 'PORTO FELIZ', 'SALTO', 'SALTO DE PIRAPORA', 'SAO ROQUE', 'SARAPUI', 'SOROCABA', 'VOTORANTIM', 'CERQUILHO', 'TATUI', 'BOITUVA',
            
            // Região da Grande SP, Guarulhos, ABC, Mogi, Osasco
            'ARUJA', 'GUARULHOS', 'SANTA ISABEL', 
            'COTIA', 'EMBU DAS ARTES', 'EMBUGUACU', 'ITAPECERICA DA SERRA', 'JUQUITIBA', 'SAO LOURENCO DA SERRA', 'TABOAO DA SERRA', 'VARGEM GRANDE PAULISTA',
            'BARUERI', 'CAJAMAR', 'CARAPICUIBA', 'ITAPEVI', 'JANDIRA', 'OSASCO', 'PIRAPORA DO BOM JESUS', 'SANTANA DE PARNAIBA',
            'CAIEIRAS', 'FRANCISCO MORATO', 'FRANCO DA ROCHA', 'MAIRIPORA',
            'DIADEMA', 'MAUA', 'RIBEIRAO PIRES', 'RIO GRANDE DA SERRA', 'SANTO ANDRE', 'SAO BERNARDO DO CAMPO', 'SAO CAETANO DO SUL', 'SAO PAULO',
            'BIRITIBA MIRIM', 'FERRAZ DE VASCONCELOS', 'GUARAREMA', 'ITAQUAQUECETUBA', 'MOGI DAS CRUZES', 'POA', 'SALESOPOLIS', 'SUZANO'
        ],
        self::NOMENCLATURA_R3 => [
            'AURIFLAMA', 'FLOREAL', 'GASTAO VIDIGAL', 'GENERAL SALGADO', 'GUZOLANDIA', 'MAGDA', 'NOVA CASTILHO', 'NOVA LUZITANIA', 'SAO JOAO DE IRACEMA',
            'MACAUBAL', 'MONCOES', 'MONTE APRAZIVEL', 'NEVES PAULISTA', 'NHANDEARA', 'NIPOA', 'POLONI', 'SEBASTIANOPOLIS DO SUL', 'UNIAO PAULISTA',
            'IRAPUA', 'ITAJOBI', 'MARAPOAMA', 'NOVO HORIZONTE', 'SALES', 'URUPES',
            'ALTO ALEGRE', 'BARBOSA', 'BILAC', 'BRAUNA', 'BREJO ALEGRE', 'BURITAMA', 'CLEMENTINA', 'COROADOS', 'GABRIEL MONTEIRO', 'LOURDES', 'LUIZIANIA', 'PIACATU', 'SANTOPOLIS DO AGUAPEI', 'TURIUBA',
            'LUCIANOPOLIS', 'PAULISTANIA', 'PIRAJUI', 'PIRATININGA', 'PONGAI', 'PRESIDENTE ALVES', 'REGINOPOLIS', 'UBIRAJARA', 'URU',
            'DRACENA', 'JUNQUEIROPOLIS', 'MONTE CASTELO', 'NOVA GUATAPORANGA', 'OURO VERDE', 'PANORAMA', 'PAULICEIA', 'SANTA MERCEDES', 'TUPI PAULISTA',
            'ADAMANTINA', 'FLORA RICA', 'FLORIDA PAULISTA', 'INUBIA PAULISTA', 'IRAPURU', 'LUCELIA', 'MARIAPOLIS', 'OSVALDO CRUZ', 'PACAEMBU', 'PARAPUA', 'PRACINHA', 'RINOPOLIS', 'SAGRES', 'SALMOURAO',
            'CATANDUVA', 'ELISIARIO', 'SANTA ADELIA', 'SAO CARLOS', 'ARARAQUARA', 'MATAO', 'IBATE', 'RIO CLARO', 'SANTA SALETE', 'JALES', 'SANTA FE DO SUL', 'TRES FRONTEIRAS', 'SAO JOSE DO RIO PRETO', 'FERNANDOPOLIS', 'MERIDIANO', 'COSMORAMA', 'VOTUPORANGA', 'BALSAMO', 'URANIA', 'CEDRAL', 'TANABI', 'ESTRELA DOESTE', 'MIRASSOL',
            'AGUAS DA PRATA', 'CACONDE', 'CASA BRANCA', 'DIVINOLANDIA', 'ESPIRITO SANTO DO PINHAL', 'ITOBI', 'MOCOCA', 'SANTO ANTONIO DO JARDIM', 'SAO JOAO DA BOA VISTA', 'SAO JOSE DO RIO PARDO', 'SAO SEBASTIAO DA GRAMA', 'TAMBAU', 'TAPIRATIBA', 'VARGEM GRANDE DO SUL',
            'CACAPAVA', 'IGARATA', 'JACAREI', 'PINDAMONHANGABA', 'SANTA BRANCA', 'SAO JOSE DOS CAMPOS', 'TAUBATE', 'TREMEMBE',
            'ALTINOPOLIS', 'BATATAIS', 'CAJURU', 'CASSIA DOS COQUEIROS', 'SANTA CRUZ DA ESPERANCA', 'SANTO ANTONIO DA ALEGRIA',
            'OLIMPIA', 'BARRETOS', 'COLINA', 'TERRA ROXA',
            'ARCOIRIS', 'BASTOS', 'HERCULANDIA', 'IACRI', 'QUEIROZ', 'QUINTANA', 'TUPA',
            'ALVARO DE CARVALHO', 'ALVINLANDIA', 'ECHAPORA', 'FERNAO', 'GALIA', 'GARCA', 'LUPERCIO', 'MARILIA', 'OCAUCU', 'ORIENTE', 'OSCAR BRESSANE', 'POMPEIA', 'VERA CRUZ',
            'ADOLFO', 'ALTAIR', 'BADY BASSITT', 'CARDOSO', 'GUAPIACU', 'PARISI', 'PONTES GESTAL', 'RIOLANDIA', 'VALENTIM GENTIL',
            'GUARACI', 'IBIRA', 'ICEM', 'IPIGUA', 'JACI', 'JOSE BONIFACIO', 'MENDONCA', 'ZACARIAS', 'MIRASSOLANDIA', 'NOVA ALIANCA', 'NOVA GRANADA', 'ONDA VERDE', 'ORINDIUVA', 'PALESTINA', 'PAULO DE FARIA', 'PLANALTO', 'POTIRENDABA', 'UBARANA', 'UCHOA',
            'ANDRADINA', 'ARACATUBA', 'AVANHANDAVA', 'BIRIGUI', 'CASTILHO', 'GLICERIO', 'GUARARAPES', 'LINS', 'MURUTINGA DO SUL', 'PENAPOLIS', 'PROMISSAO', 'VALPARAISO',
            'AGUDOS', 'BARRA BONITA', 'BAURU', 'DOIS CORREGOS', 'IGARACU DO TIETE', 'JAU', 'LENCOIS PAULISTA', 'MINEIROS DO TIETE', 'BROTAS', 'TORRINHA', 'PEDERNEIRAS', 'SAO MANUEL',
            'BARRINHA', 'BRODOWSKI', 'CRAVINHOS', 'DUMONT', 'GUATAPARA', 'JARDINOPOLIS', 'LUIZ ANTONIO', 'PONTAL', 'PRADOPOLIS', 'RIBEIRAO PRETO', 'SANTA RITA DO PASSA QUATRO', 'SANTA ROSA DE VITERBO', 'SAO SIMAO',
            'BURITIZAL', 'GUARA', 'IGARAPAVA', 'ITUVERAVA', 'CRISTAIS PAULISTA', 'FRANCA', 'ITIRAPUA', 'JERIQUARA', 'PATROCINIO PAULISTA', 'PEDREGULHO', 'RIBEIRAO CORRENTE', 'RIFAINA', 'SANTA ERNESTINA', 'SAO JOSE DA BELA VISTA', 'TAIACU', 'ARAMINA', 'TAQUARAL', 'TAQUARITINGA', 'VIRADOURO', 'VISTA ALEGRE DO ALTO',
            'CANDIDO RODRIGUES', 'FERNANDO PRESTES', 'GUARIBA', 'MONTE AZUL PAULISTA', 'PIRANGI', 'PITANGUEIRAS', 'TAIUVA', 'JABOTICABAL', 'MONTE ALTO', 'BEBEDOURO', 'COLOMBIA', 'GUAIRA', 'IPUA', 'JABORANDI', 'MIGUELOPOLIS', 'MORRO AGUDO', 'SAO JOAQUIM DA BARRA'
        ],
        self::NOMENCLATURA_R4 => [
            'CAMPOS DO JORDAO', 'MONTEIRO LOBATO', 'SANTO ANTONIO DO PINHAL', 'SAO BENTO DO SAPUCAI',
            'APARECIDA', 'CACHOEIRA PAULISTA', 'CANAS', 'CRUZEIRO', 'GUARATINGUETA', 'LAVRINHAS', 'LORENA', 'PIQUETE', 'POTIM', 'QUELUZ', 'ROSEIRA',
            'ARAPEI', 'AREIAS', 'BANANAL', 'CUNHA', 'PARAIBUNA', 'PARAITINGA', 'SAO JOSE DO BARREIRO', 'SILVEIRAS',
            'CARAGUATATUBA', 'ILHABELA', 'SAO SEBASTIAO', 'UBATUBA',
            'ALFREDO MARCONDES', 'ALVARES MACHADO', 'ANHUMAS', 'CAIABU', 'CAIUA', 'EMILIANOPOLIS', 'ESTRELA DO NORTE', 'EUCLIDES DA CUNHA PAULISTA', 'INDIANA', 'JOAO RAMALHO', 'MARABA PAULISTA', 'MARTINOPOLIS', 'MIRANTE DO PARANAPANEMA', 'NARANDIBA', 'PIQUEROBI', 'PIRAPOZINHO', 'PRESIDENTE BERNARDES', 'PRESIDENTE EPITACIO', 'PRESIDENTE PRUDENTE', 'PRESIDENTE VENCESLAU', 'RANCHARIA', 'REGENTE FEIJO', 'RIBEIRAO DOS INDIOS', 'SANDOVALINA', 'SANTO ANASTACIO', 'SANTO EXPEDITO', 'TACIBA', 'TARABAI', 'TEODORO SAMPAIO', 'ROSANA',
            'APIAI', 'BARRA DO CHAPEU', 'CAPAO BONITO', 'GUAPIARA', 'IPORANGA', 'ITAOCA', 'ITAPIRAPUA PAULISTA', 'RIBEIRA', 'RIBEIRAO BRANCO', 'RIBEIRAO GRANDE',
            'BARRA DO TURVO', 'CAJATI', 'CANANEIA', 'ELDORADO', 'IGUAPE', 'ILHA COMPRIDA', 'JACUPIRANGA', 'JUQUIA', 'MIRACATU', 'PARIQUERAACU', 'REGISTRO', 'SETE BARRAS',
            'ALAMBARI', 'ANGATUBA', 'CAMPINA DO MONTE ALEGRE', 'GUAREI', 'ITAPETININGA', 'SARAPUI',
            'BARIRI', 'BOCAINA', 'BORACEIA', 'ITAJU', 'ITAPUI', 'MACATUBA',
            'AGUAS DE SANTA BARBARA', 'ARANDU', 'AVARE', 'CERQUEIRA CESAR', 'IARAS', 'ITAI', 'ITATINGA', 'PARANAPANEMA',
            'BENTO DE ABREU', 'GUARACAI', 'ILHA SOLTEIRA', 'ITAPURA', 'LAVINIA', 'MIRANDOPOLIS', 'NOVA INDEPENDENCIA', 'PEREIRA BARRETO', 'RUBIACEA', 'SAO JOAO DO PAUDALHO', 'SUD MENNUCCI', 'SUZANAPOLIS',
            'BARAO DE ANTONINA', 'BOM SUCESSO DE ITARARE', 'BURI', 'CORONEL MACEDO', 'ITABERA', 'ITAPEVA', 'ITAPORANGA', 'ITARARE', 'NOVA CAMPINA', 'RIVERSUL', 'STO ANT DO ARACANGUA', 'TAQUARITUBA', 'TAQUARIVAI',
            'BERTIOGA', 'CUBATAO', 'GUARUJA', 'PRAIA GRANDE', 'SANTOS', 'SAO VICENTE',
            'ITANHAEM', 'ITARIRI', 'MONGAGUA', 'PEDRO DE TOLEDO', 'PERUIBE',
            'ASSIS', 'BORA', 'CAMPOS NOVOS PAULISTA', 'CANDIDO MOTA', 'CRUZALIA', 'FLORINEA', 'IBIRAREMA', 'IEPE', 'LUTECIA', 'MARACAI', 'NANTES', 'PALMITAL', 'PARAGUACU PAULISTA', 'PEDRINHAS PAULISTA', 'PLATINA', 'QUATA', 'TARUMA',
            'BERNARDINO DE CAMPOS', 'CANITAR', 'CHAVANTES', 'ESPIRITO SANTO DO TURVO', 'FARTURA', 'IPAUSSU', 'MANDURI', 'OLEO', 'OURINHOS', 'PIRAJU', 'RIBEIRAO DO SUL', 'SALTO GRANDE', 'SANTA CRUZ DO RIO PARDO', 'SAO PEDRO DO TURVO', 'SARUTAIA', 'TAGUAI', 'TEJUPA', 'TIMBURI',
            'ALVARES FLORENCE', 'AMERICO DE CAMPOS', 'GUARANI DOESTE', 'INDIAPORA', 'MACEDONIA', 'MIRA ESTRELA', 'OUROESTE', 'PEDRANOPOLIS', 'SAO JOAO DAS DUAS PONTES', 'TURMALINA',
            'CESARIO LANGE', 'LARANJAL PAULISTA', 'PEREIRAS', 'PORANGABA', 'QUADRA', 'TORRE DE PEDRA',
            'IBIUNA', 'PIEDADE', 'PILAR DO SUL', 'SAO MIGUEL ARCANJO', 'TAPIRAI',
            'ANHEMBI', 'BOFETE', 'BOTUCATU', 'CONCHAS', 'PARDINHO', 'PRATANIA',
            'APARECIDA DOESTE', 'ASPASIA', 'DIRCE REIS', 'DOLCINOPOLIS', 'JAMBEIRO', 'MARINOPOLIS', 'MESOPOLIS', 'NOVA CANAA PAULISTA', 'PALMEIRA DOESTE', 'PARANAPUA', 'PONTALINDA', 'POPULINA', 'RUBINEIA', 'SANTA ALBERTINA', 'SANTA CLARA DOESTE', 'SANTA RITA DOESTE', 'SANTANA DA PONTE PENSA', 'SAO FRANCISCO', 'VITORIA BRASIL'
        ]
    ];

    public function processar(Request $request)
    {
        // 1. Blindagem de Memória e Tempo para aguentar milhares de arquivos
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '2G');

        $request->validate([
            'file_zip' => 'required|file|mimes:zip',
        ]);

        $batchId = $request->input('batch_id', Str::uuid()->toString());
        $resultadosAtuais = [];

        // 2. Lógica de Extração do ZIP
        if ($request->hasFile('file_zip')) {
            $zip = new \ZipArchive;
            $zipFile = $request->file('file_zip')->getPathname();

            if ($zip->open($zipFile) === true) {
                // Cria pasta temporária única
                $tempDir = storage_path('app/temp_xml_e4log_' . uniqid());
                \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);
                
                // Extrai os XMLs
                $zip->extractTo($tempDir);
                $zip->close();

                // Busca todos os arquivos extraídos (mesmo que estejam dentro de subpastas no ZIP)
                $files = \Illuminate\Support\Facades\File::allFiles($tempDir);

                foreach ($files as $file) {
                    // Ignora arquivos que não sejam XML
                    if (strtolower($file->getExtension()) !== 'xml') continue;

                    // Lê o XML e processa
                    $xmlContent = file_get_contents($file->getPathname());
                    $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                    $xmlObj = simplexml_load_string($xmlContent);
                    
                    if (!$xmlObj) continue; // Pula XML corrompido para não travar
                    
                    $data = json_decode(json_encode($xmlObj), true);
                    $nomeArquivo = Str::limit($file->getFilename(), 250, '');
                    
                    // Extrações Base do XML
                    $localDestino         = $this->extractCityAndUf($data);
                    $cidadeDestino        = $localDestino['cidade'];
                    $ufDestino            = $localDestino['uf'];
                    
                    $valorCarga           = $this->extractInvoiceValue($data);
                    $observacoesTexto     = $this->extractObs($data);
                    $tipoCTe              = $this->extractTipoCTe($data);
                    
                    $temTde               = $this->verificarTde($data, $observacoesTexto, $tipoCTe);
                    $tipoOperacao         = $this->extractTipoOperacao($observacoesTexto, $tipoCTe);
                    
                    // Extração rigorosa das chaves garantindo apenas NÚMEROS (Garante o lastro do complemento)
                    $chaveCte             = $this->extractChaveCTe($data, $nomeArquivo);
                    $chaveOriginal        = $this->extractChaveOriginal($data);
                    $chaveNfe             = $this->extractChaveNFe($data); 
                    
                    // Extração Perfeita dos valores Faturados detalhados no XML (Total, Frete, TDE)
                    $valoresFaturados     = $this->extractValoresXML($data, $tipoOperacao);
                    $valorCobradoOriginal = $valoresFaturados['total'];
                    $valorFreteCobrado    = $valoresFaturados['frete'];
                    $valorTdeCobrado      = $valoresFaturados['tde'];
                    
                    $regiaoFaturadaData   = $this->descobrirRegiaoFaturada($observacoesTexto, $valorCarga, $valorCobradoOriginal, $temTde, $tipoOperacao);

                    // 1. VERIFICAÇÃO DE UF E BUSCA DA TABELA OFICIAL
                    if ($ufDestino !== 'SP' && $ufDestino !== '') {
                        $nomeRegiao = "Tabela " . $ufDestino . " Ausente";
                        $percentual = 0;
                        $minimo = 0;
                    } else {
                        $nomeRegiao = $this->getRegiaoPorCidade($cidadeDestino);

                        // Fallback Inteligente baseado na nomenclatura da Região Faturada
                        if ($nomeRegiao === '-') {
                            $regFat = strtoupper($regiaoFaturadaData['nome']);
                            if (str_contains($regFat, 'REGIÃO 1')) $nomeRegiao = self::NOMENCLATURA_R1;
                            elseif (str_contains($regFat, 'REGIÃO 2')) $nomeRegiao = self::NOMENCLATURA_R2;
                            elseif (str_contains($regFat, 'REGIÃO 3')) $nomeRegiao = self::NOMENCLATURA_R3;
                            elseif (str_contains($regFat, 'REGIÃO 4')) $nomeRegiao = self::NOMENCLATURA_R4;
                            else $nomeRegiao = 'Indefinida/SP'; 
                            
                            if ($nomeRegiao !== 'Indefinida/SP') $nomeRegiao .= ' (Auto)';
                        }
                        
                        $regiaoBase = str_replace(' (Auto)', '', $nomeRegiao);
                        $percentual = self::REGRAS_REGIAO[$regiaoBase]['pct'] ?? 0;
                        $minimo     = self::REGRAS_REGIAO[$regiaoBase]['min'] ?? 0;
                    }

                    // 2. MATEMÁTICA DE AUDITORIA (SLA)
                    $valorFreteSla = 0;
                    $valorTdeSla = 0;
                    $valorSlaCorreto = 0;
                    $diferenca = 0;

                    if ($tipoOperacao === 'Complemento') {
                        $valorFreteSla = 0; 
                        $valorTdeSla = 0;
                    } else if ($nomeRegiao !== "Tabela {$ufDestino} Ausente" && $nomeRegiao !== 'Indefinida/SP') {
                        
                        // 1) VALOR DO FRETE SLA:
                        $freteCalculado = $valorCarga * $percentual;
                        $valorFreteSla = max($minimo, $freteCalculado);
                        
                        // 2) TDE SLA: Mínimo de R$ 160,00 ou 20% do valor do frete calculado
                        $valorTdeSla = $temTde ? max(160.00, $valorFreteSla * 0.20) : 0;
                        
                        // 3) SOMA SLA
                        $valorSlaCorreto = $valorFreteSla + $valorTdeSla;
                        $diferenca       = $valorSlaCorreto - $valorCobradoOriginal;
                    }

                    // 3. DEFINIÇÃO DO MOTIVO / STATUS
                    if (str_contains($nomeRegiao, 'Ausente') || $nomeRegiao === 'Indefinida/SP') {
                        $status = 'Alerta';
                        $motivo = "Requer revisão manual (Tabela não parametrizada).";
                        $diferenca = 0; 
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
                        'chave_nfe'           => $chaveNfe, 
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
                        'valor_cobrado'       => (float) $valorCobradoOriginal,
                        'valor_frete_cobrado' => (float) $valorFreteCobrado,
                        'valor_tde_cobrado'   => (float) $valorTdeCobrado,
                        'valor_sla'           => (float) $valorSlaCorreto,
                        'valor_frete_sla'     => (float) $valorFreteSla, 
                        'valor_tde_sla'       => (float) $valorTdeSla,   
                        'diferenca'           => (float) $diferenca,
                        'status'              => $status,
                        'motivo'              => $motivo 
                    ];
                }

                // 4. Faxina: Deleta a pasta temporária do servidor
                \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
                
            } else {
                return response()->json(['message' => 'Falha ao abrir o arquivo ZIP fornecido.'], 400);
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

    // MANTIDO COMO PUBLIC PARA A DRE NÃO DAR ERRO 500
    public function agruparResultados($dadosFlat) {
        $agrupados = [];

        // 1. Mapeia todos os CTEs Normais (Pais)
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] !== 'Complemento') {
                $chave = $item['chave_cte'];
                $item['arquivos_complemento'] = []; 
                $agrupados[$chave] = $item;
            }
        }

        // 2. Faz o merge dos Complementos
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] === 'Complemento') {
                $chavePai = $item['chave_original'];

                // Encontrou o PAI: Faz a fusão matemática
                if ($chavePai && isset($agrupados[$chavePai])) {
                    
                    // Soma as quebras faturadas (O que a E4LOG cobrou)
                    $agrupados[$chavePai]['valor_cobrado']       += $item['valor_cobrado'];
                    $agrupados[$chavePai]['valor_frete_cobrado'] += $item['valor_frete_cobrado'];
                    $agrupados[$chavePai]['valor_tde_cobrado']   += $item['valor_tde_cobrado'];
                    
                    // Define TDE como Sim (Pois houve complemento)
                    $agrupados[$chavePai]['tem_tde'] = 'Sim';

                    // RECÁLCULO DO SLA (MATEMÁTICA PERFEITA)
                    // Se o SLA Pai original não tinha TDE, agora precisa ter para não gerar falsa divergência!
                    if(!str_contains($agrupados[$chavePai]['regiao_sistema'], 'Ausente') && $agrupados[$chavePai]['regiao_sistema'] !== 'Indefinida/SP'){
                        
                        $freteSlaPai = $agrupados[$chavePai]['valor_frete_sla'];
                        
                        // Garante que o SLA do TDE seja calculado (Mín 160 ou 20%)
                        if ($agrupados[$chavePai]['valor_tde_sla'] == 0) {
                            $agrupados[$chavePai]['valor_tde_sla'] = max(160.00, $freteSlaPai * 0.20);
                            $agrupados[$chavePai]['valor_sla'] = $freteSlaPai + $agrupados[$chavePai]['valor_tde_sla'];
                        }

                        // Recalcula a diferença final
                        $agrupados[$chavePai]['diferenca'] = $agrupados[$chavePai]['valor_sla'] - $agrupados[$chavePai]['valor_cobrado'];
                        
                        $diff = $agrupados[$chavePai]['diferenca'];
                        $agrupados[$chavePai]['status'] = round($diff, 2) == 0 ? 'Validado' : 'Divergente';
                        
                        // Atualiza Motivo
                        if (round($diff, 2) > 0) {
                            $agrupados[$chavePai]['motivo'] = "Cobrado a MENOS. E4LOG perdeu R$ " . number_format(abs($diff), 2, ',', '.');
                        } elseif (round($diff, 2) < 0) {
                            $agrupados[$chavePai]['motivo'] = "Cobrado a MAIS. BWT pagou R$ " . number_format(abs($diff), 2, ',', '.') . " indevidamente.";
                        } else {
                            $agrupados[$chavePai]['motivo'] = "Validação 100% Exata com a Matriz.";
                        }
                    }

                    // Salva o nome do arquivo para a View
                    $agrupados[$chavePai]['arquivos_complemento'][] = $item['arquivo'];
                } else {
                    // Complemento Órfão (O arquivo pai não foi enviado no upload)
                    $chave = $item['chave_cte'];
                    $item['arquivos_complemento'] = [];
                    $agrupados[$chave] = $item;
                }
            }
        }

        $resultadoFinal = array_values($agrupados);

        // ORDENAÇÃO E4LOG
        usort($resultadoFinal, function($a, $b) {
            $diffA = round($a['diferenca'], 2);
            $diffB = round($b['diferenca'], 2);

            // Grupo 1: Negativos, Grupo 2: Positivos, Grupo 3: Zerados
            $grupoA = $diffA < 0 ? 1 : ($diffA > 0 ? 2 : 3);
            $grupoB = $diffB < 0 ? 1 : ($diffB > 0 ? 2 : 3);

            if ($grupoA !== $grupoB) {
                return $grupoA <=> $grupoB;
            }

            // Se ambos Negativos: do MAIOR (-500) para o menor (-10)
            if ($grupoA === 1) {
                return $diffA <=> $diffB; 
            }

            // Se ambos Positivos: do MAIOR (+500) para o menor (+10)
            if ($grupoA === 2) {
                return $diffB <=> $diffA; 
            }

            return strcmp($a['cidade_destino'] . $a['arquivo'], $b['cidade_destino'] . $b['arquivo']);
        });

        return $resultadoFinal;
    }

    private function descobrirRegiaoFaturada($obs, $valorCarga, $valorCobrado, $temTde, $tipoOperacao) {
        if ($tipoOperacao === 'Complemento') return ['nome' => 'Complemento', 'pct' => '-'];
        if ($valorCobrado <= 0) return ['nome' => 'Não Faturado', 'pct' => '-'];

        foreach (self::REGRAS_REGIAO as $nome => $regra) {
            $valorFrete = max($regra['min'], $valorCarga * $regra['pct']);
            $tde = $temTde ? max(160.00, $valorFrete * 0.20) : 0;
            
            if (abs(($valorFrete + $tde) - $valorCobrado) <= 1.50) {
                return ['nome' => $nome . ' (Calc)', 'pct' => ($regra['pct'] * 100) . '%'];
            }
        }

        if (preg_match('/REGI[AÃ]O\s*(\d)/i', $obs, $matches)) {
            $num = $matches[1];
            if ($num == '1') return ['nome' => self::NOMENCLATURA_R1, 'pct' => '2%'];
            if ($num == '2') return ['nome' => self::NOMENCLATURA_R2, 'pct' => '3%'];
            if ($num == '3') return ['nome' => self::NOMENCLATURA_R3, 'pct' => '3%'];
            if ($num == '4') return ['nome' => self::NOMENCLATURA_R4, 'pct' => '4%'];
            
            return ['nome' => 'Região ' . $num, 'pct' => '-'];
        }

        return ['nome' => 'Indefinida', 'pct' => '-'];
    }

    private function gerarResumoExecutivo($dados) {
        $f_menos = 0; $f_mais = 0;  
        $total_cobrado = 0; $total_sla = 0;

        foreach ($dados as $item) {
            $total_cobrado += $item['valor_cobrado'];
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
            'balanco_geral'    => $f_mais - $f_menos,
            'faturado_a_menos' => $f_menos, 
            'faturado_a_mais'  => $f_mais,  
        ];
    }

    private function getRegiaoPorCidade(string $cidade): string {
        $cidadeLpa = preg_replace('/[^A-Z0-9 ]/', '', Str::ascii(strtoupper($cidade)));
        $cidadeLpa = trim(preg_replace('/\s+/', ' ', $cidadeLpa));

        foreach (self::CIDADES_POR_REGIAO as $regiao => $cidades) {
            if (in_array($cidadeLpa, $cidades, true)) return $regiao;
        }

        foreach (self::CIDADES_POR_REGIAO as $regiao => $cidades) {
            foreach ($cidades as $c) {
                if (str_contains($cidadeLpa, $c) || str_contains($c, $cidadeLpa)) return $regiao;
            }
        }

        return '-';
    }

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

    // EXTRAÇÃO DE CHAVE LIMPA: Resolve o problema do complemento não achar o PAI.
    private function extractChaveCTe($data, $nomeArquivo) {
        $ch = '';
        if (isset($data['protCTe']['infProt']['chCTe'])) {
            $ch = (string) $data['protCTe']['infProt']['chCTe'];
        } else {
            $base = $this->getBaseNode($data);
            if ($base && isset($base['@attributes']['Id'])) {
                $ch = str_replace('CTe', '', $base['@attributes']['Id']);
            } elseif (preg_match('/\d{44}/', $nomeArquivo, $matches)) {
                $ch = $matches[0];
            }
        }
        $ch = preg_replace('/[^0-9]/', '', $ch); // Limpa qualquer letra (ex: 'CTe')
        return empty($ch) ? Str::uuid()->toString() : $ch;
    }
    
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

    // EXTRAÇÃO DE CHAVE LIMPA: Resolve o problema do complemento não achar o PAI.
    private function extractChaveOriginal($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCteComp']['chCTe'])) {
            return preg_replace('/[^0-9]/', '', (string) $base['infCteComp']['chCTe']);
        }
        if ($base && isset($base['infCteComp']['chave'])) {
            return preg_replace('/[^0-9]/', '', (string) $base['infCteComp']['chave']);
        }
        return null;
    }
    
    private function extractTipoOperacao($observacoes, $tipoCTe) { 
        $obs = strtoupper($observacoes);
        if (str_contains($obs, 'DEVOLUCAO') || str_contains($obs, 'RETORNO')) return 'Devolução'; 
        if (str_contains($obs, 'REENTREGA')) return 'Reentrega'; 
        if ((string)$tipoCTe === '1' || str_contains($obs, 'COMPL')) return 'Complemento'; 
        return 'Entrega'; 
    }
    
    private function verificarTde($data, $obs, $tipoCTe) {
        if (str_contains(strtoupper($obs), 'TDE') || str_contains(strtoupper($obs), 'RURAL')) return true;
        if ((string)$tipoCTe === '0') {
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

    /**
     * NOVA FUNÇÃO: Quebra os valores faturados em Frete e TDE diretamente pelas tags `<Comp>` do XML
     */
    private function extractValoresXML($data, $tipoOperacao) {
        $base = $this->getBaseNode($data);
        $total = 0;
        $tde = 0;

        if ($base && isset($base['vPrest']['vTPrest'])) {
            $total = (float) $base['vPrest']['vTPrest'];
        }

        if ($base && isset($base['vPrest']['Comp'])) {
            $comps = $base['vPrest']['Comp'];
            // Se houver apenas 1 nó <Comp>, o SimpleXML não gera array numérico
            if (isset($comps['xNome'])) {
                $comps = [$comps];
            }
            foreach ($comps as $c) {
                $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                $valor = (float)($c['vComp'] ?? 0);
                
                // Extração inteligente e restrita para a tag de TDE
                if (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE') || str_contains($nome, 'TRT')) {
                    $tde += $valor;
                }
            }
        }

        // Fallback: Na BWT complementos de nota muitas vezes não trazem tag <Comp>, mas sabemos que é 100% TDE
        if ($tipoOperacao === 'Complemento' && $tde == 0) {
            $tde = $total;
        }

        // O que sobrar do total tirando o TDE, é o Frete Peso.
        $frete = $total - $tde;

        return [
            'total' => $total,
            'frete' => max(0, $frete),
            'tde'   => $tde
        ];
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
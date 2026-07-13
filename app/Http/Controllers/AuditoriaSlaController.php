<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class AuditoriaSlaController extends Controller
{
    public function index()
    {
        return Inertia::render('AuditoriaSla/Index');
    }

    public function processar(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|mimes:xml',
        ]);

        $batchId = $request->input('batch_id', Str::uuid()->toString());
        $resultadosAtuais = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                
                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
                
                $cidadeDestino = $this->extractCity($data);
                $valorCarga = $this->extractInvoiceValue($data);
                $valorCobradoOriginal = $this->extractFreightValue($data);
                
                $observacoesTexto = $this->extractObs($data);
                $tipoCTe = $this->extractTipoCTe($data);
                $temTde = $this->verificarTde($data, $observacoesTexto, $tipoCTe);
                $tipoOperacao = $this->extractTipoOperacao($observacoesTexto, $tipoCTe);
                
                $chaveCte = $this->extractChaveCTe($data, $nomeArquivo);
                $chaveOriginal = $this->extractChaveOriginal($data);
                
                $regiaoFaturadaData = $this->descobrirRegiaoFaturada($observacoesTexto, $valorCarga, $valorCobradoOriginal, $temTde, $tipoOperacao);

                // Previne falha fatal caso o XML não tenha cidade
                if (!$cidadeDestino) {
                    $cidadeDestino = 'Desconhecida'; 
                }

                // 1. BUSCA DA TABELA OFICIAL
                $nomeRegiao = $this->getRegiaoPorCidade($cidadeDestino);

                // 👇 FALLBACK INTELIGENTE (AUDITORIA 100%) 👇
                // Se a cidade não existir no mapa, o sistema autocompleta inferindo a regra cobrada no CT-e
                if ($nomeRegiao === '-') {
                    $regFat = strtolower($regiaoFaturadaData['nome']);
                    if (str_contains($regFat, '1')) $nomeRegiao = 'Região 1';
                    elseif (str_contains($regFat, '2')) $nomeRegiao = 'Região 2';
                    elseif (str_contains($regFat, '3')) $nomeRegiao = 'Região 3';
                    elseif (str_contains($regFat, '4')) $nomeRegiao = 'Região 4';
                    else $nomeRegiao = 'Região 1'; // Padrão se não conseguir descobrir
                    
                    $nomeRegiao .= ' (Auto)';
                }

                $regiaoStr = strtolower($nomeRegiao);
                $minimo = 0; $percentual = 0;

                // 2. APLICAÇÃO DAS REGRAS BWT -> SOL FÁCIL
                if (str_contains($regiaoStr, '1')) { $minimo = 350.00; $percentual = 0.03; } 
                elseif (str_contains($regiaoStr, '2')) { $minimo = 350.00; $percentual = 0.035; } 
                elseif (str_contains($regiaoStr, '3')) { $minimo = 550.00; $percentual = 0.04; } 
                elseif (str_contains($regiaoStr, '4')) { $minimo = 600.00; $percentual = 0.05; } 

                if ($tipoOperacao === 'Complemento') {
                    $freteBase = 0; 
                } else {
                    $freteCalculado = $valorCarga * $percentual;
                    $freteBase = max($minimo, $freteCalculado);
                }

                // REGRAS DA TDE ATUALIZADAS
                $valorTde = $temTde ? (($freteBase > 666.67) ? ($freteBase * 0.30) : 200.00) : 0;
                
                // ICMS a 12% por dentro (Apenas no frete base, TDE não tem ICMS)
                $freteComIcms = $freteBase / 0.88;
                $valorSlaCorreto = $freteComIcms + $valorTde;

                $diferenca = $valorSlaCorreto - $valorCobradoOriginal;
                
                $motivo = round($diferenca, 2) != 0 ? ($diferenca > 0 ? 'Deixamos de faturar (Perda de margem).' : 'Faturado indevidamente a maior.') : '-';

                $resultadosAtuais[] = [
                    'chave_cte' => $chaveCte,
                    'arquivo' => $nomeArquivo,
                    'cidade_destino' => $cidadeDestino,
                    'regiao_sistema' => $nomeRegiao,
                    'percentual_sistema' => ($percentual > 0) ? ($percentual * 100) . '%' : '-',
                    'regiao_faturada' => $regiaoFaturadaData['nome'],
                    'percentual_faturado' => $regiaoFaturadaData['pct'],
                    'tem_tde' => $temTde ? 'Sim' : 'Não',
                    'tipo_operacao' => $tipoOperacao,
                    'chave_original' => $chaveOriginal,
                    'valor_carga' => (float) $valorCarga,
                    'valor_cobrado' => (float) $valorCobradoOriginal,
                    'valor_sla' => (float) $valorSlaCorreto,
                    'diferenca' => (float) $diferenca,
                    'status' => round($diferenca, 2) == 0 ? 'Validado' : 'Divergente',
                    'motivo' => $motivo 
                ];
            }
        }

        $dadosEmCache = Cache::get('auditoria_sla_' . $batchId, []);
        $dadosAtualizados = array_merge($dadosEmCache, $resultadosAtuais);
        Cache::put('auditoria_sla_' . $batchId, $dadosAtualizados, now()->addHours(2));

        $dadosAgrupados = $this->agruparResultados($dadosAtualizados);

        return response()->json([
            'batch_id' => $batchId,
            'data' => $dadosAgrupados 
        ]);
    }

    public function exportarPdf($batchId)
    {
        // ---------------------------------------------------------
        // PREVINE ERRO DE TIMEOUT E MEMÓRIA NO DOMPDF PARA MILHARES DE LINHAS
        // ---------------------------------------------------------
        ini_set('max_execution_time', 0); // Remove o limite de 30 segundos
        ini_set('memory_limit', '2G');    // Aumenta a memória temporariamente para 2GB
        
        $dadosFlat = Cache::get('auditoria_sla_' . $batchId);

        if (!$dadosFlat) abort(404, 'Sessão de auditoria expirada.');

        $dadosAgrupados = $this->agruparResultados($dadosFlat);
        $resumo = $this->gerarResumoExecutivo($dadosAgrupados);

        $pdf = Pdf::loadView('pdf.auditoria_sla_report', [
            'dados' => $dadosAgrupados,
            'resumo' => $resumo,
            'data_auditoria' => now()->format('d/m/Y H:i')
        ]);

        $pdf->setPaper('A4', 'landscape'); 
        return $pdf->stream('auditoria_sla_solfacil_' . now()->format('YmdHi') . '.pdf');
    }

    private function agruparResultados($dadosFlat) {
        $agrupados = [];

        // Agrupa pais
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] !== 'Complemento') {
                $chave = $item['chave_cte'];
                $item['arquivos_complemento'] = []; 
                $agrupados[$chave] = $item;
            }
        }

        // Agrupa complementos
        foreach ($dadosFlat as $item) {
            if ($item['tipo_operacao'] === 'Complemento') {
                $chavePai = $item['chave_original'];

                if ($chavePai && isset($agrupados[$chavePai])) {
                    $agrupados[$chavePai]['valor_cobrado'] += $item['valor_cobrado'];
                    $agrupados[$chavePai]['valor_sla'] += $item['valor_sla'];
                    $agrupados[$chavePai]['diferenca'] += $item['diferenca'];
                    $agrupados[$chavePai]['tem_tde'] = 'Sim';
                    $agrupados[$chavePai]['arquivos_complemento'][] = $item['arquivo'];

                    $diff = $agrupados[$chavePai]['diferenca'];
                    $agrupados[$chavePai]['status'] = round($diff, 2) == 0 ? 'Validado' : 'Divergente';
                    $agrupados[$chavePai]['motivo'] = round($diff, 2) != 0 ? ($diff > 0 ? 'Deixamos de faturar (Perda de margem).' : 'Faturado indevidamente a maior.') : '-';
                } else {
                    $chave = $item['chave_cte'];
                    $item['arquivos_complemento'] = [];
                    $agrupados[$chave] = $item;
                }
            }
        }

        $resultadoFinal = array_values($agrupados);

        // ORDENAÇÃO: 1º Prejuízo (diff > 0), 2º Correto (diff = 0), 3º Lucro (diff < 0)
        usort($resultadoFinal, function($a, $b) {
            $diffA = round($a['diferenca'], 2);
            $diffB = round($b['diferenca'], 2);

            $grupoA = $diffA > 0 ? 1 : ($diffA < 0 ? 3 : 2);
            $grupoB = $diffB > 0 ? 1 : ($diffB < 0 ? 3 : 2);

            // Ordena os grupos
            if ($grupoA !== $grupoB) {
                return $grupoA <=> $grupoB;
            }

            // Dentro de Prejuízo (SLA > Cobrado), colocar o maior prejuízo primeiro (Decrescente)
            if ($grupoA === 1) {
                if ($diffA != $diffB) return $diffB <=> $diffA;
            }
            // Dentro de Lucro (SLA < Cobrado), colocar o maior lucro primeiro (Crescente, sendo número negativo)
            if ($grupoA === 3) {
                if ($diffA != $diffB) return $diffA <=> $diffB;
            }

            // Desempate alfabético por cidade e nome do arquivo
            return strcmp($a['cidade_destino'] . $a['arquivo'], $b['cidade_destino'] . $b['arquivo']);
        });

        return $resultadoFinal;
    }

    private function descobrirRegiaoFaturada($obs, $valorCarga, $valorCobrado, $temTde, $tipoOperacao) {
        if ($tipoOperacao === 'Complemento') return ['nome' => 'Complemento', 'pct' => '-'];
        if ($valorCobrado <= 0) return ['nome' => 'Não Faturado', 'pct' => '-'];

        $regras = [
            'Região 1' => ['min' => 350.00, 'pct' => 0.03],
            'Região 2' => ['min' => 350.00, 'pct' => 0.035],
            'Região 3' => ['min' => 550.00, 'pct' => 0.04],
            'Região 4' => ['min' => 600.00, 'pct' => 0.05],
        ];

        foreach ($regras as $nome => $regra) {
            $freteBase = max($regra['min'], $valorCarga * $regra['pct']);
            
            // REGRAS DA TDE ATUALIZADAS
            $tde = $temTde ? (($freteBase > 666.67) ? ($freteBase * 0.30) : 200.00) : 0;
            
            // Calculo para descobrir a região sem aplicar ICMS na TDE
            $freteComIcms = $freteBase / 0.88;
            if (abs(($freteComIcms + $tde) - $valorCobrado) <= 1.50) {
                return ['nome' => $nome . ' (Calc)', 'pct' => ($regra['pct'] * 100) . '%'];
            }
        }

        if (preg_match('/REGI[AÃ]O\s*(\d)/i', $obs, $matches)) {
            $num = $matches[1];
            $pct = '-';
            if ($num == '1') $pct = '3%';
            elseif ($num == '2') $pct = '3.5%';
            elseif ($num == '3') $pct = '4%';
            elseif ($num == '4') $pct = '5%';
            return ['nome' => 'Região ' . $num, 'pct' => $pct];
        }

        return ['nome' => 'Indefinida', 'pct' => '-'];
    }

    private function gerarResumoExecutivo($dados) {
        $f_menos = 0; $f_mais = 0;  
        $total_cobrado = 0; $total_sla = 0;

        foreach ($dados as $item) {
            $total_cobrado += $item['valor_cobrado'];
            $total_sla += $item['valor_sla'];
            
            if ($item['diferenca'] > 0) $f_menos += $item['diferenca'];
            elseif ($item['diferenca'] < 0) $f_mais += abs($item['diferenca']);
        }

        return [
            'total_documentos' => count($dados),
            'total_cobrado' => $total_cobrado,
            'total_sla' => $total_sla,
            'balanco_geral' => $f_mais - $f_menos,
            'faturado_a_menos' => $f_menos,
            'faturado_a_mais' => $f_mais,
        ];
    }

    // --- Mapeamento Limpo e Integral das Cidades Totalmente Desagrupado (Sem Barras) ---
    private function getRegiaoPorCidade($cidade) {
        $cidadeLpa = strtoupper(preg_replace('/[^A-Za-z0-9 ]/', '', Str::ascii($cidade)));
        $cidadeLpa = trim($cidadeLpa);

        $mapa = [
            'ADAMANTINA' => 'Região 3', 
            'ADOLFO' => 'Região 3', 
            'AGUAI' => 'Região 2', 
            'AGUAS DA PRATA' => 'Região 2', 
            'AGUAS DE LINDOIA' => 'Região 2', 
            'AGUAS DE SANTA BARBARA' => 'Região 4', 
            'AGUAS DE SAO PEDRO' => 'Região 2', 
            'AGUDOS' => 'Região 2', 
            'ALAMBARI' => 'Região 4', 
            'ALFREDO MARCONDES' => 'Região 4', 
            'ALTAIR' => 'Região 3', 
            'ALTINOPOLIS' => 'Região 4', 
            'ALTO ALEGRE' => 'Região 3', 
            'ALUMINIO' => 'Região 2', 
            'ALVARES FLORENCE' => 'Região 3', 
            'ALVARES MACHADO' => 'Região 4', 
            'ALVARO DE CARVALHO' => 'Região 3', 
            'ALVINLANDIA' => 'Região 3', 
            'AMERICANA' => 'Região 1', 
            'AMERICO BRASILIENSE' => 'Região 3', 
            'AMERICO DE CAMPOS' => 'Região 3', 
            'AMPARO' => 'Região 2', 
            'ANALANDIA' => 'Região 3', 
            'ANDRADINA' => 'Região 3', 
            'ANGATUBA' => 'Região 4', 
            'ANHEMBI' => 'Região 3', 
            'ANHUMAS' => 'Região 4', 
            'APARECIDA' => 'Região 4', 
            'APARECIDA DOESTE' => 'Região 3', 
            'APIAI' => 'Região 4', 
            'ARACARIGUAMA' => 'Região 2', 
            'ARACATUBA' => 'Região 3', 
            'ARACOIABA DA SERRA' => 'Região 2', 
            'ARAMINA' => 'Região 2', 
            'ARANDU' => 'Região 4', 
            'ARAPEI' => 'Região 4', 
            'ARARAQUARA' => 'Região 3', 
            'ARARAS' => 'Região 2', 
            'ARCOIRIS' => 'Região 3', 
            'AREALVA' => 'Região 3', 
            'AREIAS' => 'Região 4', 
            'AREIOPOLIS' => 'Região 3', 
            'ARIRANHA' => 'Região 3', 
            'ARTUR NOGUEIRA' => 'Região 2', 
            'ARUJA' => 'Região 1', 
            'ASPASIA' => 'Região 3', 
            'ASSIS' => 'Região 4', 
            'ATIBAIA' => 'Região 2', 
            'AURIFLAMA' => 'Região 3', 
            'AVAI' => 'Região 3', 
            'AVANHANDAVA' => 'Região 3', 
            'AVARE' => 'Região 4', 
            'BADY BASSITT' => 'Região 3', 
            'BALBINOS' => 'Região 3', 
            'BALSAMO' => 'Região 3', 
            'BANANAL' => 'Região 4', 
            'BARAO DE ANTONINA' => 'Região 4', 
            'BARBOSA' => 'Região 3', 
            'BARIRI' => 'Região 4', 
            'BARRA BONITA' => 'Região 2', 
            'BARRA DO CHAPEU' => 'Região 4', 
            'BARRA DO TURVO' => 'Região 4', 
            'BARRETOS' => 'Região 2', 
            'BARRINHA' => 'Região 2', 
            'BARUERI' => 'Região 1', 
            'BASTOS' => 'Região 3', 
            'BATATAIS' => 'Região 4', 
            'BAURU' => 'Região 2', 
            'BEBEDOURO' => 'Região 2', 
            'BENTO DE ABREU' => 'Região 4', 
            'BERNARDINO DE CAMPOS' => 'Região 4', 
            'BERTIOGA' => 'Região 4', 
            'BILAC' => 'Região 3', 
            'BIRIGUI' => 'Região 3', 
            'BIRITIBA MIRIM' => 'Região 4', 
            'BOA ESPERANCA DO SUL' => 'Região 3', 
            'BOCAINA' => 'Região 4', 
            'BOFETE' => 'Região 3', 
            'BOITUVA' => 'Região 2', 
            'BOM JESUS DOS PERDOES' => 'Região 2', 
            'BOM SUCESSO DE ITARARE' => 'Região 4', 
            'BORA' => 'Região 4', 
            'BORACEIA' => 'Região 4', 
            'BORBOREMA' => 'Região 3', 
            'BOREBI' => 'Região 3', 
            'BOTUCATU' => 'Região 3', 
            'BRAGANCA PAULISTA' => 'Região 2', 
            'BRAUNA' => 'Região 3', 
            'BREJO ALEGRE' => 'Região 3', 
            'BRODOWSKI' => 'Região 2', 
            'BROTAS' => 'Região 2', 
            'BURI' => 'Região 4', 
            'BURITAMA' => 'Região 3', 
            'BURITIZAL' => 'Região 4', 
            'CABRALIA PAULISTA' => 'Região 3', 
            'CABREUVA' => 'Região 1', 
            'CACAPAVA' => 'Região 4', 
            'CACHOEIRA PAULISTA' => 'Região 4', 
            'CACONDE' => 'Região 2', 
            'CAFELANDIA' => 'Região 3', 
            'CAIABU' => 'Região 4', 
            'CAIEIRAS' => 'Região 1', 
            'CAIUA' => 'Região 4', 
            'CAJAMAR' => 'Região 1', 
            'CAJATI' => 'Região 4', 
            'CAJOBI' => 'Região 3', 
            'CAJURU' => 'Região 4', 
            'CAMPINA DO MONTE ALEGRE' => 'Região 4', 
            'CAMPINAS' => 'Região 1', 
            'CAMPO LIMPO PAULISTA' => 'Região 1', 
            'CAMPOS DO JORDAO' => 'Região 4', 
            'CAMPOS NOVOS PAULISTA' => 'Região 4', 
            'CANANEIA' => 'Região 4', 
            'CANAS' => 'Região 4', 
            'CANDIDO MOTA' => 'Região 4', 
            'CANDIDO RODRIGUES' => 'Região 2', 
            'CANITAR' => 'Região 4', 
            'CAPAO BONITO' => 'Região 4', 
            'CAPELA DO ALTO' => 'Região 2', 
            'CAPIVARI' => 'Região 2', 
            'CARAGUATATUBA' => 'Região 4', 
            'CARAPICUIBA' => 'Região 1', 
            'CARDOSO' => 'Região 3', 
            'CASA BRANCA' => 'Região 2', 
            'CASSIA DOS COQUEIROS' => 'Região 4', 
            'CASTILHO' => 'Região 3', 
            'CATANDUVA' => 'Região 3', 
            'CATIGUA' => 'Região 3', 
            'CEDRAL' => 'Região 3', 
            'CERQUEIRA CESAR' => 'Região 4', 
            'CERQUILHO' => 'Região 2', 
            'CESARIO LANGE' => 'Região 2', 
            'CHARQUEADA' => 'Região 2', 
            'CHAVANTES' => 'Região 4', 
            'CLEMENTINA' => 'Região 3', 
            'COLINA' => 'Região 2', 
            'COLOMBIA' => 'Região 2', 
            'CONCHAL' => 'Região 2', 
            'CONCHAS' => 'Região 3', 
            'CORDEIROPOLIS' => 'Região 2', 
            'COROADOS' => 'Região 3', 
            'CORONEL MACEDO' => 'Região 4', 
            'CORUMBATAI' => 'Região 3', 
            'COSMOPOLIS' => 'Região 1', 
            'COSMORAMA' => 'Região 3', 
            'COTIA' => 'Região 1', 
            'CRAVINHOS' => 'Região 2', 
            'CRISTAIS PAULISTA' => 'Região 4', 
            'CRUZALIA' => 'Região 4', 
            'CRUZEIRO' => 'Região 4', 
            'CUBATAO' => 'Região 4', 
            'CUNHA' => 'Região 4', 
            'DESCALVADO' => 'Região 3', 
            'DIADEMA' => 'Região 1', 
            'DIRCE REIS' => 'Região 3', 
            'DIVINOLANDIA' => 'Região 2', 
            'DOBRADA' => 'Região 3', 
            'DOIS CORREGOS' => 'Região 2', 
            'DOLCINOPOLIS' => 'Região 3', 
            'DOURADO' => 'Região 3', 
            'DRACENA' => 'Região 3', 
            'DUARTINA' => 'Região 3', 
            'DUMONT' => 'Região 2', 
            'ECHAPORA' => 'Região 3', 
            'ELDORADO' => 'Região 4', 
            'ELIAS FAUSTO' => 'Região 1', 
            'ELISIARIO' => 'Região 3', 
            'EMBAUBA' => 'Região 3', 
            'EMBU DAS ARTES' => 'Região 1', 
            'EMBUGUACU' => 'Região 1', 
            'EMILIANOPOLIS' => 'Região 4', 
            'ENGENHEIRO COELHO' => 'Região 2', 
            'ESPIRITO SANTO DO PINHAL' => 'Região 2', 
            'ESPIRITO SANTO DO TURVO' => 'Região 4', 
            'ESTIVA GERBI' => 'Região 2', 
            'ESTRELA D OESTE' => 'Região 3', 
            'ESTRELA DO NORTE' => 'Região 4', 
            'ESTRELA DOESTE' => 'Região 3', 
            'EUCLIDES DA CUNHA PAULISTA' => 'Região 4', 
            'FARTURA' => 'Região 4', 
            'FERNANDO PRESTES' => 'Região 2', 
            'FERNANDOPOLIS' => 'Região 3', 
            'FERNAO' => 'Região 3', 
            'FERRAZ DE VASCONCELOS' => 'Região 4', 
            'FLORA RICA' => 'Região 3', 
            'FLOREAL' => 'Região 3', 
            'FLORIDA PAULISTA' => 'Região 3', 
            'FLORINEA' => 'Região 4', 
            'FRANCA' => 'Região 4', 
            'FRANCISCO MORATO' => 'Região 1', 
            'FRANCO DA ROCHA' => 'Região 1', 
            'GABRIEL MONTEIRO' => 'Região 3', 
            'GALIA' => 'Região 3', 
            'GARCA' => 'Região 3', 
            'GASTAO VIDIGAL' => 'Região 3', 
            'GAVIAO PEIXOTO' => 'Região 3', 
            'GENERAL SALGADO' => 'Região 3', 
            'GETULINA' => 'Região 3', 
            'GLICERIO' => 'Região 3', 
            'GUAICARA' => 'Região 3', 
            'GUAIMBE' => 'Região 3', 
            'GUAIRA' => 'Região 2', 
            'GUAPIACU' => 'Região 3', 
            'GUAPIARA' => 'Região 4', 
            'GUARA' => 'Região 4', 
            'GUARACAI' => 'Região 4', 
            'GUARACI' => 'Região 3', 
            'GUARANI DOESTE' => 'Região 3', 
            'GUARANTA' => 'Região 3', 
            'GUARARAPES' => 'Região 3', 
            'GUARAREMA' => 'Região 4', 
            'GUARATINGUETA' => 'Região 4', 
            'GUAREI' => 'Região 4', 
            'GUARIBA' => 'Região 2', 
            'GUARUJA' => 'Região 4', 
            'GUARULHOS' => 'Região 1', 
            'GUATAPARA' => 'Região 2', 
            'GUZOLANDIA' => 'Região 3', 
            'HERCULANDIA' => 'Região 3', 
            'HOLAMBRA' => 'Região 1', 
            'HORTOLANDIA' => 'Região 1', 
            'IACANGA' => 'Região 3', 
            'IACRI' => 'Região 3', 
            'IARAS' => 'Região 4', 
            'IBATE' => 'Região 3', 
            'IBIRA' => 'Região 3', 
            'IBIRAREMA' => 'Região 4', 
            'IBITINGA' => 'Região 3', 
            'IBIUNA' => 'Região 2', 
            'ICEM' => 'Região 3', 
            'IEPE' => 'Região 4', 
            'IGARACU DO TIETE' => 'Região 2', 
            'IGARAPAVA' => 'Região 4', 
            'IGARATA' => 'Região 4', 
            'IGUAPE' => 'Região 4', 
            'ILHA COMPRIDA' => 'Região 4', 
            'ILHA SOLTEIRA' => 'Região 4', 
            'ILHABELA' => 'Região 4', 
            'INDAIATUBA' => 'Região 1', 
            'INDIANA' => 'Região 4', 
            'INDIAPORA' => 'Região 3', 
            'INUBIA PAULISTA' => 'Região 3', 
            'IPAUSSU' => 'Região 4', 
            'IPERO' => 'Região 2', 
            'IPEUNA' => 'Região 3', 
            'IPIGUA' => 'Região 3', 
            'IPORANGA' => 'Região 4', 
            'IPUA' => 'Região 2', 
            'IRACEMAPOLIS' => 'Região 2', 
            'IRAPUA' => 'Região 3', 
            'IRAPURU' => 'Região 3', 
            'ITABERA' => 'Região 4', 
            'ITAI' => 'Região 4', 
            'ITAJOBI' => 'Região 3', 
            'ITAJU' => 'Região 4', 
            'ITANHAEM' => 'Região 4', 
            'ITAOCA' => 'Região 4', 
            'ITAPECERICA DA SERRA' => 'Região 1', 
            'ITAPETININGA' => 'Região 4', 
            'ITAPEVA' => 'Região 4', 
            'ITAPEVI' => 'Região 1', 
            'ITAPIRA' => 'Região 2', 
            'ITAPIRAPUA PAULISTA' => 'Região 4', 
            'ITAPOLIS' => 'Região 3', 
            'ITAPORANGA' => 'Região 4', 
            'ITAPUI' => 'Região 4', 
            'ITAPURA' => 'Região 4', 
            'ITAQUAQUECETUBA' => 'Região 4', 
            'ITARARE' => 'Região 4', 
            'ITARIRI' => 'Região 4', 
            'ITATIBA' => 'Região 2', 
            'ITATINGA' => 'Região 4', 
            'ITIRAPINA' => 'Região 3', 
            'ITIRAPUA' => 'Região 4', 
            'ITOBI' => 'Região 2', 
            'ITU' => 'Região 1', 
            'ITUPEVA' => 'Região 1', 
            'ITUVERAVA' => 'Região 4', 
            'JABORANDI' => 'Região 2', 
            'JABOTICABAL' => 'Região 2', 
            'JACAREI' => 'Região 4', 
            'JACI' => 'Região 3', 
            'JACUPIRANGA' => 'Região 4', 
            'JAGUARIUNA' => 'Região 1', 
            'JALES' => 'Região 3', 
            'JANDIRA' => 'Região 1', 
            'JARDINOPOLIS' => 'Região 2', 
            'JARINU' => 'Região 2', 
            'JAU' => 'Região 2', 
            'JERIQUARA' => 'Região 4', 
            'JOANOPOLIS' => 'Região 2', 
            'JOAO RAMALHO' => 'Região 4', 
            'JOSE BONIFACIO' => 'Região 3', 
            'JULIO MESQUITA' => 'Região 3', 
            'JUMIRIM' => 'Região 2', 
            'JUNDIAI' => 'Região 1', 
            'JUNQUEIROPOLIS' => 'Região 3', 
            'JUQUIA' => 'Região 4', 
            'JUQUITIBA' => 'Região 1', 
            'LARANJAL PAULISTA' => 'Região 2', 
            'LAVINIA' => 'Região 4', 
            'LAVRINHAS' => 'Região 4', 
            'LEME' => 'Região 2', 
            'LENCOIS PAULISTA' => 'Região 2', 
            'LIMEIRA' => 'Região 2', 
            'LINDOIA' => 'Região 2', 
            'LINS' => 'Região 3', 
            'LORENA' => 'Região 4', 
            'LOURDES' => 'Região 3', 
            'LOUVEIRA' => 'Região 1', 
            'LUCELIA' => 'Região 3', 
            'LUCIANOPOLIS' => 'Região 3', 
            'LUIZ ANTONIO' => 'Região 2', 
            'LUIZIANIA' => 'Região 3', 
            'LUPERCIO' => 'Região 3', 
            'LUTECIA' => 'Região 4', 
            'MACATUBA' => 'Região 4', 
            'MACAUBAL' => 'Região 3', 
            'MACEDONIA' => 'Região 3', 
            'MAGDA' => 'Região 3', 
            'MAIRINQUE' => 'Região 2', 
            'MAIRIPORA' => 'Região 1', 
            'MANDURI' => 'Região 4', 
            'MARABA PAULISTA' => 'Região 4', 
            'MARACAI' => 'Região 4', 
            'MARAPOAMA' => 'Região 3', 
            'MARIAPOLIS' => 'Região 3', 
            'MARILIA' => 'Região 3', 
            'MARINOPOLIS' => 'Região 3', 
            'MARTINOPOLIS' => 'Região 4', 
            'MATAO' => 'Região 3', 
            'MAUA' => 'Região 1', 
            'MENDONCA' => 'Região 3', 
            'MERIDIANO' => 'Região 3', 
            'MESOPOLIS' => 'Região 3', 
            'MIGUELOPOLIS' => 'Região 2', 
            'MINEIROS DO TIETE' => 'Região 2', 
            'MIRA ESTRELA' => 'Região 3', 
            'MIRACATU' => 'Região 4', 
            'MIRANDOPOLIS' => 'Região 4', 
            'MIRANTE DO PARANAPANEMA' => 'Região 4', 
            'MIRASSOL' => 'Região 3', 
            'MIRASSOLANDIA' => 'Região 3', 
            'MOCOCA' => 'Região 2', 
            'MOGI DAS CRUZES' => 'Região 4', 
            'MOGI GUACU' => 'Região 2', 
            'MOGI MIRIM' => 'Região 2', 
            'MOMBUCA' => 'Região 2', 
            'MONCOES' => 'Região 3', 
            'MONGAGUA' => 'Região 4', 
            'MONTE ALEGRE DO SUL' => 'Região 2', 
            'MONTE ALTO' => 'Região 2', 
            'MONTE APRAZIVEL' => 'Região 3', 
            'MONTE AZUL PAULISTA' => 'Região 2', 
            'MONTE CASTELO' => 'Região 3', 
            'MONTE MOR' => 'Região 1', 
            'MONTEIRO LOBATO' => 'Região 4', 
            'MORRO AGUDO' => 'Região 2', 
            'MORUNGABA' => 'Região 2', 
            'MOTUCA' => 'Região 3', 
            'MURUTINGA DO SUL' => 'Região 3', 
            'NANTES' => 'Região 4', 
            'NARANDIBA' => 'Região 4', 
            'NAZARE PAULISTA' => 'Região 2', 
            'NEVES PAULISTA' => 'Região 3', 
            'NHANDEARA' => 'Região 3', 
            'NIPOA' => 'Região 3', 
            'NOVA ALIANCA' => 'Região 3', 
            'NOVA CAMPINA' => 'Região 4', 
            'NOVA CANAA PAULISTA' => 'Região 3', 
            'NOVA CASTILHO' => 'Região 3', 
            'NOVA EUROPA' => 'Região 3', 
            'NOVA GRANADA' => 'Região 3', 
            'NOVA GUATAPORANGA' => 'Região 3', 
            'NOVA INDEPENDENCIA' => 'Região 4', 
            'NOVA LUZITANIA' => 'Região 3', 
            'NOVA ODESSA' => 'Região 1', 
            'NOVAIS' => 'Região 3', 
            'NOVO HORIZONTE' => 'Região 3', 
            'NUPORANGA' => 'Região 2', 
            'OCAUCU' => 'Região 3', 
            'OLEO' => 'Região 4', 
            'OLIMPIA' => 'Região 2', 
            'ONDA VERDE' => 'Região 3', 
            'ORIENTE' => 'Região 3', 
            'ORINDIUVA' => 'Região 3', 
            'ORLANDIA' => 'Região 2', 
            'OSASCO' => 'Região 1', 
            'OSCAR BRESSANE' => 'Região 3', 
            'OSVALDO CRUZ' => 'Região 3', 
            'OURINHOS' => 'Região 4', 
            'OURO VERDE' => 'Região 3', 
            'OUROESTE' => 'Região 3', 
            'PACAEMBU' => 'Região 3', 
            'PALESTINA' => 'Região 3', 
            'PALMARES' => 'Região 3', 
            'PALMEIRA DOESTE' => 'Região 3', 
            'PALMITAL' => 'Região 4', 
            'PANORAMA' => 'Região 3', 
            'PARAGUACU PAULISTA' => 'Região 4', 
            'PARAIBUNA' => 'Região 4', 
            'PARAISO' => 'Região 3', 
            'PARAITINGA' => 'Região 4', 
            'PARANAPANEMA' => 'Região 4', 
            'PARANAPUA' => 'Região 3', 
            'PARAPUA' => 'Região 3', 
            'PARDINHO' => 'Região 3', 
            'PARIQUERAACU' => 'Região 4', 
            'PARISI' => 'Região 3', 
            'PATROCINIO PAULISTA' => 'Região 4', 
            'PAULICEIA' => 'Região 3', 
            'PAULINIA' => 'Região 1', 
            'PAULISTANIA' => 'Região 3', 
            'PAULO DE FARIA' => 'Região 3', 
            'PEDERNEIRAS' => 'Região 2', 
            'PEDRA BELA' => 'Região 2', 
            'PEDRANOPOLIS' => 'Região 3', 
            'PEDREGULHO' => 'Região 4', 
            'PEDREIRA' => 'Região 1', 
            'PEDRINHAS PAULISTA' => 'Região 4', 
            'PEDRO DE TOLEDO' => 'Região 4', 
            'PENAPOLIS' => 'Região 3', 
            'PEREIRA BARRETO' => 'Região 4', 
            'PEREIRAS' => 'Região 2', 
            'PERUIBE' => 'Região 4', 
            'PIACATU' => 'Região 3', 
            'PIEDADE' => 'Região 2', 
            'PILAR DO SUL' => 'Região 2', 
            'PINDAMONHANGABA' => 'Região 4', 
            'PINDORAMA' => 'Região 3', 
            'PINHALZINHO' => 'Região 2', 
            'PIQUEROBI' => 'Região 4', 
            'PIQUETE' => 'Região 4', 
            'PIRACAIA' => 'Região 2', 
            'PIRACICABA' => 'Região 2', 
            'PIRAJU' => 'Região 4', 
            'PIRAJUI' => 'Região 3', 
            'PIRANGI' => 'Região 2', 
            'PIRAPORA DO BOM JESUS' => 'Região 1', 
            'PIRAPOZINHO' => 'Região 4', 
            'PIRASSUNUNGA' => 'Região 2', 
            'PIRATININGA' => 'Região 3', 
            'PITANGUEIRAS' => 'Região 2', 
            'PLANALTO' => 'Região 3', 
            'PLATINA' => 'Região 4', 
            'POA' => 'Região 4', 
            'POLONI' => 'Região 3', 
            'POMPEIA' => 'Região 3', 
            'PONGAI' => 'Região 3', 
            'PONTAL' => 'Região 2', 
            'PONTALINDA' => 'Região 3', 
            'PONTES GESTAL' => 'Região 3', 
            'POPULINA' => 'Região 3', 
            'PORANGABA' => 'Região 2', 
            'PORTO FELIZ' => 'Região 2', 
            'PORTO FERREIRA' => 'Região 2', 
            'POTIM' => 'Região 4', 
            'POTIRENDABA' => 'Região 3', 
            'PRACINHA' => 'Região 3', 
            'PRADOPOLIS' => 'Região 2', 
            'PRAIA GRANDE' => 'Região 4', 
            'PRATANIA' => 'Região 3', 
            'PRESIDENTE ALVES' => 'Região 3', 
            'PRESIDENTE BERNARDES' => 'Região 4', 
            'PRESIDENTE EPITACIO' => 'Região 4', 
            'PRESIDENTE PRUDENTE' => 'Região 4', 
            'PRESIDENTE VENCESLAU' => 'Região 4', 
            'PROMISSAO' => 'Região 3', 
            'QUADRA' => 'Região 2', 
            'QUATA' => 'Região 4', 
            'QUEIROZ' => 'Região 3', 
            'QUELUZ' => 'Região 4', 
            'QUINTANA' => 'Região 3', 
            'RAFARD' => 'Região 2', 
            'RANCHARIA' => 'Região 4', 
            'REGENTE FEIJO' => 'Região 4', 
            'REGINOPOLIS' => 'Região 3', 
            'REGISTRO' => 'Região 4', 
            'RESTINGA' => 'Região 2', 
            'RIBEIRA' => 'Região 4', 
            'RIBEIRAO BONITO' => 'Região 3', 
            'RIBEIRAO BRANCO' => 'Região 4', 
            'RIBEIRAO CORRENTE' => 'Região 2', 
            'RIBEIRAO DO SUL' => 'Região 4', 
            'RIBEIRAO DOS INDIOS' => 'Região 4', 
            'RIBEIRAO GRANDE' => 'Região 4', 
            'RIBEIRAO PIRES' => 'Região 1', 
            'RIBEIRAO PRETO' => 'Região 2', 
            'RIFAINA' => 'Região 2', 
            'RINCAO' => 'Região 3', 
            'RINOPOLIS' => 'Região 3', 
            'RIO CLARO' => 'Região 3', 
            'RIO DAS PEDRAS' => 'Região 2', 
            'RIO GRANDE DA SERRA' => 'Região 1', 
            'RIOLANDIA' => 'Região 3', 
            'RIVERSUL' => 'Região 4', 
            'ROSANA' => 'Região 4', 
            'ROSEIRA' => 'Região 4', 
            'RUBIACEA' => 'Região 4', 
            'RUBINEIA' => 'Região 3', 
            'SABINO' => 'Região 3', 
            'SAGRES' => 'Região 3', 
            'SALES' => 'Região 3', 
            'SALES OLIVEIRA' => 'Região 2', 
            'SALESOPOLIS' => 'Região 4', 
            'SALMOURAO' => 'Região 3', 
            'SALTINHO' => 'Região 2', 
            'SAO PEDRO' => 'Região 2', 
            'SALTO' => 'Região 1', 
            'SALTO DE PIRAPORA' => 'Região 2', 
            'SALTO GRANDE' => 'Região 4', 
            'SANDOVALINA' => 'Região 4', 
            'SANTA ADELIA' => 'Região 3', 
            'SANTA ALBERTINA' => 'Região 3', 
            'SANTA BARBARA DOESTE' => 'Região 1', 
            'SANTA BRANCA' => 'Região 4', 
            'SANTA CLARA DOESTE' => 'Região 3', 
            'SANTA CRUZ DA CONCEICAO' => 'Região 2', 
            'SANTA CRUZ DA ESPERANCA' => 'Região 4', 
            'SANTA CRUZ DAS PALMEIRAS' => 'Região 2', 
            'SANTA CRUZ DO RIO PARDO' => 'Região 4', 
            'SANTA ERNESTINA' => 'Região 2', 
            'SANTA FE DO SUL' => 'Região 3', 
            'SANTA GERTRUDES' => 'Região 2', 
            'SANTA ISABEL' => 'Região 1', 
            'SANTA LUCIA' => 'Região 3', 
            'SANTA MARIA DA SERRA' => 'Região 2', 
            'SANTA MERCEDES' => 'Região 3', 
            'SANTA RITA DO PASSA' => 'Região 2', 
            'SANTA RITA DOESTE' => 'Região 3', 
            'SANTA ROSA DE VITERBO' => 'Região 2', 
            'SANTA SALETE' => 'Região 3', 
            'SANTANA DA PONTE PENSA' => 'Região 3', 
            'SANTANA DE PARNAIBA' => 'Região 1', 
            'SANTO ANASTACIO' => 'Região 4', 
            'SANTO ANDRE' => 'Região 1', 
            'SANTO ANTONIO DA ALEGRIA' => 'Região 4', 
            'SANTO ANTONIO DE POSSE' => 'Região 2', 
            'SANTO ANTONIO DO JARDIM' => 'Região 2', 
            'SANTO ANTONIO DO PINHAL' => 'Região 4', 
            'SANTO EXPEDITO' => 'Região 4', 
            'SANTOPOLIS DO AGUAPEI' => 'Região 3', 
            'SANTOS' => 'Região 4', 
            'SAO BENTO SAPUCAI' => 'Região 4', 
            'SAO BERNARDO DO CAMPO' => 'Região 1', 
            'SAO CAETANO DO SUL' => 'Região 1', 
            'SAO CARLOS' => 'Região 3', 
            'SAO FRANCISCO' => 'Região 3', 
            'SAO JOAO DA BOA VISTA' => 'Região 2', 
            'SAO JOAO DAS DUAS PONTES' => 'Região 3', 
            'SAO JOAO DE IRACEMA' => 'Região 3', 
            'SAO JOAO DO PAUDALHO' => 'Região 4', 
            'SAO JOAQUIM DA BARRA' => 'Região 2', 
            'SAO JOSE DA BELA VISTA' => 'Região 2', 
            'SAO JOSE DO BARREIRO' => 'Região 4', 
            'SAO JOSE DO RIO PARDO' => 'Região 2', 
            'SAO JOSE DO RIO PRETO' => 'Região 3', 
            'SAO JOSE DOS CAMPOS' => 'Região 4', 
            'SAO LOURENCO DA SERRA' => 'Região 1', 
            'SAO MANUEL' => 'Região 2', 
            'SAO MIGUEL ARCANJO' => 'Região 2', 
            'SAO PAULO' => 'Região 1', 
            'SAO PEDRO' => 'Região 2', 
            'SAO PEDRO DO TURVO' => 'Região 4', 
            'SAO ROQUE' => 'Região 2', 
            'SAO SEBASTIAO' => 'Região 4', 
            'SAO SEBASTIAO DA GRAMA' => 'Região 2', 
            'SAO SIMAO' => 'Região 2', 
            'SAO VICENTE' => 'Região 4', 
            'SARAPUI' => 'Região 2', 
            'SARUTAIA' => 'Região 4', 
            'SEBASTIANOPOLIS DO SUL' => 'Região 3', 
            'SERRA AZUL' => 'Região 2', 
            'SERRA NEGRA' => 'Região 2', 
            'SERRANA' => 'Região 2', 
            'SERTAOZINHO' => 'Região 2', 
            'SETE BARRAS' => 'Região 4', 
            'SEVERINIA' => 'Região 3', 
            'SILVEIRAS' => 'Região 4', 
            'SOCORRO' => 'Região 2', 
            'SOROCABA' => 'Região 2', 
            'STO ANT DO ARACANGUA' => 'Região 4', 
            'SUD MENNUCCI' => 'Região 4', 
            'SUMARE' => 'Região 1', 
            'SUZANAPOLIS' => 'Região 4', 
            'SUZANO' => 'Região 4', 
            'TABAPUA' => 'Região 3', 
            'TABATINGA' => 'Região 3', 
            'TABOAO DA SERRA' => 'Região 1', 
            'TACIBA' => 'Região 4', 
            'TAGUAI' => 'Região 4', 
            'TAIACU' => 'Região 2', 
            'TAIUVA' => 'Região 2', 
            'TAMBAU' => 'Região 2', 
            'TANABI' => 'Região 3', 
            'TAPIRAI' => 'Região 2', 
            'TAPIRATIBA' => 'Região 2', 
            'TAQUARAL' => 'Região 2', 
            'TAQUARITUBA' => 'Região 4', 
            'TAQUARIVAI' => 'Região 4', 
            'TARABAI' => 'Região 4', 
            'TARUMA' => 'Região 4', 
            'TATUI' => 'Região 2', 
            'TAUBATE' => 'Região 4', 
            'TEJUPA' => 'Região 4', 
            'TEODORO SAMPAIO' => 'Região 4', 
            'TERRA ROXA' => 'Região 2', 
            'TIETE' => 'Região 2', 
            'TIMBURI' => 'Região 4', 
            'TORRE DE PEDRA' => 'Região 2', 
            'TORRINHA' => 'Região 2', 
            'TRABIJU' => 'Região 3', 
            'TREMEMBE' => 'Região 4', 
            'TRES FRONTEIRAS' => 'Região 3', 
            'TUIUTI' => 'Região 2', 
            'TUPA' => 'Região 3', 
            'TUPI PAULISTA' => 'Região 3', 
            'TURIUBA' => 'Região 3', 
            'TURMALINA' => 'Região 3', 
            'UBARANA' => 'Região 3', 
            'UBATUBA' => 'Região 4', 
            'UBIRAJARA' => 'Região 3', 
            'UCHOA' => 'Região 3', 
            'UNIAO PAULISTA' => 'Região 3', 
            'URANIA' => 'Região 3', 
            'URU' => 'Região 3', 
            'URUPES' => 'Região 3', 
            'VALENTIM GENTIL' => 'Região 3', 
            'VALINHOS' => 'Região 1', 
            'VALPARAISO' => 'Região 3', 
            'VARGEM' => 'Região 2', 
            'VARGEM GRANDE DO SUL' => 'Região 2', 
            'VARGEM GRANDE PAULISTA' => 'Região 1', 
            'VARZEA PAULISTA' => 'Região 1', 
            'VERA CRUZ' => 'Região 3', 
            'VINHEDO' => 'Região 1', 
            'VIRADOURO' => 'Região 2', 
            'VISTA ALEGRE DO ALTO' => 'Região 2', 
            'VITORIA BRASIL' => 'Região 3', 
            'VOTORANTIM' => 'Região 2', 
            'VOTUPORANGA' => 'Região 3', 
            'ZACARIAS' => 'Região 3', 
        ];

        if (isset($mapa[$cidadeLpa])) return $mapa[$cidadeLpa];
        
        // Busca Flexível caso o XML traga o nome um pouco diferente
        foreach ($mapa as $key => $val) {
            if (str_contains($cidadeLpa, $key) || str_contains($key, $cidadeLpa)) {
                return $val;
            }
        }
        return '-';
    }

    private function extractChaveCTe($data, $nomeArquivo) {
        if (isset($data['protCTe']['infProt']['chCTe'])) return (string) $data['protCTe']['infProt']['chCTe'];
        $base = $this->getBaseNode($data);
        if ($base && isset($base['@attributes']['Id'])) return str_replace('CTe', '', $base['@attributes']['Id']);
        if (preg_match('/\\d{44}/', $nomeArquivo, $matches)) return $matches[0];
        return Str::uuid()->toString();
    }
    
    private function extractChaveOriginal($data) {
        $base = $this->getBaseNode($data);
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
    
    private function extractCity($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); 
        return null; 
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
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class DreOperacaoController extends Controller
{
    public function confrontarOperacao(Request $request)
    {
        $request->validate([
            'batch_sla'   => 'required|string',
            'batch_e4log' => 'required|string',
        ]);

        $dadosSlaFlat   = Cache::get('auditoria_sla_' . $request->batch_sla, []);
        $dadosE4logFlat = Cache::get('auditoria_e4log_' . $request->batch_e4log, []);

        if (empty($dadosSlaFlat) || empty($dadosE4logFlat)) {
            return response()->json(['message' => 'Lotes expirados ou vazios. Processe os XMLs novamente.'], 400);
        }

        // 1. CORREÇÃO: Agrupar (mesclar complementos) ANTES de cruzar na DRE!
        $slaCtrl = new AuditoriaSlaController();
        $e4logCtrl = new AuditoriaE4logController();
        
        $dadosSla = $slaCtrl->agruparResultados($dadosSlaFlat);
        $dadosE4log = $e4logCtrl->agruparResultados($dadosE4logFlat);

        // 2. CORREÇÃO: Indexar E4LOG com Fallback para notas sem NF-e
        $custosE4logIndexados = [];
        $custosE4logSemNfe = []; // Hash Fallback
        
        foreach ($dadosE4log as $e4log) {
            $chaveNfe = $e4log['chave_nfe'] ?? null;
            if ($chaveNfe && !str_contains($chaveNfe, 'SEM_NFE')) {
                $custosE4logIndexados[$chaveNfe] = $e4log;
            } else {
                // Cria um hash único baseado na cidade e no valor exato da carga
                $hash = md5(($e4log['cidade_destino'] ?? '') . ($e4log['valor_carga'] ?? 0));
                $custosE4logSemNfe[$hash] = $e4log;
            }
        }

        $dreResultados = [];
        $resumo = [
            'total_receita_real' => 0, 'total_receita_ideal'=> 0,
            'total_custo_real'   => 0, 'total_custo_ideal'  => 0,
            'lucro_bruto_real'   => 0, 'lucro_bruto_ideal'  => 0,
            'margem_global'      => 0,
            'qtd_alertas'        => 0, 'qtd_match'          => 0, 'qtd_prejuizo' => 0,
        ];

        // 3. CRUZAMENTO (BWT -> SOL FÁCIL)
        foreach ($dadosSla as $sla) {
            $chaveNfe = $sla['chave_nfe'] ?? null;
            $e4logMatch = null;

            // Tentativa 1: Match pela NF-e exata
            if ($chaveNfe && !str_contains($chaveNfe, 'SEM_NFE') && isset($custosE4logIndexados[$chaveNfe])) {
                $e4logMatch = $custosE4logIndexados[$chaveNfe];
                unset($custosE4logIndexados[$chaveNfe]); 
            } else {
                // Tentativa 2: Fallback Match (Salva as notas sem NF-e da perdição)
                $hashFallback = md5(($sla['cidade_destino'] ?? '') . ($sla['valor_carga'] ?? 0));
                if (isset($custosE4logSemNfe[$hashFallback])) {
                    $e4logMatch = $custosE4logSemNfe[$hashFallback];
                    unset($custosE4logSemNfe[$hashFallback]);
                }
            }
            
            // Separação de Receita Real (Faturado)
            $recValorFaturado = (float) ($sla['valor_cobrado'] ?? 0);
            $recFreteCobrado  = (float) ($sla['valor_frete_cobrado'] ?? $recValorFaturado);
            $recTdeCobrado    = (float) ($sla['valor_tde_cobrado'] ?? 0);
            
            // Separação de Receita Ideal (SLA MATRIZ)
            $recValorIdeal    = (float) ($sla['valor_sla'] ?? 0);
            $recFreteIdeal    = (float) ($sla['valor_frete_sla'] ?? $recValorIdeal);
            $recTdeIdeal      = (float) ($sla['valor_tde_sla'] ?? 0);
            
            // Variáveis Custo Iniciais
            $cusMatriz = '-'; $cusFaturada = '-';
            $cusValorCobrado = 0; $cusFreteCobrado = 0; $cusTdeCobrado = 0;
            $cusValorIdeal = 0;   $cusFreteIdeal = 0;   $cusTdeIdeal = 0;
            $cusDiferenca = 0;
            
            $arquivoE4log = 'CUSTO PENDENTE';
            $arquivosE4logCompl = [];

            // Se achou correspondência no Custo (Match)
            if ($e4logMatch) {
                $cusMatriz = $e4logMatch['regiao_sistema'] ?? '-';
                $cusFaturada = ($e4logMatch['regiao_faturada'] ?? '-') . ' (' . ($e4logMatch['percentual_faturado'] ?? '-') . ')';
                
                // Custo Real
                $cusValorCobrado = (float) ($e4logMatch['valor_cobrado'] ?? 0);
                $cusFreteCobrado = (float) ($e4logMatch['valor_frete_cobrado'] ?? $cusValorCobrado);
                $cusTdeCobrado   = (float) ($e4logMatch['valor_tde_cobrado'] ?? 0);
                
                // Custo Ideal
                $cusValorIdeal   = (float) ($e4logMatch['valor_sla'] ?? 0);
                $cusFreteIdeal   = (float) ($e4logMatch['valor_frete_sla'] ?? $cusValorIdeal);
                $cusTdeIdeal     = (float) ($e4logMatch['valor_tde_sla'] ?? 0);
                
                $cusDiferenca = (float) ($e4logMatch['diferenca'] ?? 0);
                $arquivoE4log = $e4logMatch['arquivo'] ?? '-';
                $arquivosE4logCompl = $e4logMatch['arquivos_complemento'] ?? [];

                $resumo['qtd_match']++;
            }

            $lucroReal = $recValorFaturado - $cusValorCobrado;
            $lucroIdeal = $recValorIdeal - $cusValorIdeal;
            $margemRealPct = $recValorFaturado > 0 ? ($lucroReal / $recValorFaturado) * 100 : 0;

            $statusGeral = 'OK';
            if ($cusValorCobrado == 0) $statusGeral = 'CUSTO PENDENTE';
            elseif ($lucroReal < 0) { $statusGeral = 'PREJUÍZO DRE'; $resumo['qtd_prejuizo']++; } 
            elseif (($sla['diferenca'] ?? 0) != 0 || $cusDiferenca != 0) { $statusGeral = 'DIVERGÊNCIA'; $resumo['qtd_alertas']++; }

            $resumo['total_receita_real'] += $recValorFaturado; $resumo['total_custo_real'] += $cusValorCobrado;
            $resumo['total_receita_ideal'] += $recValorIdeal;   $resumo['total_custo_ideal'] += $cusValorIdeal;
            $resumo['lucro_bruto_real'] += $lucroReal;          $resumo['lucro_bruto_ideal'] += $lucroIdeal;

            $dreResultados[] = [
                'chave_nfe'     => str_contains($chaveNfe, 'SEM_NFE') ? 'SEM NF-e (Match via Hash)' : $chaveNfe,
                'cidade'        => $sla['cidade_destino'] ?? 'Desconhecida',
                'valor_carga'   => $sla['valor_carga'] ?? 0,
                'tem_tde'       => $sla['tem_tde'] ?? 'Não',
                
                'arquivo_bwt'        => $sla['arquivo'] ?? '-',
                'arquivos_bwt_compl' => $sla['arquivos_complemento'] ?? [],
                'arquivo_e4log'        => $arquivoE4log,
                'arquivos_e4log_compl' => $arquivosE4logCompl,
                
                'receita' => [
                    'matriz'      => $sla['regiao_sistema'] ?? '-', 
                    'faturada'    => ($sla['regiao_faturada'] ?? '-') . ' (' . ($sla['percentual_faturado'] ?? '-') . ')',
                    'real'        => $recValorFaturado, 
                    'real_frete'  => $recFreteCobrado, 
                    'real_tde'    => $recTdeCobrado,
                    'ideal'       => $recValorIdeal, 
                    'ideal_frete' => $recFreteIdeal, 
                    'ideal_tde'   => $recTdeIdeal,
                    'diferenca'   => (float) ($sla['diferenca'] ?? 0)
                ],
                'custo' => [
                    'matriz'      => $cusMatriz, 
                    'faturada'    => $cusFaturada,
                    'real'        => $cusValorCobrado, 
                    'real_frete'  => $cusFreteCobrado, 
                    'real_tde'    => $cusTdeCobrado,
                    'ideal'       => $cusValorIdeal, 
                    'ideal_frete' => $cusFreteIdeal, 
                    'ideal_tde'   => $cusTdeIdeal,
                    'diferenca'   => $cusDiferenca
                ],
                'dre' => [
                    'lucro_real' => $lucroReal, 'lucro_ideal' => $lucroIdeal, 'margem_real' => round($margemRealPct, 2),
                ],
                'status' => $statusGeral
            ];
        }

        // 4. FUROS DE FATURAMENTO (Custos E4LOG Órfãos sem Receita)
        // Combina o que sobrou nos dois arrays de controle
        $sobrasE4log = array_merge(array_values($custosE4logIndexados), array_values($custosE4logSemNfe));
        
        foreach ($sobrasE4log as $e4log) {
            $chaveOriginalNfe = $e4log['chave_nfe'] ?? '-';
            $cusValorCobrado = (float) ($e4log['valor_cobrado'] ?? 0);
            $cusFreteCobrado = (float) ($e4log['valor_frete_cobrado'] ?? $cusValorCobrado);
            $cusTdeCobrado   = (float) ($e4log['valor_tde_cobrado'] ?? 0);
            
            $cusValorIdeal   = (float) ($e4log['valor_sla'] ?? 0);
            $cusFreteIdeal   = (float) ($e4log['valor_frete_sla'] ?? $cusValorIdeal);
            $cusTdeIdeal     = (float) ($e4log['valor_tde_sla'] ?? 0);
            
            $resumo['total_custo_real'] += $cusValorCobrado; $resumo['lucro_bruto_real'] -= $cusValorCobrado;
            $resumo['total_custo_ideal'] += $cusValorIdeal;  $resumo['lucro_bruto_ideal'] -= $cusValorIdeal;
            $resumo['qtd_alertas']++; $resumo['qtd_prejuizo']++;

            $dreResultados[] = [
                'chave_nfe'     => str_contains($chaveOriginalNfe, 'SEM_NFE') ? 'SEM NF-e' : $chaveOriginalNfe,
                'cidade'        => $e4log['cidade_destino'] ?? '-',
                'valor_carga'   => $e4log['valor_carga'] ?? 0,
                'tem_tde'       => $e4log['tem_tde'] ?? 'Não',
                'arquivo_bwt'        => 'NÃO LOCALIZADO NO LOTE (FURO DE RECEITA)',
                'arquivos_bwt_compl' => [],
                'arquivo_e4log'        => $e4log['arquivo'] ?? '-',
                'arquivos_e4log_compl' => $e4log['arquivos_complemento'] ?? [],
                'receita' => [
                    'matriz' => 'FURO', 'faturada' => '-', 
                    'real' => 0, 'real_frete' => 0, 'real_tde' => 0, 
                    'ideal' => 0, 'ideal_frete' => 0, 'ideal_tde' => 0, 
                    'diferenca' => 0
                ],
                'custo' => [
                    'matriz'      => $e4log['regiao_sistema'] ?? '-', 
                    'faturada'    => ($e4log['regiao_faturada'] ?? '-') . ' (' . ($e4log['percentual_faturado'] ?? '-') . ')',
                    'real'        => $cusValorCobrado, 
                    'real_frete'  => $cusFreteCobrado, 
                    'real_tde'    => $cusTdeCobrado,
                    'ideal'       => $cusValorIdeal, 
                    'ideal_frete' => $cusFreteIdeal, 
                    'ideal_tde'   => $cusTdeIdeal,
                    'diferenca'   => (float) ($e4log['diferenca'] ?? 0)
                ],
                'dre' => [
                    'lucro_real' => -$cusValorCobrado, 'lucro_ideal' => -$cusValorIdeal, 'margem_real' => -100,
                ],
                'status' => 'FURO DE RECEITA'
            ];
        }

        // Calcula Margem Global
        $resumo['margem_global'] = $resumo['total_receita_real'] > 0 
            ? round(($resumo['lucro_bruto_real'] / $resumo['total_receita_real']) * 100, 2) 
            : 0;

        // Ordenação da DRE (Maior prejuízo primeiro, depois maior lucro, depois zerados)
        usort($dreResultados, function($a, $b) {
            // 1º Prioridade Absoluta: Furos de Receita sempre no topo
            if ($a['status'] === 'FURO DE RECEITA' && $b['status'] !== 'FURO DE RECEITA') return -1;
            if ($b['status'] === 'FURO DE RECEITA' && $a['status'] !== 'FURO DE RECEITA') return 1;

            $lucroA = round($a['dre']['lucro_real'], 2);
            $lucroB = round($b['dre']['lucro_real'], 2);

            // Separar em 3 grupos: 1 = Negativos, 2 = Positivos, 3 = Zerados
            $grupoA = $lucroA < 0 ? 1 : ($lucroA > 0 ? 2 : 3);
            $grupoB = $lucroB < 0 ? 1 : ($lucroB > 0 ? 2 : 3);

            // Se estão em grupos diferentes, ordena pela ordem (Negativos -> Positivos -> Zerados)
            if ($grupoA !== $grupoB) {
                return $grupoA <=> $grupoB;
            }

            // Se ambos são Negativos, do MAIOR prejuízo para o menor (ex: -5000 vem antes de -100)
            if ($grupoA === 1) {
                return $lucroA <=> $lucroB; 
            }

            // Se ambos são Positivos, do MAIOR lucro para o menor (ex: 5000 vem antes de 100)
            if ($grupoA === 2) {
                return $lucroB <=> $lucroA; 
            }

            // Desempate por cidade se for tudo igual
            return strcmp($a['cidade'] . $a['chave_nfe'], $b['cidade'] . $b['chave_nfe']);
        });

        $batchDreId = Str::uuid()->toString();
        Cache::put('dre_operacao_' . $batchDreId, ['dados' => $dreResultados, 'resumo' => $resumo], now()->addHours(2));

        return response()->json(['batch_dre' => $batchDreId, 'resumo' => $resumo, 'data' => $dreResultados]);
    }

    public function exportarPdf($batchDreId) {
        ini_set('max_execution_time', 0); ini_set('memory_limit', '2G');    
        $dreData = Cache::get('dre_operacao_' . $batchDreId);
        if (!$dreData) abort(404, 'Sessão da DRE expirada.');

        $pdf = Pdf::loadView('pdf.dre_operacao_report', [
            'dados' => $dreData['dados'], 'resumo' => $dreData['resumo'], 'data_auditoria' => now()->format('d/m/Y H:i')
        ]);
        $pdf->setPaper('A4', 'landscape'); 
        return $pdf->stream('dre_operacao_' . now()->format('YmdHi') . '.pdf');
    }
}
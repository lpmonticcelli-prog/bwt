<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Faturamento;
use App\Models\Frete;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $fechamentoId = $request->input('fechamento_id');

        $faturamentoQuery = Faturamento::query();
        $freteQuery = Frete::query();
        
        $fretesDetalhados = []; 
        $faturamentosDetalhados = []; 
        $cruzamentoViagens = []; 

        if ($fechamentoId) {
            // Correção de Chaves de Complemento (Herda a Chave da Nota Original)
            $fatsComComplemento = Faturamento::whereNotNull('chave_complementada')->get();
            foreach($fatsComComplemento as $fat) {
                $parent = Faturamento::where('cte_chave', $fat->chave_complementada)->first();
                if ($parent && $parent->nfe_chave && $parent->nfe_chave !== 'N/A' && $fat->nfe_chave !== $parent->nfe_chave) {
                    $fat->nfe_chave = $parent->nfe_chave;
                    $fat->save();
                }
            }

            $fretesComComplemento = Frete::whereNotNull('chave_complementada')->get();
            foreach($fretesComComplemento as $frete) {
                $parent = Frete::where('cte_chave', $frete->chave_complementada)->first();
                if ($parent && $parent->nfe_chave && $parent->nfe_chave !== 'N/A' && $frete->nfe_chave !== $parent->nfe_chave) {
                    $frete->nfe_chave = $parent->nfe_chave;
                    $frete->save();
                }
            }

            $faturamentoQuery->where('fechamento_periodo_id', $fechamentoId);
            $freteQuery->where('fechamento_periodo_id', $fechamentoId);

            // Carrega Listas Detalhadas
            $fretesDetalhados = Frete::where('fechamento_periodo_id', $fechamentoId)
                ->select('id', 'arquivo', 'destino', 'tipo_operacao', 'data_emissao', 'data_entrega', 'valorNF', 'freteBaseCalculado', 'taxasExtras', 'temTde', 'tdeCalculado', 'cobrado', 'correto', 'diferenca', 'is_correto', 'regra', 'cte_chave', 'chave_complementada', 'observacoes')
                ->orderBy('diferenca', 'desc')->get();

            $faturamentosDetalhados = Faturamento::where('fechamento_periodo_id', $fechamentoId)
                ->whereRaw('receita_teorica > receita_real')
                ->select('id', 'arquivo', 'destino', 'tipo_operacao', 'nfe_chave', 'regra', 'valor_carga', 'receita_frete_base', 'receita_tde', 'receita_icms', 'receita_teorica', 'receita_real', 'cte_chave', 'chave_complementada', 'observacoes')
                ->selectRaw('(receita_teorica - receita_real) as gap_individual')
                ->orderBy('gap_individual', 'desc')->get();

            $todosFaturamentos = Faturamento::where('fechamento_periodo_id', $fechamentoId)->get();
            $todosFretes = Frete::where('fechamento_periodo_id', $fechamentoId)->get();

            $fretesAgrupados = $todosFretes->groupBy('nfe_chave');
            $faturamentosAgrupados = $todosFaturamentos->groupBy('nfe_chave'); 

            $chavesProcessadas = [];

            // Cruzamento de Dados (Faturamento -> Custo)
            foreach ($faturamentosAgrupados as $chaveNfe => $faturamentosDaCarga) {
                $isSemChave = empty($chaveNfe) || $chaveNfe === 'N/A';
                if ($isSemChave) {
                    foreach ($faturamentosDaCarga as $fat) {
                        $cruzamentoViagens[] = [
                            'id' => 'fat_'.$fat->id, 'nfe_chave' => 'N/A (Complemento sem Lastro)', 'destino' => $fat->destino,
                            'cte_bwt' => [$fat->arquivo], 'ctes_e4log' => [], 'receita' => $fat->receita_real, 'custo' => 0, 'lucro' => $fat->receita_real,
                            'status' => 'sem_custo', 'bwt_detalhes' => [$fat], 'e4log_detalhes' => [] 
                        ];
                    }
                    continue;
                }

                $fretesMatch = $fretesAgrupados->get($chaveNfe, collect());
                $chavesProcessadas[] = $chaveNfe; 
                
                $receitaTotal = $faturamentosDaCarga->sum('receita_real');
                $custoTotal = $fretesMatch->sum('cobrado');

                $cruzamentoViagens[] = [
                    'id' => 'match_'.$chaveNfe, 'nfe_chave' => $chaveNfe, 'destino' => $faturamentosDaCarga->first()->destino, 
                    'cte_bwt' => $faturamentosDaCarga->pluck('arquivo')->toArray(), 'ctes_e4log' => $fretesMatch->pluck('arquivo')->toArray(), 
                    'receita' => $receitaTotal, 'custo' => $custoTotal, 'lucro' => $receitaTotal - $custoTotal,
                    'status' => $fretesMatch->isEmpty() ? 'sem_custo' : 'casada',
                    'bwt_detalhes' => $faturamentosDaCarga->values()->all(), 'e4log_detalhes' => $fretesMatch->values()->all() 
                ];
            }

            // Cruzamento Órfãos (Custo sem Faturamento)
            foreach ($fretesAgrupados as $chaveNfe => $fretesDaCarga) {
                $isSemChave = empty($chaveNfe) || $chaveNfe === 'N/A';
                if (!$isSemChave && in_array($chaveNfe, $chavesProcessadas)) continue; 

                if ($isSemChave) {
                    foreach ($fretesDaCarga as $frete) {
                        $cruzamentoViagens[] = [
                            'id' => 'frete_'.$frete->id, 'nfe_chave' => 'N/A (Complemento sem Lastro)', 'destino' => $frete->destino,
                            'cte_bwt' => ['NÃO FATURADO'], 'ctes_e4log' => [$frete->arquivo], 'receita' => 0, 'custo' => $frete->cobrado,
                            'lucro' => 0 - $frete->cobrado, 'status' => 'sem_receita', 'bwt_detalhes' => [], 'e4log_detalhes' => [$frete]
                        ];
                    }
                    continue;
                }

                $custoTotal = $fretesDaCarga->sum('cobrado');
                $cruzamentoViagens[] = [
                    'id' => 'orphan_match_'.$chaveNfe, 'nfe_chave' => $chaveNfe, 'destino' => $fretesDaCarga->first()->destino,
                    'cte_bwt' => ['NÃO FATURADO'], 'ctes_e4log' => $fretesDaCarga->pluck('arquivo')->toArray(),
                    'receita' => 0, 'custo' => $custoTotal, 'lucro' => 0 - $custoTotal, 'status' => 'sem_receita',
                    'bwt_detalhes' => [], 'e4log_detalhes' => $fretesDaCarga->values()->all()
                ];
            }

            usort($cruzamentoViagens, function($a, $b) { return $a['lucro'] <=> $b['lucro']; });
        }

        $faturamento = [
            'total_notas' => $faturamentoQuery->count(), 'receita_total' => $faturamentoQuery->sum('receita_real'),
            'receita_teorica' => $faturamentoQuery->sum('receita_teorica'), 'lucro_total' => $faturamentoQuery->sum('lucro'),
        ];

        $auditoria = [
            'total_notas' => $freteQuery->count(), 'custo_cobrado' => $freteQuery->sum('cobrado'),
            'custo_correto' => $freteQuery->sum('correto'), 'diferenca_total' => $freteQuery->sum('diferenca'),
        ];

        // ATENÇÃO: Retorna o Inertia Render com o novo caminho 'Dashboard/Index' que criámos!
        return Inertia::render('Dashboard/Index', [
            'resumoFaturamento' => $faturamento, 
            'resumoAuditoria' => $auditoria,
            'fechamentos' => \App\Models\FechamentoPeriodo::orderBy('data_inicio', 'desc')->get(), 
            'fechamento_id' => $fechamentoId, 
            'fretesDetalhados' => $fretesDetalhados, 
            'faturamentosDetalhados' => $faturamentosDetalhados, 
            'cruzamentoViagens' => $cruzamentoViagens 
        ]);
    }
}
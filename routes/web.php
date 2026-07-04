<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use App\Http\Controllers\FechamentoController;
use App\Models\Faturamento;
use App\Models\Frete;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () { return redirect()->route('login'); });

Route::get('/dashboard', function (Request $request) {
    $fechamentoId = $request->input('fechamento_id');

    $faturamentoQuery = Faturamento::query();
    $freteQuery = Frete::query();
    
    $fretesDetalhados = []; 
    $faturamentosDetalhados = []; 
    $cruzamentoViagens = []; 

    if ($fechamentoId) {
        
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

    return Inertia::render('Dashboard', [
        'resumoFaturamento' => $faturamento, 'resumoAuditoria' => $auditoria,
        'fechamentos' => \App\Models\FechamentoPeriodo::orderBy('data_inicio', 'desc')->get(), 'fechamento_id' => $fechamentoId, 
        'fretesDetalhados' => $fretesDetalhados, 'faturamentosDetalhados' => $faturamentosDetalhados, 'cruzamentoViagens' => $cruzamentoViagens 
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/fechamentos', [FechamentoController::class, 'index'])->name('fechamentos.index');
Route::post('/fechamentos', [FechamentoController::class, 'store'])->name('fechamentos.store');
Route::get('/fechamentos/{id}', [FechamentoController::class, 'show'])->name('fechamentos.show');
Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');
Route::post('/auditoria/processar', [AuditController::class, 'processarCusto'])->name('auditoria.processar');
Route::get('/auditoria/processar', function () { return redirect()->route('auditoria.index'); });
Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');
Route::post('/faturamento/processar', [AuditController::class, 'processarReceita'])->name('faturamento.processar');

Route::post('/fechamentos/{id}/sincronizar', function (Request $request, $id) {
    $servico = new \App\Services\BsoftSyncService();
    $resultado = $servico->atualizarBaixasBwt($id);
    if ($request->wantsJson()) return response()->json($resultado);
    return back()->with('success', $resultado['message']);
})->name('fechamentos.sincronizar');
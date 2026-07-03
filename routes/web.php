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

// Redireciona a raiz direto para o login ou dashboard
Route::get('/', function () {
    return redirect()->route('login');
});

// Rota do Dashboard com a injeção de dados, Filtro de Fechamento e Listagem para o Raio-X
Route::get('/dashboard', function (Request $request) {
    
    // Identifica se você selecionou um fechamento específico no dropdown
    $fechamentoId = $request->input('fechamento_id');

    // Prepara as consultas no banco de dados base
    $faturamentoQuery = Faturamento::query();
    $freteQuery = Frete::query();
    $fretesDetalhados = []; // Matriz vazia por padrão (Visão Global)

    // Se um fechamento foi selecionado na tela, aplica o filtro matemático
    if ($fechamentoId) {
        $faturamentoQuery->where('fechamento_periodo_id', $fechamentoId);
        $freteQuery->where('fechamento_periodo_id', $fechamentoId);

        // Busca a lista detalhada de XMLs da E4LOG para montar a Tabela e o Raio-X
        $fretesDetalhados = Frete::where('fechamento_periodo_id', $fechamentoId)
            ->select(
                'id', 'arquivo', 'destino', 'tipo_operacao', 'data_emissao', 'data_entrega', 
                'valorNF', 'freteBaseCalculado', 'taxasExtras', 'temTde', 'tdeCalculado', 
                'cobrado', 'correto', 'diferenca', 'is_correto', 'regra'
            )
            ->orderBy('diferenca', 'desc') // Traz as piores divergências primeiro
            ->get();
    }

    $faturamento = [
        'total_notas' => $faturamentoQuery->count(),
        'receita_total' => $faturamentoQuery->sum('receita_real'),
        'receita_teorica' => $faturamentoQuery->sum('receita_teorica'), 
        'lucro_total' => $faturamentoQuery->sum('lucro'),
    ];

    $auditoria = [
        'total_notas' => $freteQuery->count(),
        'custo_cobrado' => $freteQuery->sum('cobrado'),
        'custo_correto' => $freteQuery->sum('correto'), 
        'diferenca_total' => $freteQuery->sum('diferenca'),
    ];

    return Inertia::render('Dashboard', [
        'resumoFaturamento' => $faturamento,
        'resumoAuditoria' => $auditoria,
        'fechamentos' => \App\Models\FechamentoPeriodo::orderBy('data_inicio', 'desc')->get(), // Envia os fechamentos para o Menu
        'fechamento_id' => $fechamentoId, // Mantém o filtro selecionado na tela
        'fretesDetalhados' => $fretesDetalhados // Envia os dados cirúrgicos para a nova funcionalidade de Raio-X
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Rotas de Gestão de Fechamentos (ioapps ERP)
Route::get('/fechamentos', [FechamentoController::class, 'index'])->name('fechamentos.index');
Route::post('/fechamentos', [FechamentoController::class, 'store'])->name('fechamentos.store');
Route::get('/fechamentos/{id}', [FechamentoController::class, 'show'])->name('fechamentos.show');

// Rotas do nosso Painel de Auditoria E4LOG
Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');

// 🚀 A MÁGICA ACONTECE AQUI: Rota Universal de Processamento
Route::post('/auditoria/processar', [AuditController::class, 'processar'])->name('auditoria.processar');

Route::get('/auditoria/processar', function () {
    return redirect()->route('auditoria.index');
});

// Rotas do nosso Painel de Receita (Sol Fácil)
Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');

// 🚀 A MÁGICA ACONTECE AQUI TAMBÉM: Aponta para o mesmo Cérebro
Route::post('/faturamento/processar', [AuditController::class, 'processar'])->name('faturamento.processar');

// ROTA ATUALIZADA: Robô de Integração focado apenas no ID do Fechamento selecionado (Performance Bsoft)
Route::post('/fechamentos/{id}/sincronizar', function (Request $request, $id) {
    $servico = new \App\Services\BsoftSyncService();
    $resultado = $servico->atualizarBaixasBwt($id);
    
    if ($request->wantsJson()) {
        return response()->json($resultado);
    }
    return back()->with('success', $resultado['message']);
})->name('fechamentos.sincronizar');
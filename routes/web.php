<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use App\Http\Controllers\FechamentoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SimuladorController; 
use App\Http\Controllers\AuditoriaSlaController;
use App\Http\Controllers\AuditoriaE4logController; 
use App\Http\Controllers\DreOperacaoController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () { return redirect()->route('login'); });

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ==========================================
// MÓDULO: ESTEIRA DE AUDITORIA E DRE (NOVO)
// ==========================================
Route::middleware('auth')->group(function () {
    // 1. Auditoria SLA (BWT -> Sol Fácil) - A tela principal do Index.vue fica aqui
    Route::get('/auditoria-sla', [AuditoriaSlaController::class, 'index'])->name('auditoria-sla.index');
    Route::post('/auditoria-sla/processar', [AuditoriaSlaController::class, 'processar'])->name('auditoria-sla.processar');
    Route::get('/auditoria-sla/exportar-pdf/{batchId}', [AuditoriaSlaController::class, 'exportarPdf'])->name('auditoria-sla.export');

    // 2. Auditoria de Custos (E4LOG -> BWT)
    Route::post('/auditoria/e4log/processar', [AuditoriaE4logController::class, 'processar'])->name('auditoria.e4log.processar');
    Route::get('/auditoria/e4log/exportar-pdf/{batchId}', [AuditoriaE4logController::class, 'exportarPdf'])->name('auditoria.e4log.export');

    // 3. DRE (O Cruzamento final)
    Route::post('/dre/confrontar', [DreOperacaoController::class, 'confrontarOperacao'])->name('dre.confrontar');
    Route::get('/dre/exportar-pdf/{batchDreId}', [DreOperacaoController::class, 'exportarPdf'])->name('dre.exportar');
});

// ==========================================
// MÓDULOS ANTIGOS / OUTROS
// ==========================================

// Auditoria Antiga (Custo Padrão)
Route::middleware('auth')->group(function () {
    Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');
    Route::get('/auditoria/regras', [AuditController::class, 'regras'])->name('auditoria.regras');
    Route::delete('/auditoria/limpar', [AuditController::class, 'limparLote'])->name('auditoria.limpar');
    Route::post('/auditoria/processar', [AuditController::class, 'processarCusto'])->name('auditoria.processar');
});

// Módulo de Faturamento
Route::middleware('auth')->group(function () {
    Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');
    Route::post('/faturamento/processar', [FaturamentoController::class, 'processar'])->name('faturamento.processar');
});

// Módulo de Fechamentos (Lotes)
Route::middleware('auth')->group(function () {
    Route::get('/fechamentos', [FechamentoController::class, 'index'])->name('fechamentos.index');
    Route::post('/fechamentos', [FechamentoController::class, 'store'])->name('fechamentos.store');
    Route::get('/fechamentos/{id}', [FechamentoController::class, 'show'])->name('fechamentos.show');
    
    Route::post('/fechamentos/{id}/sincronizar', function (Request $request, $id) {
        $servico = new \App\Services\BsoftSyncService();
        $resultado = $servico->atualizarBaixasBwt($id);
        if ($request->wantsJson()) return response()->json($resultado);
        return back()->with('success', $resultado['message']);
    })->name('fechamentos.sincronizar');
});

// Módulo PRO (Simulador de Contratos) 
Route::middleware('auth')->group(function () {
    Route::get('/simulador-contratos', [SimuladorController::class, 'index'])->name('simulador.index');
});

// Gestão do Perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
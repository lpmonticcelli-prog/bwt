<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use App\Http\Controllers\FechamentoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SimuladorController; // 👇 IMPORTAÇÃO DO NOVO CONTROLLER AQUI
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () { return redirect()->route('login'); });

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Módulo de Auditoria (Custo E4LOG - Ambiente Sandbox)
Route::middleware('auth')->group(function () {
    Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');
    Route::get('/auditoria/regras', [AuditController::class, 'regras'])->name('auditoria.regras');
    Route::delete('/auditoria/limpar', [AuditController::class, 'limparLote'])->name('auditoria.limpar');
    
    Route::post('/auditoria/processar', [AuditController::class, 'processarCusto'])->name('auditoria.processar');
    Route::get('/auditoria/processar', function () { return redirect()->route('auditoria.index'); });
});

// Módulo de Faturamento (Receita Sol Fácil)
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

// 👇 NOVA ROTA: Módulo PRO (Simulador de Contratos) 👇
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
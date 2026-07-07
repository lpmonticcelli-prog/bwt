<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use App\Http\Controllers\FechamentoController;
use App\Http\Controllers\DashboardController; // NOVO: Controller focado apenas no Dashboard
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

Route::get('/', function () { return redirect()->route('login'); });

// A Rota do Dashboard agora aponta para um Controller dedicado, não faz o código espaguete aqui!
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Módulo de Auditoria (Custo E4LOG)
Route::middleware('auth')->group(function () {
    Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');
    Route::post('/auditoria/processar', [AuditController::class, 'processarCusto'])->name('auditoria.processar');
    Route::get('/auditoria/processar', function () { return redirect()->route('auditoria.index'); });
});

// Módulo de Faturamento (Receita Sol Fácil)
Route::middleware('auth')->group(function () {
    Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');
    // Atenção: A sua rota antiga estava a apontar para o AuditController, mas o código limpo agora está no FaturamentoController
    Route::post('/faturamento/processar', [FaturamentoController::class, 'processar'])->name('faturamento.processar');
});

// Módulo de Fechamentos (Lotes)
Route::middleware('auth')->group(function () {
    Route::get('/fechamentos', [FechamentoController::class, 'index'])->name('fechamentos.index');
    Route::post('/fechamentos', [FechamentoController::class, 'store'])->name('fechamentos.store');
    Route::get('/fechamentos/{id}', [FechamentoController::class, 'show'])->name('fechamentos.show');
    
    // Rota de Sincronização Bsoft
    Route::post('/fechamentos/{id}/sincronizar', function (Request $request, $id) {
        $servico = new \App\Services\BsoftSyncService();
        $resultado = $servico->atualizarBaixasBwt($id);
        if ($request->wantsJson()) return response()->json($resultado);
        return back()->with('success', $resultado['message']);
    })->name('fechamentos.sincronizar');
});

// Gestão do Perfil do Utilizador
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
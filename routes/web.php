<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use App\Models\Faturamento;
use App\Models\Frete;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redireciona a raiz direto para o login ou dashboard
Route::get('/', function () {
    return redirect()->route('login');
});

// Rota do Dashboard com a injeção de dados reais
Route::get('/dashboard', function () {
    $faturamento = [
        'total_notas' => Faturamento::count(),
        'receita_total' => Faturamento::sum('receita_real'),
        'lucro_total' => Faturamento::sum('lucro'),
    ];

    $auditoria = [
        'total_notas' => Frete::count(),
        'custo_cobrado' => Frete::sum('cobrado'),
        'diferenca_total' => Frete::sum('diferenca'),
    ];

    return Inertia::render('Dashboard', [
        'resumoFaturamento' => $faturamento,
        'resumoAuditoria' => $auditoria
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Rotas do nosso Painel de Auditoria E4LOG
Route::get('/auditoria', [AuditController::class, 'index'])->name('auditoria.index');
Route::post('/auditoria/processar', [AuditController::class, 'processar'])->name('auditoria.processar');

Route::get('/auditoria/processar', function () {
    return redirect()->route('auditoria.index');
});

// Rotas do nosso Painel de Receita (Sol Fácil)
Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');
Route::post('/faturamento/processar', [FaturamentoController::class, 'processar'])->name('faturamento.processar');
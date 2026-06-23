<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\FaturamentoController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
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

// Trava de segurança: Se o usuário der F5 na tela de processo, devolve ele pro painel suavemente
Route::get('/auditoria/processar', function () {
    return redirect()->route('auditoria.index');
});

// Rotas do nosso Painel de Receita (Sol Fácil)
Route::get('/faturamento/solfacil', [FaturamentoController::class, 'index'])->name('faturamento.index');
Route::post('/faturamento/processar', [FaturamentoController::class, 'processar'])->name('faturamento.processar');
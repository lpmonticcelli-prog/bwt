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
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BsoftController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\BsoftSyncService;

Route::get('/', function () { return redirect()->route('login'); });

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Centralizando todas as rotas autenticadas em um único grupo
Route::middleware('auth')->group(function () {

    // ==========================================
    // MÓDULO: ESTEIRA DE AUDITORIA E DRE
    // ==========================================
    
    // 1. Auditoria SLA (BWT -> Sol Fácil)
    Route::controller(AuditoriaSlaController::class)->prefix('auditoria-sla')->name('auditoria-sla.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/processar', 'processar')->name('processar');
        Route::get('/exportar-pdf/{batchId}', 'exportarPdf')->name('export');
    });

    // 2. Auditoria de Custos (E4LOG -> BWT)
    Route::controller(AuditoriaE4logController::class)->prefix('auditoria/e4log')->name('auditoria.e4log.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/processar', 'processar')->name('processar');
        Route::get('/exportar-pdf/{batchId}', 'exportarPdf')->name('export');
    });

    // 3. DRE (O Cruzamento final)
    Route::controller(DreOperacaoController::class)->prefix('dre')->name('dre.')->group(function () {
        Route::post('/confrontar', 'confrontarOperacao')->name('confrontar');
        Route::get('/exportar-pdf/{batchDreId}', 'exportarPdf')->name('exportar');
    });


    // ==========================================
    // MÓDULOS OPERACIONAIS
    // ==========================================

    // Auditoria Antiga (Custo Padrão)
    Route::controller(AuditController::class)->prefix('auditoria')->name('auditoria.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/regras', 'regras')->name('regras');
        Route::delete('/limpar', 'limparLote')->name('limpar');
        Route::post('/processar', 'processarCusto')->name('processar');
    });

    // Módulo de Faturamento
    Route::controller(FaturamentoController::class)->prefix('faturamento')->name('faturamento.')->group(function () {
        Route::get('/', 'index')->name('index'); 
        Route::post('/processar', 'processar')->name('processar');
        
        // NOVA ROTA GLOBAL: O Botão Azul e o Piloto Automático (1 minuto) disparam aqui!
        Route::post('/sincronizar-geral', 'sincronizarGeral')->name('sincronizar-geral');
    });

    // Módulo de Fechamentos (Lotes)
    Route::controller(FechamentoController::class)->prefix('fechamentos')->name('fechamentos.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->name('show');
    });
    

    // ==========================================
    // INTEGRAÇÃO BSOFT (XML / CT-e)
    // ==========================================

    // NOVA ROTA DEFINITIVA: Acionada pelo botão manual para forçar a busca de XMLs na Bsoft
    Route::get('/bsoft/sincronizar-manual', [BsoftController::class, 'sincronizar'])->name('bsoft.sincronizar');

    // Rota Antiga de Sincronização Bsoft (API) atrelada a Lote (Mantida por segurança)
    Route::post('/fechamentos/{id}/sincronizar', function (Request $request, $id) {
        $servico = new BsoftSyncService();
        $resultado = $servico->atualizarBaixasBwt($id);
        
        if ($request->wantsJson()) return response()->json($resultado);
        return back()->with('success', $resultado['message']);
    })->name('fechamentos.sincronizar');


    // ==========================================
    // MÓDULOS PRO & INTELIGÊNCIA FINANCEIRA
    // ==========================================

    // Simulador de Contratos 
    Route::get('/simulador-contratos', [SimuladorController::class, 'index'])->name('simulador.index');

    // Budget / Planejamento Financeiro
    Route::controller(BudgetController::class)->prefix('budget')->name('budget.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/exportar-pdf', 'exportarPdf')->name('exportar-pdf');
        
        // ==========================================
        // ROTAS SSW
        // ==========================================
        Route::get('/ssw-extrato', 'showSswExtrato')->name('ssw-extrato');
        Route::post('/ssw-sync', 'syncSsw')->name('ssw-sync'); // <-- ROTA ADICIONADA AQUI!
        
        // --> Rota nova para o botão "+ Linha"
        Route::post('/{id}/add-item', 'addItem')->name('item.store'); 
        
        Route::put('/item/{id}', 'updateItem')->name('item.update');
        Route::post('/{id}/congelar', 'congelar')->name('congelar');
        Route::post('/{id}/descongelar', 'descongelar')->name('descongelar');
        Route::post('/{id}/predict', 'runPredictiveEngine')->name('predict');
        Route::post('/{id}/predict/undo', 'undoPredictiveEngine')->name('predict.undo');
    });


    // ==========================================
    // GESTÃO DO SISTEMA
    // ==========================================

    // Gestão do Perfil
    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });
});

require __DIR__.'/auth.php';
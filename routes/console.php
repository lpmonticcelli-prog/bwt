<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\BsoftSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// ==========================================
// PILOTO AUTOMÁTICO BSOFT (CRON JOB)
// ==========================================

// Roda a cada 2 horas buscando e salvando as CT-es dos últimos 3 dias silenciosamente.
Schedule::call(function () {
    app(BsoftSyncService::class)->sincronizarNotasRecentes(3);
})->everyTwoHours();
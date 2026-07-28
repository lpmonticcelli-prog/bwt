<?php

namespace App\Jobs;

use App\Services\BsoftSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBsoftLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Dá até 20 minutos para o trabalhador ler os XMLs desse lote sem morrer
    public $timeout = 1200; 
    public $tries = 3; // Tenta 3 vezes se der erro de conexão
    
    protected $lote;

    public function __construct(array $lote)
    {
        $this->lote = $lote;
    }

    public function handle(BsoftSyncService $bsoftService)
    {
        $quantidade = count($this->lote);
        Log::info("👷 [WORKER INICIADO] Começando a processar um lote de $quantidade notas da Bsoft...");
        
        // Passa a bola para o Service fazer o trabalho pesado
        $bsoftService->processarLoteDeNotas($this->lote);
    }
}
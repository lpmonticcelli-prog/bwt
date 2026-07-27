<?php

namespace App\Http\Controllers;

use App\Services\BsoftSyncService;
use Illuminate\Http\Request;

class BsoftController extends Controller
{
    public function sincronizar(BsoftSyncService $bsoftService)
    {
        // O Service agora detecta a Quinzena atual automaticamente e pagina as notas!
        $resultado = $bsoftService->sincronizarNotasRecentes();

        return response()->json([
            'success' => $resultado['success'],
            'message' => $resultado['message']
        ]);
    }
}
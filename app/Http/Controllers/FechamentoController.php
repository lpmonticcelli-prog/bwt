<?php

namespace App\Http\Controllers;

use App\Models\FechamentoPeriodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class FechamentoController extends Controller
{
    // Lista todos os fechamentos na tela inicial
    public function index()
    {
        // Traz os fechamentos mais recentes primeiro
        $fechamentos = FechamentoPeriodo::orderBy('data_inicio', 'desc')
            ->withCount(['fretes', 'faturamentos']) // Conta quantas notas já existem dentro
            ->get();

        return Inertia::render('Fechamentos/Index', [
            'fechamentos' => $fechamentos
        ]);
    }

    // Cria um novo fechamento (Aplica a regra dos 30 dias)
    public function store(Request $request)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        // Regra de Negócio ioapps: O vencimento é o Data Fim + 30 dias corridos
        $vencimento = Carbon::parse($request->data_fim)->addDays(30);

        FechamentoPeriodo::create([
            'titulo' => $request->titulo,
            'data_inicio' => $request->data_inicio,
            'data_fim' => $request->data_fim,
            'data_vencimento' => $vencimento,
            'status' => 'aberto'
        ]);

        return redirect()->route('fechamentos.index');
    }

    // Abre a tela de trabalho de um fechamento específico (Lado Esquerdo vs Lado Direito)
    public function show($id)
    {
        $fechamento = FechamentoPeriodo::findOrFail($id);

        return Inertia::render('Fechamentos/Show', [
            'fechamento' => $fechamento
        ]);
    }
}
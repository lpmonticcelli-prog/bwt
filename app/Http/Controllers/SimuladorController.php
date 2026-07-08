<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SimuladorController extends Controller
{
    public function index()
    {
        // Como o simulador tem a inteligência focada no reativo do frontend (Vue),
        // só precisamos carregar a página. Futuramente, você pode enviar 
        // os dados de média de faturamento do banco para cá, se desejar.
        return Inertia::render('Simulador/Index');
    }
}
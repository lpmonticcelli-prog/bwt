<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetController extends Controller
{
    // ==========================================
    // EXIBE A TELA E MONTA OS DADOS (DRE)
    // ==========================================
    public function index()
    {
        $budget = Budget::with('items')->where('ativo', true)->where('versao', 'Oficial')->first();

        if (!$budget) {
            return Inertia::render('Budget/Index', [
                'error' => 'Nenhum orçamento ativo encontrado.',
                'budgetId' => null,
                'budgetAno' => date('Y'),
                'budgetVersao' => '-',
                'budgetStatus' => 'Rascunho',
                'budgetCategories' => [],
                'dadosReais' => []
            ]);
        }

        // Padronização de Categorias e Resgate de Órfãos
        $categoriasGrouped = $budget->items->groupBy(function ($item) {
            $categoria = trim($item->categoria ?? '');
            if (empty($categoria)) {
                return 'OUTROS CUSTOS (SEM CATEGORIA)';
            }
            return mb_strtoupper($categoria, 'UTF-8'); 
        });
        
        $budgetData = [];
        
        foreach ($categoriasGrouped as $categoria => $itens) {
            $budgetData[] = [
                'categoria' => $categoria,
                'itens' => $itens->map(function ($item) {
                    
                    $valores = $item->valores_mensais;
                    if (is_string($valores)) {
                        $valores = json_decode($valores, true);
                    }

                    return [
                        'id' => $item->id,
                        'nome' => trim($item->nome),
                        'valores' => $valores ?? [],
                    ];
                })->values(),
            ];
        }

        return Inertia::render('Budget/Index', [
            'budgetId' => $budget->id,
            'budgetAno' => $budget->ano,
            'budgetVersao' => $budget->versao,
            'budgetStatus' => $budget->status ?? 'Rascunho',
            'budgetCategories' => $budgetData,
            'dadosReais' => []
        ]);
    }

    // ==========================================
    // EDIÇÃO INLINE DE VALORES 
    // ==========================================
    public function updateItem(Request $request, $id)
    {
        $item = BudgetItem::with('budget')->findOrFail($id);
        
        if (optional($item->budget)->status === 'Congelado') {
            abort(403, 'Acesso Negado: Este orçamento está congelado e auditado.');
        }

        $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'valor' => 'required|numeric'
        ]);
        
        $valores = $item->valores_mensais;
        if (is_string($valores)) {
            $valores = json_decode($valores, true) ?? [];
        } elseif (!is_array($valores)) {
            $valores = [];
        }

        $valores[$request->mes] = $request->valor;
        
        $item->valores_mensais = $valores;
        $item->save();

        return back()->with('success', 'Valor atualizado!');
    }

    // ==========================================
    // SEGURANÇA E AUDITORIA (TRAVAS)
    // ==========================================
    public function congelar($id)
    {
        $budget = Budget::findOrFail($id);
        $budget->status = 'Congelado';
        $budget->save();

        return back()->with('success', 'Orçamento Auditado e Congelado com sucesso!');
    }

    public function descongelar($id)
    {
        $budget = Budget::findOrFail($id);
        $budget->status = 'Rascunho';
        $budget->save();

        return back()->with('success', 'Orçamento DESTRAVADO! Cuidado ao realizar as edições.');
    }

    // ==========================================
    // INTELIGÊNCIA: MOTOR PREDITIVO ESTATÍSTICO
    // ==========================================
    public function runPredictiveEngine($id)
    {
        $budget = Budget::with('items')->findOrFail($id);

        if ($budget->status === 'Congelado') {
            abort(403, 'Acesso Negado: Descongele o orçamento para rodar as previsões.');
        }

        foreach ($budget->items as $item) {
            $valores = is_string($item->valores_mensais) ? json_decode($item->valores_mensais, true) : ($item->valores_mensais ?? []);
            
            $mesesPreenchidos = [];
            $valoresPreenchidos = [];

            for ($m = 1; $m <= 12; $m++) {
                if (isset($valores[$m]) && (float)$valores[$m] > 0) {
                    $mesesPreenchidos[] = $m;
                    $valoresPreenchidos[] = (float)$valores[$m];
                }
            }

            $count = count($mesesPreenchidos);

            if ($count >= 2) {
                $sumX = array_sum($mesesPreenchidos);
                $sumY = array_sum($valoresPreenchidos);
                $sumXX = 0;
                $sumXY = 0;

                foreach ($mesesPreenchidos as $k => $x) {
                    $sumXX += $x * $x;
                    $sumXY += $x * $valoresPreenchidos[$k];
                }

                $denominator = (($count * $sumXX) - ($sumX * $sumX));
                $m = $denominator != 0 ? (($count * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
                $b = ($sumY - ($m * $sumX)) / $count;

                for ($month = 1; $month <= 12; $month++) {
                    if (!isset($valores[$month]) || (float)$valores[$month] == 0) {
                        $predicted = ($m * $month) + $b;
                        $valores[$month] = round(max(0, $predicted), 2);
                    }
                }
            } 
            elseif ($count === 1) {
                $val = $valoresPreenchidos[0];
                for ($month = 1; $month <= 12; $month++) {
                    if (!isset($valores[$month]) || (float)$valores[$month] == 0) {
                        $valores[$month] = $val;
                    }
                }
            }

            $item->valores_mensais = $valores;
            $item->save();
        }

        return back()->with('success', '✨ Motor Preditivo Executado! Projeções geradas com sucesso.');
    }

    // ==========================================
    // RESTAURAR: VOLTA PARA O EXCEL ORIGINAL
    // ==========================================
    public function undoPredictiveEngine($id)
    {
        $budget = Budget::with('items')->findOrFail($id);

        if ($budget->status === 'Congelado') {
            return back()->withErrors(['error' => 'Não é possível restaurar um orçamento congelado.']);
        }

        foreach ($budget->items as $item) {
            // Verifica se existe o backup na coluna nova do banco
            if (!empty($item->valores_originais)) {
                $originais = is_string($item->valores_originais) ? json_decode($item->valores_originais, true) : $item->valores_originais;
                $item->valores_mensais = $originais;
                $item->save();
            }
        }

        return back()->with('success', '⏪ Reset Concluído! O orçamento voltou exatamente como foi importado no início.');
    }
}
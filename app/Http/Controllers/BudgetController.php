<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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
    // EXPORTAÇÃO DE RELATÓRIO PDF (DUPLO FORMATO)
    // ==========================================
    public function exportarPdf(Request $request)
    {
        $tipo = $request->query('tipo', 'trimestral'); // Pega o tipo de PDF da URL (padrão é trimestral)
        
        $budget = Budget::with('items')->where('ativo', true)->where('versao', 'Oficial')->firstOrFail();

        $categoriasGrouped = $budget->items->groupBy(function ($item) {
            $categoria = trim($item->categoria ?? '');
            if (empty($categoria)) return 'OUTROS CUSTOS';
            return mb_strtoupper($categoria, 'UTF-8'); 
        });

        $budgetData = [];
        foreach ($categoriasGrouped as $categoria => $itens) {
            $budgetData[] = [
                'categoria' => $categoria,
                'itens' => $itens->map(function ($item) {
                    $valores = is_string($item->valores_mensais) ? json_decode($item->valores_mensais, true) : $item->valores_mensais;
                    return [
                        'nome' => trim($item->nome),
                        'valores' => $valores ?? [],
                    ];
                })->values(),
            ];
        }

        // Escolhe o template dependendo do que o usuário clicou no front-end
        $viewName = $tipo === 'mensal' ? 'pdf.budget_executivo_mensal' : 'pdf.budget_executivo';
        
        $pdf = Pdf::loadView($viewName, [
            'budgetAno' => $budget->ano,
            'budgetStatus' => $budget->status,
            'budgetCategories' => $budgetData,
            'dataEmissao' => date('d/m/Y H:i'),
        ]);

        // Define o papel como A4 Paisagem para caber todas as colunas
        $pdf->setPaper('a4', 'landscape');

        // Formata o nome do arquivo que será baixado
        $fileName = $tipo === 'mensal' ? 'Budget_Mensal_' : 'Budget_Trimestral_';
        
        return $pdf->stream($fileName . $budget->ano . '_BWT.pdf');
    }

    // ==========================================
    // EDIÇÃO INLINE E MOTOR AUTOMÁTICO
    // ==========================================
    public function updateItem(Request $request, $id)
    {
        $item = BudgetItem::with('budget')->findOrFail($id);
        
        if (optional($item->budget)->status === 'Congelado') {
            abort(403, 'Acesso Negado: Este orçamento está congelado.');
        }

        $request->validate([
            'mes' => 'required|integer|min:1|max:12',
            'valor' => 'required|numeric'
        ]);
        
        $valores = is_string($item->valores_mensais) ? json_decode($item->valores_mensais, true) : ($item->valores_mensais ?? []);
        $valores[$request->mes] = $request->valor;
        
        $item->valores_mensais = json_encode($valores);
        $item->save();

        // MÁGICA: Se o usuário editou Vendas, recalcula os impostos na hora!
        if (in_array($item->nome, ['Solfácil Distribuidora', 'Empilhadeira', 'Total Vendas'])) {
            $this->recalcularImpostos($item->budget_id, $request->mes);
        }

        return back()->with('success', 'Valor atualizado e impostos recalculados!');
    }

    private function recalcularImpostos($budgetId, $mes)
    {
        $items = BudgetItem::where('budget_id', $budgetId)->get();
        
        $solfacil = $items->where('nome', 'Solfácil Distribuidora')->first();
        $empilhadeira = $items->where('nome', 'Empilhadeira')->first();
        $totalVendas = $items->where('nome', 'Total Vendas')->first();
        
        $somaVendas = 0;
        if ($solfacil || $empilhadeira) {
            $valS = $solfacil ? (float)(is_string($solfacil->valores_mensais) ? json_decode($solfacil->valores_mensais, true)[$mes] ?? 0 : $solfacil->valores_mensais[$mes] ?? 0) : 0;
            $valE = $empilhadeira ? (float)(is_string($empilhadeira->valores_mensais) ? json_decode($empilhadeira->valores_mensais, true)[$mes] ?? 0 : $empilhadeira->valores_mensais[$mes] ?? 0) : 0;
            $somaVendas = $valS + $valE;
            
            if ($totalVendas && $somaVendas > 0) {
                $vTV = is_string($totalVendas->valores_mensais) ? json_decode($totalVendas->valores_mensais, true) : $totalVendas->valores_mensais;
                $vTV[$mes] = $somaVendas;
                $totalVendas->valores_mensais = json_encode($vTV);
                $totalVendas->save();
            }
        }

        $baseCalculo = $totalVendas ? (float)(is_string($totalVendas->valores_mensais) ? json_decode($totalVendas->valores_mensais, true)[$mes] ?? 0 : $totalVendas->valores_mensais[$mes] ?? 0) : $somaVendas;

        // Regras Fiscais Oficiais BWT
        $taxas = [
            'PIS 0,65%' => 0.0065,
            'COFINS 3%' => 0.0300,
            'ICMS 10%'  => 0.1000,
            'IRPJ'      => 0.0120, // 8% base x 15% alíquota IR
            'CSLL'      => 0.0108  // 12% base x 9% alíquota CSLL
        ];

        foreach ($taxas as $nomeImposto => $percentual) {
            $impostoItem = $items->where('nome', $nomeImposto)->first();
            if ($impostoItem) {
                $vImp = is_string($impostoItem->valores_mensais) ? json_decode($impostoItem->valores_mensais, true) : $impostoItem->valores_mensais;
                $vImp[$mes] = round($baseCalculo * $percentual, 2);
                $impostoItem->valores_mensais = json_encode($vImp);
                $impostoItem->save();
            }
        }
    }

    // ==========================================
    // TRAVAS DE SEGURANÇA E AUDITORIA
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
    // MOTOR PREDITIVO ESTATÍSTICO (IA)
    // ==========================================
    public function runPredictiveEngine($id)
    {
        $budget = Budget::with('items')->findOrFail($id);

        if ($budget->status === 'Congelado') {
            abort(403, 'Acesso Negado: Descongele o orçamento para rodar as previsões.');
        }

        foreach ($budget->items as $item) {
            $valores = is_string($item->valores_mensais) ? json_decode($item->valores_mensais, true) : ($item->valores_mensais ?? []);
            $mesesPreenchidos = []; $valoresPreenchidos = [];

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
                $sumXX = 0; $sumXY = 0;

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
            } elseif ($count === 1) {
                $val = $valoresPreenchidos[0];
                for ($month = 1; $month <= 12; $month++) {
                    if (!isset($valores[$month]) || (float)$valores[$month] == 0) {
                        $valores[$month] = $val;
                    }
                }
            }

            $item->valores_mensais = json_encode($valores);
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
            // Verifica se existe o backup na coluna original
            if (!empty($item->valores_originais)) {
                $originais = is_string($item->valores_originais) ? json_decode($item->valores_originais, true) : $item->valores_originais;
                
                // Restaura sobrescrevendo e transformando de volta em JSON
                $item->valores_mensais = json_encode($originais);
                $item->save();
            }
        }

        return back()->with('success', '⏪ Reset Concluído! O orçamento voltou exatamente como foi importado no início.');
    }
}
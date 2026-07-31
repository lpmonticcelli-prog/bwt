<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class BudgetController extends Controller
{
    // Helper privado para evitar código repetido e garantir leitura segura do Array
    private function getArrayValues($item, $column = 'valores_mensais')
    {
        if (!$item || empty($item->$column)) return [];
        return is_string($item->$column) ? json_decode($item->$column, true) : $item->$column;
    }

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
                    return [
                        'id' => $item->id,
                        'nome' => trim($item->nome),
                        'valores' => $this->getArrayValues($item),
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
    // ADICIONAR NOVA LINHA DINÂMICA
    // ==========================================
    public function addItem(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);

        if ($budget->status === 'Congelado') {
            abort(403, 'Acesso Negado: Este orçamento está congelado.');
        }

        $request->validate([
            'categoria' => 'required|string',
            'nome' => 'required|string'
        ]);

        $mesesZerados = array_fill_keys(range(1, 12), 0);
        
        // Passa o Array direto (o $casts do Laravel cuida do JSON)
        $budget->items()->create([
            'categoria' => mb_strtoupper(trim($request->categoria), 'UTF-8'),
            'nome' => trim($request->nome),
            'valores_mensais' => $mesesZerados,
            'valores_originais' => $mesesZerados,
        ]);

        return back()->with('success', 'Nova linha adicionada com sucesso!');
    }

    // ==========================================
    // EXPORTAÇÃO DE RELATÓRIO PDF
    // ==========================================
    public function exportarPdf(Request $request)
    {
        $tipo = $request->query('tipo', 'trimestral');
        
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
                    return [
                        'nome' => trim($item->nome),
                        'valores' => $this->getArrayValues($item),
                    ];
                })->values(),
            ];
        }

        $viewName = $tipo === 'mensal' ? 'pdf.budget_executivo_mensal' : 'pdf.budget_executivo';
        
        $pdf = Pdf::loadView($viewName, [
            'budgetAno' => $budget->ano,
            'budgetStatus' => $budget->status,
            'budgetCategories' => $budgetData,
            'dataEmissao' => date('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');
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
        
        $valores = $this->getArrayValues($item);
        $valores[$request->mes] = $request->valor;
        
        // Passa o Array direto (sem json_encode)
        $item->valores_mensais = $valores;
        $item->save();

        if (in_array($item->nome, ['Solfácil Distribuidora', 'Empilhadeira', 'Total Vendas'])) {
            $this->recalcularImpostos($item->budget_id, $request->mes);
        }

        return back()->with('success', 'Valor atualizado com sucesso!');
    }

    private function recalcularImpostos($budgetId, $mes)
    {
        $items = BudgetItem::where('budget_id', $budgetId)->get();
        
        $solfacil = $items->where('nome', 'Solfácil Distribuidora')->first();
        $empilhadeira = $items->where('nome', 'Empilhadeira')->first();
        $totalVendas = $items->where('nome', 'Total Vendas')->first();
        
        $valS = $solfacil ? (float)($this->getArrayValues($solfacil)[$mes] ?? 0) : 0;
        $valE = $empilhadeira ? (float)($this->getArrayValues($empilhadeira)[$mes] ?? 0) : 0;
        $somaVendas = $valS + $valE;

        if ($totalVendas && $somaVendas > 0) {
            $vTV = $this->getArrayValues($totalVendas);
            $vTV[$mes] = $somaVendas;
            $totalVendas->valores_mensais = $vTV;
            $totalVendas->save();
        }

        $baseCalculo = $totalVendas ? (float)($this->getArrayValues($totalVendas)[$mes] ?? 0) : $somaVendas;

        // Regras Fiscais
        $taxas = [
            'PIS 0,65%' => 0.0065,
            'COFINS 3%' => 0.0300,
            'ICMS 10%'  => 0.1000,
            'IRPJ'      => 0.0120,
            'CSLL'      => 0.0108 
        ];

        foreach ($taxas as $nomeImposto => $percentual) {
            $impostoItem = $items->where('nome', $nomeImposto)->first();
            if ($impostoItem) {
                $vImp = $this->getArrayValues($impostoItem);
                $vImp[$mes] = round($baseCalculo * $percentual, 2);
                $impostoItem->valores_mensais = $vImp;
                $impostoItem->save();
            }
        }
    }

    // ==========================================
    // TRAVAS DE SEGURANÇA
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
        return back()->with('success', 'Orçamento DESTRAVADO!');
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
            $valores = $this->getArrayValues($item);
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
            $originais = $this->getArrayValues($item, 'valores_originais');
            if (!empty($originais)) {
                $item->valores_mensais = $originais;
                $item->save();
            }
        }

        return back()->with('success', '⏪ Reset Concluído! O orçamento voltou exatamente como foi importado.');
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class BudgetController extends Controller
{
    // Helper privado para evitar código repetido e garantir leitura segura do Array/JSON
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
        // CORREÇÃO: Busca o orçamento do ano atual para garantir que os dados importados do Excel apareçam na tela
        $budget = Budget::with('items')->where('ano', date('Y'))->first();

        if (!$budget) {
            return Inertia::render('Budget/Index', [
                'error' => 'Nenhum orçamento ativo encontrado para este ano.',
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
    public function storeItem(Request $request, $id)
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
        
        // CORREÇÃO: json_encode para o MySQL aceitar o Array nativamente
        $budget->items()->create([
            'categoria' => mb_strtoupper(trim($request->categoria), 'UTF-8'),
            'nome' => trim($request->nome),
            'valores_mensais' => json_encode($mesesZerados),
            'valores_originais' => json_encode($mesesZerados),
        ]);

        return back()->with('success', 'Nova linha adicionada com sucesso!');
    }

    // ==========================================
    // EXPORTAÇÃO DE RELATÓRIO PDF
    // ==========================================
    public function exportarPdf(Request $request)
    {
        $tipo = $request->query('tipo', 'trimestral');
        
        // CORREÇÃO: Mesma regra de busca para garantir que o PDF seja gerado com os dados do Excel
        $budget = Budget::with('items')->where('ano', date('Y'))->firstOrFail();

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
        
        // CORREÇÃO: json_encode para garantir compatibilidade do MySQL
        $item->valores_mensais = json_encode($valores);
        $item->save();

        // Se for receita, aciona a engine de impostos
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
            $totalVendas->valores_mensais = json_encode($vTV);
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
                $impostoItem->valores_mensais = json_encode($vImp);
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
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SswDespesa;
use App\Models\Budget;
use Carbon\Carbon;

class ImportSswCsvCommand extends Command
{
    // O comando agora exige que você diga o nome do arquivo e se é 'despesa' ou 'receita'
    protected $signature = 'ssw:importar {arquivo : Nome do arquivo CSV na pasta raiz} {tipo : Digite despesa ou receita}';
    protected $description = 'Importa um CSV manual baixado da opção 056 da SSW para preencher meses anteriores.';

    public function handle()
    {
        $arquivo = $this->argument('arquivo');
        $tipo = strtolower($this->argument('tipo'));

        $caminhoCompleto = base_path($arquivo);

        if (!file_exists($caminhoCompleto)) {
            $this->error("Arquivo não encontrado: {$caminhoCompleto}");
            $this->line("Dica: Coloque o arquivo CSV solto dentro da pasta raiz 'bwt'.");
            return Command::FAILURE;
        }

        if (!in_array($tipo, ['despesa', 'receita'])) {
            $this->error("O tipo deve ser 'despesa' ou 'receita'.");
            return Command::FAILURE;
        }

        $this->info("Lendo o arquivo {$arquivo} como {$tipo}...");
        
        $conteudo = file_get_contents($caminhoCompleto);
        $linhas = explode("\n", $conteudo);
        $headerProcessado = false;
        $cadastros = 0;

        foreach ($linhas as $linha) {
            if (empty(trim($linha))) continue;
            
            $colunas = str_getcsv($linha, ';'); 

            // ==========================================
            // LÓGICA PARA IMPORTAR DESPESAS (CONTAS A PAGAR)
            // ==========================================
            if ($tipo === 'despesa') {
                if (!$headerProcessado) { $headerProcessado = true; continue; }
                if (count($colunas) < 7) continue;

                $lancamento = trim($colunas[0]);
                $filial     = trim($colunas[1]);
                
                if (!preg_match('/^(IND|MTZ)/i', $filial)) continue; 

                $fornecedor  = trim($colunas[2]);
                $evento      = trim($colunas[3]);
                $valor       = $this->limparMoeda($colunas[4]);
                $vencimento  = trim($colunas[5]);
                $pagamento   = trim($colunas[6]);

                $competencia = null;
                if (!empty($vencimento)) {
                    $partes = explode('/', $vencimento);
                    if (count($partes) >= 3) $competencia = $partes[1] . '/' . $partes[2];
                }

                SswDespesa::updateOrCreate(
                    ['lancamento' => $lancamento],
                    [
                        'inclusao'    => $this->formatarData($vencimento) ?? date('Y-m-d'),
                        'filial'      => $filial,
                        'evento'      => $evento,
                        'historico'   => $evento,
                        'fornecedor'  => $fornecedor,
                        'nota_fiscal' => 'N/D',
                        'valor'       => $valor,
                        'vencimento'  => $this->formatarData($vencimento),
                        'pagamento'   => $this->formatarData($pagamento),
                        'competencia' => $competencia,
                        'situacao'    => !empty($pagamento) ? 'Liquidado' : 'Pendente',
                        'tipo'        => 'DESPESA'
                    ]
                );
                $cadastros++;
            }

            // ==========================================
            // LÓGICA PARA IMPORTAR RECEITAS (FATURAS)
            // ==========================================
            if ($tipo === 'receita') {
                if (!isset($colunas[0]) || trim($colunas[0]) !== '2') continue;
                if (count($colunas) < 38) continue;

                $lancamento = "FAT-" . trim($colunas[1]); 
                $filial     = trim($colunas[19]);
                
                if (!preg_match('/^(IND|MTZ)/i', $filial)) continue; 

                $cliente     = trim($colunas[3]);
                $vencimento  = trim($colunas[23]);
                $pagamento   = trim($colunas[26]);
                $situacaoFatura = trim($colunas[30]);
                
                $valorFatura = $this->limparMoeda($colunas[38]);
                if ($valorFatura == 0) $valorFatura = $this->limparMoeda($colunas[34]);

                $competencia = null;
                if (!empty($vencimento)) {
                    $partes = explode('/', $vencimento);
                    if (count($partes) >= 3) $competencia = $partes[1] . '/' . $partes[2];
                }

                SswDespesa::updateOrCreate(
                    ['lancamento' => $lancamento],
                    [
                        'inclusao'    => $this->formatarData($vencimento) ?? date('Y-m-d'),
                        'filial'      => $filial,
                        'evento'      => 'RECEITA BRUTA', 
                        'historico'   => 'FATURAMENTO',
                        'fornecedor'  => $cliente, 
                        'nota_fiscal' => 'N/D',
                        'valor'       => $valorFatura,
                        'vencimento'  => $this->formatarData($vencimento),
                        'pagamento'   => $this->formatarData($pagamento),
                        'competencia' => $competencia,
                        'situacao'    => ($situacaoFatura === 'LIQUIDADO' || !empty($pagamento)) ? 'Liquidado' : 'Pendente',
                        'tipo'        => 'RECEITA'
                    ]
                );
                $cadastros++;
            }
        }

        $this->info("Importação concluída! {$cadastros} registros processados.");
        $this->info("Recalculando DRE/Budget Anual...");
        $this->recalcularBudgetAnual();
        
        $this->info("Tudo pronto! Seu DRE está atualizado.");
        return Command::SUCCESS;
    }

    private function descobrirLinhaBudget($historico, $fornecedor, $evento)
    {
        return mb_strtoupper(trim($evento), 'UTF-8'); 
    }

    private function recalcularBudgetAnual()
    {
        $anoAtual = date('Y');
        $anoSsw = substr($anoAtual, -2);
        
        $budget = Budget::with('items')->where('ano', $anoAtual)->first();
        if (!$budget) return;

        foreach ($budget->items as $item) {
            $item->valores_mensais = json_encode(array_fill_keys(range(1, 12), 0));
            $item->save();
        }

        $movimentacoes = SswDespesa::where('situacao', 'Liquidado')
            ->where('competencia', 'like', "%/{$anoSsw}")
            ->get();

        $valoresAgrupados = []; 
        
        foreach ($movimentacoes as $mov) {
            $linhaDestino = $this->descobrirLinhaBudget($mov->historico, $mov->fornecedor, $mov->evento);
            $mesInt = (int) explode('/', $mov->competencia)[0]; 

            if ($linhaDestino && $mesInt >= 1 && $mesInt <= 12) {
                if (!isset($valoresAgrupados[$linhaDestino])) {
                    $valoresAgrupados[$linhaDestino] = array_fill_keys(range(1, 12), 0);
                }
                $valoresAgrupados[$linhaDestino][$mesInt] += $mov->valor;
            }
        }

        foreach ($valoresAgrupados as $nomeDaLinha => $mesesValores) {
            $budgetItem = $budget->items->where('nome', $nomeDaLinha)->first();
            
            if (!$budgetItem) {
                $categoria = ($nomeDaLinha === 'RECEITA BRUTA') ? 'RECEITAS' : 'CUSTOS OPERACIONAIS';
                
                $budgetItem = $budget->items()->create([
                    'categoria' => $categoria,
                    'nome' => $nomeDaLinha,
                    'valores_mensais' => json_encode(array_fill_keys(range(1, 12), 0)),
                    'valores_originais' => json_encode(array_fill_keys(range(1, 12), 0)),
                ]);
                $budget->load('items');
            }

            $budgetItem->valores_mensais = json_encode($mesesValores);
            $budgetItem->save();
        }
    }

    private function formatarData($dataString)
    {
        $dataString = trim($dataString);
        if (empty($dataString)) return null;
        try { return Carbon::createFromFormat('d/m/y', $dataString)->format('Y-m-d'); } 
        catch (\Exception $e) {
            try { return Carbon::createFromFormat('d/m/Y', $dataString)->format('Y-m-d'); } 
            catch (\Exception $e2) { return null; }
        }
    }

    private function limparMoeda($valorString)
    {
        $valorString = trim($valorString);
        if (empty($valorString)) return 0;
        $valorString = str_replace('.', '', $valorString); 
        $valorString = str_replace(',', '.', $valorString); 
        return (float) $valorString;
    }
}
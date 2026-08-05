<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SswDespesa;
use App\Models\Budget;
use Carbon\Carbon;

class SyncSswCommand extends Command
{
    protected $signature = 'ssw:sync';
    protected $description = 'Sincroniza SSW garantindo Filial no nome, espelho exato e roteamento rigoroso.';

    public function handle()
    {
        $this->info("Iniciando motor SSW com Identificação de Filial Dinâmica e Roteamento Avançado...");

        $dominio  = 'BW1';
        $senhaBi2 = env('SSW_BI2_PASSWORD');
        $hashMd5  = md5($senhaBi2); 

        // ==========================================
        // ESTÁGIO 1: CONTAS A PAGAR (DESPESAS & IMPOSTOS SSW)
        // ==========================================
        $this->info("Puxando TODAS as Despesas e Impostos da SSW...");
        $urlDespesas = "https://ssw.inf.br/api/bi2/{$dominio}/caixa?codigo=0202";
        
        try {
            $response = Http::withHeaders(['Authorization' => 'Basic ' . $hashMd5])->timeout(30)->get($urlDespesas);
            if ($response->successful() && !str_contains($response->body(), '<html')) {
                $linhas = explode("\n", $response->body());
                $headerProcessado = false;
                
                foreach ($linhas as $linha) {
                    if (empty(trim($linha))) continue;
                    $colunas = str_getcsv($linha, ';'); 
                    if (!$headerProcessado) { $headerProcessado = true; continue; }
                    if (count($colunas) < 5) continue;

                    $lancamento = trim($colunas[0] ?? '');
                    $filial     = trim($colunas[1] ?? '');
                    $fornecedor = trim($colunas[2] ?? '');
                    $evento     = trim($colunas[3] ?? '');
                    $valor      = $this->limparMoeda($colunas[4] ?? '0');
                    $vencimento = trim($colunas[5] ?? '');
                    $pagamento  = trim($colunas[6] ?? '');

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
                }
            }
        } catch (\Exception $e) { $this->error("Erro em Despesas: " . $e->getMessage()); }

        // ==========================================
        // ESTÁGIO 2: FATURAS EMITIDAS (RECEITAS)
        // ==========================================
        $this->info("Puxando TODAS as Receitas (Faturamento)...");
        $urlReceitas = "https://ssw.inf.br/api/bi2/{$dominio}/caixa?codigo=0241";
        
        try {
            $response = Http::withHeaders(['Authorization' => 'Basic ' . $hashMd5])->timeout(30)->get($urlReceitas);
            if ($response->successful() && !str_contains($response->body(), '<html')) {
                $linhas = explode("\n", $response->body());
                foreach ($linhas as $linha) {
                    if (empty(trim($linha))) continue;
                    $colunas = str_getcsv($linha, ';'); 
                    if (!isset($colunas[0]) || trim($colunas[0]) !== '2') continue;
                    if (count($colunas) < 30) continue; 

                    $lancamento = "FAT-" . trim($colunas[1] ?? ''); 
                    $filial     = trim($colunas[19] ?? '');
                    $cliente    = trim($colunas[3] ?? '');
                    $vencimento = trim($colunas[23] ?? '');
                    $pagamento  = trim($colunas[26] ?? '');
                    $situacaoFatura = trim($colunas[30] ?? '');
                    
                    $valorFatura = $this->limparMoeda($colunas[38] ?? '0');
                    if ($valorFatura == 0) $valorFatura = $this->limparMoeda($colunas[34] ?? '0');

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
                }
            }
        } catch (\Exception $e) { $this->error("Erro em Receitas: " . $e->getMessage()); }

        $this->info("Recalculando DRE e extraindo siglas de filiais dinamicamente...");
        $this->recalcularBudgetAnual();
        
        $this->info("Sincronização do Espelho Perfeito 100% concluída!");
        return Command::SUCCESS;
    }

    private function recalcularBudgetAnual()
    {
        $anoAtual = date('Y');
        $anoSsw = substr($anoAtual, -2);
        
        $budget = Budget::with('items')->where('ano', $anoAtual)->first();
        if (!$budget) return;

        $movimentacoes = SswDespesa::whereIn('situacao', ['Liquidado', 'Pendente'])
            ->where('competencia', 'like', "%/{$anoSsw}")
            ->get();

        $mesesDominadosPelaSsw = [];
        $valoresAgrupados = []; 
        $tiposAgrupados = [];
        
        foreach ($movimentacoes as $mov) {
            $fornecedor = trim($mov->fornecedor);
            $evento = trim($mov->evento);
            $filialBruta = mb_strtoupper(trim($mov->filial), 'UTF-8');

            if (empty($fornecedor)) $fornecedor = "FORNECEDOR DIVERSO";
            if (empty($evento)) $evento = trim($mov->historico);
            if (empty($evento)) $evento = "DESPESA NAO IDENTIFICADA";
            
            // ==========================================
            // EXTRAÇÃO INTELIGENTE DE QUALQUER SIGLA DE 3 LETRAS
            // ==========================================
            $filialLimpa = 'GERAL';
            if (!empty($filialBruta)) {
                // Procura pelas 3 primeiras letras seguidas, ignorando números e espaços
                preg_match('/[A-Z]{3}/', preg_replace('/[^A-Z]/', '', $filialBruta), $matches);
                if (!empty($matches[0])) {
                    $filialLimpa = $matches[0];
                }
            }
            
            // O NOME EXATO DO ESPELHO COM FILIAL LIMPA E DINÂMICA
            $linhaDestino = mb_strtoupper('[' . $filialLimpa . '] ' . $fornecedor . ' - ' . $evento, 'UTF-8');
            $mesInt = (int) explode('/', $mov->competencia)[0]; 

            if ($linhaDestino && $mesInt >= 1 && $mesInt <= 12) {
                if (!in_array($mesInt, $mesesDominadosPelaSsw)) {
                    $mesesDominadosPelaSsw[] = $mesInt;
                }
                if (!isset($valoresAgrupados[$linhaDestino])) {
                    $valoresAgrupados[$linhaDestino] = array_fill_keys(range(1, 12), 0);
                    $tiposAgrupados[$linhaDestino] = $mov->tipo;
                }
                $valoresAgrupados[$linhaDestino][$mesInt] += $mov->valor;
            }
        }

        // ==========================================
        // IMPOSTO AUTOMÁTICO (10% LÍQUIDO)
        // ==========================================
        $impostosCalculados = array_fill_keys(range(1, 12), 0);
        foreach ($valoresAgrupados as $linha => $mesesValores) {
            // Conta 10% APENAS sobre Faturamento (Receita Bruta)
            if (preg_match('/\b(RECEITA BRUTA)\b/i', $linha)) {
                foreach ($mesesDominadosPelaSsw as $mes) {
                    $impostosCalculados[$mes] += $mesesValores[$mes] * 0.10; 
                }
            }
        }
        $valoresAgrupados['[SISTEMA] IMPOSTO SOBRE FATURAMENTO (10%)'] = $impostosCalculados;
        $tiposAgrupados['[SISTEMA] IMPOSTO SOBRE FATURAMENTO (10%)'] = 'IMPOSTO';

        // LIMPA OS MESES DA SSW NAS CONTAS EXISTENTES (Protege manual)
        foreach ($budget->items as $item) {
            $valoresAtuais = json_decode($item->valores_mensais, true) ?? array_fill_keys(range(1, 12), 0);
            foreach ($mesesDominadosPelaSsw as $m) {
                $valoresAtuais[$m] = 0; 
            }
            $item->valores_mensais = json_encode($valoresAtuais);
            $item->save();
        }

        // ==========================================
        // MURALHA DE ROTEAMENTO (BLINDADA CONTRA FALSOS POSITIVOS)
        // ==========================================
        foreach ($valoresAgrupados as $nomeDaLinha => $mesesValores) {
            $tipo = $tiposAgrupados[$nomeDaLinha] ?? 'DESPESA';
            
            // REGRA 1: Verifica primeiro se é Imposto
            if (
                $tipo === 'IMPOSTO' || 
                preg_match('/\b(IMPOSTO|IMPOSTOS|ICMS|PIS|COFINS|IRPJ|CSLL|DARF|SIMPLES)\b/i', $nomeDaLinha)
            ) {
                $categoriaDestino = 'IMPOSTOS';
            } 
            // REGRA 2: Depois verifica se é Receita
            elseif (
                $tipo === 'RECEITA' || 
                preg_match('/\b(RECEITA BRUTA|FATURAMENTO)\b/i', $nomeDaLinha)
            ) {
                $categoriaDestino = 'RECEITAS';
            } 
            // REGRA 3: Se não é nenhum dos dois, cai em Custos Operacionais
            else {
                $categoriaDestino = 'CUSTOS OPERACIONAIS';
            }

            $budgetItem = $budget->items->where('nome', $nomeDaLinha)->first();

            if (!$budgetItem) {
                $budgetItem = $budget->items()->create([
                    'categoria' => $categoriaDestino,
                    'nome' => $nomeDaLinha,
                    'valores_mensais' => json_encode(array_fill_keys(range(1, 12), 0)),
                    'valores_originais' => json_encode(array_fill_keys(range(1, 12), 0)),
                ]);
            } else {
                // FORÇA A ATUALIZAÇÃO DA CATEGORIA PARA LIMPAR ERROS DO BANCO
                $budgetItem->categoria = $categoriaDestino;
            }

            $valoresAtuais = json_decode($budgetItem->valores_mensais, true) ?? array_fill_keys(range(1, 12), 0);
            foreach ($mesesDominadosPelaSsw as $m) {
                $valoresAtuais[$m] = $mesesValores[$m];
            }
            
            $budgetItem->valores_mensais = json_encode($valoresAtuais);
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
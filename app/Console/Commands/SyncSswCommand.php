<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http; // <-- MUDOU AQUI: Usando o motor HTTP
use Illuminate\Support\Facades\Log;
use App\Models\SswDespesa;
use App\Models\Budget;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SyncSswCommand extends Command
{
    protected $signature = 'ssw:sync';
    protected $description = 'Conecta na WebAPI da SSW, puxa TUDO de MTZ/IND e injeta 100% no Budget.';

    public function handle()
    {
        $this->info("Iniciando motor de sincronização SSW (Modo WebAPI - Bypassing FTP)...");
        Log::info("SSW Sync: Iniciado via API HTTPS.");

        // ==========================================
        // CONFIGURAÇÕES DA API (Baseado no Vídeo)
        // ==========================================
        $dominio  = 'BW1'; // A Sigla da sua empresa que vimos na imagem anterior
        $pasta    = 'caixa'; // <-- AJUSTE AQUI: O nome da pasta onde a SSW joga o CSV financeiro
        $codigo   = '0013';  // <-- AJUSTE AQUI: O código do relatório gerado (se exigido)
        
        // A Mágica do Vídeo: Transformando a senha em Hash MD5
        $senhaBi2 = env('SSW_BI2_PASSWORD');
        $hashMd5  = md5($senhaBi2); 

        // Montando a URL exata ensinada no vídeo
        $url = "https://ssw.inf.br/api/bi2/{$dominio}/{$pasta}?codigo={$codigo}";
        
        $this->info("Conectando de forma segura em: {$url}");

        try {
            // Requisição HTTPS ignorando Firewall
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $hashMd5
            ])->timeout(30)->get($url);

            if ($response->failed()) {
                $this->error("Erro na API da SSW: Código " . $response->status());
                Log::error("SSW API Error: " . $response->body());
                return Command::FAILURE;
            }

            $conteudo = $response->body();
            
            // Tratamento caso a SSW devolva um HTML de erro em vez do CSV
            if (empty(trim($conteudo)) || str_contains($conteudo, '<html')) {
                $this->warn("A API respondeu, mas não retornou um CSV válido. Verifique se a variável \$pasta ou \$codigo estão corretas no código.");
                Log::warning("SSW Retorno Inválido: " . substr($conteudo, 0, 100));
                return Command::SUCCESS;
            }

            $this->info("Arquivo CSV baixado com sucesso! Processando as linhas...");
            
            // O conteúdo já é o CSV pronto! Vamos fatiar.
            $linhas = explode("\n", $conteudo);
            $headerProcessado = false;
            
            foreach ($linhas as $linha) {
                if (empty(trim($linha))) continue;
                
                $colunas = str_getcsv($linha, ';'); 
                
                if (!$headerProcessado) {
                    $headerProcessado = true;
                    continue;
                }

                if (count($colunas) < 12) continue;

                $lancamento = trim($colunas[0]);
                $filial     = trim($colunas[2]);
                
                if (!preg_match('/^(IND|MTZ)/i', $filial)) continue; 

                $evento      = trim($colunas[3]);
                $historico   = trim($colunas[4]);
                $fornecedor  = trim($colunas[5]);
                $notaFiscal  = trim($colunas[6]);
                $valor       = $this->limparMoeda($colunas[7]);
                $competencia = trim($colunas[10]); 
                $situacao    = trim($colunas[11]); 

                SswDespesa::updateOrCreate(
                    ['lancamento' => $lancamento],
                    [
                        'inclusao'    => $this->formatarData($colunas[1]),
                        'filial'      => $filial,
                        'evento'      => $evento,
                        'historico'   => $historico,
                        'fornecedor'  => $fornecedor,
                        'nota_fiscal' => $notaFiscal,
                        'valor'       => $valor,
                        'vencimento'  => $this->formatarData($colunas[8]),
                        'pagamento'   => $this->formatarData($colunas[9]),
                        'competencia' => $competencia,
                        'situacao'    => $situacao,
                    ]
                );
            }

            $this->info("Leitura da API finalizada. Recalculando Budget...");
            $this->recalcularBudgetAnual();
            
            $this->info("Sincronização 100% concluída!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Erro de Sistema: " . $e->getMessage());
            Log::error("SSW API System Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ==========================================
    // A INTELIGÊNCIA ARTIFICIAL: LEITURA DE HISTÓRICO
    // ==========================================
    private function descobrirLinhaBudget($historico, $fornecedor, $evento)
    {
        $hist = Str::ascii(mb_strtoupper(trim($historico), 'UTF-8'));
        $forn = Str::ascii(mb_strtoupper(trim($fornecedor), 'UTF-8'));
        $eventoTratado = mb_strtoupper(trim($evento), 'UTF-8');
        
        if (str_contains($hist, 'TELEFONE') || str_contains($hist, 'CELULAR') || str_contains($forn, 'VIVO')) return 'TELEFONE';
        if (str_contains($hist, 'COOWORKING') || str_contains($forn, 'TILIT')) return 'COWORK SJC';
        if (str_contains($hist, 'CONTABILIDADE') || str_contains($forn, 'MASTER CONTABIL')) return 'CONTABILIDADE';
        if (str_contains($hist, 'SSW') || str_contains($forn, 'SSW')) return 'SSW';
        if (str_contains($hist, 'PRO LABORE') || str_contains($evento, '5466')) return 'PRO LABORE';
        if (str_contains($hist, 'MARIA CLARA')) return 'MARIA CLARA (MEI)';
        if (str_contains($hist, 'ENERGIA')) return 'CONTA DE ENERGIA';

        if (str_contains($hist, 'E4LOG') || str_contains($forn, 'E4LOG')) return 'FECHAMENTO E4LOG';
        if (str_contains($hist, 'SEGURO') && (str_contains($forn, 'ALLIANZ') || str_contains($forn, 'SOMPO') || str_contains($forn, 'TOKIO'))) return 'SEGURO DE CARGA - TOKIO MARINE - SOMPO';
        if (str_contains($hist, 'COMISSAO MAURICIO')) return 'COMISSAO MAURÍCIO';
        if (str_contains($hist, 'MONITORAMENTO') || str_contains($hist, 'GERENCIAMENTO') || str_contains($forn, 'TS CONTROL')) return 'GERENCIAMENTO RISCO';
        if (str_contains($hist, 'PEDAGIO')) return 'PEDAGIO BRUNO';
        if (str_contains($hist, 'COLETA') || str_contains($hist, 'FRETE')) return 'CUSTO DE COLETA';
        
        if (str_contains($hist, 'TARIFA') || str_contains($hist, 'CARTAO')) return 'DESPESA BANCARIA';
        if (str_contains($hist, 'ICMS') || str_contains($hist, 'PIS') || str_contains($hist, 'COFINS') || str_contains($hist, 'IRPJ') || str_contains($hist, 'CSLL')) return 'IMPOSTOS E TAXAS';

        return $eventoTratado; 
    }

    // ==========================================
    // INJEÇÃO TOTAL NO BUDGET
    // ==========================================
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

        $despesas = SswDespesa::where('situacao', 'Liquidado')
            ->where('competencia', 'like', "%/{$anoSsw}")
            ->get();

        $valoresAgrupados = []; 
        
        foreach ($despesas as $desp) {
            $linhaDestino = $this->descobrirLinhaBudget($desp->historico, $desp->fornecedor, $desp->evento);
            $mesInt = (int) explode('/', $desp->competencia)[0]; 

            if ($linhaDestino && $mesInt >= 1 && $mesInt <= 12) {
                if (!isset($valoresAgrupados[$linhaDestino])) {
                    $valoresAgrupados[$linhaDestino] = array_fill_keys(range(1, 12), 0);
                }
                $valoresAgrupados[$linhaDestino][$mesInt] += $desp->valor;
            }
        }

        foreach ($valoresAgrupados as $nomeDaLinha => $mesesValores) {
            $budgetItem = $budget->items->where('nome', $nomeDaLinha)->first();
            
            if (!$budgetItem) {
                $isEventoSsw = preg_match('/^[0-9]{4}-/', $nomeDaLinha);
                $budgetItem = $budget->items()->create([
                    'categoria' => $isEventoSsw ? 'SSW - FORA DO BUDGET' : 'CUSTOS OPERACIONAIS',
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

    // ==========================================
    // HELPERS DE LIMPEZA DE DADOS
    // ==========================================
    private function formatarData($dataString)
    {
        $dataString = trim($dataString);
        if (empty($dataString)) return null;
        
        try {
            return Carbon::createFromFormat('d/m/y', $dataString)->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('d/m/Y', $dataString)->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
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
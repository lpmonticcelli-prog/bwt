<?php

namespace App\Services;

use App\Models\City;
use App\Models\Faturamento;
use App\Services\CalculadoraReceitaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Jobs\ProcessBsoftLoteJob;

class BsoftSyncService
{
    private string $baseUrl = 'https://api.bsoft.com.br/sistema/v2';
    private ?string $token = null;
    
    // O CNPJ oficial da BWT Logística
    private string $cnpjBWT = '55008868000120';
    
    private array $credenciais = [
        "tag" => "ETL277",
        "usuario_sistema" => "API",
        "senha_sistema" => "E4log@2024", 
        "empresa" => 1
    ];

    public function __construct()
    {
        // Removido o limite de tempo do construtor para evitar falhas no boot do Laravel
        $this->login();
    }

    private function login(): void
    {
        try {
            $response = Http::withoutVerifying()
                ->acceptJson()
                ->timeout(15)
                ->post($this->baseUrl . '/login', $this->credenciais);

            if ($response->successful()) {
                $this->token = $response->json('access_token');
            }
        } catch (\Exception $e) {
            Log::error("BsoftSync [ERRO LOGIN]: " . $e->getMessage());
        }
    }

    private function getXmlCte(string $id_cte): ?string 
    {
        if (!$this->token) return null;

        try {
            $response = Http::withoutVerifying()
                ->withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->baseUrl . '/cte/' . $id_cte . '/xml');

            if ($response->successful()) {
                $body = $response->body();
                
                if (strpos($body, '<?xml') !== false) {
                    return $body;
                }
                
                $dados = $response->json();
                if (is_array($dados)) {
                    if (isset($dados['xml'])) return base64_decode($dados['xml']); 
                    if (isset($dados['data']['xml'])) return base64_decode($dados['data']['xml']);
                }
            }
        } catch (\Exception $e) {
            Log::error("BsoftSync [ERRO DOWNLOAD XML $id_cte]: " . $e->getMessage());
        }
        
        return null;
    }

    // =========================================================================
    // HELPER MÁGICO: Evita o erro de "Array to string conversion" do XML
    // =========================================================================
    private function safeString($val): string 
    {
        if (is_array($val)) {
            if (empty($val)) return '';
            return implode(' ', array_filter(array_map(function($v) {
                return is_scalar($v) ? (string)$v : '';
            }, $val)));
        }
        return trim((string)$val);
    }

    // =========================================================================
    // ETAPA 1: O "DESPACHANTE" - Apenas puxa os IDs e joga na Fila
    // =========================================================================
    public function sincronizarNotasRecentes(): array 
    {
        // Proteções contra queda do navegador adicionadas apenas no momento do clique
        @set_time_limit(120); 
        @ini_set('memory_limit', '512M');

        Log::info("==================================================");
        Log::info("🚀 [DESPACHANTE] INICIANDO BUSCA DE NOTAS BSOFT");

        if (!$this->token) {
            return ['success' => false, 'message' => 'Falha na autenticação com a Bsoft.'];
        }

        $hoje = Carbon::now('America/Sao_Paulo');
        
        if ($hoje->day <= 15) {
            $dataInicial = $hoje->copy()->startOfMonth()->format('d/m/Y');
            $dataFinal   = $hoje->format('d/m/Y'); 
        } else {
            $dataInicial = $hoje->copy()->day(16)->format('d/m/Y');
            $dataFinal   = $hoje->format('d/m/Y'); 
        }

        $offset = 0;
        $quantidade = 100; // REDUZIDO para não estourar o servidor
        $paginasBaixadas = 0;
        $totalEnviadoFila = 0;
        $inicioRotina = time();

        try {
            do {
                Log::info("📡 Buscando lote de notas na Bsoft (Offset: $offset)...");
                
                $response = Http::withoutVerifying()
                    ->withToken($this->token)
                    ->acceptJson()
                    ->timeout(60)
                    ->get($this->baseUrl . '/cte', [
                        'data_inicial' => $dataInicial,
                        'data_final'   => $dataFinal,
                        'quantidade'   => $quantidade,
                        'offset'       => $offset,
                        'status'       => '100'
                    ]);

                if (!$response->successful()) {
                    return ['success' => false, 'message' => "Erro na API da Bsoft (HTTP " . $response->status() . ")."];
                }

                $dadosCte = $response->json();
                $loteAtual = isset($dadosCte['data']) ? $dadosCte['data'] : (is_array($dadosCte) ? $dadosCte : []);
                $qtdLote = count($loteAtual);
                
                if (!empty($loteAtual)) {
                    ProcessBsoftLoteJob::dispatch($loteAtual);
                    $totalEnviadoFila += $qtdLote;
                    Log::info("📦 Lote de $qtdLote notas despachado para processamento.");
                }

                $offset += $quantidade;
                $paginasBaixadas++;

                // TRAVA DE SEGURANÇA CONTRA ERRO 500: Se passar de 45 segundos, para e avisa o usuário.
                if (env('QUEUE_CONNECTION', 'sync') === 'sync' && (time() - $inicioRotina > 45)) {
                    Log::warning("⚠️ Tempo limite da Web atingido. Salvando parciais e interrompendo paginação.");
                    return [
                        'success' => true,
                        'message' => "🚀 Sucesso Parcial!\n\nProcessamos $totalEnviadoFila notas agora.\n\nPara não travar o seu sistema, fizemos uma pausa. Clique em SINCRONIZAR novamente para puxar as notas restantes!"
                    ];
                }

                if ($paginasBaixadas >= 500) break; // Limite de emergência

            } while ($qtdLote == $quantidade); 

            Log::info("🚀 [DESPACHANTE] FINALIZADO! Total enviado: $totalEnviadoFila");

            return [
                'success' => true, 
                'message' => "🚀 Sincronização Concluída!\n\nForam baixadas $totalEnviadoFila notas.\nTodas as páginas do período foram lidas com sucesso!"
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => "ERRO DO LARAVEL:\n\n" . $e->getMessage()];
        }
    }

    // =========================================================================
    // ETAPA 2: O "TRABALHADOR" - Lê o XML e salva no Banco
    // =========================================================================
    public function processarLoteDeNotas(array $lote): void
    {
        if (!$this->token) {
            $this->login();
        }

        $discoLocal = Storage::disk('local');
        $discoLocal->makeDirectory('xml_e4log/bwt_processados');
        
        $processadas = 0; 
        $atualizadas = 0; 
        $ignoradas = 0;

        foreach ($lote as $index => $cteApi) {
            $chaveCte = $cteApi['chave_acesso'] ?? $cteApi['chave'] ?? null;
            $idCte = $cteApi['id'] ?? null; 
            $valorTotal = $cteApi['valor_total'] ?? 0;
            
            if (!$chaveCte || !$idCte) continue;

            $nomeArquivo = 'E4LOG_' . $chaveCte . '.xml';
            $caminhoArquivo = 'xml_e4log/bwt_processados/' . $nomeArquivo;
            $xmlContent = null;

            if ($discoLocal->exists($caminhoArquivo)) {
                $xmlContent = $discoLocal->get($caminhoArquivo);
            } else {
                $xmlContent = $this->getXmlCte((string) $idCte);
                if ($xmlContent && strpos($xmlContent, 'ERRO_API') === false) {
                    $discoLocal->put($caminhoArquivo, $xmlContent);
                }
            }

            if (!$xmlContent || strpos($xmlContent, 'ERRO_API') === 0) continue;

            $xmlLimpo = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
            $xmlObj = @simplexml_load_string($xmlLimpo);
            $data = $xmlObj ? json_decode(json_encode($xmlObj), true) : [];
            
            $cnpjContratante = $this->extractCnpjPagador($data);
            
            if ($cnpjContratante !== $this->cnpjBWT) {
                $ignoradas++;
                continue;
            }
            
            $cidadeDestino = $this->extractCity($data) ?: 'DESCONHECIDA';
            $dataEmissao = $this->extractDataEmissao($data) ?? Carbon::now('America/Sao_Paulo')->format('Y-m-d');
            $valorCarga = $this->extractInvoiceValue($data);
            $observacoes = strtoupper($this->extractObs($data));
            $tipoCTe = $this->extractTipoCTe($data);
            $nfeChave = $this->extractNfe($data);
            $produto = $this->extractProduto($data);
            $tipoOperacao = $this->extractTipoOperacao($observacoes, $tipoCTe);

            $criterioBusca = ($nfeChave !== 'N/A' && !empty($nfeChave)) 
                ? ['nfe_chave' => Str::limit($nfeChave, 250, '')] 
                : ['arquivo' => Str::limit($nomeArquivo, 250, '')];

            $freteExistente = Faturamento::where($criterioBusca)->first();

            if ($freteExistente) {
                $freteExistente->e4log_faturado = true;
                $freteExistente->custo_e4log = $valorTotal;
                $freteExistente->custo_total = $valorTotal;
                
                $receitaReferencia = $freteExistente->receita_real > 0 
                    ? $freteExistente->receita_real 
                    : $freteExistente->receita_teorica;
                    
                $freteExistente->lucro = $receitaReferencia - $valorTotal;
                $freteExistente->save();
                $atualizadas++;
                continue; 
            }

            $city = City::where('name', $cidadeDestino)->with('regions.pricingRules')->first();
            $receitaFreteBase = 0; 
            $receitaTde = 0; 
            $receitaIcms = 0; 
            $receitaTeorica = 0; 
            $regraNome = 'E4LOG Automática (Sem Região)';
            $temTde = str_contains($observacoes, 'TDE') || str_contains($observacoes, 'RURAL') || $tipoCTe == '1';

            if ($city && $city->regions->isNotEmpty()) {
                $regionBwt = $city->regions->filter(fn($r) => strtolower($r->context) === 'bwt')->first();
                
                if ($regionBwt && $regionBwt->pricingRules->isNotEmpty()) {
                    $ruleBwt = $regionBwt->pricingRules->first();
                    $matematicaSolfacil = CalculadoraReceitaService::calcularSolfacil($ruleBwt, $valorCarga, $temTde, $tipoOperacao);
                    
                    $receitaFreteBase = $matematicaSolfacil['frete_base'];
                    $receitaTde       = $matematicaSolfacil['tde'];
                    $receitaIcms      = $matematicaSolfacil['icms'];
                    $receitaTeorica   = $matematicaSolfacil['total'];
                    $regraNome        = $regionBwt->name . " (Sol Fácil)";
                }
            }

            Faturamento::create([
                'arquivo'            => Str::limit($nomeArquivo, 250, ''),
                'destino'            => Str::limit($cidadeDestino, 150, ''),
                'regra'              => Str::limit($regraNome, 100, ''),
                'tipo_operacao'      => Str::limit($tipoOperacao, 50, ''),
                'data_emissao'       => $dataEmissao,
                'tipo_cte'           => Str::limit($tipoOperacao, 100, ''), 
                'nfe_chave'          => Str::limit($nfeChave, 250, ''),
                'produto'            => Str::limit($produto, 250, ''),
                'valor_carga'        => $valorCarga,
                'e4log_faturado'     => true,
                'custo_e4log'        => $valorTotal,
                'custo_frete_base'   => 0,
                'custo_tde'          => 0,
                'custo_total'        => $valorTotal,
                'receita_frete_base' => $receitaFreteBase,
                'receita_tde'        => $receitaTde,
                'receita_icms'       => $receitaIcms,
                'receita_teorica'    => $receitaTeorica,
                'receita_real'       => 0,
                'lucro'              => $receitaTeorica - $valorTotal, 
            ]);
            
            $processadas++;
        }

        Log::info("✅ [WORKER FINALIZADO] Lote: Novas $processadas | Atuais $atualizadas | Ignoradas: $ignoradas");
    }

    // =========================================================================
    // MÉTODOS AUXILIARES BLINDADOS PELO "safeString"
    // =========================================================================

    private function getBaseNode(array $data): ?array 
    { 
        if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; 
        if (isset($data['infCte'])) return $data['infCte']; 
        return null; 
    }

    private function extractCnpjPagador(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['exped']['CNPJ'])) {
            return $this->safeString($base['exped']['CNPJ']);
        } 
        if ($base && isset($base['rem']['CNPJ'])) {
            return $this->safeString($base['rem']['CNPJ']);
        }
        return null; 
    }

    private function extractCity(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['dest']['enderDest']['xMun'])) {
            return strtoupper(Str::slug($this->safeString($base['dest']['enderDest']['xMun']), ' '));
        } 
        return null; 
    }

    private function extractInvoiceValue(array $data): float 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) {
            return (float) $this->safeString($base['infCTeNorm']['infCarga']['vCarga']);
        } 
        return 0.00; 
    }

    private function extractObs(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['compl']['xObs'])) {
            return $this->safeString($base['compl']['xObs']);
        } 
        return ''; 
    }

    private function extractTipoCTe(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['tpCTe'])) {
            return $this->safeString($base['ide']['tpCTe']);
        } 
        return '0'; 
    }

    private function extractNfe(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infDoc']['infNFe'])) { 
            $nfe = $base['infCTeNorm']['infDoc']['infNFe']; 
            
            if (isset($nfe['chave'])) {
                return $this->safeString($nfe['chave']);
            } 
            if (is_array($nfe) && isset($nfe[0]['chave'])) {
                return $this->safeString($nfe[0]['chave']);
            } 
        } 
        return 'N/A'; 
    }

    private function extractProduto(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['proPred'])) { 
            return $this->safeString($base['infCTeNorm']['infCarga']['proPred']);
        } 
        return 'N/A'; 
    }

    private function extractDataEmissao(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['dhEmi'])) {
            return substr($this->safeString($base['ide']['dhEmi']), 0, 10);
        } 
        return null; 
    }

    private function extractTipoOperacao(string $observacoes, string $tipoCTe): string 
    { 
        if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) {
            return 'Devolução';
        } 
        if (str_contains($observacoes, 'REENTREGA')) {
            return 'Reentrega';
        } 
        if ($tipoCTe == '1') {
            return 'Complemento';
        } 
        return 'Entrega'; 
    }
}

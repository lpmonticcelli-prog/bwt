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
    
    // O CNPJ oficial da BWT Logística (Sem pontuação)
    private string $cnpjBWT = '55008868000120';
    
    private array $credenciais = [
        "tag" => "ETL277",
        "usuario_sistema" => "API",
        "senha_sistema" => "E4log@2024", 
        "empresa" => 1
    ];

    public function __construct()
    {
        set_time_limit(0); 
        ini_set('memory_limit', '512M');
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
    // ETAPA 1: O "DESPACHANTE" - Apenas puxa os IDs e joga na Fila
    // =========================================================================
    public function sincronizarNotasRecentes(): array 
    {
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
        $quantidade = 500; 
        $paginasBaixadas = 0;
        $totalEnviadoFila = 0;

        try {
            do {
                Log::info("📡 Buscando lote de notas na Bsoft (Offset: $offset)...");
                
                // As barras das datas são convertidas magicamente pela chamada em array do HTTP
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
                    Log::info("📦 Lote de $qtdLote notas despachado para a Fila de Processamento.");
                }

                $offset += $quantidade;
                $paginasBaixadas++;

                if ($paginasBaixadas >= 200) {
                    Log::warning("⚠️ Limite de 100.000 notas atingido!");
                    break;
                }

            } while ($qtdLote == $quantidade); 

            Log::info("🚀 [DESPACHANTE] FINALIZADO! Total enviado para a fila: $totalEnviadoFila");

            return [
                'success' => true, 
                'message' => "🚀 Sucesso!\n\nForam encontradas $totalEnviadoFila notas.\nElas foram enviadas para o motor de processamento invisível e estarão no sistema em alguns minutos!"
            ];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => "ERRO DO LARAVEL:\n\n" . $e->getMessage()];
        }
    }

    // =========================================================================
    // ETAPA 2: O "TRABALHADOR" - Roda no Background (Lê o XML e salva no Banco)
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
            
            // 🛑 O NOVO FILTRO TITÃ: Verifica de quem é a nota
            $cnpjContratante = $this->extractCnpjPagador($data);
            
            if ($cnpjContratante !== $this->cnpjBWT) {
                // A nota não é da BWT. O robô simplesmente pula para a próxima!
                $ignoradas++;
                continue;
            }
            
            // ==========================================================
            // DAQUI PARA BAIXO, TEMOS CERTEZA DE QUE É UMA NOTA DA BWT
            // ==========================================================
            
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

            // Atualização de frete existente
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

            // Cálculo para novo frete BWT
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

        Log::info("✅ [WORKER FINALIZADO] Lote: Novas $processadas | Atuais $atualizadas | Ignoradas (Outros Clientes): $ignoradas");
    }

    // =========================================================================
    // MÉTODOS AUXILIARES DE EXTRAÇÃO XML
    // =========================================================================

    private function getBaseNode(array $data): ?array 
    { 
        if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; 
        if (isset($data['infCte'])) return $data['infCte']; 
        return null; 
    }

    /**
     * Extrai o CNPJ de quem expediu ou remeteu a carga para identificar o dono da nota
     */
    private function extractCnpjPagador(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        
        // Prioriza o Expedidor (no caso da BWT, ela costuma ser a expedidora)
        if ($base && isset($base['exped']['CNPJ'])) {
            return (string) $base['exped']['CNPJ'];
        } 
        
        // Fallback para o Remetente se não houver expedidor
        if ($base && isset($base['rem']['CNPJ'])) {
            return (string) $base['rem']['CNPJ'];
        }
        
        return null; 
    }

    private function extractCity(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['dest']['enderDest']['xMun'])) {
            return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' '));
        } 
        return null; 
    }

    private function extractInvoiceValue(array $data): float 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) {
            return (float) $base['infCTeNorm']['infCarga']['vCarga'];
        } 
        return 0.00; 
    }

    private function extractObs(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['compl']['xObs'])) {
            return (string) $base['compl']['xObs'];
        } 
        return ''; 
    }

    private function extractTipoCTe(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['tpCTe'])) {
            return (string) $base['ide']['tpCTe'];
        } 
        return '0'; 
    }

    private function extractNfe(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infDoc']['infNFe'])) { 
            $nfe = $base['infCTeNorm']['infDoc']['infNFe']; 
            
            if (isset($nfe['chave'])) {
                return (string) $nfe['chave'];
            } 
            if (is_array($nfe) && isset($nfe[0]['chave'])) {
                return (string) $nfe[0]['chave'];
            } 
        } 
        return 'N/A'; 
    }

    private function extractProduto(array $data): string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infCarga']['proPred'])) { 
            $prod = $base['infCTeNorm']['infCarga']['proPred']; 
            
            if (is_array($prod)) {
                return implode(" ", $prod);
            } 
            return (string) $prod; 
        } 
        return 'N/A'; 
    }

    private function extractDataEmissao(array $data): ?string 
    { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['ide']['dhEmi'])) {
            return substr((string) $base['ide']['dhEmi'], 0, 10);
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
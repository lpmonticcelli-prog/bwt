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

class BsoftSyncService
{
    private $baseUrl = 'https://api.bsoft.com.br/sistema/v2';
    private $token = null;
    private $credenciais = [
        "tag" => "ETL277",
        "usuario_sistema" => "API",
        "senha_sistema" => "E4log@2024", 
        "empresa" => 1
    ];

    public function __construct()
    {
        // ==============================================================
        // MÁGICA DE INFRAESTRUTURA: Diz ao servidor para não dar erro 500
        // e dar todo o tempo do mundo para o robô baixar as 10.000 notas.
        // ==============================================================
        set_time_limit(0); 
        ini_set('memory_limit', '512M');
        
        $this->login();
    }

    private function login()
    {
        try {
            $response = Http::withoutVerifying()->acceptJson()->timeout(15)->post($this->baseUrl . '/login', $this->credenciais);
            if ($response->successful()) {
                $this->token = $response->json('access_token');
            }
        } catch (\Exception $e) {
            Log::error("BsoftSync [ERRO LOGIN]: " . $e->getMessage());
        }
    }

    private function getXmlCte($id_cte) 
    {
        if (!$this->token) return null;
        try {
            $response = Http::withoutVerifying()->withToken($this->token)->acceptJson()->timeout(15)->get($this->baseUrl . '/cte/' . $id_cte . '/xml');
            if ($response->successful()) {
                $body = $response->body();
                if (strpos($body, '<?xml') !== false) return $body;
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

    public function sincronizarNotasRecentes() 
    {
        Log::info("==================================================");
        Log::info("🚀 INICIANDO SINCRONIZAÇÃO BSOFT (MANUAL)");

        if (!$this->token) {
            Log::error("❌ Falha de Autenticação.");
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

        Log::info("📅 Período Alvo: $dataInicial até $dataFinal");

        $offset = 0;
        $quantidade = 500; 
        $listaCompleta = [];
        $paginasBaixadas = 0;

        try {
            do {
                Log::info("📡 Buscando lote de notas na Bsoft (Offset: $offset)...");
                
                $urlComFiltros = $this->baseUrl . "/cte?data_inicial={$dataInicial}&data_final={$dataFinal}&quantidade={$quantidade}&offset={$offset}&status=100";

                $response = Http::withoutVerifying()
                    ->withToken($this->token)
                    ->acceptJson()
                    ->timeout(60) // Aumentamos o limite para a API Bsoft
                    ->get($urlComFiltros);

                if (!$response->successful()) {
                    Log::error("❌ Erro HTTP " . $response->status());
                    return ['success' => false, 'message' => "Erro na API da Bsoft (HTTP " . $response->status() . ")."];
                }

                $dadosCte = $response->json();
                $loteAtual = isset($dadosCte['data']) ? $dadosCte['data'] : (is_array($dadosCte) ? $dadosCte : []);
                
                $qtdLote = count($loteAtual);
                Log::info("✅ Lote recebido com $qtdLote notas.");

                if (!empty($loteAtual)) {
                    $listaCompleta = array_merge($listaCompleta, $loteAtual);
                }

                $offset += $quantidade;
                $paginasBaixadas++;

                // Aumentado o limite real e visualmente
                if ($paginasBaixadas >= 200) {
                    Log::warning("⚠️ Trava de Segurança Acionada! Abortando paginação no limite de 10.000 notas.");
                    break;
                }

            } while ($qtdLote == $quantidade); 

            $totalBaixado = count($listaCompleta);
            Log::info("📦 Total de notas encontradas no período: $totalBaixado");

            if (empty($listaCompleta)) {
                return ['success' => true, 'message' => "Sincronização concluída. Nenhuma nota encontrada."];
            }

            Storage::disk('local')->makeDirectory('xml_e4log/bwt_processados');
            $processadas = 0; $atualizadas = 0; 
            
            Log::info("⚙️ Iniciando processamento individual das notas...");

            foreach ($listaCompleta as $index => $cteApi) {
                $chaveCte = $cteApi['chave_acesso'] ?? $cteApi['chave'] ?? null;
                $idCte = $cteApi['id'] ?? null; 
                $valorTotal = $cteApi['valor_total'] ?? 0;
                
                if (!$chaveCte || !$idCte) continue;

                $numAtual = $index + 1;
                $nomeArquivo = 'E4LOG_' . $chaveCte . '.xml';
                $caminhoArquivo = 'xml_e4log/bwt_processados/' . $nomeArquivo;

                if (Storage::disk('local')->exists($caminhoArquivo)) {
                    $xmlContent = Storage::disk('local')->get($caminhoArquivo);
                } else {
                    Log::info("[$numAtual/$totalBaixado] 🌐 Baixando XML da API: $chaveCte");
                    $xmlContent = $this->getXmlCte($idCte);
                    if ($xmlContent && strpos($xmlContent, 'ERRO_API') === false) {
                        Storage::disk('local')->put($caminhoArquivo, $xmlContent);
                    }
                }

                if (!$xmlContent || strpos($xmlContent, 'ERRO_API') === 0) {
                    continue;
                }

                $xmlLimpo = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = @simplexml_load_string($xmlLimpo);
                $data = $xmlObj ? json_decode(json_encode($xmlObj), true) : [];
                
                $cidadeDestino = $this->extractCity($data) ?: 'DESCONHECIDA';
                $dataEmissao = $this->extractDataEmissao($data) ?? Carbon::now('America/Sao_Paulo')->format('Y-m-d');
                $valorCarga = $this->extractInvoiceValue($data);
                $observacoes = strtoupper($this->extractObs($data));
                $tipoCTe = $this->extractTipoCTe($data);
                $nfeChave = $this->extractNfe($data);
                $produto = $this->extractProduto($data);
                $tipoOperacao = $this->extractTipoOperacao($observacoes, $tipoCTe);

                $criterioBusca = ($nfeChave !== 'N/A' && !empty($nfeChave)) ? ['nfe_chave' => Str::limit($nfeChave, 250, '')] : ['arquivo' => Str::limit($nomeArquivo, 250, '')];

                $freteExistente = Faturamento::where($criterioBusca)->first();

                if ($freteExistente) {
                    $freteExistente->e4log_faturado = true;
                    $freteExistente->custo_e4log = $valorTotal;
                    $freteExistente->custo_total = $valorTotal;
                    $receitaReferencia = $freteExistente->receita_real > 0 ? $freteExistente->receita_real : $freteExistente->receita_teorica;
                    $freteExistente->lucro = $receitaReferencia - $valorTotal;
                    $freteExistente->save();
                    $atualizadas++;
                    continue; 
                }

                $city = City::where('name', $cidadeDestino)->with('regions.pricingRules')->first();
                $receitaFreteBase = 0; $receitaTde = 0; $receitaIcms = 0; $receitaTeorica = 0; $regraNome = 'E4LOG Automática (Sem Região)';
                $temTde = str_contains($observacoes, 'TDE') || str_contains($observacoes, 'RURAL') || $tipoCTe == '1';

                if ($city && $city->regions->isNotEmpty()) {
                    $regionBwt = $city->regions->filter(fn($r) => strtolower($r->context) === 'bwt')->first();
                    if ($regionBwt && $regionBwt->pricingRules->isNotEmpty()) {
                        $ruleBwt = $regionBwt->pricingRules->first();
                        $matematicaSolfacil = CalculadoraReceitaService::calcularSolfacil($ruleBwt, $valorCarga, $temTde, $tipoOperacao);
                        $receitaFreteBase = $matematicaSolfacil['frete_base'];
                        $receitaTde = $matematicaSolfacil['tde'];
                        $receitaIcms = $matematicaSolfacil['icms'];
                        $receitaTeorica = $matematicaSolfacil['total'];
                        $regraNome = $regionBwt->name . " (Sol Fácil)";
                    }
                }

                Faturamento::create([
                    'arquivo' => Str::limit($nomeArquivo, 250, ''),
                    'destino' => Str::limit($cidadeDestino, 150, ''),
                    'regra' => Str::limit($regraNome, 100, ''),
                    'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                    'data_emissao' => $dataEmissao,
                    'tipo_cte' => Str::limit($tipoOperacao, 100, ''),
                    'nfe_chave' => Str::limit($nfeChave, 250, ''),
                    'produto' => Str::limit($produto, 250, ''),
                    'valor_carga' => $valorCarga,
                    'e4log_faturado' => true,
                    'custo_e4log' => $valorTotal,
                    
                    // CAMPOS OBRIGATÓRIOS DO BANCO
                    'custo_frete_base' => 0,
                    'custo_tde' => 0,
                    'custo_total' => $valorTotal,
                    
                    'receita_frete_base' => $receitaFreteBase,
                    'receita_tde' => $receitaTde,
                    'receita_icms' => $receitaIcms,
                    'receita_teorica' => $receitaTeorica,
                    'receita_real' => 0,
                    'lucro' => $receitaTeorica - $valorTotal, 
                ]);
                
                $processadas++;
            }

            Log::info("🏁 FINALIZADO: Salvas: $processadas | Atualizadas: $atualizadas");
            Log::info("==================================================");

            // CORREÇÃO: Usando a data formatada direto na mensagem! Adeus erro Carbon!
            return ['success' => true, 'message' => "🚀 Sucesso! (Quinzena: $dataInicial a $dataFinal)\n\nBaixadas da Bsoft: $totalBaixado notas\nNovas Salvas no Banco: $processadas"];

        } catch (\Throwable $e) {
            Log::error("❌ BsoftSync Falha Geral: " . $e->getMessage());
            return ['success' => false, 'message' => "ERRO DO LARAVEL:\n\n" . $e->getMessage()];
        }
    }

    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; return 0.00; }
    private function extractObs($data) { $base = $this->getBaseNode($data); if ($base && isset($base['compl']['xObs'])) return (string) $base['compl']['xObs']; return ''; }
    private function extractTipoCTe($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; return '0'; }
    private function extractNfe($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCTeNorm']['infDoc']['infNFe'])) {
            $nfe = $base['infCTeNorm']['infDoc']['infNFe'];
            if (isset($nfe['chave'])) return (string) $nfe['chave'];
            if (is_array($nfe) && isset($nfe[0]['chave'])) return (string) $nfe[0]['chave'];
        }
        return 'N/A';
    }
    private function extractProduto($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCTeNorm']['infCarga']['proPred'])) {
            $prod = $base['infCTeNorm']['infCarga']['proPred'];
            if (is_array($prod)) return implode(" ", $prod);
            return (string) $prod;
        }
        return 'N/A';
    }
    private function extractDataEmissao($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['ide']['dhEmi'])) return substr((string) $base['ide']['dhEmi'], 0, 10);
        return null;
    }
    private function extractTipoOperacao($observacoes, $tipoCTe) {
        if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) return 'Devolução';
        if (str_contains($observacoes, 'REENTREGA')) return 'Reentrega';
        if ($tipoCTe == '1') return 'Complemento';
        return 'Entrega';
    }
}
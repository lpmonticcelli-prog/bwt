<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;
use App\Models\Faturamento;
use App\Services\CalculadoraReceitaService; 
use App\Services\BsoftSyncService; 
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;
use Exception;

class FaturamentoController extends Controller
{
    // =========================================================================
    // EXIBIÇÃO DA TELA (BUSCA O MÊS ATUAL PARA O FRONTEND FILTRAR AS QUINZENAS)
    // =========================================================================
    public function index(Request $request)
    {
        $fechamentoId = $request->query('fechamento_id');

        $query = Faturamento::query();
        
        if ($fechamentoId) {
            $query->where('fechamento_periodo_id', $fechamentoId);
        } else {
            // Buscamos o mês inteiro. O Vue.js se encarregará de separar as quinzenas.
            $hoje = \Carbon\Carbon::now('America/Sao_Paulo');
            $inicio = $hoje->copy()->startOfMonth()->format('Y-m-d');
            $fim    = $hoje->copy()->endOfMonth()->format('Y-m-d');
            
            $query->whereBetween('data_emissao', [$inicio, $fim]);
        }

        $faturamentos = $query->orderBy('data_emissao', 'desc')->get();

        $fechamentos = [];
        if (class_exists('\App\Models\Fechamento')) {
            $fechamentos = \App\Models\Fechamento::orderBy('id', 'desc')->get()->map(function($f) {
                return [
                    'id' => $f->id,
                    'titulo' => $f->nome ?? $f->titulo ?? 'Lote ' . $f->id
                ];
            });
        }

        return Inertia::render('Faturamento/Index', [
            'fechamento_id' => $fechamentoId,
            'fechamentos' => $fechamentos,
            'fretesDetalhados' => $faturamentos,
            'faturamentosDetalhados' => $faturamentos,
            'cruzamentoViagens' => [],
            'resumoFaturamento' => [], // Otimizado: O Vue já calcula tudo no frontend
            'resumoAuditoria' => [],     
        ]);
    }

    // =========================================================================
    // SINCRONIZAÇÃO GLOBAL API BSOFT 
    // =========================================================================
    public function sincronizarGeral(Request $request)
    {
        try {
            $servico = new BsoftSyncService();
            // A Bsoft buscará os últimos 15 a 30 dias para garantir o mês vigente
            $resultado = $servico->sincronizarNotasRecentes(); 
            
            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // PROCESSAMENTO DE UPLOAD DE XML (SSW)
    // =========================================================================
    public function processar(Request $request)
    {
        try {
            \DB::connection()->getPdo();

            if (!$request->hasFile('xml_files')) throw new Exception("Nenhum ficheiro recebido.");
            $request->validate(['xml_files' => 'required|array']);
            
            $resultados = [];
            $errosDetetive = []; 

            foreach ($request->file('xml_files') as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xml') continue;

                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $cidadeDestino = $this->extractCity($data);
                $valorCarga = $this->extractInvoiceValue($data);
                $receitaBwtXML = $this->extractFreightValue($data); 
                $observacoes = strtoupper($this->extractObs($data));
                $tipoCTe = $this->extractTipoCTe($data);
                $nfeChave = $this->extractNfe($data);
                $produto = $this->extractProduto($data);
                $dataEmissao = $this->extractDataEmissao($data);
                $tipoOperacao = $this->extractTipoOperacao($observacoes, $tipoCTe);

                if (!$cidadeDestino) continue;

                $city = City::where('name', $cidadeDestino)->with('regions.pricingRules')->first();

                if (!$city) { $errosDetetive[] = "[{$cidadeDestino} não cadastrada]"; continue; }
                if ($city->regions->isEmpty()) { $errosDetetive[] = "[{$cidadeDestino} sem Região]"; continue; }

                $regionE4log = $city->regions->filter(fn($r) => strtolower($r->context) === 'e4log')->first();
                $regionBwt = $city->regions->filter(fn($r) => strtolower($r->context) === 'bwt')->first();

                if (!$regionE4log || !$regionBwt) { $errosDetetive[] = "[{$cidadeDestino} não tem rota E4LOG ou BWT]"; continue; }

                $ruleE4log = $regionE4log->pricingRules->first();
                $ruleBwt = $regionBwt->pricingRules->first();

                if (!$ruleE4log || !$ruleBwt) { $errosDetetive[] = "[A Região de {$cidadeDestino} está sem preço]"; continue; }

                $temTde = str_contains($observacoes, 'TDE') || str_contains($observacoes, 'RURAL') || $tipoCTe == '1';

                $custoFixo = (float) $ruleE4log->fixed_value;
                $custoPct = (float) $ruleE4log->excess_percentage / 100;
                $custoFreteBase = max($custoFixo, ($valorCarga * $custoPct));
                
                $custoTde = 0;
                if ($temTde) {
                    $tdePercentE4log = (float) ($ruleE4log->tde_percentage ?? 20); 
                    $custoTde = max(160.00, $custoFreteBase * ($tdePercentE4log / 100));
                }
                
                $custoTotalE4log = $custoFreteBase + $custoTde;
                if ($tipoCTe == '1') { $custoFreteBase = 0; $custoTotalE4log = $custoTde; }

                $matematicaSolfacil = CalculadoraReceitaService::calcularSolfacil($ruleBwt, $valorCarga, $temTde, $tipoOperacao);
                
                $criterioBusca = ($nfeChave !== 'N/A' && !empty($nfeChave)) 
                    ? ['nfe_chave' => Str::limit($nfeChave, 250, '')]
                    : ['arquivo' => Str::limit($file->getClientOriginalName(), 250, '')];

                $freteExistente = Faturamento::where($criterioBusca)->first();
                
                $custoDefinitivo = $freteExistente && $freteExistente->custo_e4log > 0 
                                    ? $freteExistente->custo_e4log 
                                    : $custoTotalE4log;

                $lucroLiquido = $receitaBwtXML - $custoDefinitivo;

                $resultados[] = Faturamento::updateOrCreate(
                    $criterioBusca, 
                    [
                        'arquivo' => Str::limit($file->getClientOriginalName(), 250, ''),
                        'fechamento_periodo_id' => $request->input('fechamento_id'),
                        'destino' => Str::limit($cidadeDestino, 150, ''),
                        'regra' => Str::limit($regionBwt->name . " (Sol Fácil)", 100, ''),
                        'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                        'data_emissao' => $dataEmissao,
                        'data_entrega' => null, 
                        'tipo_cte' => Str::limit($tipoOperacao, 100, ''),
                        'nfe_chave' => Str::limit($nfeChave, 250, ''),
                        'produto' => Str::limit($produto, 250, ''),
                        'valor_carga' => $valorCarga,
                        
                        'custo_frete_base' => $custoFreteBase,
                        'custo_tde' => $custoTde,
                        'custo_total' => $custoDefinitivo, 
                        
                        'receita_frete_base' => $matematicaSolfacil['frete_base'],
                        'receita_tde' => $matematicaSolfacil['tde'],
                        'receita_icms' => $matematicaSolfacil['icms'],
                        'receita_teorica' => $matematicaSolfacil['total'],
                        'receita_real' => $receitaBwtXML,
                        'lucro' => $lucroLiquido
                    ]
                );
            }

            if (!empty($errosDetetive)) {
                Log::warning("Rentabilidade BWT - Lote parcial. Descartes: " . implode(" | ", array_unique($errosDetetive)));
            }

            if (empty($resultados)) {
                $motivo = empty($errosDetetive) ? "Nenhum ficheiro XML válido encontrado." : implode(" | ", array_unique($errosDetetive));
                throw new Exception($motivo);
            }

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return redirect()->route('faturamento.index');

        } catch (Throwable $e) {
            if ($request->wantsJson()) return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422);
            return back()->withErrors(['erro_fatal' => 'DETALHE DO ERRO: ' . $e->getMessage()]);
        }
    }

    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; return 0.00; }
    private function extractFreightValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest']; return 0.00; }
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
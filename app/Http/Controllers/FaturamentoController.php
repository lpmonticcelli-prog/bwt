<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;
use App\Models\Faturamento;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;
use Exception;

class FaturamentoController extends Controller
{
    public function index()
    {
        return Inertia::render('Faturamento/Index', [
            'faturamentosProcessados' => Faturamento::orderBy('id', 'desc')->get()
        ]);
    }

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
                
                if (!$cidadeDestino) continue;

                $city = City::where('name', $cidadeDestino)->with('regions.pricingRules')->first();

                // Capta falhas nas planilhas e evita erros silenciosos
                if (!$city) { $errosDetetive[] = "[{$cidadeDestino} não cadastrada]"; continue; }
                if ($city->regions->isEmpty()) { $errosDetetive[] = "[{$cidadeDestino} sem Região]"; continue; }

                $regionE4log = $city->regions->filter(fn($r) => strtolower($r->context) === 'e4log')->first();
                $regionBwt = $city->regions->filter(fn($r) => strtolower($r->context) === 'bwt')->first();

                if (!$regionE4log || !$regionBwt) { $errosDetetive[] = "[{$cidadeDestino} não tem rota E4LOG ou BWT]"; continue; }

                $ruleE4log = $regionE4log->pricingRules->first();
                $ruleBwt = $regionBwt->pricingRules->first();

                if (!$ruleE4log || !$ruleBwt) { $errosDetetive[] = "[A Região de {$cidadeDestino} está sem preço]"; continue; }

                $temTde = str_contains($observacoes, 'TDE') || str_contains($observacoes, 'RURAL') || $tipoCTe == '1';

                // 1. CUSTO E4LOG (O QUE VOCÊ PAGA)
                $custoFixo = (float) $ruleE4log->fixed_value;
                $custoPct = (float) $ruleE4log->excess_percentage / 100;
                $custoFreteBase = max($custoFixo, ($valorCarga * $custoPct));
                
                $custoTde = 0;
                if ($temTde) {
                    $tdePercentE4log = (float) ($ruleE4log->tde_percentage ?? 20); 
                    // REGRA APLICADA: Mínimo 160 reais ou 20% sob o valor do frete base
                    $custoTde = max(160.00, $custoFreteBase * ($tdePercentE4log / 100));
                }
                
                $custoTotalE4log = $custoFreteBase + $custoTde;
                if ($tipoCTe == '1') { $custoFreteBase = 0; $custoTotalE4log = $custoTde; }

                // 2. RECEITA BWT (O QUE VOCÊ COBRA DA SOL FÁCIL)
                $receitaFixo = (float) $ruleBwt->fixed_value;
                $receitaPct = (float) $ruleBwt->excess_percentage / 100;
                $receitaFretePct = $valorCarga * $receitaPct;
                $receitaFreteBase = max($receitaFixo, $receitaFretePct);

                $receitaTde = 0;
                if ($temTde) {
                    $tdeMinBwt = (float) ($ruleBwt->tde_min_value ?? 200.00);
                    $tdePercentBwt = (float) ($ruleBwt->tde_percentage ?? 30);
                    // REGRA APLICADA: Mínimo 200 reais ou 30% sob o valor do frete
                    $receitaTde = max($tdeMinBwt, $receitaFreteBase * ($tdePercentBwt / 100));
                }

                if ($tipoCTe == '1') { $receitaFreteBase = 0; $receitaSemImposto = $receitaTde; } 
                else { $receitaSemImposto = $receitaFreteBase + $receitaTde; }

                $icmsPercent = (float) ($ruleBwt->icms_percentage ?? 12);
                $fatorIcms = 1 - ($icmsPercent / 100); 
                
                $receitaTeoricaTotal = $receitaSemImposto > 0 ? ($receitaSemImposto / $fatorIcms) : 0; 
                $icmsCalculado = $receitaTeoricaTotal - $receitaSemImposto;

                $lucroLiquido = $receitaBwtXML - $custoTotalE4log;

                $nomeOperacao = 'Entrega Normal';
                if ($tipoCTe == '1') $nomeOperacao = 'Complemento de Valor';
                if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) $nomeOperacao = 'Devolução / Retorno';

                $resultados[] = Faturamento::updateOrCreate(
                    ['arquivo' => Str::limit($file->getClientOriginalName(), 250, '')], 
                    [
                        'destino' => Str::limit($cidadeDestino, 150, ''),
                        'regra' => Str::limit($regionBwt->name . " (Sol Fácil)", 100, ''),
                        'tipo_cte' => Str::limit($nomeOperacao, 100, ''),
                        'nfe_chave' => Str::limit($nfeChave, 250, ''),
                        'produto' => Str::limit($produto, 250, ''),
                        'valor_carga' => $valorCarga,
                        'custo_frete_base' => $custoFreteBase,
                        'custo_tde' => $custoTde,
                        'custo_total' => $custoTotalE4log,
                        'receita_frete_base' => $receitaFreteBase,
                        'receita_tde' => $receitaTde,
                        'receita_icms' => $icmsCalculado,
                        'receita_teorica' => $receitaTeoricaTotal,
                        'receita_real' => $receitaBwtXML,
                        'lucro' => $lucroLiquido
                    ]
                );
            }

            // Grava os descartes no log do sistema
            if (!empty($errosDetetive)) {
                Log::warning("Rentabilidade BWT - Lote parcial. Descartes: " . implode(" | ", array_unique($errosDetetive)));
            }

            if (empty($resultados)) {
                $motivo = empty($errosDetetive) ? "Nenhum ficheiro XML válido encontrado." : implode(" | ", array_unique($errosDetetive));
                throw new Exception($motivo);
            }

            // Responde via JSON para preservar o ecrã
            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->route('faturamento.index');

        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422);
            }
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
}
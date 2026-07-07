<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;
use App\Models\Frete;
use App\Services\CalculadoraCustoService; // Importação do nosso Cérebro de Auditoria
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;
use Exception;

class AuditController extends Controller
{
    public function index()
    {
        return Inertia::render('Auditoria/Index', [
            'fretesProcessados' => Frete::orderBy('id', 'desc')->get()
        ]);
    }

    public function processarCusto(Request $request)
    {
        try {
            \DB::connection()->getPdo();

            if (!$request->hasFile('xml_files')) throw new Exception("Nenhum ficheiro recebido.");
            $request->validate(['xml_files' => 'required|array']);
            
            $resultados = [];
            $errosDetetive = [];

            foreach ($request->file('xml_files') as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xml') continue;

                // Leitura Limpa do XML
                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
                $cidadeDestino = $this->extractCity($data);
                $valorCarga = $this->extractInvoiceValue($data);
                $valorCobradoNoXML = $this->extractFreightValue($data);  
                
                $observacoes = strtoupper($this->extractObs($data));
                $tipoCTe = $this->extractTipoCTe($data);
                
                $dataEmissao = $this->extractDataEmissao($data);
                $produto = $this->extractProduto($data);
                $tipoOperacao = $this->extractTipoOperacao($observacoes, $tipoCTe);
                
                $cteChave = $this->extractCteKey($data);
                $chaveComplementada = $this->extractChaveComplementada($data);
                $nfeChave = $this->extractNfe($data); 
                
                if (!$cidadeDestino) continue;

                // Cruzamento Geográfico
                $city = City::where('name', $cidadeDestino)->with('regions.pricingRules')->first();

                if (!$city) { $errosDetetive[] = "[{$cidadeDestino} não cadastrada]"; continue; }
                if ($city->regions->isEmpty()) { $errosDetetive[] = "[{$cidadeDestino} sem Região]"; continue; }

                // Procuramos exclusivamente a regra da transportadora parceira
                $regionE4log = $city->regions->filter(fn($r) => strtolower($r->context) === 'e4log')->first();
                
                if (!$regionE4log) { $errosDetetive[] = "[{$cidadeDestino} não tem rota E4LOG mapeada]"; continue; }

                $ruleE4log = $regionE4log->pricingRules->first();

                if (!$ruleE4log) { $errosDetetive[] = "[A Região de {$cidadeDestino} está sem tabela de preço]"; continue; }

                $temTde = str_contains($observacoes, 'TDE') || str_contains($observacoes, 'RURAL') || $tipoCTe == '1';

                // ==========================================
                // ARQUITETURA LIMPA: CÁLCULO PELO SERVICE
                // ==========================================
                $matematicaE4log = CalculadoraCustoService::calcularE4log($ruleE4log, $valorCarga, $temTde, $tipoOperacao);
                
                // Diferença (Risco de Glosa)
                // Se cobraram mais do que a tabela permite, gera diferença positiva (Glosa).
                $diferenca = $valorCobradoNoXML - $matematicaE4log['total'];

                Frete::updateOrCreate(
                    ['arquivo' => $nomeArquivo], 
                    [
                        'fechamento_periodo_id' => $request->input('fechamento_id'),
                        'destino' => Str::limit($cidadeDestino, 150, ''),
                        'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                        'data_emissao' => $dataEmissao,
                        'nfe_chave' => Str::limit($nfeChave, 250, ''), 
                        'cte_chave' => $cteChave,
                        'chave_complementada' => $chaveComplementada,
                        'valorNF' => $valorCarga,
                        
                        // Gravando os valores cravados do Service
                        'fixoRegra' => $ruleE4log->fixed_value,
                        'percentualRegra' => $ruleE4log->excess_percentage,
                        'freteBaseCalculado' => $matematicaE4log['frete_base'],
                        'temTde' => $temTde,
                        'tdeCalculado' => $matematicaE4log['tde'],
                        'taxasExtras' => 0, // Pode evoluir para mapear Ad Valorem, Pedágios
                        
                        'cobrado' => round($valorCobradoNoXML, 2),
                        'correto' => $matematicaE4log['total'],
                        'diferenca' => round($diferenca, 2), 
                        
                        // Margem de erro de arredondamento aceitável da SEFAZ
                        'is_correto' => abs($diferenca) <= 0.50,
                        'regra' => Str::limit($regionE4log->name . " (E4LOG)", 100, ''),
                        'observacoes' => Str::limit($observacoes, 1000, '')
                    ]
                );

                $resultados[] = $nomeArquivo;
            }

            if (!empty($errosDetetive)) { Log::warning("Auditoria E4LOG - Lote parcial. Descartes: " . implode(" | ", array_unique($errosDetetive))); }
            if (empty($resultados)) throw new Exception("Nenhum XML processado. " . implode(" | ", array_unique($errosDetetive)));

            if ($request->wantsJson()) return response()->json(['success' => true]);
            return redirect()->back();

        } catch (Throwable $e) { 
            if ($request->wantsJson()) return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422);
            return back()->withErrors(['erro_fatal' => 'DETALHE: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // FUNÇÕES EXTRATORAS DE XML (HELPERS PRIVADOS)
    // =========================================================================
    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; return 0.00; }
    private function extractFreightValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest']; return 0.00; }
    
    private function extractObs($data) { 
        $base = $this->getBaseNode($data); 
        $obs = '';
        if ($base && isset($base['compl']['xObs'])) $obs .= (string) $base['compl']['xObs']; 
        if ($base && isset($base['compl']['ObsCont'])) {
            $obsCont = $base['compl']['ObsCont'];
            if (isset($obsCont['xTexto'])) $obsCont = [$obsCont];
            foreach($obsCont as $o) { if (isset($o['xTexto'])) $obs .= " | " . (string)$o['xTexto']; }
        }
        return $obs;
    }

    private function extractTipoCTe($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; return '0'; }
    
    private function extractCteKey($data) {
        if (isset($data['CTe']['infCte']['@attributes']['Id'])) return str_replace('CTe', '', $data['CTe']['infCte']['@attributes']['Id']);
        if (isset($data['infCte']['@attributes']['Id'])) return str_replace('CTe', '', $data['infCte']['@attributes']['Id']);
        return null;
    }

    private function extractChaveComplementada($data) {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCteComp']['chCTe'])) return (string) $base['infCteComp']['chCTe'];
        return null;
    }

    private function extractNfe($data) { 
        $base = $this->getBaseNode($data); 
        if ($base && isset($base['infCTeNorm']['infDoc']['infNFe'])) { 
            $nfe = $base['infCTeNorm']['infDoc']['infNFe']; 
            if (isset($nfe['chave'])) return (string) $nfe['chave']; 
            if (is_array($nfe) && isset($nfe[0]['chave'])) return (string) $nfe[0]['chave']; 
        } 
        return 'N/A';
    }

    private function extractProduto($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['proPred'])) { $prod = $base['infCTeNorm']['infCarga']['proPred']; if (is_array($prod)) return implode(" ", $prod); return (string) $prod; } return 'N/A'; }
    private function extractDataEmissao($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['dhEmi'])) return substr((string) $base['ide']['dhEmi'], 0, 10); return null; }
    
    private function extractTipoOperacao($observacoes, $tipoCTe) { 
        if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) return 'Devolução'; 
        if (str_contains($observacoes, 'REENTREGA')) return 'Reentrega'; 
        if ($tipoCTe == '1' || str_contains($observacoes, 'COMPL')) return 'Complemento'; 
        return 'Entrega'; 
    }
}
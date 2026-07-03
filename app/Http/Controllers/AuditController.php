<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RegiaoFrete;
use App\Models\Frete;
use App\Models\Faturamento;
use App\Services\CalculadoraFreteService; 
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

    public function processar(Request $request)
    {
        try {
            if (!$request->hasFile('xml_files')) {
                throw new Exception("Nenhum ficheiro recebido.");
            }

            $resultados = [];
            $errosDetetive = [];

            foreach ($request->file('xml_files') as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xml') continue;

                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
                $cidadeDestino = $this->extractCity($data);
                $valorNF = $this->extractInvoiceValue($data);
                $valorCobradoE4log = $this->extractFreightValue($data);
                
                $observacoesTexto = strtoupper($this->extractObs($data));
                $tipoCTe = $this->extractTipoCTe($data);
                $dataEmissao = $this->extractDataEmissao($data);
                $nfeChave = $this->extractNfe($data);
                $produto = $this->extractProduto($data);
                $tipoOperacao = $this->extractTipoOperacao($observacoesTexto, $tipoCTe);
                
                if (!$cidadeDestino) continue;

                // 1. O CÉREBRO: Descobrir o De/Para da Cidade
                $mapaGeografico = RegiaoFrete::where('cidade', $cidadeDestino)->first();

                if (!$mapaGeografico) {
                    $errosDetetive[] = "[{$cidadeDestino} não mapeada nas planilhas]"; 
                    continue; 
                }

                // 2. Extração de Taxas Extras e Verificação de TDE
                $taxasExtrasSomadas = 0;
                $teveTdeOuRural = false;
                if ($tipoCTe === '0') {
                    $componentesXML = $this->extractExtraFees($data);
                    foreach ($componentesXML as $nome => $valor) {
                        if (str_contains($nome, 'FRETE') || str_contains($nome, 'PESO') || str_contains($nome, 'VALOR')) continue;
                        if (str_contains($nome, 'TDE') || str_contains($nome, 'DIFICULDADE') || str_contains($nome, 'RURAL')) {
                            $teveTdeOuRural = true;
                        } else {
                            $taxasExtrasSomadas += $valor;
                        }
                    }
                    if (!$teveTdeOuRural && (str_contains($observacoesTexto, 'TDE') || str_contains($observacoesTexto, 'RURAL'))) {
                        $teveTdeOuRural = true;
                    }
                } else {
                     if (str_contains($observacoesTexto, 'TDE') || str_contains($observacoesTexto, 'RURAL')) {
                         $teveTdeOuRural = true;
                     } else {
                         $taxasExtrasSomadas = $valorCobradoE4log; 
                     }
                }

                // ==========================================
                // AUDITORIA E4LOG (Tabela Fretes)
                // ==========================================
                $freteCustoFinal = 0;
                if ($mapaGeografico->regiao_e4log) {
                    $custoE4log = CalculadoraFreteService::calcularE4log($mapaGeografico->regiao_e4log, $valorNF, $teveTdeOuRural, $taxasExtrasSomadas);
                    
                    if ($tipoCTe !== '0') {
                        $custoE4log['total'] = $valorCobradoE4log; 
                    }

                    $diferenca = $valorCobradoE4log - $custoE4log['total'];
                    $isCorreto = abs($diferenca) <= 0.50; 

                    Frete::updateOrCreate(
                        ['arquivo' => $nomeArquivo], 
                        [
                            'fechamento_periodo_id' => $request->input('fechamento_id'),
                            'destino' => Str::limit($cidadeDestino, 150, ''),
                            'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                            'data_emissao' => $dataEmissao,
                            'valorNF' => $valorNF,
                            'fixoRegra' => $custoE4log['frete_base'], 
                            'percentualRegra' => 0, 
                            'adValoremCalculado' => 0, 
                            'freteBaseCalculado' => $custoE4log['frete_base'],
                            'taxasExtras' => $custoE4log['extras'],
                            'temTde' => $teveTdeOuRural,
                            'tdeCalculado' => $custoE4log['tde'],
                            'cobrado' => round($valorCobradoE4log, 2),
                            'correto' => round($custoE4log['total'], 2),
                            'diferenca' => round($diferenca, 2),
                            'is_correto' => $isCorreto,
                            'regra' => 'Região ' . $mapaGeografico->regiao_e4log
                        ]
                    );

                    $freteCustoFinal = $custoE4log['total'];
                }

                // ==========================================
                // RECEITA BWT SOLFÁCIL (Tabela Faturamentos)
                // ==========================================
                if ($mapaGeografico->regiao_solfacil) {
                    $receitaSolfacil = CalculadoraFreteService::calcularSolfacil($mapaGeografico->regiao_solfacil, $valorNF, $teveTdeOuRural, $taxasExtrasSomadas);
                    
                    $receitaFinal = $tipoCTe !== '0' ? $valorCobradoE4log : $receitaSolfacil['total']; 

                    Faturamento::updateOrCreate(
                        ['arquivo' => $nomeArquivo], 
                        [
                            'fechamento_periodo_id' => $request->input('fechamento_id'),
                            'destino' => Str::limit($cidadeDestino, 150, ''),
                            'regra' => 'Região ' . $mapaGeografico->regiao_solfacil,
                            'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                            'tipo_cte' => Str::limit($tipoOperacao, 100, ''), // FIX PARA O ERRO 1364 (Tabela Legado)
                            'data_emissao' => $dataEmissao,
                            'nfe_chave' => Str::limit($nfeChave, 250, ''),
                            'produto' => Str::limit($produto, 250, ''),
                            'valor_carga' => $valorNF,
                            'custo_frete_base' => $custoE4log['frete_base'] ?? 0,
                            'custo_tde' => $custoE4log['tde'] ?? 0,
                            'custo_total' => $freteCustoFinal,
                            'receita_frete_base' => $receitaSolfacil['frete_base'],
                            'receita_tde' => $receitaSolfacil['tde'],
                            'receita_icms' => $receitaSolfacil['icms'],
                            'receita_teorica' => round($receitaFinal, 2),
                            'receita_real' => round($receitaFinal, 2), 
                            'lucro' => round($receitaFinal - $freteCustoFinal, 2)
                        ]
                    );
                }

                $resultados[] = $nomeArquivo;
            }

            if (!empty($errosDetetive)) {
                Log::warning("XMLs descartados: " . implode(" | ", array_unique($errosDetetive)));
            }

            if (empty($resultados)) {
                $motivo = empty($errosDetetive) ? "Nenhum XML de entrega válido." : implode(" | ", array_unique($errosDetetive));
                throw new Exception($motivo);
            }

            return response()->json(['success' => true]);

        } catch (Throwable $e) {
            return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422);
        }
    }

    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga']; return 0.00; }
    private function extractFreightValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest']; return 0.00; }
    private function extractExtraFees($data) { $base = $this->getBaseNode($data); $fees = []; if ($base && isset($base['vPrest']['Comp'])) { $comps = $base['vPrest']['Comp']; if (isset($comps['xNome'])) $comps = [$comps]; foreach ($comps as $comp) { if (isset($comp['xNome']) && isset($comp['vComp'])) { $nome = strtoupper(trim((string)$comp['xNome'])); $fees[$nome] = (float)$comp['vComp']; } } } return $fees; }
    private function extractObs($data) { $base = $this->getBaseNode($data); if ($base && isset($base['compl']['xObs'])) return (string) $base['compl']['xObs']; return ''; }
    private function extractTipoCTe($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; return '0'; }
    private function extractNfe($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infDoc']['infNFe'])) { $nfe = $base['infCTeNorm']['infDoc']['infNFe']; if (isset($nfe['chave'])) return (string) $nfe['chave']; if (is_array($nfe) && isset($nfe[0]['chave'])) return (string) $nfe[0]['chave']; } return 'N/A'; }
    private function extractProduto($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['proPred'])) { $prod = $base['infCTeNorm']['infCarga']['proPred']; if (is_array($prod)) return implode(" ", $prod); return (string) $prod; } return 'N/A'; }
    private function extractDataEmissao($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['dhEmi'])) return substr((string) $base['ide']['dhEmi'], 0, 10); return null; }
    private function extractTipoOperacao($observacoes, $tipoCTe) { if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) return 'Devolução'; if (str_contains($observacoes, 'REENTREGA')) return 'Reentrega'; if ($tipoCTe == '1') return 'Complemento'; return 'Entrega'; }
}
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
        // 1. Pega os dados ACUMULATIVOS do sistema
        $fretes = Frete::orderBy('id', 'desc')->get();
        $faturamentos = Faturamento::all();

        // 2. Calcula os totais REAIS para alimentar o Simulador War Room
        $faturamentoTotal = $faturamentos->sum('receita_real');
        $custoTotal = $fretes->sum('cobrado');
        $lucroAtual = $faturamentoTotal - $custoTotal;
        $valorNFTotal = $fretes->sum('valorNF') > 0 ? $fretes->sum('valorNF') : 1; // Evita divisão por zero

        $resumo = [
            'faturamento_total' => $faturamentoTotal,
            'custo_total' => $custoTotal,
            'lucro_atual' => $lucroAtual,
            'valor_nf_total' => $valorNFTotal,
            'qtd_notas' => $fretes->count(),
        ];

        // 3. Gera o HEATMAP GEOGRÁFICO cruzando Custo x Receita agrupado por Cidade
        $heatmapData = $fretes->groupBy('destino')->map(function ($grupo, $cidade) use ($faturamentos) {
            $custo = $grupo->sum('cobrado');
            $faturamentoCidade = $faturamentos->where('destino', $cidade)->sum('receita_real');
            $lucro = $faturamentoCidade - $custo;
            
            // Lógica Térmica: Define a cor do card na interface
            $status = 'ok';
            if ($lucro < 0) { $status = 'perigo'; } // Vermelho (Prejuízo)
            elseif ($lucro >= 0 && $lucro < 100) { $status = 'alerta'; } // Amarelo (Margem espremida)
            else { $status = 'lucro'; } // Verde (Lucro Saudável)
            
            return [
                'cidade' => empty($cidade) ? 'DESCONHECIDO' : $cidade,
                'notas' => $grupo->count(),
                'faturamento' => $faturamentoCidade,
                'custo' => $custo,
                'status' => $status
            ];
        })->sortBy('status')->values(); // Ordena para mostrar os prejuízos primeiro

        // 4. Mantém a lista de divergências pontuais
        $fretesDivergentes = $fretes->where('is_correto', false)->values();

        return Inertia::render('Auditoria/Index', [
            'fretesProcessados' => $fretes, // Mantido por segurança para o seu código base
            'resumo' => $resumo,
            'heatmapData' => $heatmapData,
            'fretesDivergentes' => $fretesDivergentes
        ]);
    }

    // Rota que abre a janela de configuração das Regras Base
    public function regras()
    {
        return Inertia::render('Auditoria/Regras');
    }

    // =========================================================================
    // SEU CÓDIGO ORIGINAL INTACTO ABAIXO DESTA LINHA
    // =========================================================================

    public function processarCusto(Request $request) { return $this->processarLote($request, 'custo'); }
    public function processarReceita(Request $request) { return $this->processarLote($request, 'receita'); }

    private function processarLote(Request $request, $tipoProcessamento)
    {
        try {
            if (!$request->hasFile('xml_files')) throw new Exception("Nenhum ficheiro recebido.");

            $resultados = [];

            foreach ($request->file('xml_files') as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xml') continue;

                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $nomeArquivo = Str::limit($file->getClientOriginalName(), 250, '');
                $cidadeDestino = $this->extractCity($data);
                $valorNF = $this->extractInvoiceValue($data);
                $valorCobradoNoXML = $this->extractFreightValue($data);  
                 
                $observacoesTexto = $this->extractObs($data); // TEXTO ORIGINAL DAS OBSERVAÇÕES
                $tipoCTe = $this->extractTipoCTe($data);
                $isComplemento = ($tipoCTe === '1' || str_contains(strtoupper($observacoesTexto), 'COMPL'));
                
                $dataEmissao = $this->extractDataEmissao($data);
                $produto = $this->extractProduto($data);
                $tipoOperacao = $this->extractTipoOperacao(strtoupper($observacoesTexto), $tipoCTe);
                
                $cteChave = $this->extractCteKey($data);
                $chaveComplementada = $this->extractChaveComplementada($data);
                $nfeChave = $this->extractNfe($data); 
                 
                if (!$cidadeDestino) continue;

                $mapaGeografico = RegiaoFrete::where('cidade', $cidadeDestino)->first();
                $teveTde = $this->verificarTde($data, strtoupper($observacoesTexto), $tipoCTe);

                if ($tipoProcessamento === 'custo') {
                    if ($mapaGeografico && $mapaGeografico->regiao_e4log) {
                        $custoE4log = CalculadoraFreteService::calcularE4log($mapaGeografico->regiao_e4log, $valorNF, $teveTde);
                        $regraTexto = 'Região ' . $mapaGeografico->regiao_e4log;
                    } else {
                        $custoE4log = ['total' => 0, 'frete_base' => 0, 'tde' => 0];
                        $regraTexto = '⚠️ CIDADE NÃO MAPEADA';
                    }
                     
                    $custoPresumido = $isComplemento ? $valorCobradoNoXML : $custoE4log['total'];
                    $diferenca = $valorCobradoNoXML - $custoPresumido;

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
                            'valorNF' => $valorNF,
                            'fixoRegra' => $custoE4log['frete_base'], 
                            'percentualRegra' => 0, 
                            'adValoremCalculado' => 0, 
                            'freteBaseCalculado' => $custoE4log['frete_base'],
                            'taxasExtras' => 0, 
                            'temTde' => $teveTde,
                            'tdeCalculado' => $custoE4log['tde'],
                            'cobrado' => round($valorCobradoNoXML, 2),
                            'correto' => round($custoPresumido, 2),
                            'diferenca' => round($diferenca, 2), 
                            'is_correto' => abs($diferenca) <= 0.50,
                            'regra' => $regraTexto,
                            'observacoes' => Str::limit($observacoesTexto, 1000, '') // SALVA AS OBSERVAÇÕES
                        ]
                    );
                } 
                elseif ($tipoProcessamento === 'receita') {
                    if ($mapaGeografico && $mapaGeografico->regiao_solfacil) {
                        $receitaSolfacil = CalculadoraFreteService::calcularSolfacil($mapaGeografico->regiao_solfacil, $valorNF, $teveTde);
                        $regraTexto = 'Região ' . $mapaGeografico->regiao_solfacil;
                    } else {
                        $receitaSolfacil = ['total' => 0, 'frete_base' => 0, 'tde' => 0, 'icms' => 0];
                        $regraTexto = '⚠️ CIDADE NÃO MAPEADA';
                    }

                    $receitaPresumida = $isComplemento ? $valorCobradoNoXML : $receitaSolfacil['total']; 

                    $custoE4logTeorico = 0;
                    if ($mapaGeografico && $mapaGeografico->regiao_e4log) {
                        $cTeorico = CalculadoraFreteService::calcularE4log($mapaGeografico->regiao_e4log, $valorNF, $teveTde);
                        $custoE4logTeorico = $cTeorico['total'];
                    }

                    $compsBwt = $this->extractComponentes($data);
                    $rec_frete = $compsBwt['frete'];
                    $rec_tde = $compsBwt['tde'];
                    $rec_icms = $compsBwt['icms'];

                    if ($rec_frete == 0 && $rec_tde == 0 && $rec_icms == 0) {
                        if ($isComplemento) { $rec_tde = $valorCobradoNoXML; } else { $rec_frete = $valorCobradoNoXML; }
                    }

                    Faturamento::updateOrCreate(
                        ['arquivo' => $nomeArquivo], 
                        [
                            'fechamento_periodo_id' => $request->input('fechamento_id'),
                            'destino' => Str::limit($cidadeDestino, 150, ''),
                            'regra' => $regraTexto,
                            'tipo_operacao' => Str::limit($tipoOperacao, 50, ''),
                            'tipo_cte' => Str::limit($tipoOperacao, 100, ''),
                            'data_emissao' => $dataEmissao,
                            'nfe_chave' => Str::limit($nfeChave, 250, ''),
                            'cte_chave' => $cteChave,
                            'chave_complementada' => $chaveComplementada,
                            'produto' => Str::limit($produto, 250, ''),
                            'valor_carga' => $valorNF,
                            'custo_frete_base' => 0,
                            'custo_tde' => 0,
                            'custo_total' => $custoE4logTeorico, 
                            'receita_frete_base' => round($rec_frete, 2),
                            'receita_tde' => round($rec_tde, 2),
                            'receita_icms' => round($rec_icms, 2),
                            'receita_teorica' => round($receitaPresumida, 2), 
                            'receita_real' => round($valorCobradoNoXML, 2), 
                            'lucro' => round($valorCobradoNoXML - $custoE4logTeorico, 2),
                            'observacoes' => Str::limit($observacoesTexto, 1000, '') // SALVA AS OBSERVAÇÕES
                        ]
                    );
                }
                $resultados[] = $nomeArquivo;
            }
            if (empty($resultados)) throw new Exception("Nenhum XML foi processado.");
            return response()->json(['success' => true]);

        } catch (Throwable $e) { return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422); }
    }

    private function verificarTde($data, $obs, $tipoCTe) {
        if (str_contains($obs, 'TDE') || str_contains($obs, 'RURAL')) return true;
        if ($tipoCTe === '0') {
            $base = $this->getBaseNode($data);
            if ($base && isset($base['vPrest']['Comp'])) {
                $comps = $base['vPrest']['Comp'];
                if (isset($comps['xNome'])) $comps = [$comps];
                foreach ($comps as $c) {
                    $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                    if (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE')) return true;
                }
            }
        }
        return false;
    }

    private function extractComponentes($data) {
        $base = $this->getBaseNode($data);
        $frete = 0; $tde = 0; $icms = 0;

        if ($base && isset($base['vPrest']['Comp'])) {
            $comps = $base['vPrest']['Comp'];
            if (isset($comps['xNome'])) $comps = [$comps];
            foreach ($comps as $c) {
                $nome = strtoupper(trim((string)($c['xNome'] ?? '')));
                $valor = (float)($c['vComp'] ?? 0);
                
                if (str_contains($nome, 'FRETE') || str_contains($nome, 'PESO')) { $frete += $valor; } 
                elseif (str_contains($nome, 'TDE') || str_contains($nome, 'RURAL') || str_contains($nome, 'DIFICULDADE') || str_contains($nome, 'ENTREGA')) { $tde += $valor; } 
                elseif (str_contains($nome, 'IMP') || str_contains($nome, 'ICMS') || str_contains($nome, 'TRIBUTO')) { $icms += $valor; } 
                else { if (!str_contains($nome, 'PEDAGIO') && !str_contains($nome, 'GRIS')) { $frete += $valor; } }
            }
        }
        return ['frete' => $frete, 'tde' => $tde, 'icms' => $icms];
    }

    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga']; return 0.00; }
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
    private function extractTipoOperacao($observacoes, $tipoCTe) { if (str_contains($observacoes, 'DEVOLUCAO') || str_contains($observacoes, 'RETORNO')) return 'Devolução'; if (str_contains($observacoes, 'REENTREGA')) return 'Reentrega'; if ($tipoCTe == '1' || str_contains($observacoes, 'COMPL')) return 'Complemento'; return 'Entrega'; }
}
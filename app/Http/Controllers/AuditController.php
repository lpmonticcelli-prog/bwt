<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\City;
use App\Models\Frete;
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
            \DB::connection()->getPdo();

            if (!$request->hasFile('xml_files')) {
                throw new Exception("Nenhum ficheiro recebido.");
            }

            $request->validate([
                'xml_files' => 'required|array',
            ]);

            $resultados = [];
            $errosDetetive = [];

            foreach ($request->file('xml_files') as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'xml') continue;

                $xmlContent = file_get_contents($file->getPathname());
                $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
                $xmlObj = simplexml_load_string($xmlContent);
                $data = json_decode(json_encode($xmlObj), true);

                $cidadeDestino = $this->extractCity($data);
                $valorNF = $this->extractInvoiceValue($data);
                $valorCobradoE4log = $this->extractFreightValue($data);
                $observacoesTexto = strtoupper($this->extractObs($data));
                $tipoCTe = $this->extractTipoCTe($data);
                
                if (!$cidadeDestino) continue;

                $city = City::where('name', $cidadeDestino)->with(['regions' => function($q) {
                    $q->where('context', 'e4log');
                }, 'regions.pricingRules'])->first();

                // Regista as cidades não encontradas para o ficheiro de log
                if (!$city) { $errosDetetive[] = "[{$cidadeDestino} não cadastrada]"; continue; }
                if ($city->regions->isEmpty()) { $errosDetetive[] = "[{$cidadeDestino} sem Região E4LOG]"; continue; }

                $region = $city->regions->first();
                $rule = $region->pricingRules->first();

                if (!$rule) { $errosDetetive[] = "[A Região de {$cidadeDestino} está sem preço]"; continue; }

                $taxaFixa = 0;
                $porcentagem = 0;
                $fretePorcentagem = 0;
                $freteBaseCalculado = 0;
                $valorTDECalculado = 0;
                $taxasExtrasSomadas = 0;
                $teveTdeOuRural = false;

                // LÓGICA PARA CTE NORMAL (0)
                if ($tipoCTe === '0') {
                    $taxaFixa = (float) $rule->fixed_value;
                    $porcentagem = (float) $rule->excess_percentage / 100;
                    $fretePorcentagem = $valorNF * $porcentagem;
                    
                    // Compara o mínimo da tabela com o % ad valorem
                    $freteBaseCalculado = max($taxaFixa, $fretePorcentagem);

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

                    if ($teveTdeOuRural) {
                        $tdePercent = $rule->tde_percentage ?? 20; 
                        // REGRA APLICADA: Mínimo 160 reais ou 20% sob o valor do frete
                        $valorTDECalculado = max(160.00, $freteBaseCalculado * ($tdePercent / 100));
                    }

                    $freteTotalFinalCalculado = $freteBaseCalculado + $valorTDECalculado + $taxasExtrasSomadas;
                } 
                // LÓGICA PARA CTE COMPLEMENTAR (1) OU OUTROS (Reentrega, Devolução)
                else {
                     $freteTotalFinalCalculado = $valorCobradoE4log; 
                     if (str_contains($observacoesTexto, 'TDE') || str_contains($observacoesTexto, 'RURAL')) {
                         $teveTdeOuRural = true;
                         $valorTDECalculado = $valorCobradoE4log;
                     } else {
                         $taxasExtrasSomadas = $valorCobradoE4log;
                     }
                }
                
                $freteTotalFinalCalculado = round($freteTotalFinalCalculado, 2);
                $valorCobradoE4log = round($valorCobradoE4log, 2);
                $diferenca = $valorCobradoE4log - $freteTotalFinalCalculado;
                
                $isCorreto = abs($diferenca) <= 0.50;

                $freteSalvo = Frete::updateOrCreate(
                    ['arquivo' => Str::limit($file->getClientOriginalName(), 250, '')], 
                    [
                        'destino' => Str::limit($cidadeDestino, 150, ''),
                        'valorNF' => $valorNF,
                        'fixoRegra' => $taxaFixa,
                        'percentualRegra' => $rule->excess_percentage,
                        'adValoremCalculado' => $fretePorcentagem,
                        'freteBaseCalculado' => $freteBaseCalculado,
                        'taxasExtras' => $taxasExtrasSomadas,
                        'temTde' => $teveTdeOuRural,
                        'tdeCalculado' => $valorTDECalculado,
                        'cobrado' => $valorCobradoE4log,
                        'correto' => $freteTotalFinalCalculado,
                        'diferenca' => round($diferenca, 2),
                        'is_correto' => $isCorreto,
                        'regra' => Str::limit($region->name, 100, '')
                    ]
                );

                $resultados[] = $freteSalvo;
            }

            // Grava as cidades descartadas no log silenciosamente
            if (!empty($errosDetetive)) {
                Log::warning("Auditoria E4LOG - Lote parcial. Descartes: " . implode(" | ", array_unique($errosDetetive)));
            }

            if (empty($resultados)) {
                $motivo = empty($errosDetetive) ? "Nenhum ficheiro XML válido encontrado." : implode(" | ", array_unique($errosDetetive));
                throw new Exception($motivo);
            }

            // Responde para o Vue de forma silenciosa para não quebrar a fila
            if ($request->wantsJson()) {
                return response()->json(['success' => true]);
            }
            return redirect()->route('auditoria.index');

        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'ERRO: ' . $e->getMessage()], 422);
            }
            return back()->withErrors(['erro_fatal' => 'DETALHE DO ERRO: ' . $e->getMessage()]);
        }
    }

    private function getBaseNode($data) { if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte']; if (isset($data['infCte'])) return $data['infCte']; return null; }
    private function extractCity($data) { $base = $this->getBaseNode($data); if ($base && isset($base['dest']['enderDest']['xMun'])) return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' ')); return null; }
    private function extractInvoiceValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga']; if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga']; return 0.00; }
    private function extractFreightValue($data) { $base = $this->getBaseNode($data); if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest']; return 0.00; }
    private function extractExtraFees($data) { $base = $this->getBaseNode($data); $fees = []; if ($base && isset($base['vPrest']['Comp'])) { $comps = $base['vPrest']['Comp']; if (isset($comps['xNome'])) $comps = [$comps]; foreach ($comps as $comp) { if (isset($comp['xNome']) && isset($comp['vComp'])) { $nome = strtoupper(trim((string)$comp['xNome'])); $fees[$nome] = (float)$comp['vComp']; } } } return $fees; }
    private function extractObs($data) { $base = $this->getBaseNode($data); if ($base && isset($base['compl']['xObs'])) return (string) $base['compl']['xObs']; return ''; }
    private function extractTipoCTe($data) { $base = $this->getBaseNode($data); if ($base && isset($base['ide']['tpCTe'])) return (string) $base['ide']['tpCTe']; return '0'; }
}
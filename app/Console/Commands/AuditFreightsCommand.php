<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\City;
use Illuminate\Support\Str;

class AuditFreightsCommand extends Command
{
    protected $signature = 'audit:freights';
    protected $description = 'Audita os XMLs cruzando a regra Fixo vs % e validando TDE/Rural nas tags XML';

    public function handle()
    {
        $xmlFolder = storage_path('app/xmls');

        if (!File::isDirectory($xmlFolder)) {
            $this->error("A pasta {$xmlFolder} não existe.");
            return;
        }

        $files = File::files($xmlFolder);

        if (empty($files)) {
            $this->error("Nenhum arquivo XML encontrado.");
            return;
        }

        $this->info("Iniciando auditoria avançada com TDE em " . count($files) . " arquivos...\n");

        $acertos = 0;
        $divergencias = 0;

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'xml') continue;

            $xmlContent = File::get($file->getPathname());
            $xmlContent = str_replace(['xmlns=', 'cte:', 'nfe:'], ['ns=', '', ''], $xmlContent);
            $xmlObj = simplexml_load_string($xmlContent);
            $data = json_decode(json_encode($xmlObj), true);

            $cidadeDestino = $this->extractCity($data);
            $valorNF = $this->extractInvoiceValue($data);
            $valorCobradoE4log = $this->extractFreightValue($data);
            
            if (!$cidadeDestino) continue;

            $city = City::where('name', $cidadeDestino)->with(['regions' => function($q) {
                $q->where('context', 'e4log');
            }, 'regions.pricingRules'])->first();

            if (!$city || $city->regions->isEmpty()) {
                $this->error("-> SEM REGRA: A cidade '{$cidadeDestino}' não está mapeada. (" . $file->getFilename() . ")");
                continue;
            }

            $region = $city->regions->first();
            $rule = $region->pricingRules->first();

            // ==========================================
            // 1. CÁLCULO DO FRETE BASE (Fixo vs % Ad Valorem)
            // ==========================================
            $taxaFixa = (float) $rule->fixed_value;
            $porcentagem = (float) $rule->excess_percentage / 100;
            $fretePorcentagem = $valorNF * $porcentagem;
            $freteBaseCalculado = max($taxaFixa, $fretePorcentagem);

            // ==========================================
            // 2. EXTRAÇÃO E CÁLCULO DE TAXAS EXTRAS (TDE/RURAL)
            // ==========================================
            $componentesXML = $this->extractExtraFees($data);
            $taxasExtrasSomadas = 0;
            $teveTdeOuRural = false;
            $nomeTaxaExtra = '';

            foreach ($componentesXML as $nome => $valor) {
                // Ignora o componente do frete principal, pois nós mesmos já calculamos ele
                if (str_contains($nome, 'FRETE') || str_contains($nome, 'PESO') || str_contains($nome, 'VALOR')) {
                    continue;
                }

                if (str_contains($nome, 'TDE') || str_contains($nome, 'DIFICULDADE') || str_contains($nome, 'RURAL')) {
                    $teveTdeOuRural = true;
                    $nomeTaxaExtra = $nome; // Guarda o nome para avisar no terminal
                } else {
                    // Outras taxas como Pedágio, GRIS, Despacho (Apenas somamos para bater o total final)
                    $taxasExtrasSomadas += $valor;
                }
            }

            // O nosso cálculo mestre da TDE
            $valorTDECalculado = 0;
            if ($teveTdeOuRural) {
                $tdePercent = $rule->tde_percentage ?? 20; 
                
                // REGRA ATUALIZADA: Porcentagem do Frete Base + R$ 160,00 Fixos
                $valorTDECalculado = ($freteBaseCalculado * ($tdePercent / 100)) + 160.00;
            }

            // ==========================================
            // 3. FRETE TOTAL FINAL
            // ==========================================
            $freteTotalFinalCalculado = $freteBaseCalculado + $valorTDECalculado + $taxasExtrasSomadas;
            
            $freteTotalFinalCalculado = round($freteTotalFinalCalculado, 2);
            $valorCobradoE4log = round($valorCobradoE4log, 2);

            // ==========================================
            // VEREDITO
            // ==========================================
            $alertaTDE = $teveTdeOuRural ? " <fg=yellow>(Com {$nomeTaxaExtra})</>" : "";

            // Margem de erro de arredondamento de até 50 centavos
            if (abs($valorCobradoE4log - $freteTotalFinalCalculado) <= 0.50) { 
                $this->line("<fg=green>✔ OK:</> " . $file->getFilename() . " | Destino: {$cidadeDestino} | Cobrado: R$ " . number_format($valorCobradoE4log, 2, ',', '.') . $alertaTDE);
                $acertos++;
            } else {
                $this->line("<fg=red>✖ DIVERGÊNCIA:</> " . $file->getFilename() . " | Destino: {$cidadeDestino}" . $alertaTDE);
                $this->line("   -> Cobrado: R$ " . number_format($valorCobradoE4log, 2, ',', '.') . " | Correto: R$ " . number_format($freteTotalFinalCalculado, 2, ',', '.') . " | Regra: {$region->name}");
                $divergencias++;
            }
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("RESULTADO FINAL DA AUDITORIA:");
        $this->info("✔ Fretes Corretos: {$acertos}");
        $this->info("✖ Fretes Divergentes: {$divergencias}");
        $this->info("========================================");
    }

    // ==========================================
    // EXTRATORES DE XML
    // ==========================================
    
    private function getBaseNode($data)
    {
        if (isset($data['CTe']['infCte'])) return $data['CTe']['infCte'];
        if (isset($data['infCte'])) return $data['infCte'];
        return null;
    }

    private function extractCity($data)
    {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['dest']['enderDest']['xMun'])) {
            return strtoupper(Str::slug((string) $base['dest']['enderDest']['xMun'], ' '));
        }
        return null;
    }

    private function extractInvoiceValue($data)
    {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['infCTeNorm']['infCarga']['vCarga'])) return (float) $base['infCTeNorm']['infCarga']['vCarga'];
        if ($base && isset($base['infCarga']['vCarga'])) return (float) $base['infCarga']['vCarga'];
        return 0.00;
    }

    private function extractFreightValue($data)
    {
        $base = $this->getBaseNode($data);
        if ($base && isset($base['vPrest']['vTPrest'])) return (float) $base['vPrest']['vTPrest'];
        return 0.00;
    }

    private function extractExtraFees($data)
    {
        $base = $this->getBaseNode($data);
        $fees = [];
        
        if ($base && isset($base['vPrest']['Comp'])) {
            $comps = $base['vPrest']['Comp'];
            if (isset($comps['xNome'])) $comps = [$comps];
            
            foreach ($comps as $comp) {
                if (isset($comp['xNome']) && isset($comp['vComp'])) {
                    $nome = strtoupper(trim((string)$comp['xNome']));
                    $valor = (float)$comp['vComp'];
                    $fees[$nome] = $valor;
                }
            }
        }
        return $fees;
    }
}
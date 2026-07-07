<?php

namespace App\Services;

use App\Models\PricingRule;

class CalculadoraReceitaService
{
    /**
     * Calcula o valor que a BWT deveria faturar para a Sol Fácil (Receita Presumida)
     */
    public static function calcularSolfacil(PricingRule $rule, float $valorCarga, bool $temTde = false, string $tipoOperacao = 'Entrega'): array
    {
        // 1. Regra de Complemento Isolado
        if ($tipoOperacao === 'Complemento') {
            $tde = 0;
            if ($temTde) {
                // Se o complemento for apenas TDE, calcula o valor percentual da TDE sobre o frete base teórico
                $freteBaseTeorico = max((float)$rule->fixed_value, ($valorCarga * ((float)$rule->excess_percentage / 100)));
                $tde = max((float)($rule->tde_min_value ?? 200.00), $freteBaseTeorico * ((float)($rule->tde_percentage ?? 30) / 100));
            }
            // Não aplica ICMS isolado num documento de complemento simples neste escopo
            return [
                'frete_base' => 0,
                'tde' => $tde,
                'icms' => 0,
                'total' => $tde
            ];
        }

        // 2. Regra de Frete Normal (Cálculo MECE)
        $receitaFixo = (float) $rule->fixed_value;
        $receitaPct = (float) $rule->excess_percentage / 100;
        
        // Frete Base
        $receitaFretePct = $valorCarga * $receitaPct;
        $receitaFreteBase = max($receitaFixo, $receitaFretePct);

        // TDE (Se aplicável)
        $receitaTde = 0;
        if ($temTde) {
            $tdeMinBwt = (float) ($rule->tde_min_value ?? 200.00);
            $tdePercentBwt = (float) ($rule->tde_percentage ?? 30);
            $receitaTde = max($tdeMinBwt, $receitaFreteBase * ($tdePercentBwt / 100));
        }

        // Soma sem Imposto
        $receitaSemImposto = $receitaFreteBase + $receitaTde;

        // Projeção do Imposto (Gross-up de ICMS)
        $icmsPercent = (float) ($rule->icms_percentage ?? 12);
        $fatorIcms = 1 - ($icmsPercent / 100); 
        
        $receitaTeoricaTotal = $receitaSemImposto > 0 ? ($receitaSemImposto / $fatorIcms) : 0; 
        $icmsCalculado = $receitaTeoricaTotal - $receitaSemImposto;

        return [
            'frete_base' => round($receitaFreteBase, 2),
            'tde' => round($receitaTde, 2),
            'icms' => round($icmsCalculado, 2),
            'total' => round($receitaTeoricaTotal, 2)
        ];
    }
}
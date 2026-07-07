<?php

namespace App\Services;

use App\Models\PricingRule;

class CalculadoraCustoService
{
    /**
     * Calcula o valor exato que a E4LOG tem o direito de cobrar por tabela (Custo Aprovado)
     */
    public static function calcularE4log(PricingRule $rule, float $valorCarga, bool $temTde = false, string $tipoOperacao = 'Entrega'): array
    {
        // 1. Regra para CT-e Complementar Exclusivo
        if ($tipoOperacao === 'Complemento') {
            $tde = 0;
            if ($temTde) {
                // Num complemento de TDE, calculamos quanto seria a TDE baseada no frete teórico
                $freteBaseTeorico = max((float)$rule->fixed_value, ($valorCarga * ((float)$rule->excess_percentage / 100)));
                $tdePercentE4log = (float)($rule->tde_percentage ?? 20);
                $tde = max(160.00, $freteBaseTeorico * ($tdePercentE4log / 100));
            }
            
            return [
                'frete_base' => 0,
                'tde' => round($tde, 2),
                'total' => round($tde, 2)
            ];
        }

        // 2. Regra de Frete Normal (MECE)
        $custoFixo = (float) $rule->fixed_value;
        $custoPct = (float) $rule->excess_percentage / 100;
        
        // Frete Base
        $custoFreteBase = max($custoFixo, ($valorCarga * $custoPct));

        // TDE
        $custoTde = 0;
        if ($temTde) {
            $tdePercentE4log = (float) ($rule->tde_percentage ?? 20);
            $custoTde = max(160.00, $custoFreteBase * ($tdePercentE4log / 100));
        }

        // Soma Total Aprovada
        $custoTotal = $custoFreteBase + $custoTde;

        return [
            'frete_base' => round($custoFreteBase, 2),
            'tde' => round($custoTde, 2),
            'total' => round($custoTotal, 2)
        ];
    }
}
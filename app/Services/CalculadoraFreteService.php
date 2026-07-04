<?php

namespace App\Services;

class CalculadoraFreteService
{
    private const E4LOG_REGRAS = [
        1 => ['minimo' => 200.00, 'percentual_nf' => 0.02],
        2 => ['minimo' => 250.00, 'percentual_nf' => 0.03],
        3 => ['minimo' => 350.00, 'percentual_nf' => 0.03],
        4 => ['minimo' => 420.00, 'percentual_nf' => 0.04],
    ];

    private const BWT_REGRAS = [
        1 => ['minimo' => 350.00, 'percentual_nf' => 0.03],
        2 => ['minimo' => 350.00, 'percentual_nf' => 0.04],
        3 => ['minimo' => 550.00, 'percentual_nf' => 0.06],
        4 => ['minimo' => 600.00, 'percentual_nf' => 0.03],
    ];

    public static function calcularE4log($regiao, float $valorNf, bool $temTde)
    {
        $regra = self::E4LOG_REGRAS[$regiao] ?? self::E4LOG_REGRAS[1];
        $freteBase = max($valorNf * $regra['percentual_nf'], $regra['minimo']);

        $valorTde = $temTde ? max(160.00, $freteBase * 0.20) : 0;

        return [
            'frete_base' => round($freteBase, 2),
            'tde' => round($valorTde, 2),
            'total' => round($freteBase + $valorTde, 2)
        ];
    }

    public static function calcularSolfacil($regiao, float $valorNf, bool $temTde)
    {
        $regra = self::BWT_REGRAS[$regiao] ?? self::BWT_REGRAS[1];
        $freteBase = max($valorNf * $regra['percentual_nf'], $regra['minimo']);

        $valorTde = $temTde ? max(200.00, $freteBase * 0.30) : 0;

        $subtotalSemImposto = $freteBase + $valorTde;
        $totalFaturado = $subtotalSemImposto / (1 - 0.12);
        $icms = $totalFaturado - $subtotalSemImposto;

        return [
            'frete_base' => round($freteBase, 2),
            'tde' => round($valorTde, 2),
            'icms' => round($icms, 2),
            'total' => round($totalFaturado, 2)
        ];
    }
}
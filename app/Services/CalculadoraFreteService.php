<?php

namespace App\Services;

class CalculadoraFreteService
{
    /**
     * Tabelas de Custo E4LOG
     */
    private const E4LOG_REGRAS = [
        1 => ['minimo' => 200, 'percentual_nf' => 0.02],
        2 => ['minimo' => 250, 'percentual_nf' => 0.03],
        3 => ['minimo' => 350, 'percentual_nf' => 0.03],
        4 => ['minimo' => 420, 'percentual_nf' => 0.04],
    ];

    /**
     * Tabelas de Faturamento BWT -> Sol Fácil
     */
    private const BWT_REGRAS = [
        1 => ['minimo' => 350, 'percentual_nf' => 0.03],
        2 => ['minimo' => 350, 'percentual_nf' => 0.04],
        3 => ['minimo' => 550, 'percentual_nf' => 0.06],
        4 => ['minimo' => 600, 'percentual_nf' => 0.03],
    ];

    /**
     * Calcula o Custo exato que a E4LOG deve cobrar
     */
    public static function calcularE4log($regiao, float $valorNf, bool $temTde, float $taxasExtras)
    {
        $regra = self::E4LOG_REGRAS[$regiao] ?? self::E4LOG_REGRAS[1];
        
        $freteCalculado = $valorNf * $regra['percentual_nf'];
        $freteBase = max($freteCalculado, $regra['minimo']);

        $valorTde = 0;
        if ($temTde) {
            $tdePercentual = $freteBase * 0.20;
            $valorTde = max(160.00, $tdePercentual);
        }

        return [
            'frete_base' => $freteBase,
            'tde' => $valorTde,
            'extras' => $taxasExtras,
            'total' => $freteBase + $valorTde + $taxasExtras
        ];
    }

    /**
     * Calcula a Receita exata a faturar da Sol Fácil
     */
    public static function calcularSolfacil($regiao, float $valorNf, bool $temTde, float $taxasExtras)
    {
        $regra = self::BWT_REGRAS[$regiao] ?? self::BWT_REGRAS[1];
        
        $freteCalculado = $valorNf * $regra['percentual_nf'];
        $freteBase = max($freteCalculado, $regra['minimo']);

        $valorTde = 0;
        if ($temTde) {
            $tdePercentual = $freteBase * 0.30;
            $valorTde = max(200.00, $tdePercentual);
        }

        $subtotal = $freteBase + $valorTde + $taxasExtras;
        $icms = $subtotal * 0.12;

        return [
            'frete_base' => $freteBase,
            'tde' => $valorTde,
            'icms' => $icms,
            'extras' => $taxasExtras,
            'total' => $subtotal + $icms
        ];
    }
}
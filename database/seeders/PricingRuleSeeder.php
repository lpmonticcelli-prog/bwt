<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Region;
use App\Models\PricingRule;
use App\Models\City;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. CRIA AS REGIÕES E REGRAS E4LOG (CUSTO)
        $e4logRules = [
            '1' => ['fixed_value' => 200.00, 'excess_percentage' => 2.00, 'tde_min_value' => 200.00, 'tde_percentage' => 20.00, 'icms_percentage' => 0.00],
            '2' => ['fixed_value' => 250.00, 'excess_percentage' => 3.00, 'tde_min_value' => 200.00, 'tde_percentage' => 20.00, 'icms_percentage' => 0.00],
            '3' => ['fixed_value' => 350.00, 'excess_percentage' => 3.00, 'tde_min_value' => 200.00, 'tde_percentage' => 20.00, 'icms_percentage' => 0.00],
            '4' => ['fixed_value' => 420.00, 'excess_percentage' => 4.00, 'tde_min_value' => 200.00, 'tde_percentage' => 20.00, 'icms_percentage' => 0.00],
        ];

        foreach ($e4logRules as $num => $rule) {
            $region = Region::firstOrCreate(['name' => "Região $num", 'context' => 'e4log']);
            PricingRule::updateOrCreate(['region_id' => $region->id], $rule);
        }

        // 2. CRIA AS REGIÕES E REGRAS BWT (RECEITA)
        $bwtRules = [
            '1' => ['fixed_value' => 350.00, 'excess_percentage' => 3.00, 'tde_min_value' => 200.00, 'tde_percentage' => 30.00, 'icms_percentage' => 12.00],
            '2' => ['fixed_value' => 350.00, 'excess_percentage' => 4.00, 'tde_min_value' => 200.00, 'tde_percentage' => 30.00, 'icms_percentage' => 12.00],
            '3' => ['fixed_value' => 550.00, 'excess_percentage' => 6.00, 'tde_min_value' => 200.00, 'tde_percentage' => 30.00, 'icms_percentage' => 12.00],
            '4' => ['fixed_value' => 600.00, 'excess_percentage' => 3.00, 'tde_min_value' => 200.00, 'tde_percentage' => 30.00, 'icms_percentage' => 12.00],
        ];

        foreach ($bwtRules as $num => $rule) {
            $region = Region::firstOrCreate(['name' => "Região $num", 'context' => 'bwt']);
            PricingRule::updateOrCreate(['region_id' => $region->id], $rule);
        }

        // 3. INJEÇÃO DIRETA DE CIDADES
        $cidadesTeste = [
            'SAO PAULO' => '1', 'JUNDIAI' => '1', 'INDAIATUBA' => '1', 'CESARIO LANGE' => '1',
            'RIO CLARO' => '1', 'SAO PEDRO' => '1', 'BOITUVA' => '1', 'ITAPETININGA' => '1', 'SALTO' => '1',
            'IPERO' => '2', 'TATUI' => '2', 'PEDRA BELA' => '2', 'BRAGANCA PAULISTA' => '2',
            'LARANJAL PAULISTA' => '3', 'RIBEIRAO DO SUL' => '3', 'ALVARES FLORENCE' => '3', 'MIRA ESTRELA' => '3', 'BURI' => '3',
            'VOTUPORANGA' => '4', 'PORANGABA' => '4', 'JUQUIA' => '4', 'CAPAO BONITO' => '4'
        ];

        foreach ($cidadesTeste as $nomeCidade => $numRegiao) {
            // Removi o 'uf' => 'SP', agora ele só busca/cria pelo nome exato!
            $city = City::firstOrCreate(['name' => $nomeCidade]);
            
            // Vincula a cidade à regra E4LOG
            $regE4log = Region::where('name', "Região $numRegiao")->where('context', 'e4log')->first();
            if ($regE4log) $city->regions()->syncWithoutDetaching([$regE4log->id]);

            // Vincula a cidade à regra BWT
            $regBwt = Region::where('name', "Região $numRegiao")->where('context', 'bwt')->first();
            if ($regBwt) $city->regions()->syncWithoutDetaching([$regBwt->id]);
        }
    }
}
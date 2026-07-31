<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Facades\Schema;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        // Limpeza segura contornando a trava de chaves estrangeiras
        Schema::disableForeignKeyConstraints();
        Budget::truncate();
        BudgetItem::truncate();
        Schema::enableForeignKeyConstraints();

        // Cria a Capa do Orçamento 2026
        $budget = Budget::create([
            'ano' => 2026,
            'versao' => 'Oficial',
            'status' => 'Rascunho',
            'ativo' => true,
        ]);

        // Itens de 2026 (Extraídos e separados do "ano passado")
        $items = [
            // === RECEITAS ===
            [
                'categoria' => 'RECEITAS',
                'nome' => 'Solfácil Distribuidora',
                'valores' => ["1"=>242293,"2"=>294707,"3"=>429290,"4"=>399308,"5"=>363877,"6"=>298704,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'RECEITAS',
                'nome' => 'Empilhadeira',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],

            // === IMPOSTOS ===
            [
                'categoria' => 'IMPOSTOS', 'nome' => 'PIS 0,65%',
                'valores' => ["1"=>1574.90,"2"=>1915.60,"3"=>2757.89,"4"=>2595.50,"5"=>2365.20,"6"=>1941.58,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'IMPOSTOS', 'nome' => 'COFINS 3%',
                'valores' => ["1"=>7268.79,"2"=>8841.21,"3"=>12728.7,"4"=>11979.24,"5"=>10916.31,"6"=>8961.12,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'IMPOSTOS', 'nome' => 'ICMS 10%',
                'valores' => ["1"=>24229.3,"2"=>29470.7,"3"=>42929.0,"4"=>39930.8,"5"=>36387.7,"6"=>29870.4,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'IMPOSTOS', 'nome' => 'IRPJ',
                'valores' => ["1"=>2907.52,"2"=>3536.48,"3"=>5091.48,"4"=>4791.70,"5"=>4366.52,"6"=>3584.45,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'IMPOSTOS', 'nome' => 'CSLL',
                'valores' => ["1"=>2616.76,"2"=>3182.84,"3"=>4582.33,"4"=>4312.53,"5"=>3929.87,"6"=>3226.00,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],

            // === CUSTOS FIXOS ===
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Telefone',
                'valores' => ["1"=>85,"2"=>85,"3"=>85,"4"=>85,"5"=>85,"6"=>85,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Cowork SJC',
                'valores' => ["1"=>259.0,"2"=>259.0,"3"=>259.0,"4"=>264.35,"5"=>269.0,"6"=>269.0,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Cartão de Crédito',
                'valores' => ["1"=>704.23,"2"=>1057.0,"3"=>220.73,"4"=>544.05,"5"=>401.0,"6"=>325.0,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Contabilidade',
                'valores' => ["1"=>989.0,"2"=>1057.0,"3"=>1057.0,"4"=>1078.84,"5"=>1096.9,"6"=>1096.9,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Sistema SSW',
                'valores' => ["1"=>1523.05,"2"=>1625.32,"3"=>1621.0,"4"=>1621.0,"5"=>1621.0,"6"=>1623.16,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Pró-Labore',
                'valores' => ["1"=>24000,"2"=>24000,"3"=>24000,"4"=>24000,"5"=>24000,"6"=>24000,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'INSS Pró-Labore',
                'valores' => ["1"=>470.58,"2"=>502.51,"3"=>502.51,"4"=>502.51,"5"=>502.51,"6"=>502.51,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Vale Refeição (MEI)',
                'valores' => ["1"=>600,"2"=>600,"3"=>600,"4"=>600,"5"=>600,"6"=>600,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Maria Clara (MEI)',
                'valores' => ["1"=>4000,"2"=>4000,"3"=>4000,"4"=>4000,"5"=>4000,"6"=>4000,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],

            // === CUSTOS VARIÁVEIS ===
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Seguro de Carga (Tokio/Sompo)',
                'valores' => ["1"=>2147.6,"2"=>2149.38,"3"=>2151.52,"4"=>2151.52,"5"=>2428.76,"6"=>2927.06,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Fechamento E4LOG',
                'valores' => ["1"=>133022.36,"2"=>90765.24,"3"=>179980.51,"4"=>214326.22,"5"=>260740.7,"6"=>233521.0,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Custo de Coleta',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>16000,"5"=>16000,"6"=>16000,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Comissão Maurício',
                'valores' => ["1"=>6306.96,"2"=>7268.79,"3"=>8541.21,"4"=>12543.73,"5"=>10414.0,"6"=>8961.12,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Despesa de Viagem',
                'valores' => ["1"=>957.03,"2"=>0,"3"=>1341.51,"4"=>469.71,"5"=>739.0,"6"=>326.08,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Gerenciamento Risco',
                'valores' => ["1"=>125.0,"2"=>0,"3"=>0,"4"=>127.5,"5"=>271.25,"6"=>156.45,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Despesa Bancária',
                'valores' => ["1"=>237.98,"2"=>258.65,"3"=>9.0,"4"=>90.38,"5"=>31.2,"6"=>137.98,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Google',
                'valores' => ["1"=>164.79,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>0,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
        ];

        // Salva no banco (Agora com conversão blindada para JSON)
        foreach ($items as $item) {
            $budget->items()->create([
                'categoria' => $item['categoria'],
                'nome' => $item['nome'],
                'valores_mensais' => json_encode($item['valores']), 
                'valores_originais' => json_encode($item['valores']),
            ]);
        }
    }
}
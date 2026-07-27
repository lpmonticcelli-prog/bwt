<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Budget;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cria o "Capa" do Orçamento para o ano em que os dados começam
        $budget = Budget::create([
            'ano' => 2026,
            'versao' => 'Oficial',
            'ativo' => true,
        ]);

        // 2. Prepara os Itens do Orçamento (dados do Excel)
        // Usamos as chaves numéricas de 1 a 12 para representar os meses (Janeiro a Dezembro)
        $items = [
            // --- RECEITAS ---
            [
                'categoria' => 'Receitas',
                'nome' => 'Solfacil Distribuidora',
                'valores_mensais' => ["4" => 310000, "5" => 213285, "6" => 135267, "7" => 151016, "8" => 208420, "9" => 338457]
            ],
            // --- IMPOSTOS ---
            [
                'categoria' => 'Impostos',
                'nome' => 'PIS 0,65%',
                'valores_mensais' => ["4" => 2015, "5" => 1386.35, "6" => 879.23, "7" => 602.16, "8" => 1354.73, "9" => 2222.72]
            ],
            [
                'categoria' => 'Impostos',
                'nome' => 'Cofins 3%',
                'valores_mensais' => ["4" => 9300, "5" => 6398.55, "6" => 4058.01, "7" => 2779.16, "8" => 6252.6, "9" => 10258.71]
            ],
            [
                'categoria' => 'Impostos',
                'nome' => 'ICMS 10%',
                'valores_mensais' => ["4" => 37200, "5" => 21328.5, "6" => 13526.7, "7" => 15101.6, "8" => 20842, "9" => 33845.7]
            ],
            // --- CUSTOS FIXOS ---
            [
                'categoria' => 'Custos Fixos',
                'nome' => 'SSW',
                'valores_mensais' => ["4" => 1450, "5" => 1522.04, "6" => 1518, "7" => 1524.06, "8" => 1518, "9" => 1518]
            ],
            [
                'categoria' => 'Custos Fixos',
                'nome' => 'Pró-Labore',
                'valores_mensais' => ["4" => 24000, "5" => 24000, "6" => 24000, "7" => 12000, "8" => 32300, "9" => 24000]
            ],
            [
                'categoria' => 'Custos Fixos',
                'nome' => 'Contabilidade',
                'valores_mensais' => ["4" => 989, "5" => 989, "6" => 989, "7" => 989, "8" => 989, "9" => 989]
            ],
            // --- CUSTOS VARIÁVEIS ---
            [
                'categoria' => 'Custos Variáveis',
                'nome' => 'Fechamento E4log',
                'valores_mensais' => ["4" => 195300, "5" => 134369.55, "6" => 86570.88, "7" => 97834.24, "8" => 126580.52, "9" => 218852.48]
            ],
            [
                'categoria' => 'Custos Variáveis',
                'nome' => 'Seguro Tokio Marine',
                'valores_mensais' => ["4" => 3000, "5" => 2147.6, "6" => 2147.6, "7" => 2147, "8" => 2147.6, "9" => 2147.6]
            ],
            [
                'categoria' => 'Custos Variáveis',
                'nome' => 'Comissão',
                'valores_mensais' => ["4" => 9300, "5" => 6398.55, "6" => 4058.01, "7" => 4585.98, "8" => 4530.48, "9" => 10258.71]
            ],
        ];

        // 3. Salva os itens vinculados ao Orçamento criado
        foreach ($items as $item) {
            $budget->items()->create($item);
        }
    }
}
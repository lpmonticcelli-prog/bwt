<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Facades\DB;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpeza segura contornando a trava de chaves estrangeiras
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Budget::truncate();
        BudgetItem::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Cria a Capa do Orçamento
        $budget = Budget::create([
            'ano' => 2026,
            'versao' => 'Oficial',
            'status' => 'Rascunho', // Status rascunho para permitir edições
            'ativo' => true,
        ]);

        // 3. Prepara os Itens COMPLETOS extraídos da sua planilha oficial
        $items = [
            // === RECEITAS ===
            [
                'categoria' => 'RECEITAS',
                'nome' => 'Total Vendas',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>310000,"5"=>213285,"6"=>135267,"7"=>152866,"8"=>208420,"9"=>341957,"10"=>272506,"11"=>220302,"12"=>210132]
            ],
            [
                'categoria' => 'RECEITAS',
                'nome' => 'Solfácil Distribuidora',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>310000,"5"=>213285,"6"=>135267,"7"=>151016,"8"=>208420,"9"=>338457,"10"=>272506,"11"=>220302,"12"=>210132]
            ],
            [
                'categoria' => 'RECEITAS',
                'nome' => 'Empilhadeira',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>1850,"8"=>0,"9"=>3500,"10"=>0,"11"=>0,"12"=>0]
            ],

            // === IMPOSTOS ===
            [
                'categoria' => 'IMPOSTOS',
                'nome' => 'PIS 0,65%',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>2015,"5"=>1386.35,"6"=>879.24,"7"=>993.63,"8"=>1354.73,"9"=>2222.72,"10"=>1771.29,"11"=>1431.96,"12"=>1365.86]
            ],
            [
                'categoria' => 'IMPOSTOS',
                'nome' => 'COFINS 3%',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>9300,"5"=>6398.55,"6"=>4058.01,"7"=>4585.98,"8"=>6252.6,"9"=>10258.71,"10"=>8175.18,"11"=>6609.06,"12"=>6303.96]
            ],
            [
                'categoria' => 'IMPOSTOS',
                'nome' => 'ICMS 10%',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>37200,"5"=>21328.5,"6"=>13526.7,"7"=>15286.6,"8"=>20842,"9"=>34195.7,"10"=>27250.6,"11"=>22030.2,"12"=>21013.2]
            ],
            [
                'categoria' => 'IMPOSTOS',
                'nome' => 'IRPJ',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>3720,"5"=>2559.42,"6"=>1623.20,"7"=>1834.39,"8"=>2501.04,"9"=>4103.48,"10"=>3270.07,"11"=>2643.62,"12"=>2521.58]
            ],
            [
                'categoria' => 'IMPOSTOS',
                'nome' => 'CSLL',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>3348,"5"=>2303.48,"6"=>1460.88,"7"=>1650.95,"8"=>2250.94,"9"=>3693.14,"10"=>2943.06,"11"=>2379.26,"12"=>2269.43]
            ],

            // === CUSTOS FIXOS ===
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Telefone',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>85,"5"=>85,"6"=>85,"7"=>85,"8"=>0,"9"=>0,"10"=>0,"11"=>0,"12"=>0]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Cowork SJC',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>259,"5"=>259,"6"=>259,"7"=>259,"8"=>259,"9"=>259,"10"=>259,"11"=>259,"12"=>259]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Contabilidade',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>989,"5"=>989,"6"=>989,"7"=>989,"8"=>989,"9"=>989,"10"=>989,"11"=>989,"12"=>989]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Sistema SSW',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>1450,"5"=>1522.04,"6"=>1518,"7"=>1524.06,"8"=>1518,"9"=>1518,"10"=>1518,"11"=>1518,"12"=>1518]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Pró-Labore',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>24000,"5"=>24000,"6"=>24000,"7"=>12000,"8"=>32300,"9"=>24000,"10"=>24000,"11"=>24000,"12"=>24000]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'INSS Pró-Labore',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>470.58,"8"=>517.44,"9"=>517.44,"10"=>517.44,"11"=>517.44,"12"=>517.44]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Vale Refeição (MEI)',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>600,"5"=>600,"6"=>600,"7"=>600,"8"=>600,"9"=>600,"10"=>600,"11"=>600,"12"=>600]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Maria Clara (MEI)',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>4000,"5"=>4000,"6"=>4000,"7"=>4000,"8"=>4000,"9"=>4000,"10"=>4000,"11"=>4000,"12"=>4000]
            ],
            [
                'categoria' => 'CUSTO FIXO', 'nome' => 'Verisure',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>0,"8"=>0,"9"=>158.33,"10"=>193.33,"11"=>193.33,"12"=>193.33]
            ],

            // === CUSTOS VARIÁVEIS ===
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Fechamento E4LOG',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>195300,"5"=>134369.55,"6"=>86570.88,"7"=>97834.24,"8"=>126580.52,"9"=>218852.48,"10"=>177128.9,"11"=>143196.3,"12"=>136585.8]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Seguro de Carga (Tokio/Sompo)',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>3000,"5"=>2147.6,"6"=>2147.6,"7"=>2147,"8"=>2147.6,"9"=>2147.6,"10"=>1987.82,"11"=>1721.2,"12"=>1454.49]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Comissão Maurício',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>9300,"5"=>6398.55,"6"=>4058.01,"7"=>4585.98,"8"=>4530.48,"9"=>10258.71,"10"=>8175.18,"11"=>6609.06,"12"=>6303.96]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Despesa Bancária',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>200,"5"=>218,"6"=>481,"7"=>273,"8"=>199,"9"=>255.4,"10"=>335,"11"=>166,"12"=>335]
            ],
            [
                'categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Google',
                'valores' => ["1"=>0,"2"=>0,"3"=>0,"4"=>0,"5"=>0,"6"=>0,"7"=>0,"8"=>138,"9"=>138,"10"=>138,"11"=>138,"12"=>138]
            ],
        ];

        // 4. Salva no banco
        foreach ($items as $item) {
            $budget->items()->create([
                'categoria' => $item['categoria'],
                'nome' => $item['nome'],
                'valores_mensais' => json_encode($item['valores']),
                'valores_originais' => json_encode($item['valores']), // Salva o Backup
            ]);
        }
    }
}
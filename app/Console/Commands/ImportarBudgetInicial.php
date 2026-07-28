<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Facades\DB;

class ImportarBudgetInicial extends Command
{
    protected $signature = 'budget:import {filepath}';
    protected $description = 'Importa o budget inicial completo a partir da planilha BUDGET - BWT.xlsx';

    public function handle()
    {
        $filepath = $this->argument('filepath');

        if (!file_exists($filepath)) {
            $this->error("Arquivo não encontrado: {$filepath}");
            return;
        }

        $this->info("🚀 Iniciando a importação do budget a partir de: {$filepath}");

        DB::beginTransaction();

        try {
            $budget = Budget::create([
                'ano' => 2026,
                'versao' => 'Oficial',
                'status' => 'Rascunho',
                'ativo' => true,
            ]);

            $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
                public function array(array $array) { return $array; }
            }, $filepath);

            $sheet = $data[0]; 

            // Nomes corrigidos sem os espaços fantasmas
            $rowMappings = [
                'TOTAL VENDAS' => ['categoria' => 'RECEITAS', 'nome' => 'Total Vendas'],
                'SOLFACIL DISTRIBUIDORA' => ['categoria' => 'RECEITAS', 'nome' => 'Solfácil Distribuidora'],
                'EMPILHADEIRA' => ['categoria' => 'RECEITAS', 'nome' => 'Empilhadeira'],

                'PIS 0,65%' => ['categoria' => 'IMPOSTOS', 'nome' => 'PIS 0,65%'],
                'Cofins 3%' => ['categoria' => 'IMPOSTOS', 'nome' => 'COFINS 3%'],
                'ICMS 10%' => ['categoria' => 'IMPOSTOS', 'nome' => 'ICMS 10%'],
                'IRPJ' => ['categoria' => 'IMPOSTOS', 'nome' => 'IRPJ'],
                'CSLL' => ['categoria' => 'IMPOSTOS', 'nome' => 'CSLL'],

                'TELEFONE' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Telefone'],
                'COWORK SJC' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Cowork SJC'],
                'CARTAO DE CREDITO' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Cartão de Crédito'],
                'CONTABILIDADE' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Contabilidade'],
                'SSW' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Sistema SSW'],
                'PRO LABORE' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Pró-Labore'],
                'VERISURE' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Verisure'],
                'INSS SOBRE O PROLABORE' => ['categoria' => 'CUSTO FIXO', 'nome' => 'INSS Pró-Labore'],
                'VALE REFEIÇÃO (MEI)' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Vale Refeição (MEI)'],
                'MARIA CLARA (MEI)' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Maria Clara (MEI)'],
                'DIVERSOS (SITE)' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Diversos (Site)'],
                'DIVERSOS RECISAO ESTER' => ['categoria' => 'CUSTO FIXO', 'nome' => 'Diversos Recisão Ester'],

                'SEGURO DE CARGA - TOKIO MARINE - SOMPO' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Seguro de Carga (Tokio/Sompo)'],
                'FECHAMENTO E4LOG' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Fechamento E4LOG'],
                'AVARIA CARGA NA COLETA' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Avaria Carga na Coleta'],
                'CUSTO DE COLETA' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Custo de Coleta'],
                'MARKETING E BONIFICAÇAO' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Marketing e Bonificação'],
                'COMISSAO MAURÍCIO' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Comissão Maurício'],
                'DESPESA DE VIAGEM' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Despesa de Viagem'],
                'GERENCIAMENTO RISCO' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Gerenciamento Risco'],
                'IMPOSTOS E TAXAS' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Impostos e Taxas Diversas'],
                'DESPESA BANCARIA' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Despesa Bancária'],
                'JUROS (ANTECIPAÇÃO)' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Juros (Antecipação)'],
                'GOOGLE' => ['categoria' => 'CUSTO VARIÁVEL', 'nome' => 'Google'],
            ];

            $itemsImported = 0;
            foreach ($sheet as $rowIndex => $row) {
                $mappedItem = null;
                
                // O Radar Titã: Varre das colunas A até E (0 a 4) procurando as palavras
                for ($col = 0; $col <= 4; $col++) {
                    $identifier = trim((string)($row[$col] ?? ''));
                    if (isset($rowMappings[$identifier])) {
                        $mappedItem = $rowMappings[$identifier];
                        break; // Achou! Interrompe a busca nesta linha
                    }
                }

                if ($mappedItem) {
                    $valoresMensais = [
                        1 => 0, 2 => 0, 3 => 0,
                        4 => $this->cleanValue($row[4] ?? 0),
                        5 => $this->cleanValue($row[5] ?? 0),
                        6 => $this->cleanValue($row[6] ?? 0),
                        7 => $this->cleanValue($row[7] ?? 0),
                        8 => $this->cleanValue($row[8] ?? 0),
                        9 => $this->cleanValue($row[9] ?? 0),
                        10 => $this->cleanValue($row[10] ?? 0),
                        11 => $this->cleanValue($row[11] ?? 0),
                        12 => $this->cleanValue($row[12] ?? 0),
                    ];

                    BudgetItem::create([
                        'budget_id' => $budget->id,
                        'categoria' => $mappedItem['categoria'],
                        'nome' => $mappedItem['nome'],
                        'valores_mensais' => json_encode($valoresMensais), 
                        'valores_originais' => json_encode($valoresMensais),
                    ]);
                    
                    $itemsImported++;
                }
            }

            DB::commit();
            $this->info("✅ Importação concluída com sucesso! {$itemsImported} itens foram importados.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Erro ao importar o budget: " . $e->getMessage());
        }
    }

    private function cleanValue($val)
    {
        if (empty($val) || strtolower(trim((string)$val)) === 'nan' || is_string($val)) {
            return 0.00;
        }
        return round((float)$val, 2);
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\City;
use App\Models\Region;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarCidades extends Command
{
    protected $signature = 'importar:cidades';
    protected $description = 'Lê as planilhas XLSX originais e cadastra as cidades nas suas respectivas regiões';

    public function handle()
    {
        $this->info("Iniciando varredura e importação direto do Excel (.xlsx)...");

        // Mapeamento dos arquivos
        $arquivos = [
            'bwt'   => storage_path('app/tabelas/REGIÃO 5.xlsx'),
            'e4log' => storage_path('app/tabelas/REGIÃO 6.xlsx')
        ];

        DB::beginTransaction();

        try {
            $cidadesAdicionadas = 0;

            foreach ($arquivos as $contexto => $caminho) {
                if (!file_exists($caminho)) {
                    $this->warn("Arquivo não encontrado: {$caminho}");
                    continue;
                }

                $this->info("\nLendo arquivo do contexto [{$contexto}]...");
                
                $spreadsheet = IOFactory::load($caminho);

                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $tituloAba = strtoupper($sheet->getTitle());

                    $nomeRegiao = null;
                    if (str_contains($tituloAba, 'REGIÃO 1') || str_contains($tituloAba, 'REGIAO 1')) $nomeRegiao = 'Região 1';
                    elseif (str_contains($tituloAba, 'REGIÃO 2') || str_contains($tituloAba, 'REGIAO 2')) $nomeRegiao = 'Região 2';
                    elseif (str_contains($tituloAba, 'REGIÃO 3') || str_contains($tituloAba, 'REGIAO 3')) $nomeRegiao = 'Região 3';
                    elseif (str_contains($tituloAba, 'REGIÃO 4') || str_contains($tituloAba, 'REGIAO 4')) $nomeRegiao = 'Região 4';
                    elseif (str_contains($tituloAba, 'MG')) $nomeRegiao = 'MG';

                    if (!$nomeRegiao) {
                        $this->line("Pulando aba '{$tituloAba}' (não é uma região válida).");
                        continue;
                    }

                    $region = Region::where('name', $nomeRegiao)->where('context', strtolower($contexto))->first();
                    
                    if (!$region) {
                        $this->error("Região [{$nomeRegiao}] ({$contexto}) não existe no banco. Crie-a no painel primeiro.");
                        continue;
                    }

                    $linhas = $sheet->toArray();

                    foreach ($linhas as $linha) {
                        foreach ($linha as $coluna) {
                            $texto = trim((string) $coluna);
                            
                            // Ignora vazios, números puros e textos curtos
                            if (empty($texto) || is_numeric($texto) || strlen($texto) <= 2) continue;
                            
                            // Ignora cabeçalhos, valores financeiros e descrições de prazo
                            if (preg_match('/(MÍNIMO|TABELA|Prazo|úteis|Microrregião|Municípios|Data:|ESTADO|VALOR|REGIÃO|REGIAO|ICMS|TDE|%|\$|R\$|FRETE|PLACAS)/i', $texto)) continue;

                            // Normaliza: Remove acentos e deixa tudo em maiúsculo (ex: SÃO PAULO -> SAO PAULO)
                            $nomeNormalizado = strtoupper(Str::slug($texto, ' '));

                            // Salva no banco e amarra à região
                            $city = City::firstOrCreate(['name' => $nomeNormalizado]);
                            $city->regions()->syncWithoutDetaching([$region->id]);

                            $cidadesAdicionadas++;
                        }
                    }
                    
                    $this->info("✔ Aba '{$tituloAba}' importada com sucesso para {$nomeRegiao} ({$contexto}).");
                }
            }

            DB::commit();
            $this->info("\nImportação concluída! {$cidadesAdicionadas} amarrações de cidades foram processadas.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Erro fatal: " . $e->getMessage());
        }
    }
}
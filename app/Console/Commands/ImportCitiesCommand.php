<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\City;
use App\Models\Region;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class ImportCitiesCommand extends Command
{
    protected $signature = 'import:cities';
    protected $description = 'Importa as cidades lendo as ABAS dos arquivos Excel dinamicamente';

    public function handle()
    {
        $path = storage_path('app/imports');

        if (!file_exists($path)) {
            $this->error("Pasta {$path} não existe.");
            return;
        }

        $files = File::files($path);
        $excelFiles = array_filter($files, function($file) {
            return in_array(strtolower($file->getExtension()), ['xlsx', 'xls']);
        });

        if (empty($excelFiles)) {
            $this->error("Nenhum arquivo Excel encontrado na pasta {$path}.");
            return;
        }

        $this->info("Iniciando a varredura de " . count($excelFiles) . " arquivos...\n");

        foreach ($excelFiles as $file) {
            $this->info("-> Abrindo arquivo: " . $file->getFilename());
            $this->processFile($file->getPathname());
        }

        $this->info("\nImportação finalizada com SUCESSO ABSOLUTO!");
    }

    private function processFile($fullPath)
    {
        $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
            public function array(array $array) {}
        }, $fullPath);

        $sheetCounter = 1;

        // O Excel retorna um array onde cada item é uma ABA (Sheet)
        foreach ($data as $sheetName => $rows) {
            
            // Tenta descobrir o número da região pelo NOME DA ABA (Ex: "REGIÃO 3 INDI")
            preg_match('/(\d)/', $sheetName, $matches);
            
            if (isset($matches[1]) && $matches[1] >= 1 && $matches[1] <= 4) {
                $numRegiao = $matches[1];
            } else {
                // Se a aba não tiver número no nome, assume a ordem (Aba 1 = Reg 1)
                $numRegiao = $sheetCounter;
            }

            // Ignora abas extras como "MG" ou "Planilha1"
            if ($numRegiao > 4) {
                $sheetCounter++;
                continue; 
            }

            $regE4log = Region::where('name', "Região {$numRegiao}")->where('context', 'e4log')->first();
            $regBwt = Region::where('name', "Região {$numRegiao}")->where('context', 'bwt')->first();

            if (!$regE4log && !$regBwt) {
                $sheetCounter++;
                continue;
            }

            $count = 0;

            foreach ($rows as $index => $row) {
                if ($index < 1) continue; // Pula cabeçalhos

                if (count($row) === 1 && str_contains($row[0], ';')) {
                    $row = explode(';', $row[0]);
                }

                // Colunas onde as cidades ficam
                $colIndices = [1, 3, 5, 7, 9, 11];

                foreach ($colIndices as $colIdx) {
                    $cellValue = isset($row[$colIdx]) ? trim($row[$colIdx]) : null;

                    if (!empty($cellValue)) {
                        if (preg_match('/(MÍNIMO|DIAS|UTEIS|ÚTEIS|VALOR|NF|ATÉ|PRAZO|TABELA|REGIÃO|MUNIC|MICRO|FRETE|%|R\$)/i', $cellValue)) {
                            continue;
                        }

                        $cities = explode('/', $cellValue);
                        
                        foreach ($cities as $cityName) {
                            $cityName = trim($cityName);
                            if (empty($cityName)) continue;

                            $cleanName = strtoupper(Str::slug($cityName, ' '));

                            $city = City::firstOrCreate(['name' => $cleanName]);
                            
                            if ($regE4log) $city->regions()->syncWithoutDetaching([$regE4log->id]);
                            if ($regBwt) $city->regions()->syncWithoutDetaching([$regBwt->id]);
                            
                            $count++;
                        }
                    }
                }
            }
            
            if ($count > 0) {
                $this->line("   OK: {$count} cidades cadastradas na Região {$numRegiao}.");
            }
            $sheetCounter++;
        }
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegiaoFrete;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportarDicionario extends Command
{
    protected $signature = 'importar:dicionario';
    protected $description = 'Lê as planilhas do Excel e cria o Cérebro Geográfico (De/Para)';

    public function handle()
    {
        $this->info("Iniciando a criação do Dicionário Geográfico...");

        $arquivos = [
            'solfacil' => storage_path('app/tabelas/REGIÃO 5.xlsx'),
            'e4log' => storage_path('app/tabelas/REGIÃO 6.xlsx')
        ];

        // 1. LIMPEZA FORA DA TRANSAÇÃO
        // O Truncate causa commit automático no MySQL, por isso tem de ficar de fora!
        DB::table('regiao_fretes')->truncate();

        // 2. INICIA A SEGURANÇA AGORA
        DB::beginTransaction();

        try {
            $cidadesProcessadas = 0;

            foreach ($arquivos as $contexto => $caminho) {
                if (!file_exists($caminho)) {
                    $this->warn("Arquivo não encontrado: {$caminho}");
                    continue;
                }

                $this->info("\nLendo arquivo [{$contexto}]...");
                $spreadsheet = IOFactory::load($caminho);

                foreach ($spreadsheet->getAllSheets() as $sheet) {
                    $tituloAba = strtoupper($sheet->getTitle());

                    // Descobre a Região pela Aba
                    $numRegiao = null;
                    if (str_contains($tituloAba, 'REGIÃO 1') || str_contains($tituloAba, 'REGIAO 1')) $numRegiao = 1;
                    elseif (str_contains($tituloAba, 'REGIÃO 2') || str_contains($tituloAba, 'REGIAO 2')) $numRegiao = 2;
                    elseif (str_contains($tituloAba, 'REGIÃO 3') || str_contains($tituloAba, 'REGIAO 3')) $numRegiao = 3;
                    elseif (str_contains($tituloAba, 'REGIÃO 4') || str_contains($tituloAba, 'REGIAO 4')) $numRegiao = 4;

                    if (!$numRegiao) continue;

                    $linhas = $sheet->toArray();

                    foreach ($linhas as $linha) {
                        foreach ($linha as $coluna) {
                            $texto = trim((string) $coluna);
                            
                            // Regras de exclusão de cabeçalhos
                            if (empty($texto) || is_numeric($texto) || strlen($texto) <= 2) continue;
                            if (preg_match('/(MÍNIMO|TABELA|Prazo|úteis|Microrregião|Municípios|Data:|ESTADO|VALOR|REGIÃO|REGIAO|ICMS|TDE|%|\$|R\$|FRETE|PLACAS)/i', $texto)) continue;

                            // Tem cidades que vêm agrupadas com barra no Excel. Ex: "JALES / ESTRELA D`OESTE"
                            $cidadesQuebradas = explode('/', $texto);

                            foreach ($cidadesQuebradas as $nomeDaCidade) {
                                $nomeDaCidade = trim($nomeDaCidade);
                                if (empty($nomeDaCidade)) continue;

                                // A MÁGICA: Normalização exata como é feita no XML!
                                // Tira acentos, converte aspas para espaço e coloca tudo em maiúsculas
                                $nomeNormalizado = strtoupper(Str::slug($nomeDaCidade, ' '));

                                // Busca ou instancia o registro
                                $regiaoFrete = RegiaoFrete::where('cidade', $nomeNormalizado)->first();
                                
                                if (!$regiaoFrete) {
                                    $regiaoFrete = new RegiaoFrete();
                                    $regiaoFrete->cidade = $nomeNormalizado;
                                    $regiaoFrete->uf = 'SP';
                                }

                                // Atualiza a coluna certa baseada no arquivo que está sendo lido
                                if ($contexto === 'e4log') {
                                    $regiaoFrete->regiao_e4log = $numRegiao;
                                } else {
                                    $regiaoFrete->regiao_solfacil = $numRegiao;
                                }
                                
                                $regiaoFrete->save();
                                $cidadesProcessadas++;
                            }
                        }
                    }
                    $this->info("✔ Aba '{$tituloAba}' mapeada como Região {$numRegiao}.");
                }
            }

            DB::commit();
            $this->info("\nImportação concluída! O Cérebro mapeou {$cidadesProcessadas} cidades com SUCESSO.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nErro fatal: " . $e->getMessage());
        }
    }
}
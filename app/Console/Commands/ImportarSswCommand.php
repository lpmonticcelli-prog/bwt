<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SswDespesa;
use Carbon\Carbon;

class ImportarSswCommand extends Command
{
    protected $signature = 'ssw:importar {arquivo}';
    protected $description = 'Importa o histórico da SSW (Tela 477) e constrói o Budget com Filiais e Eventos separados.';

    public function handle()
    {
        $arquivo = $this->argument('arquivo');
        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");
            return;
        }

        $linhas = file($arquivo);
        $header = [];
        $dados = [];

        foreach ($linhas as $linha) {
            $linha = mb_convert_encoding(trim($linha), 'UTF-8', 'ISO-8859-1');
            $colunas = explode(';', $linha);

            if (count($colunas) > 0 && $colunas[0] === '3') {
                // Captura o cabeçalho e transforma tudo em maiúsculo para não errar a busca
                $header = array_map(function($h) { return trim(strtoupper($h)); }, $colunas);
            } 
            elseif (count($colunas) > 0 && $colunas[0] === '5') {
                if (empty($header)) continue;
                $row = [];
                foreach ($header as $index => $nomeColuna) {
                    $row[$nomeColuna] = trim($colunas[$index] ?? '');
                }
                $dados[] = $row;
            }
        }

        if (empty($dados)) {
            $this->error("Nenhum dado encontrado. Tem certeza que é o CSV da tela 477 da SSW?");
            return;
        }

        $this->info("Injetando " . count($dados) . " despesas históricas no banco de dados...");

        foreach ($dados as $d) {
            // Buscas flexíveis nos cabeçalhos para evitar erros
            $filialBruta = $d['SIGLA DA FILIAL'] ?? $d['FILIAL'] ?? 'GERAL';
            $fornecedor  = $d['NOME FORNECEDOR'] ?? $d['FORNECEDOR'] ?? 'DIVERSOS';
            $evento      = $d['DESCR EVENTO'] ?? $d['EVENTO'] ?? '';
            $historico   = $d['HISTORICO DA DESPESA'] ?? $d['HISTORICO'] ?? '';
            $valorStr    = $d['VLR FINAL'] ?? $d['VALOR FINAL'] ?? $d['VALOR'] ?? '0';
            $pagamento   = $d['DATA PGTO'] ?? $d['DATA PAGAMENTO'] ?? $d['PAGAMENTO'] ?? '';
            $vencimento  = $d['DATA VENCIMENTO'] ?? $d['DT VENCIMENTO'] ?? $d['VENCIMENTO'] ?? $pagamento;
            $situacao    = $d['SIT DES'] ?? $d['SITUACAO'] ?? 'LIQU';
            $competencia = $d['MES COMPETENCIA'] ?? $d['COMPETENCIA'] ?? '';
            
            // Corrige o erro 1364 do Banco de Dados capturando o ID do Lançamento
            $lancamento  = $d['N LANCAMENTO'] ?? $d['Nº LANÇAMENTO'] ?? $d['LANCAMENTO'] ?? $d['N° LANÇAMENTO'] ?? '';

            if (empty($evento)) $evento = $historico;
            
            // Ignora despesas canceladas (como os fretes do Felipe Cristovam)
            if (str_contains(strtoupper($situacao), 'CANC')) continue;

            $valor = (float) str_replace(['.', ','], ['', '.'], $valorStr);
            if ($valor == 0) continue;

            // Se a SSW não enviar o número do lançamento no CSV, nós geramos um ID blindado e único
            if (empty($lancamento)) {
                $lancamento = 'HIST-' . md5($fornecedor . $evento . $valorStr . $competencia . $vencimento);
            }

            // Extrator inteligente de sigla de filial (MTZ, IND, SJK, FRT)
            $filialLimpa = 'GERAL';
            if (!empty($filialBruta)) {
                preg_match('/[A-Z]{3}/', preg_replace('/[^A-Z]/', '', $filialBruta), $matches);
                if (!empty($matches[0])) {
                    $filialLimpa = $matches[0];
                }
            }

            // O updateOrCreate garante que, se você rodar o comando duas vezes, ele não vai duplicar
            SswDespesa::updateOrCreate(
                ['lancamento' => substr($lancamento, 0, 50)],
                [
                    'fornecedor' => $fornecedor,
                    'evento' => $evento,
                    'valor' => $valor,
                    'competencia' => $competencia,
                    'filial' => $filialLimpa,
                    'inclusao' => $this->formatarData($vencimento) ?? date('Y-m-d'),
                    'historico' => $historico,
                    'nota_fiscal' => 'N/D',
                    'vencimento' => $this->formatarData($vencimento) ?? date('Y-m-d'),
                    'pagamento' => $this->formatarData($pagamento),
                    'situacao' => empty($pagamento) ? 'Pendente' : 'Liquidado',
                    'tipo' => 'DESPESA'
                ]
            );
        }

        $this->info("Importação de histórico concluída!");
        $this->info("Acionando o robô principal para construir o Budget...");
        
        // Chama o robô que faz a mágica de distribuir nas gavetas na tela
        $this->call('ssw:sync'); 
    }

    private function formatarData($dataString)
    {
        $dataString = trim($dataString);
        if (empty($dataString)) return null;
        try { return Carbon::createFromFormat('d/m/y', $dataString)->format('Y-m-d'); } 
        catch (\Exception $e) {
            try { return Carbon::createFromFormat('d/m/Y', $dataString)->format('Y-m-d'); } 
            catch (\Exception $e2) { return null; }
        }
    }
}
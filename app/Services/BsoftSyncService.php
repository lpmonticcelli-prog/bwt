<?php

namespace App\Services;

use App\Models\Frete;
use App\Models\Faturamento;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; // <-- Importação do motor de datas do Laravel

class BsoftSyncService
{
    private $baseUrl = 'https://api.bsoft.com.br/sistema/v2';
    private $token = null;
    private $credenciais = [
        "tag" => "ETL277",
        "usuario_sistema" => "API",
        "senha_sistema" => "E4log@2024", 
        "empresa" => 1
    ];

    public function __construct()
    {
        $this->login();
    }

    private function login()
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . '/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($this->credenciais),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false 
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->token = $data['access_token'] ?? null;
        } else {
            Log::error("BsoftSync: Falha no login da API. Código: " . $httpCode);
        }
    }

    public function atualizarBaixasBwt($fechamentoId)
    {
        if (!$this->token) return ['success' => false, 'message' => 'Sem token de autenticação da Bsoft'];

        $fretesAguardandoBaixa = Frete::where('fechamento_periodo_id', $fechamentoId)
            ->whereNull('data_entrega')
            ->get();

        if ($fretesAguardandoBaixa->isEmpty()) {
            return ['success' => true, 'message' => 'Nenhuma entrega pendente de baixa neste lote.'];
        }

        $atualizadas = 0;
        $errosDiagnostico = [];

        // Define a Data de Hoje cravada no Fuso Horário do Brasil (PR/BR)
        $hojeReal = Carbon::now('America/Sao_Paulo');

        foreach ($fretesAguardandoBaixa as $frete) {
            
            // Extrai o Número ou a Chave do nome do arquivo XML
            preg_match('/(\d+)/', $frete->arquivo, $matches);
            $identificador = $matches[1] ?? null;

            if (!$identificador) continue;

            // =====================================================================
            // MOTOR DE DATAS CARBON (Fuso Horário BRASIL + Proteção Anti-Falhas)
            // =====================================================================
            if ($frete->data_emissao) {
                $dataEmissao = Carbon::parse($frete->data_emissao, 'America/Sao_Paulo');
            } else {
                $dataEmissao = $hojeReal->copy();
            }
            
            $dataInicial = $dataEmissao->copy()->subDays(30);
            $dataFinal   = $dataEmissao->copy()->addDays(30);
            
            // PROTEÇÃO 1: Se a projeção final for no futuro, trava no dia de hoje
            if ($dataFinal->greaterThan($hojeReal)) {
                $dataFinal = $hojeReal->copy();
            }

            // PROTEÇÃO 2: Se o XML for de 2026, a Data Inicial ficaria maior que a Final (2024).
            // Corrigimos empurrando a Inicial para 60 dias atrás da Final travada.
            if ($dataInicial->greaterThan($dataFinal)) {
                $dataInicial = $dataFinal->copy()->subDays(60);
            }

            // Formatação oficial Bsoft V2 Desktop
            $parametros = [
                'data_inicial' => $dataInicial->format('d/m/Y'),
                'data_final'   => $dataFinal->format('d/m/Y'),
                'quantidade'   => 50
            ];

            if (strlen($identificador) == 44) {
                $parametros['chave'] = $identificador;
            } else {
                $parametros['numero'] = (int)$identificador;
            }
            
            $queryString = http_build_query($parametros);
            $url = $this->baseUrl . '/cte?' . $queryString . '&include=ocorrencias';
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->token,
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // Tratamento de Erros da API
            if ($httpCode !== 200) {
                $dadosErro = json_decode($response, true);
                $msgAPI = 'Requisição Inválida';
                if ($dadosErro) {
                    $msgErroArray = $dadosErro['errors'] ?? [];
                    if (!empty($msgErroArray) && isset($msgErroArray[0]['erro'])) {
                        $msgAPI = $msgErroArray[0]['erro'];
                    } else {
                        $msgAPI = $dadosErro['message'] ?? $dadosErro['title'] ?? json_encode($dadosErro);
                    }
                }
                
                $errosDiagnostico[] = "API rejeitou CT-e {$identificador} (Cod {$httpCode}: {$msgAPI})";
                continue; 
            }

            $dadosCte = json_decode($response, true);
            
            if (isset($dadosCte['data']) && count($dadosCte['data']) > 0) {
                $cteApi = $dadosCte['data'][0]; 
                $dataEntregaEncontrada = null;

                // Lê a data nativa ou o Dicionário de EDI Logístico
                if (!empty($cteApi['data_entrega'])) {
                    $dataEntregaEncontrada = $cteApi['data_entrega'];
                } 
                else if (isset($cteApi['dados_entrega']) && !empty($cteApi['dados_entrega']['data_baixa'])) {
                    $dataEntregaEncontrada = $cteApi['dados_entrega']['data_baixa'];
                }
                else if (!empty($cteApi['ocorrencias'])) {
                    $dicionarioEDI = ['entreg', 'baixa', 'realizad', 'concluid', 'recebid', 'edi 01', 'ocorrência 01', 'ocorrencia 01', 'oc 01'];
                    foreach ($cteApi['ocorrencias'] as $oc) {
                        $descricao = strtolower($oc['descricao'] ?? '');
                        $codigo = (string)($oc['codigo'] ?? '');
                        
                        $isEntregue = ($codigo === '01' || $codigo === '100');
                        if (!$isEntregue) {
                            foreach ($dicionarioEDI as $jargao) {
                                if (str_contains($descricao, $jargao)) {
                                    $isEntregue = true;
                                    break;
                                }
                            }
                        }

                        if ($isEntregue && isset($oc['data_ocorrencia'])) {
                            $dataEntregaEncontrada = $oc['data_ocorrencia'];
                            break; 
                        }
                    }
                }

                if ($dataEntregaEncontrada) {
                    // Formata a data devolução Bsoft (DD/MM/YYYY) para o banco do ioapps (YYYY-MM-DD)
                    $dataFormatada = Carbon::createFromFormat('d/m/Y H:i', $dataEntregaEncontrada, 'America/Sao_Paulo')->format('Y-m-d');
                    
                    $frete->data_entrega = $dataFormatada;
                    $frete->save();

                    // Replica a baixa também para o faturamento Sol Fácil simultaneamente
                    Faturamento::where('fechamento_periodo_id', $fechamentoId)
                        ->where('valor_carga', $frete->valorNF)
                        ->where('destino', $frete->destino)
                        ->update(['data_entrega' => $dataFormatada]);

                    $atualizadas++;
                } else {
                    $errosDiagnostico[] = "CT-e {$identificador}: AINDA SEM BAIXA (Em trânsito).";
                }
            } else {
                $errosDiagnostico[] = "CT-e {$identificador}: Não encontrado na janela de busca.";
            }
        }

        // Mensagem Executiva para a Dashboard
        $msgExtra = "";
        if (!empty($errosDiagnostico)) {
            $errosUnicos = array_unique($errosDiagnostico);
            $msgExtra = " | Diagnóstico: " . implode(" | ", array_slice($errosUnicos, 0, 3));
            if (count($errosUnicos) > 3) {
                $msgExtra .= " e mais " . (count($errosUnicos) - 3) . " notas analisadas...";
            }
        }

        return ['success' => true, 'message' => "Sincronização concluída: $atualizadas baixas efetuadas.\n\n$msgExtra"];
    }
}
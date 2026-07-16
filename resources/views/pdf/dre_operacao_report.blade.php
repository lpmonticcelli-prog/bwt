<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório DRE Operação - BWT x E4LOG</title>
    <style>
        /* Base e Tipografia otimizadas para PDF (A4 Paisagem) */
        @page { margin: 1cm; size: A4 landscape; }
        
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8px; 
            color: #334155;
            margin: 0;
            padding: 0;
        }
        
        /* Cabeçalho */
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 15px;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 900;
        }
        .header p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 9px;
        }
        
        /* Painel Executivo do DRE */
        .panel {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .panel-table {
            width: 100%;
            border-collapse: collapse;
        }
        .panel-table td {
            padding: 4px 8px;
            vertical-align: top;
            border-right: 1px solid #cbd5e1;
        }
        .panel-table td:last-child {
            border-right: none;
        }

        /* Cores e Utilitários */
        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }
        .text-blue { color: #2563eb; }
        .text-orange { color: #ea580c; }
        .font-bold { font-weight: bold; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        
        /* Tipografia de Matemática Quebrada (Idêntica à Imagem) */
        .val-soma-rec { color: #0284c7; font-weight: bold; font-size: 9.5px; margin-bottom: 2px; }
        .val-soma-cus { color: #b91c1c; font-weight: bold; font-size: 9.5px; margin-bottom: 2px; }
        .val-soma-sla { color: #475569; font-weight: bold; font-size: 9.5px; margin-bottom: 2px; }
        
        .text-breakdown-frt { color: #94a3b8; font-size: 8px; margin-top: 1px; }
        .text-breakdown-tde { color: #ea580c; font-size: 8px; margin-top: 1px; font-weight: bold; }
        .text-compl-orange { color: #ea580c; font-size: 7.5px; font-weight: bold; margin-top: 2px; }
        
        /* Badges de Status DRE */
        .badge {
            padding: 3px 5px;
            font-size: 7px;
            font-weight: bold;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-furo { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-alerta { background-color: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
        .badge-pendente { background-color: #e0f2fe; color: #1e40af; border: 1px solid #bae6fd; }
        .badge-validado { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        
        /* Tabela Principal */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            table-layout: fixed; 
        }
        .data-table th, .data-table td {
            border: 1px solid #e2e8f0;
            padding: 5px;
            text-align: left;
            vertical-align: middle;
            word-wrap: break-word;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        /* Larguras das Colunas (Ajustadas para caber TUDO no PDF A4) */
        .col-nfe { width: 10%; }
        .col-arquivos { width: 18%; }
        .col-rec { width: 12%; background-color: #eff6ff; } /* Fundo leve azul para Receita */
        .col-rec-sla { width: 12%; }
        .col-cus { width: 12%; background-color: #fef2f2; } /* Fundo leve vermelho para Custo */
        .col-cus-sla { width: 12%; }
        .col-dre { width: 14%; background-color: #f1f5f9; }
        .col-status { width: 10%; }
    </style>
</head>
<body>

    <div class="header">
        <h1>DRE OPERACIONAL: CONFRONTO BWT (RECEITA) x E4LOG (CUSTO)</h1>
        <p>Extraído e Gerado em: {{ $data_auditoria }}</p>
    </div>

    <!-- PAINEL EXECUTIVO -->
    <div class="panel">
        <table class="panel-table">
            <tr>
                <!-- Alertas -->
                <td style="width: 25%;">
                    <div style="font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: bold;">Alertas & Divergências</div>
                    <div style="font-size: 16px; font-weight: 900; color: #ea580c; margin-top: 2px;">{{ $resumo['qtd_alertas'] }} Entregas</div>
                    <div style="font-size: 8px; color: #94a3b8; margin-top: 2px;">Com divergência ou furo de receita</div>
                </td>
                
                <!-- Receita BWT -->
                <td style="width: 25%;">
                    <div style="font-size: 8px; color: #1e40af; text-transform: uppercase; font-weight: bold;">Receita Total (BWT)</div>
                    <div style="font-size: 16px; font-weight: 900; color: #2563eb; margin-top: 2px;">R$ {{ number_format($resumo['total_receita_real'], 2, ',', '.') }}</div>
                    <div style="font-size: 8px; color: #3b82f6; margin-top: 2px; font-weight: bold;">Ideal SLA: R$ {{ number_format($resumo['total_receita_ideal'], 2, ',', '.') }}</div>
                </td>
                
                <!-- Custo E4LOG -->
                <td style="width: 25%;">
                    <div style="font-size: 8px; color: #991b1b; text-transform: uppercase; font-weight: bold;">Custo Total (E4LOG)</div>
                    <div style="font-size: 16px; font-weight: 900; color: #dc2626; margin-top: 2px;">R$ {{ number_format($resumo['total_custo_real'], 2, ',', '.') }}</div>
                    <div style="font-size: 8px; color: #ef4444; margin-top: 2px; font-weight: bold;">Ideal SLA: R$ {{ number_format($resumo['total_custo_ideal'], 2, ',', '.') }}</div>
                </td>
                
                <!-- Spread Bruto -->
                <td style="width: 25%;">
                    <div style="font-size: 8px; color: #334155; text-transform: uppercase; font-weight: bold;">Spread Bruto Real (DRE)</div>
                    <div style="font-size: 16px; font-weight: 900; color: {{ $resumo['lucro_bruto_real'] < 0 ? '#dc2626' : '#059669' }}; margin-top: 2px;">
                        R$ {{ number_format($resumo['lucro_bruto_real'], 2, ',', '.') }}
                    </div>
                    <div style="font-size: 8px; color: #64748b; margin-top: 2px; font-weight: bold;">Ideal SLA: R$ {{ number_format($resumo['lucro_bruto_ideal'], 2, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABELA DRE (CRUZAMENTO) -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-nfe">DADOS DA CARGA</th>
                <th class="col-arquivos">ARQUIVOS ASSOCIADOS</th>
                <th class="col-rec text-right">REC. BWT (REAL)</th>
                <th class="col-rec-sla text-right">REC. SLA (MATRIZ)</th>
                <th class="col-cus text-right">CUSTO E4LOG (REAL)</th>
                <th class="col-cus-sla text-right">CUSTO SLA (MATRIZ)</th>
                <th class="col-dre text-right">SPREAD (LUCRO)</th>
                <th class="col-status text-center">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados as $dre)
                <tr>
                    <!-- 1. DADOS DA CARGA -->
                    <td>
                        <strong style="color: #0f172a; font-size: 9px; word-break: break-all;">NFe: {{ $dre['chave_nfe'] }}</strong>
                        <div style="font-size: 7.5px; color: #475569; margin-top: 4px; line-height: 1.3;">
                            <span class="font-bold">Destino:</span> {{ $dre['cidade'] }}<br>
                            <span class="font-bold">V. Carga:</span> R$ {{ number_format($dre['valor_carga'], 2, ',', '.') }}<br>
                            <span class="font-bold">TDE:</span> <span class="{{ $dre['tem_tde'] === 'Sim' ? 'text-green font-bold' : '' }}">{{ $dre['tem_tde'] }}</span>
                        </div>
                    </td>

                    <!-- 2. ARQUIVOS (COM COMPLEMENTOS EM LARANJA) -->
                    <td>
                        <!-- Receita BWT -->
                        @if($dre['arquivo_bwt'] !== 'NÃO LOCALIZADO NO LOTE (FURO DE RECEITA)')
                            <div style="color: #1d4ed8; font-size: 7.5px; word-break: break-all; margin-bottom: 2px;">
                                <strong>BWT:</strong> {{ $dre['arquivo_bwt'] }}
                            </div>
                            @if(!empty($dre['arquivos_bwt_compl']))
                                <div style="margin-bottom: 4px;">
                                    <span style="font-size: 6.5px; color: #ea580c; font-weight: bold;">+ COMPL. BWT:</span>
                                    @foreach($dre['arquivos_bwt_compl'] as $comp)
                                        <div class="text-compl-orange" style="word-break: break-all;">{{ $comp }}</div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div style="margin-bottom: 6px;"><span style="background-color:#fee2e2; color:#991b1b; padding:2px; font-size:6.5px; font-weight:bold; border-radius:2px;">BWT NÃO LOCALIZADA</span></div>
                        @endif

                        <div style="border-top: 1px dashed #cbd5e1; margin: 4px 0;"></div>

                        <!-- Custo E4LOG -->
                        @if($dre['arquivo_e4log'] !== 'CUSTO PENDENTE')
                            <div style="color: #b91c1c; font-size: 7.5px; word-break: break-all; margin-bottom: 2px;">
                                <strong>E4L:</strong> {{ $dre['arquivo_e4log'] }}
                            </div>
                            @if(!empty($dre['arquivos_e4log_compl']))
                                <div>
                                    <span style="font-size: 6.5px; color: #ea580c; font-weight: bold;">+ COMPL. E4LOG:</span>
                                    @foreach($dre['arquivos_e4log_compl'] as $comp)
                                        <div class="text-compl-orange" style="word-break: break-all;">{{ $comp }}</div>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            <div><span style="background-color:#e0f2fe; color:#1e40af; padding:2px; font-size:6.5px; font-weight:bold; border-radius:2px;">E4LOG PENDENTE</span></div>
                        @endif
                    </td>

                    <!-- 3. RECEITA BWT (FATURADO REAL) -->
                    <td class="text-right" style="background-color: #eff6ff;">
                        <div style="font-size: 7px; color: #1e40af; font-weight: bold; margin-bottom: 4px; text-transform: uppercase;">{{ $dre['receita']['matriz'] }}</div>
                        <div class="val-soma-rec">
                            R$ {{ number_format($dre['receita']['real'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($dre['receita']['real_frete'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($dre['receita']['real_tde'], 2, ',', '.') }}
                        </div>
                    </td>

                    <!-- 4. RECEITA SLA MATRIZ -->
                    <td class="text-right">
                        <div style="font-size: 7px; color: #64748b; font-weight: bold; margin-bottom: 4px;">SLA CONTRATUAL</div>
                        <div class="val-soma-sla">
                            R$ {{ number_format($dre['receita']['ideal'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($dre['receita']['ideal_frete'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($dre['receita']['ideal_tde'], 2, ',', '.') }}
                        </div>
                        
                        @php $recDiff = round($dre['receita']['diferenca'], 2); @endphp
                        @if($recDiff != 0)
                            <div style="margin-top: 4px; font-size: 7px; font-weight: bold; color: {{ $recDiff > 0 ? '#16a34a' : '#dc2626' }};">
                                Desvio: {{ $recDiff > 0 ? '+' : '' }}R$ {{ number_format($recDiff, 2, ',', '.') }}
                            </div>
                        @endif
                    </td>

                    <!-- 5. CUSTO E4LOG (PAGO REAL) -->
                    <td class="text-right" style="background-color: #fef2f2;">
                        <div style="font-size: 7px; color: #991b1b; font-weight: bold; margin-bottom: 4px; text-transform: uppercase;">{{ $dre['custo']['matriz'] }}</div>
                        <div class="val-soma-cus">
                            R$ {{ number_format($dre['custo']['real'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($dre['custo']['real_frete'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($dre['custo']['real_tde'], 2, ',', '.') }}
                        </div>
                    </td>

                    <!-- 6. CUSTO SLA MATRIZ -->
                    <td class="text-right">
                        <div style="font-size: 7px; color: #64748b; font-weight: bold; margin-bottom: 4px;">SLA CONTRATUAL</div>
                        <div class="val-soma-sla">
                            R$ {{ number_format($dre['custo']['ideal'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($dre['custo']['ideal_frete'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($dre['custo']['ideal_tde'], 2, ',', '.') }}
                        </div>

                        @php $cusDiff = round($dre['custo']['diferenca'], 2) * -1; @endphp
                        @if($cusDiff != 0)
                            <div style="margin-top: 4px; font-size: 7px; font-weight: bold; color: {{ $cusDiff > 0 ? '#16a34a' : '#dc2626' }};">
                                Desvio: {{ $cusDiff > 0 ? '+' : '' }}R$ {{ number_format($cusDiff, 2, ',', '.') }}
                            </div>
                        @endif
                    </td>

                    <!-- 7. SPREAD / LUCRO DRE -->
                    <td class="text-right" style="background-color: #f1f5f9;">
                        @php $lucro = round($dre['dre']['lucro_real'], 2); @endphp
                        <div style="font-size: 11px; font-weight: 900; color: {{ $lucro < 0 ? '#dc2626' : '#059669' }}; margin-bottom: 3px;">
                            {{ $lucro > 0 ? '+' : '' }} R$ {{ number_format($lucro, 2, ',', '.') }}
                        </div>
                        <div style="font-size: 8px; color: #475569; font-weight: bold;">
                            Margem: <span style="color: {{ $dre['dre']['margem_real'] < 0 ? '#dc2626' : '#059669' }}">{{ number_format($dre['dre']['margem_real'], 2, ',', '.') }}%</span>
                        </div>
                        
                        <div style="margin-top: 4px; border-top: 1px dashed #cbd5e1; padding-top: 3px; font-size: 7px; color: #64748b;">
                            Spread Ideal: <br>
                            <strong>R$ {{ number_format($dre['dre']['lucro_ideal'], 2, ',', '.') }}</strong>
                        </div>
                    </td>

                    <!-- 8. STATUS -->
                    <td class="text-center">
                        @php
                            $badgeClass = 'badge-validado';
                            if ($dre['status'] === 'PREJUÍZO DRE' || $dre['status'] === 'FURO DE RECEITA') $badgeClass = 'badge-furo';
                            elseif ($dre['status'] === 'DIVERGÊNCIA') $badgeClass = 'badge-alerta';
                            elseif ($dre['status'] === 'CUSTO PENDENTE') $badgeClass = 'badge-pendente';
                        @endphp
                        
                        <span class="badge {{ $badgeClass }}">
                            {{ $dre['status'] }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
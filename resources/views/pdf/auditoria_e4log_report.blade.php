<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Auditoria CTe - E4LOG</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            color: #1e3a8a;
        }
        .header p {
            margin: 2px 0;
            color: #6b7280;
        }
        .panel {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .panel-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
        }
        .panel-table {
            width: 100%;
            border: none;
        }
        .panel-table td {
            padding: 3px 0;
            font-size: 10px;
            border: none;
        }
        .text-red { color: #dc2626; font-weight: bold; }
        .text-green { color: #16a34a; font-weight: bold; }
        .text-indigo { color: #4f46e5; font-weight: bold; }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            text-align: left;
            word-wrap: break-word;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        
        /* Largura das colunas */
        .col-arquivo { width: 14%; }
        .col-destino { width: 12%; }
        .col-regiao { width: 10%; }
        .col-valores { width: 9%; }
        .col-diff { width: 9%; }
        .col-motivo { width: 19%; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Relatório Executivo de Auditoria CTe - E4LOG x BWT</h1>
        <p>Gerado em: {{ $data_auditoria }}</p>
    </div>

    <!-- PAINEL EXECUTIVO -->
    <div class="panel">
        <div class="panel-title">Painel Executivo de Consolidação</div>
        <table class="panel-table">
            <tr>
                <td style="width: 50%;">
                    <strong>Total de Operações:</strong> {{ $resumo['total_documentos'] }} auditadas<br><br>
                    <strong>Quanto foi cobrado (Pela E4LOG):</strong> R$ {{ number_format($resumo['total_cobrado'], 2, ',', '.') }}<br>
                    <strong style="color: #4f46e5;">O que teria que cobrar (Matriz E4LOG):</strong> R$ {{ number_format($resumo['total_sla'], 2, ',', '.') }}
                </td>
                <td style="width: 50%;">
                    <!-- Se E4LOG cobrou menos do que deveria (E4LOG perdeu margem, BWT lucrou) -->
                    <span class="text-green">E4LOG cobrou a MENOS (Economia): R$ {{ number_format($resumo['faturado_a_menos'], 2, ',', '.') }}</span><br><br>
                    
                    <!-- Se E4LOG cobrou mais do que deveria (BWT pagou a mais indevidamente) -->
                    <span class="text-red">E4LOG cobrou a MAIS (Prejuízo BWT): R$ {{ number_format($resumo['faturado_a_mais'], 2, ',', '.') }}</span><br><br>
                    
                    <strong>Impacto Líquido:</strong> 
                    <span class="{{ $resumo['balanco_geral'] < 0 ? 'text-green' : 'text-red' }}">
                        R$ {{ number_format(abs($resumo['balanco_geral']), 2, ',', '.') }}
                        {{ $resumo['balanco_geral'] < 0 ? '(A favor da BWT)' : '(Contra a BWT)' }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABELA DE DADOS -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-arquivo">ARQUIVO / CHAVE</th>
                <th class="col-destino">DESTINO</th>
                <th class="col-regiao">REGIÃO MATRIZ</th>
                <th class="col-regiao">REGIÃO COBRADA</th>
                <th class="text-center" style="width: 5%;">TDE?</th>
                <th class="col-valores text-right">COBRADO (E4LOG)</th>
                <th class="col-valores text-right" style="color: #4f46e5;">SLA CORRETO</th>
                <th class="col-diff text-right">DIFERENÇA</th>
                <th class="col-motivo">MOTIVO / STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados as $item)
                <tr>
                    <td>
                        @if($item['tipo_operacao'] == 'Complemento')
                            <span style="background-color: #fef08a; color: #92400e; padding: 1px 3px; font-size: 7px; font-weight: bold; border-radius: 2px;">COMPL. ÓRFÃO</span><br>
                        @endif
                        <strong>{{ $item['arquivo'] }}</strong>
                        <div style="font-size: 7px; color: #64748b; margin-top: 2px;">{{ $item['chave_cte'] }}</div>
                        
                        @if(!empty($item['arquivos_complemento']))
                            <div style="margin-top: 4px; border-top: 1px solid #e2e8f0; padding-top: 2px;">
                                <span style="font-size: 8px; color: #d97706; font-weight: bold;">Complementos Inclusos:</span>
                                @foreach($item['arquivos_complemento'] as $comp)
                                    <div style="font-size: 7px; color: #d97706;">{{ $comp }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>{{ $item['cidade_destino'] }}</td>
                    <td class="text-indigo">{{ $item['regiao_sistema'] }} ({{ $item['percentual_sistema'] }})</td>
                    <td style="color: #64748b;">{{ $item['regiao_faturada'] }}</td>
                    <td class="text-center {{ $item['tem_tde'] == 'Sim' ? 'text-green' : '' }}">{{ $item['tem_tde'] }}</td>
                    
                    <td class="text-right" style="color: #991b1b;">
                        <strong>R$ {{ number_format($item['valor_cobrado'], 2, ',', '.') }}</strong>
                        
                        @if(($item['valor_tde_cobrado'] ?? 0) > 0)
                            <div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">
                                Frt: R$ {{ number_format($item['valor_frete_cobrado'] ?? 0, 2, ',', '.') }}<br>
                                <span style="color: #d97706;">TDE: R$ {{ number_format($item['valor_tde_cobrado'] ?? 0, 2, ',', '.') }}</span>
                            </div>
                        @endif
                    </td>
                    
                    <td class="text-right text-indigo">R$ {{ number_format($item['valor_sla'], 2, ',', '.') }}</td>
                    
                    @php
                        $diff = round($item['diferenca'], 2);
                        $colorClass = $diff == 0 ? 'color: #94a3b8;' : ($diff > 0 ? 'color: #16a34a; font-weight: bold;' : 'color: #dc2626; font-weight: bold;');
                        $sinal = $diff > 0 ? '+' : ($diff < 0 ? '-' : '');
                    @endphp
                    
                    <td class="text-right" style="{{ $colorClass }}">
                        {{ $sinal }} R$ {{ number_format(abs($diff), 2, ',', '.') }}
                    </td>
                    <td style="font-size: 8px;">
                        @if($diff == 0)
                            <span style="color: #64748b;">Validado - Cobrança Exata.</span>
                        @elseif($diff > 0)
                            <span style="color: #16a34a;">A transportadora cobrou a MENOS do que estipula a tabela matriz.</span>
                        @else
                            <span style="color: #dc2626;">A transportadora cobrou a MAIS. BWT pagou indevidamente.</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
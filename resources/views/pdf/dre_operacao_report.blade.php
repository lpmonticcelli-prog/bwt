<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DRE por Operação - Confronto BWT x E4LOG</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header p {
            margin: 5px 0 0;
            color: #64748b;
            font-size: 10px;
        }
        
        /* Painel Executivo */
        .panel {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .panel-title {
            font-size: 12px;
            font-weight: bold;
            color: #334155;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .panel-table {
            width: 100%;
            border-collapse: collapse;
        }
        .panel-table td {
            vertical-align: top;
            font-size: 11px;
            line-height: 1.6;
        }
        .highlight-box {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 8px;
            border-radius: 4px;
            text-align: center;
        }
        .highlight-title {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
        }
        .highlight-value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
        }

        /* Cores de Status */
        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }
        .text-orange { color: #ea580c; }
        .text-blue { color: #2563eb; }
        
        .bg-red { background-color: #fef2f2; color: #991b1b; }
        .bg-green { background-color: #f0fdf4; color: #166534; }
        .bg-orange { background-color: #fff7ed; color: #9a3412; }

        /* Tabela de Dados */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            text-align: left;
            word-wrap: break-word;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        
        /* Alinhamentos e Colunas */
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; }
        
        .col-nfe { width: 18%; }
        .col-cidade { width: 12%; }
        .col-regiao { width: 14%; }
        .col-valor { width: 10%; }
        .col-lucro { width: 12%; }
        .col-status { width: 12%; }
        
        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Demonstração do Resultado por Operação (DRE)</h1>
        <p>Cruzamento de Receita (BWT/Sol Fácil) versus Custos (E4LOG) | Gerado em: {{ $data_auditoria }}</p>
    </div>

    <!-- PAINEL EXECUTIVO -->
    <div class="panel">
        <div class="panel-title">Resumo Financeiro Consolidado</div>
        <table class="panel-table">
            <tr>
                <td style="width: 33%; padding-right: 10px;">
                    <div class="highlight-box">
                        <div class="highlight-title">Total de Cargas Cruzadas</div>
                        <div class="highlight-value text-blue">{{ $resumo['qtd_match'] }} NF-es</div>
                    </div>
                </td>
                <td style="width: 33%; padding-right: 10px;">
                    <div class="highlight-box">
                        <div class="highlight-title">Receita Total (Faturado Sol Fácil)</div>
                        <div class="highlight-value text-green">R$ {{ number_format($resumo['total_receita'], 2, ',', '.') }}</div>
                    </div>
                </td>
                <td style="width: 34%;">
                    <div class="highlight-box">
                        <div class="highlight-title">Custo Total (Pago E4LOG)</div>
                        <div class="highlight-value text-red">R$ {{ number_format($resumo['total_custo'], 2, ',', '.') }}</div>
                    </div>
                </td>
            </tr>
        </table>
        
        <table class="panel-table" style="margin-top: 10px;">
            <tr>
                <td style="width: 50%;">
                    <strong>Indicadores de Saúde da Operação:</strong><br>
                    <span class="text-green font-bold">✔ Operações Lucrativas:</span> {{ $resumo['qtd_lucro'] }} entregas<br>
                    <span class="text-red font-bold">✖ Operações em Prejuízo:</span> {{ $resumo['qtd_prejuizo'] }} entregas
                </td>
                <td style="width: 50%; text-align: right;">
                    <div style="font-size: 14px;">
                        <strong>Lucro Bruto Líquido (Spread):</strong> 
                        <span class="{{ $resumo['lucro_bruto'] < 0 ? 'text-red' : 'text-green' }} font-bold" style="font-size: 16px;">
                            R$ {{ number_format($resumo['lucro_bruto'], 2, ',', '.') }}
                        </span>
                    </div>
                    @php
                        $margemGeral = $resumo['total_receita'] > 0 ? ($resumo['lucro_bruto'] / $resumo['total_receita']) * 100 : 0;
                    @endphp
                    <div style="color: #64748b; margin-top: 5px; font-size: 11px;">
                        Margem Geral da Operação: <strong>{{ round($margemGeral, 2) }}%</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABELA DE DRE (NOTA A NOTA) -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-nfe">NF-e (PAINEL SOLAR)</th>
                <th class="col-cidade">DESTINO</th>
                <th class="col-regiao">MATRIZ (RECEITA X CUSTO)</th>
                <th class="col-valor text-right">RECEITA BWT</th>
                <th class="col-valor text-right">CUSTO E4LOG</th>
                <th class="col-lucro text-right">LUCRO BRUTO (R$)</th>
                <th class="col-status text-center">STATUS / MARGEM</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados as $item)
                @php
                    $isPrejuizo = $item['lucro_bruto'] < 0;
                    $isMargemBaixa = $item['status'] === 'MARGEM BAIXA';
                    
                    if ($isPrejuizo) {
                        $rowColor = 'bg-red';
                        $valorColor = 'text-red';
                        $badgeClass = 'bg-red';
                    } elseif ($isMargemBaixa) {
                        $rowColor = '';
                        $valorColor = 'text-orange';
                        $badgeClass = 'bg-orange';
                    } else {
                        $rowColor = '';
                        $valorColor = 'text-green';
                        $badgeClass = 'bg-green';
                    }
                @endphp
                <tr class="{{ $rowColor }}">
                    <td>
                        <strong style="font-size: 8px;">{{ $item['chave_nfe'] }}</strong>
                        <div style="font-size: 7px; color: #94a3b8; margin-top: 3px;">
                            SLA: {{ Str::limit($item['arquivo_bwt'], 20) }}<br>
                            E4L: {{ Str::limit($item['arquivo_e4log'], 20) }}
                        </div>
                    </td>
                    <td class="font-bold">{{ $item['cidade'] }}</td>
                    <td>
                        <div style="color: #16a34a; font-size: 8px; margin-bottom: 2px;">Venda: {{ $item['regiao_venda'] }}</div>
                        <div style="color: #dc2626; font-size: 8px;">Custo: {{ $item['regiao_custo'] }}</div>
                    </td>
                    <td class="text-right font-bold" style="color: #166534;">
                        R$ {{ number_format($item['receita_bwt'], 2, ',', '.') }}
                    </td>
                    <td class="text-right font-bold" style="color: #991b1b;">
                        R$ {{ number_format($item['custo_e4log'], 2, ',', '.') }}
                    </td>
                    <td class="text-right font-bold {{ $valorColor }}">
                        {{ $isPrejuizo ? '-' : '+' }} R$ {{ number_format(abs($item['lucro_bruto']), 2, ',', '.') }}
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $badgeClass }}">
                            {{ $item['status'] }}
                        </span>
                        <div class="font-bold" style="margin-top: 3px; font-size: 10px;">
                            {{ $item['margem_pct'] }}%
                        </div>
                    </td>
                </tr>
            @endforeach
            
            @if(count($dados) === 0)
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px; color: #64748b;">
                        Nenhuma operação correspondente (Match por NF-e) foi encontrada entre os lotes.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
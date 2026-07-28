<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Budget Financeiro Mensal - BWT</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header h1 {
            color: #1e3a8a;
            margin: 0;
            font-size: 16px;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0 0 0;
            color: #6b7280;
            font-size: 9px;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-box {
            width: 19%;
            display: inline-block;
            background-color: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 8px;
            box-sizing: border-box;
            vertical-align: top;
        }
        .kpi-title {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 4px 2px;
            text-align: right;
            white-space: nowrap;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7px;
            text-align: center;
        }
        .col-header { text-align: left !important; width: 14%; font-size: 8px; }
        .row-category {
            background-color: #e2e8f0;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            color: #1e293b;
            font-size: 8px;
        }
        .row-item {
            text-align: left;
            padding-left: 10px;
            color: #475569;
            font-size: 8px;
            white-space: normal;
        }
        .val-mes { font-weight: 500; color: #334155; font-size: 8px; }
        .val-total { background-color: #eff6ff; font-weight: bold; color: #1e3a8a; font-size: 8px; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Budget Mensal Detalhado - Auditoria Fretes ({{ $budgetAno }})</h1>
        <p>Documento Gerado em: {{ $dataEmissao }} | Abertura: Janeiro a Dezembro</p>
    </div>

    @php
        $receitaAnual = 0; $custoAnual = 0; $impostoAnual = 0;
        foreach($budgetCategories as $grupo) {
            foreach($grupo['itens'] as $item) {
                $totalItem = array_sum(array_values($item['valores']));
                if ($grupo['categoria'] === 'RECEITAS') $receitaAnual += $totalItem;
                else if ($grupo['categoria'] === 'IMPOSTOS') $impostoAnual += $totalItem;
                else $custoAnual += $totalItem;
            }
        }
        $receitaLiquidaAnual = $receitaAnual - $impostoAnual;
        $resultadoAnual = $receitaLiquidaAnual - $custoAnual;
        $margem = $receitaAnual > 0 ? ($resultadoAnual / $receitaAnual) * 100 : 0;
    @endphp

    <div class="kpi-container">
        <div class="kpi-box" style="border-left-color: #64748b;">
            <div class="kpi-title">STATUS</div>
            <div class="kpi-value">{{ $budgetStatus }}</div>
        </div>
        <div class="kpi-box" style="border-left-color: #3b82f6;">
            <div class="kpi-title">RECEITA PREVISTA</div>
            <div class="kpi-value">R$ {{ number_format($receitaAnual, 2, ',', '.') }}</div>
        </div>
        <div class="kpi-box" style="border-left-color: #ef4444;">
            <div class="kpi-title">CUSTO PREVISTO</div>
            <div class="kpi-value">R$ {{ number_format($custoAnual, 2, ',', '.') }}</div>
        </div>
        <div class="kpi-box" style="border-left-color: #10b981;">
            <div class="kpi-title">RESULTADO ANUAL</div>
            <div class="kpi-value">R$ {{ number_format($resultadoAnual, 2, ',', '.') }}</div>
        </div>
        <div class="kpi-box" style="border-left-color: #8b5cf6;">
            <div class="kpi-title">MARGEM MÉDIA</div>
            <div class="kpi-value">{{ number_format($margem, 2, ',', '.') }}%</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="col-header">CONTA</th>
                <th>JAN</th><th>FEV</th><th>MAR</th><th>ABR</th>
                <th>MAI</th><th>JUN</th><th>JUL</th><th>AGO</th>
                <th>SET</th><th>OUT</th><th>NOV</th><th>DEZ</th>
                <th style="background-color: #dbeafe;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($budgetCategories as $grupo)
                <tr>
                    <td class="row-category" colspan="14">{{ $grupo['categoria'] }}</td>
                </tr>
                @foreach($grupo['itens'] as $item)
                    @php
                        $total = array_sum(array_values($item['valores']));
                    @endphp
                    <tr>
                        <td class="row-item">{{ $item['nome'] }}</td>
                        @for($m=1; $m<=12; $m++)
                            @php $val = $item['valores'][$m] ?? 0; @endphp
                            <td class="val-mes">{{ $val != 0 ? number_format($val, 2, ',', '.') : '-' }}</td>
                        @endfor
                        <td class="val-total">{{ $total != 0 ? 'R$ '.number_format($total, 2, ',', '.') : '-' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

</body>
</html>
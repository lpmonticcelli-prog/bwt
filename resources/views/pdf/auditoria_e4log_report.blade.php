<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Auditoria CTe - E4LOG</title>
    <style>
        @page { margin: 1cm; size: A4 landscape; }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 8px; 
            color: #334155;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px solid #1e3a8a;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0; font-size: 14px; color: #1e3a8a; text-transform: uppercase;
        }
        .header p { margin: 2px 0 0 0; color: #64748b; font-size: 9px; }
        
        .panel {
            background-color: #f8fafc; border: 1px solid #cbd5e1;
            padding: 5px; margin-bottom: 10px;
        }
        .panel-table { width: 100%; border-collapse: collapse; }
        .panel-table td { padding: 2px 5px; font-size: 9px; vertical-align: top; }
        
        .ref-table { width: 100%; border-collapse: collapse; font-size: 7px; margin-top: 2px; }
        .ref-table th, .ref-table td { padding: 2px; border: 1px solid #cbd5e1; }
        .ref-table th { background-color: #e2e8f0; font-weight: bold; }

        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }
        .text-indigo { color: #4338ca; }
        .text-orange { color: #ea580c; }
        .text-gray { color: #64748b; }
        .font-bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .badge {
            padding: 1px 3px; font-size: 7px; font-weight: bold;
            display: inline-block; text-transform: uppercase; border-radius: 2px; margin-bottom: 2px;
        }
        .badge-validado { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-divergente { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-alerta { background-color: #fef08a; color: #854d0e; border: 1px solid #fde047; }
        .badge-compl { background-color: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        
        .data-table {
            width: 100%; border-collapse: collapse;
        }
        .data-table th, .data-table td {
            border: 1px solid #e2e8f0; padding: 3px; vertical-align: top;
            word-wrap: break-word; overflow-wrap: break-word;
        }
        .data-table th {
            background-color: #f1f5f9; color: #334155; font-size: 7.5px;
            font-weight: bold; text-transform: uppercase; text-align: left;
        }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .val-total { color: #0284c7; font-weight: bold; font-size: 9px; }
        .val-sub { font-size: 7.5px; }
        .tde-sub { color: #ea580c; font-size: 7.5px; }

        .w-20 { width: 20%; }
        .w-12 { width: 12%; }
        .w-10 { width: 10%; }
        .w-8 { width: 8%; }
        .w-5 { width: 5%; }
        
    </style>
</head>
<body>

    <div class="header">
        <h1>Relatório Executivo de Auditoria BWT</h1>
        <p>Documento gerado em: {{ $data_auditoria }}</p>
    </div>

    <!-- PAINEL EXECUTIVO -->
    <div class="panel">
        <div class="font-bold text-indigo" style="font-size: 9px; margin-bottom: 4px; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px;">RESUMO FINANCEIRO E PARÂMETROS (MATRIZ OFICIAL SP)</div>
        <table class="panel-table">
            <tr>
                <td style="width: 45%; border-right: 1px solid #cbd5e1;">
                    <div><span class="font-bold">Total de Documentos:</span> {{ $resumo['total_documentos'] }} operações auditadas</div>
                    <div><span class="font-bold">Faturado pela E4LOG:</span> <span class="text-red font-bold">R$ {{ number_format($resumo['total_cobrado'], 2, ',', '.') }}</span></div>
                    <div style="margin-bottom: 4px;"><span class="font-bold text-indigo">SLA Contratual:</span> <span class="font-bold">R$ {{ number_format($resumo['total_sla'], 2, ',', '.') }}</span></div>
                    
                    <div><span class="font-bold text-green">E4LOG Cobrou a Menos:</span> R$ {{ number_format($resumo['faturado_a_menos'], 2, ',', '.') }}</div>
                    <div><span class="font-bold text-red">BWT Pagou a Mais:</span> R$ {{ number_format($resumo['faturado_a_mais'], 2, ',', '.') }}</div>
                    
                    <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed #cbd5e1;">
                        <span class="font-bold">Impacto Líquido:</span> 
                        <strong class="{{ $resumo['balanco_geral'] > 0 ? 'text-red' : 'text-green' }}">
                            R$ {{ number_format(abs($resumo['balanco_geral']), 2, ',', '.') }}
                            {{ $resumo['balanco_geral'] > 0 ? '(Prejuízo)' : '(Economia)' }}
                        </strong>
                    </div>
                </td>
                
                <td style="width: 55%; padding-left: 5px;">
                    <div class="font-bold text-indigo" style="font-size: 7.5px;">TABELA E4LOG X BWT</div>
                    <table class="ref-table">
                        <tr>
                            <th>REGIÕES SÃO PAULO</th>
                            <th class="text-right">FRETE MÍNIMO</th>
                            <th class="text-right">% DA NF</th>
                        </tr>
                        <tr><td>REGIÃO 1 - SP E CAMPINAS</td><td class="text-right">R$ 200,00</td><td class="text-right">2,00%</td></tr>
                        <tr><td>REGIÃO 2 - CENTRO DO ESTADO</td><td class="text-right">R$ 250,00</td><td class="text-right">3,00%</td></tr>
                        <tr><td>REGIÃO 3 - REGIÕES DISTANTES</td><td class="text-right">R$ 350,00</td><td class="text-right">3,00%</td></tr>
                        <tr><td>REGIÃO 4 - BAIXA DEMANDA</td><td class="text-right">R$ 420,00</td><td class="text-right">4,00%</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABELA DE DADOS -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="w-20">ARQUIVO / CHAVES</th>
                <th class="w-10">LOCAL / UF</th>
                <th class="w-12">REGIÃO MATRIZ</th>
                <th class="w-12">REGIÃO FATURADA</th>
                <th class="w-5 text-center">TDE</th>
                <th class="w-8 text-right">V. CARGA</th>
                <th class="w-10 text-right">SOMA FAT.</th>
                <th class="w-10 text-right">SOMA SLA</th>
                <th class="w-8 text-right">DIFERENÇA</th>
                <th class="w-10">STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados as $item)
                <tr>
                    <!-- ARQUIVO E CHAVES -->
                    <td style="word-break: break-all;">
                        @if($item['tipo_operacao'] === 'Complemento')
                            <span class="badge badge-compl">COMPL. ÓRFÃO</span><br>
                        @endif
                        <strong style="color: #0f172a;">{{ $item['arquivo'] }}</strong><br>
                        <span class="text-gray" style="font-size: 6.5px;">CTe: {{ $item['chave_cte'] }}</span><br>
                        <span class="text-gray" style="font-size: 6.5px;">NFe: {{ $item['chave_nfe'] }}</span>
                        
                        @if(!empty($item['arquivos_complemento']))
                            <div style="margin-top: 3px; border-top: 1px dotted #cbd5e1; padding-top: 2px;">
                                <span style="font-size: 6px;" class="font-bold text-orange">+ COMPL:</span><br>
                                @foreach($item['arquivos_complemento'] as $comp)
                                    <span class="font-bold text-orange" style="font-size: 6.5px;">{{ $comp }}</span><br>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <!-- DESTINO -->
                    <td>{{ $item['cidade_destino'] }}</td>

                    <!-- REGIÃO MATRIZ -->
                    <td>
                        <span class="text-indigo font-bold">{{ $item['regiao_sistema'] }}</span><br>
                        @if($item['percentual_sistema'] !== '-')
                            <span class="text-gray" style="font-size: 6.5px;">Taxa: {{ $item['percentual_sistema'] }}</span>
                        @endif
                    </td>

                    <!-- REGIÃO FATURADA -->
                    <td>
                        <span class="font-bold" style="color: #475569;">{{ $item['regiao_faturada'] }}</span><br>
                        @if($item['percentual_faturado'] !== '-')
                            <span class="text-gray" style="font-size: 6.5px;">Taxa: {{ $item['percentual_faturado'] }}</span>
                        @endif
                    </td>

                    <!-- TDE -->
                    <td class="text-center">
                        @if($item['tem_tde'] === 'Sim')
                            <span class="text-green font-bold">SIM</span>
                        @else
                            <span class="text-gray">NÃO</span>
                        @endif
                    </td>
                    
                    <!-- V. CARGA -->
                    <td class="text-right font-bold" style="font-size: 8px;">
                        {{ number_format($item['valor_carga'], 2, ',', '.') }}
                    </td>

                    <!-- SOMA FATURADA -->
                    <td class="text-right">
                        <span class="val-total">{{ number_format($item['valor_cobrado'], 2, ',', '.') }}</span><br>
                        <span class="text-gray val-sub">Frt: {{ number_format($item['valor_frete_cobrado'], 2, ',', '.') }}</span><br>
                        <span class="tde-sub">TDE: {{ number_format($item['valor_tde_cobrado'], 2, ',', '.') }}</span>
                    </td>
                    
                    <!-- SOMA SLA -->
                    <td class="text-right">
                        <span class="val-total">{{ number_format($item['valor_sla'], 2, ',', '.') }}</span><br>
                        <span class="text-gray val-sub">Frt: {{ number_format($item['valor_frete_sla'], 2, ',', '.') }}</span><br>
                        <span class="tde-sub">TDE: {{ number_format($item['valor_tde_sla'], 2, ',', '.') }}</span>
                    </td>
                    
                    <!-- DIFERENÇA -->
                    @php
                        $diff = round($item['diferenca'], 2);
                        $colorClass = $diff == 0 ? 'color: #94a3b8;' : ($diff > 0 ? 'color: #16a34a; font-weight: bold;' : 'color: #dc2626; font-weight: bold;');
                        $sinal = $diff > 0 ? '+' : ($diff < 0 ? '-' : '');
                    @endphp
                    <td class="text-right" style="{{ $colorClass }}">
                        {{ $sinal }}{{ number_format(abs($diff), 2, ',', '.') }}
                    </td>

                    <!-- MOTIVO / STATUS -->
                    <td>
                        @if($item['status'] === 'Validado')
                            <span class="badge badge-validado">Validado</span>
                        @elseif($item['status'] === 'Divergente')
                            <span class="badge badge-divergente">Divergente</span>
                        @elseif($item['status'] === 'Alerta')
                            <span class="badge badge-alerta">Alerta</span>
                        @endif
                        <br>
                        <span style="font-size: 6.5px; color: #475569;">
                            {{ Str::limit($item['motivo'], 45) }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
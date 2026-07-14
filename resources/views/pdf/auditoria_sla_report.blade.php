<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Auditoria SLA - Sol Fácil</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; color: #2c3e50; }
        .data-header { text-align: center; color: #7f8c8d; margin-bottom: 20px; font-size: 10px; }
        .summary-box { border: 1px solid #bdc3c7; background-color: #ecf0f1; padding: 15px; margin-bottom: 25px; border-radius: 5px; }
        .summary-box h3 { margin-top: 0; color: #2980b9; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; }
        th { background-color: #f2f2f2; font-size: 9px; text-transform: uppercase; }
        .text-danger { color: #c0392b; font-weight: bold; }
        .text-success { color: #27ae60; font-weight: bold; }
        .text-info { color: #2980b9; font-weight: bold; }
        .obs { font-size: 10px; color: #7f8c8d; font-style: italic; margin-bottom: 10px;}
        .badge-tde-sim { background-color: #d4edda; color: #155724; padding: 2px 4px; border-radius: 4px; font-size: 8px; font-weight: bold; }
        .badge-tde-nao { color: #bdc3c7; font-size: 8px; }
        .badge-compl { background-color: #fde68a; color: #92400e; padding: 1px 4px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .compl-item { font-size: 8px; color: #d35400; display: block; margin-top: 3px; }
        .pct-label { font-size: 9px; color: #7f8c8d; font-weight: normal; }
        
        .status-validado { color: #27ae60; font-size: 8px; font-weight: bold; display: block; margin-bottom: 2px; }
        .status-divergente { color: #c0392b; font-size: 8px; font-weight: bold; display: block; margin-bottom: 2px; }
        .status-ignorado { color: #e67e22; font-size: 8px; font-weight: bold; display: block; margin-bottom: 2px; }
    </style>
</head>
<body>
    <h2>Auditoria SLA - Faturamento Sol Fácil</h2>
    <div class="data-header">Gerado a: {{ $data_auditoria }}</div>

    <div class="summary-box">
        <h3>Painel Executivo de Consolidação</h3>
        <table style="border: none; margin-bottom: 0;">
            <tr style="border: none;">
                <td style="border: none; width: 50%;">
                    <p><strong>Total de Operações (100% Auditadas):</strong> {{ $resumo['total_documentos'] }}</p>
                    <p><strong>Faturamento Realizado:</strong> R$ {{ number_format($resumo['total_cobrado'], 2, ',', '.') }}</p>
                    <p><strong>Faturamento Correto (SLA):</strong> R$ {{ number_format($resumo['total_sla'], 2, ',', '.') }}</p>
                </td>
                <td style="border: none; width: 50%;">
                    <p class="text-danger"><strong>Deixamos de Faturar (Perda):</strong> R$ {{ number_format($resumo['faturado_a_menos'], 2, ',', '.') }}</p>
                    <p class="text-success"><strong>Faturado Indevidamente (A maior):</strong> R$ {{ number_format($resumo['faturado_a_mais'], 2, ',', '.') }}</p>
                    <p style="font-size: 13px; margin-top: 15px;"><strong>Impacto Líquido:</strong> 
                        <span class="{{ $resumo['balanco_geral'] >= 0 ? 'text-success' : 'text-danger' }}">
                            R$ {{ number_format(abs($resumo['balanco_geral']), 2, ',', '.') }}
                        </span>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <p class="obs">
        * <strong>Autocompletar Inteligente:</strong> Cidades assinaladas com "(Auto)" ou fora de SP têm a sua região inferida pelo sistema para garantir 100% de auditoria.<br>
        * <strong>Agrupamento:</strong> CT-es de TDE são aglutinados com as suas notas originais (os valores finais refletem a soma da operação).
    </p>
    
    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Operação (Ficheiros)</th>
                <th style="width: 12%;">Destino</th>
                <th style="width: 12%;">Região Correta</th>
                <th style="width: 12%;">Região Faturada</th>
                <th style="text-align: center; width: 5%;">TDE?</th>
                <th>V. Carga</th>
                <th>Soma Faturada</th>
                <th>Soma SLA</th>
                <th>Diferença</th>
            </tr>
        </thead>
        <tbody>
            @foreach($dados as $item)
                <tr>
                    <td style="font-size: 9px; word-break: break-all;">
                        
                        @if($item['status'] == 'Validado')
                            <span class="status-validado">[ VALIDADO ]</span>
                        @elseif(str_contains($item['regiao_sistema'], 'Auto') || str_contains($item['regiao_sistema'], 'Padrão'))
                            <span class="status-ignorado">[ AUTOCOMPLETADO ]</span>
                        @else
                            <span class="status-divergente">[ DIVERGENTE ]</span>
                        @endif

                        @if($item['tipo_operacao'] == 'Complemento')
                            <span class="badge-compl" style="background-color: #ffcccc; color: #c0392b;">COMPL. ÓRFÃO</span><br>
                        @endif
                        <strong>{{ $item['arquivo'] }}</strong>
                        
                        @if(isset($item['arquivos_complemento']) && count($item['arquivos_complemento']) > 0)
                            @foreach($item['arquivos_complemento'] as $comp)
                                <span class="compl-item"><span class="badge-compl">COMPL</span> {{ $comp }}</span>
                            @endforeach
                        @endif
                    </td>
                    
                    <td style="font-size: 9px;">{{ $item['cidade_destino'] }}</td>
                    
                    <td style="font-size: 10px; font-weight: bold; color: #2980b9;">
                        {{ $item['regiao_sistema'] ?? '-' }} 
                        @if($item['percentual_sistema'] != '-')
                            <span class="pct-label">({{ $item['percentual_sistema'] }})</span>
                        @endif
                    </td>
                    
                    <td style="font-size: 10px; color: #7f8c8d;">
                        {{ $item['regiao_faturada'] ?? '-' }} 
                        @if($item['percentual_faturado'] != '-')
                            <span class="pct-label">({{ $item['percentual_faturado'] }})</span>
                        @endif
                    </td>
                    
                    <td style="text-align: center;">
                        @if(($item['tem_tde'] ?? 'Não') == 'Sim')
                            <span class="badge-tde-sim">SIM</span>
                        @else
                            <span class="badge-tde-nao">NÃO</span>
                        @endif
                    </td>

                    <td style="font-weight: bold; font-size: 10px;">R$ {{ number_format($item['valor_carga'] ?? 0, 2, ',', '.') }}</td>
                    
                    <td class="text-info" style="font-size: 10px;">
                        <strong>R$ {{ number_format($item['valor_cobrado'], 2, ',', '.') }}</strong>
                        @if(($item['valor_tde_cobrado'] ?? 0) > 0)
                            <div style="font-size: 8px; color: #7f8c8d; margin-top: 2px; font-weight: normal;">
                                Frt: R$ {{ number_format($item['valor_frete_cobrado'] ?? 0, 2, ',', '.') }}<br>
                                <span style="color: #d35400;">TDE: R$ {{ number_format($item['valor_tde_cobrado'] ?? 0, 2, ',', '.') }}</span>
                            </div>
                        @endif
                    </td>
                    
                    <td class="text-info" style="font-size: 10px;">R$ {{ number_format($item['valor_sla'], 2, ',', '.') }}</td>
                    
                    <td class="{{ $item['diferenca'] == 0 ? '' : ($item['diferenca'] > 0 ? 'text-danger' : 'text-success') }}" style="font-weight: bold; font-size: 10px;">
                        @if($item['diferenca'] != 0)
                            R$ {{ number_format(abs($item['diferenca']), 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
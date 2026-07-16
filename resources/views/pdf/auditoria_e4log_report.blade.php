<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Auditoria CTe - E4LOG</title>
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
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 15px;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 9px;
        }
        
        /* Painel Executivo */
        .panel {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .panel-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            text-transform: uppercase;
        }
        .panel-table {
            width: 100%;
            border-collapse: collapse;
        }
        .panel-table td {
            padding: 2px 8px;
            font-size: 9px;
            vertical-align: top;
            border: none;
        }
        
        /* Tabela Matriz Referência no Topo */
        .ref-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
            text-align: left;
            margin-top: 4px;
        }
        .ref-table th {
            background-color: #e2e8f0;
            padding: 3px;
            color: #1e293b;
            font-weight: bold;
            border: 1px solid #cbd5e1;
        }
        .ref-table td {
            padding: 3px;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        /* Cores e Utilitários */
        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }
        .text-indigo { color: #4338ca; }
        .font-bold { font-weight: bold; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        
        /* Tipografia de Matemática Quebrada (Imagem) */
        .val-soma-total {
            color: #0284c7; 
            font-weight: bold;
            font-size: 9.5px;
            margin-bottom: 2px;
        }
        .text-breakdown-frt {
            color: #94a3b8;
            font-size: 8px;
            margin-top: 1px;
        }
        .text-breakdown-tde {
            color: #ea580c; /* Laranja da imagem */
            font-size: 8px;
            margin-top: 1px;
        }
        
        /* Badges de Status */
        .badge {
            padding: 2px 4px;
            font-size: 7px;
            font-weight: bold;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-validado { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-divergente { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-alerta { background-color: #fef08a; color: #854d0e; border: 1px solid #fde047; }
        .badge-compl { background-color: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        
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
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        /* Larguras das Colunas */
        .col-arquivo { width: 12%; }
        .col-destino { width: 10%; }
        .col-matriz { width: 14%; }
        .col-faturada { width: 13%; }
        .col-tde { width: 4%; }
        .col-carga { width: 9%; font-weight: bold; font-size: 9px; }
        .col-soma-fat { width: 10%; }
        .col-soma-sla { width: 10%; }
        .col-diff { width: 8%; }
        .col-motivo { width: 10%; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Relatório Executivo de Auditoria BWT</h1>
        <p>Documento gerado em: {{ $data_auditoria }}</p>
    </div>

    <!-- PAINEL EXECUTIVO -->
    <div class="panel">
        <div class="panel-title">Resumo Financeiro e Parâmetros (Matriz Oficial SP)</div>
        <table class="panel-table">
            <tr>
                <td style="width: 45%; border-right: 1px solid #cbd5e1; padding-right: 10px;">
                    <div style="margin-bottom: 4px;"><span class="font-bold">Total de Documentos:</span> {{ $resumo['total_documentos'] }} operações auditadas</div>
                    <div style="margin-bottom: 4px;"><span class="font-bold">Faturado pela E4LOG:</span> <span class="text-red font-bold">R$ {{ number_format($resumo['total_cobrado'], 2, ',', '.') }}</span></div>
                    <div style="margin-bottom: 8px;"><span class="font-bold text-indigo">SLA Contratual (Matriz):</span> <span class="font-bold">R$ {{ number_format($resumo['total_sla'], 2, ',', '.') }}</span></div>
                    
                    <div style="margin-bottom: 4px;">
                        <span class="font-bold text-green">A favor da E4LOG (Cobrou a Menos):</span> 
                        R$ {{ number_format($resumo['faturado_a_menos'], 2, ',', '.') }}
                    </div>
                    <div style="margin-bottom: 4px;">
                        <span class="font-bold text-red">A favor da BWT (Pagou a Mais):</span> 
                        R$ {{ number_format($resumo['faturado_a_mais'], 2, ',', '.') }}
                    </div>
                    <div style="margin-top: 6px; border-top: 1px dashed #cbd5e1; padding-top: 6px;">
                        <span class="font-bold">Impacto Líquido:</span> 
                        <strong class="{{ $resumo['balanco_geral'] > 0 ? 'text-red' : 'text-green' }}" style="font-size: 10px;">
                            R$ {{ number_format(abs($resumo['balanco_geral']), 2, ',', '.') }}
                            {{ $resumo['balanco_geral'] > 0 ? '(Prejuízo BWT)' : '(Economia BWT)' }}
                        </strong>
                    </div>
                </td>
                
                <td style="width: 55%; padding-left: 10px;">
                    <div style="font-weight: bold; color: #1e3a8a; font-size: 8px; text-transform: uppercase;">TABELA E4LOG X BWT (ESTADO DE SP)</div>
                    <table class="ref-table">
                        <tr>
                            <th style="width: 50%;">REGIÕES SÃO PAULO</th>
                            <th style="width: 25%; text-align: right;">FRETE MÍNIMO</th>
                            <th style="width: 25%; text-align: right;">% DA NF</th>
                        </tr>
                        <tr>
                            <td><strong>REGIÃO 1 - REGIÃO DE SP E CAMPINAS</strong></td>
                            <td class="text-right">R$ 200,00</td>
                            <td class="text-right">2,00%</td>
                        </tr>
                        <tr>
                            <td><strong>REGIÃO 2 - CENTRO DO ESTADO</strong></td>
                            <td class="text-right">R$ 250,00</td>
                            <td class="text-right">3,00%</td>
                        </tr>
                        <tr>
                            <td><strong>REGIÃO 3 - REGIÕES DISTANTES</strong></td>
                            <td class="text-right">R$ 350,00</td>
                            <td class="text-right">3,00%</td>
                        </tr>
                        <tr>
                            <td><strong>REGIÃO 4 - BAIXA DEMANDA</strong></td>
                            <td class="text-right">R$ 420,00</td>
                            <td class="text-right">4,00%</td>
                        </tr>
                    </table>
                    <div style="font-size: 7px; color: #64748b; margin-top: 4px; font-weight: bold;">
                        
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- TABELA DE DADOS -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="col-arquivo">ARQUIVO / CHAVES</th>
                <th class="col-destino">LOCAL / UF</th>
                <th class="col-matriz">REGIÃO MATRIZ</th>
                <th class="col-faturada">REGIÃO FATURADA</th>
                <th class="col-tde text-center">TDE</th>
                <th class="col-carga text-left">V. CARGA</th>
                <th class="col-soma-fat text-left">SOMA FATURADA</th>
                <th class="col-soma-sla text-left">SOMA SLA</th>
                <th class="col-diff text-left">DIFERENÇA</th>
                <th class="col-motivo">MOTIVO / STATUS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($dados as $item)
                <tr>
                    <!-- ARQUIVO E CHAVES -->
                    <td>
                        @if($item['tipo_operacao'] === 'Complemento')
                            <span class="badge badge-compl" style="margin-bottom: 3px;">COMPL. ÓRFÃO</span><br>
                        @endif
                        <strong style="color: #0f172a; word-break: break-all;">{{ $item['arquivo'] }}</strong>
                        <div style="font-size: 7px; color: #64748b; margin-top: 3px; word-break: break-all;">CTe: {{ $item['chave_cte'] }}</div>
                        <div style="font-size: 7px; color: #64748b; word-break: break-all;">NFe: {{ $item['chave_nfe'] }}</div>
                        
                        <!-- VISUAL DO COMPLEMENTO VINCULADO EM LARANJA -->
                        @if(!empty($item['arquivos_complemento']))
                            <div style="margin-top: 5px; padding-top: 4px; border-top: 1px dotted #cbd5e1;">
                                <span style="font-size: 7px; color: #ea580c; font-weight: bold; text-transform: uppercase;">+ COMPLEMENTO VINCULADO:</span>
                                @foreach($item['arquivos_complemento'] as $comp)
                                    <div style="font-size: 7.5px; font-weight: bold; color: #ea580c; word-break: break-all; margin-top: 2px;">{{ $comp }}</div>
                                @endforeach
                            </div>
                        @endif
                    </td>

                    <!-- DESTINO -->
                    <td>{{ $item['cidade_destino'] }}</td>

                    <!-- REGIÃO MATRIZ -->
                    <td>
                        <span class="text-indigo font-bold">{{ $item['regiao_sistema'] }}</span>
                        @if($item['percentual_sistema'] !== '-')
                            <br><span style="font-size: 7.5px; color: #64748b;">Taxa: {{ $item['percentual_sistema'] }}</span>
                        @endif
                    </td>

                    <!-- REGIÃO FATURADA -->
                    <td>
                        <span style="color: #475569; font-weight: bold;">{{ $item['regiao_faturada'] }}</span>
                        @if($item['percentual_faturado'] !== '-')
                            <br><span style="font-size: 7.5px; color: #64748b;">Taxa: {{ $item['percentual_faturado'] }}</span>
                        @endif
                    </td>

                    <!-- TDE -->
                    <td class="text-center">
                        @if($item['tem_tde'] === 'Sim')
                            <span class="text-green font-bold">SIM</span>
                        @else
                            <span style="color: #94a3b8;">NÃO</span>
                        @endif
                    </td>
                    
                    <!-- V. CARGA -->
                    <td class="col-carga">
                        R$ {{ number_format($item['valor_carga'], 2, ',', '.') }}
                    </td>

                    <!-- SOMA FATURADA (Sempre exibe os 3 campos independente de TDE zerado) -->
                    <td>
                        <div class="val-soma-total">
                            R$ {{ number_format($item['valor_cobrado'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($item['valor_frete_cobrado'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($item['valor_tde_cobrado'], 2, ',', '.') }}
                        </div>
                    </td>
                    
                    <!-- SOMA SLA (Sempre exibe os 3 campos independente de TDE zerado) -->
                    <td>
                        <div class="val-soma-total">
                            R$ {{ number_format($item['valor_sla'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-frt">
                            Frt: R$ {{ number_format($item['valor_frete_sla'], 2, ',', '.') }}
                        </div>
                        <div class="text-breakdown-tde">
                            TDE: R$ {{ number_format($item['valor_tde_sla'], 2, ',', '.') }}
                        </div>
                    </td>
                    
                    <!-- DIFERENÇA -->
                    @php
                        $diff = round($item['diferenca'], 2);
                        $colorClass = $diff == 0 ? 'color: #94a3b8;' : ($diff > 0 ? 'color: #16a34a; font-weight: bold;' : 'color: #dc2626; font-weight: bold;');
                        $sinal = $diff > 0 ? '+' : ($diff < 0 ? '-' : '');
                    @endphp
                    <td style="{{ $colorClass }}">
                        {{ $sinal }} R$ {{ number_format(abs($diff), 2, ',', '.') }}
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
                        
                        <div style="margin-top: 4px; font-size: 7.5px; color: #475569; line-height: 1.2;">
                            {{ $item['motivo'] }}
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
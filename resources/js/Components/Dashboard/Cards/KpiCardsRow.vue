<script setup>
import { computed } from 'vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    cruzamentoViagens: { type: Array, default: () => [] },
    fretesDetalhados: { type: Array, default: () => [] },
    resumoFaturamento: { type: Object, required: true },
    resumoAuditoria: { type: Object, required: true },
    totaisCorretos: { type: Object, required: true },
    totaisDivergentes: { type: Object, required: true },
    totaisMatch: { type: Object, required: true }
});

const { formatMoney } = useFormatters();

// =========================================================================
// INTELIGÊNCIA DE DATAS E QUINZENAS
// =========================================================================
const dataRangeText = computed(() => {
    const fretes = props.fretesDetalhados || [];
    if (fretes.length === 0) return 'Nenhum período disponível';
    
    let datas = fretes.map(f => f.data_emissao).filter(Boolean).sort();
    if (datas.length === 0) return 'Datas não informadas';

    const min = new Date(datas[0] + 'T00:00:00').toLocaleDateString('pt-BR');
    const max = new Date(datas[datas.length - 1] + 'T00:00:00').toLocaleDateString('pt-BR');
    return `${min} até ${max}`;
});

const periodosAgrupados = computed(() => {
    const fretes = props.fretesDetalhados || [];
    if (fretes.length === 0) return [];

    let grupos = {};

    fretes.forEach(frete => {
        if (!frete.data_emissao) return;
        const data = new Date(frete.data_emissao + 'T00:00:00');
        const dia = data.getDate();
        const mesAno = data.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
        
        // Formata o nome do mês com primeira letra maiúscula
        const mesAnoFormatado = mesAno.charAt(0).toUpperCase() + mesAno.slice(1);

        // Define se é 1ª ou 2ª Quinzena
        const quinzenaNome = dia <= 15 ? `1ª Quinzena de ${mesAnoFormatado}` : `2ª Quinzena de ${mesAnoFormatado}`;
        const custo = Number(frete.custo_e4log || frete.custo_total || 0);

        if (!grupos[quinzenaNome]) {
            grupos[quinzenaNome] = { 
                label: quinzenaNome, 
                total: 0, 
                count: 0, 
                minDate: frete.data_emissao 
            };
        }
        grupos[quinzenaNome].total += custo;
        grupos[quinzenaNome].count += 1;
        
        if (frete.data_emissao < grupos[quinzenaNome].minDate) {
            grupos[quinzenaNome].minDate = frete.data_emissao;
        }
    });

    // Ordena os grupos cronologicamente pela menor data
    return Object.values(grupos).sort((a, b) => a.minDate.localeCompare(b.minDate));
});
</script>

<template>
    <div class="space-y-6 mb-8">
        
        <!-- Linha Superior: Os 2 Cards Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Card 1: Total de Notas -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow-md transition-all">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total de Notas</p>
                            <p class="text-sm text-slate-400">Documentos capturados da E4LOG</p>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-4xl font-black text-slate-800 tracking-tight">{{ fretesDetalhados.length }} <span class="text-lg font-medium text-slate-400 tracking-normal">CT-es</span></h3>
                </div>
            </div>

            <!-- Card 2: Valor Total Faturado -->
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-6 border border-slate-700 shadow-md hover:shadow-xl transition-all relative overflow-hidden">
                <div class="absolute right-0 top-0 w-32 h-32 bg-gradient-to-bl from-orange-500/20 to-transparent rounded-bl-full -mr-4 -mt-4 pointer-events-none"></div>
                <div class="flex items-center justify-between mb-4 relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/10 text-orange-400 flex items-center justify-center border border-white/10 backdrop-blur-sm">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-300 uppercase tracking-wider">Custo Faturado (E4LOG)</p>
                            <p class="text-sm text-slate-400">Soma exata das faturas</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full bg-orange-500/20 text-orange-400 border border-orange-500/30 shadow-sm">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Monitoramento Ativo
                    </span>
                </div>
                <div class="relative z-10">
                    <h3 class="text-4xl font-black text-white tracking-tight truncate" :title="formatMoney(totaisCorretos.valor)">{{ formatMoney(totaisCorretos.valor) }}</h3>
                </div>
            </div>

        </div>

        <!-- Card Inteligente de Período (Quinzena / Mês) -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm transition-all">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 text-base">Evolução por Período</h4>
                        <p class="text-xs text-slate-500">Intervalo analisado: <span class="font-semibold text-indigo-600">{{ dataRangeText }}</span></p>
                    </div>
                </div>
                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                    Organizado por Quinzena
                </span>
            </div>

            <!-- Listagem Dinâmica das Quinzenas / Meses -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="(item, index) in periodosAgrupados" :key="index" class="bg-slate-50/80 rounded-xl p-4 border border-slate-200 hover:border-indigo-200 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-black text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">{{ item.label }}</span>
                        <span class="text-xs font-bold text-slate-500">{{ item.count }} CT-es</span>
                    </div>
                    <div class="mt-3">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Custo do Período</p>
                        <p class="text-xl font-black text-slate-800 tracking-tight">{{ formatMoney(item.total) }}</p>
                    </div>
                </div>
                
                <div v-if="periodosAgrupados.length === 0" class="col-span-full py-6 text-center text-slate-400 text-sm font-medium">
                    Nenhum dado de emissão encontrado para gerar o agrupamento por período.
                </div>
            </div>
        </div>

    </div>
</template>
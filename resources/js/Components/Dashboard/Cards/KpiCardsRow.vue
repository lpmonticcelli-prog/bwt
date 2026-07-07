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

const emit = defineEmits(['abrir-kpi']);
const { formatMoney } = useFormatters();

// Correção da Matemática: A margem agora reflete o lucro da operação visível na tela
const lucroLiquidoExibido = computed(() => {
    return props.totaisMatch.lucro - props.totaisMatch.prejuizo;
});

const margemLiquida = computed(() => {
    if (!props.resumoFaturamento.receita_total || props.resumoFaturamento.receita_total === 0) return 0;
    return ((lucroLiquidoExibido.value / props.resumoFaturamento.receita_total) * 100).toFixed(1);
});

const gapReceita = computed(() => {
    const gap = (props.resumoFaturamento.receita_teorica || 0) - (props.resumoFaturamento.receita_total || 0);
    return gap > 0 ? gap : 0;
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-5 mb-8">
        
        <!-- Card 1: Total de Viagens -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-all relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-xl bg-slate-50 text-slate-600 border border-slate-200 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                </div>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Total de Viagens</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">{{ cruzamentoViagens.length }} <span class="text-sm font-medium text-slate-400 tracking-normal">Documentos cruzados</span></h3>
            </div>
        </div>

        <!-- Card 2: Receita Faturada -->
        <div @click="emit('abrir-kpi', 'gap')" class="cursor-pointer bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center group-hover:bg-blue-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span v-if="gapReceita > 0" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 shadow-sm">
                    ⚠️ Faltou cobrar: {{ formatMoney(gapReceita) }}
                </span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 group-hover:text-blue-600 transition-colors">Receita Faturada</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate" :title="formatMoney(resumoFaturamento.receita_total)">{{ formatMoney(resumoFaturamento.receita_total) }}</h3>
            </div>
        </div>

        <!-- Card 3: Custo Aprovado -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md transition-all relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 border border-orange-100 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path></svg>
                </div>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Conferido
                </span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Custo Aprovado</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate" :title="formatMoney(totaisCorretos.liberado)">{{ formatMoney(totaisCorretos.liberado) }}</h3>
            </div>
        </div>

        <!-- Card 4: Cobranças Bloqueadas (Antiga Glosa) -->
        <div @click="emit('abrir-kpi', 'divergencia')" class="cursor-pointer bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-red-300 transition-all relative group">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center group-hover:bg-rose-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
                <span v-if="totaisDivergentes.glosa > 0" class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-rose-50 text-rose-700 border border-rose-200 shadow-sm">
                    Bloqueado
                </span>
            </div>
            <div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 group-hover:text-rose-600 transition-colors">Cobranças Bloqueadas</p>
                <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate group-hover:text-rose-600 transition-colors" :title="formatMoney(totaisDivergentes.glosa)">{{ formatMoney(totaisDivergentes.glosa) }}</h3>
            </div>
        </div>

        <!-- Card 5: Lucro da Operação (Antigo EBITDA) -->
        <div @click="emit('abrir-kpi', 'fuga')" class="cursor-pointer bg-gradient-to-br from-slate-900 to-slate-800 rounded-2xl p-5 border border-slate-700 shadow-md hover:shadow-xl hover:border-indigo-500/50 transition-all relative group overflow-hidden">
            <div class="absolute right-0 top-0 w-32 h-32 bg-gradient-to-bl from-indigo-500/10 to-transparent rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110 pointer-events-none"></div>
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div class="w-10 h-10 rounded-xl bg-white/10 text-white flex items-center justify-center border border-white/10 backdrop-blur-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                </div>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-indigo-500 text-white border border-indigo-400 shadow-sm group-hover:bg-indigo-400 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> Margem {{ margemLiquida }}%
                </span>
            </div>
            <div class="relative z-10">
                <p class="text-[11px] font-bold text-indigo-300 uppercase tracking-wider mb-1">Lucro da Operação</p>
                <h3 class="text-2xl font-black text-white tracking-tight truncate" :title="formatMoney(lucroLiquidoExibido)">{{ formatMoney(lucroLiquidoExibido) }}</h3>
            </div>
        </div>

    </div>
</template>
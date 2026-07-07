<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import KpiCardsRow from '@/Components/Dashboard/Cards/KpiCardsRow.vue';
import MatchTable from '@/Components/Dashboard/Tables/MatchTable.vue';
import DivergenciasTable from '@/Components/Dashboard/Tables/DivergenciasTable.vue';
import ValidadosTable from '@/Components/Dashboard/Tables/ValidadosTable.vue';
import SuperModalDacte from '@/Components/Dashboard/Modais/SuperModalDacte.vue';
import ModalKpi from '@/Components/Dashboard/Modais/ModalKpi.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    resumoFaturamento: { type: Object, default: () => ({ total_notas: 0, receita_total: 0, receita_teorica: 0, lucro_total: 0 }) },
    resumoAuditoria: { type: Object, default: () => ({ total_notas: 0, custo_cobrado: 0, custo_correto: 0, diferenca_total: 0 }) },
    fechamentos: { type: Array, default: () => [] },
    fechamento_id: { type: [String, Number], default: '' },
    fretesDetalhados: { type: Array, default: () => [] },
    faturamentosDetalhados: { type: Array, default: () => [] },
    cruzamentoViagens: { type: Array, default: () => [] } 
});

const { formatMoney } = useFormatters();

// Controle de Filtro
const filtroSelecionado = ref(props.fechamento_id || '');
const aplicarFiltro = () => router.get('/dashboard', { fechamento_id: filtroSelecionado.value }, { preserveState: true, preserveScroll: true });

// Controle de Abas
const abaExpandida = ref('match'); 
const alternarAba = (aba) => { abaExpandida.value = abaExpandida.value === aba ? null : aba; };

// Lógica MECE para os micro-cards das Abas
const totaisMatch = computed(() => {
    let lucro = 0; let prejuizo = 0;
    props.cruzamentoViagens.forEach(v => {
        const l = Number(v.lucro) || 0;
        if (l > 0) lucro += l;
        else if (l < 0) prejuizo += Math.abs(l);
    });
    return { lucro, prejuizo };
});

const fretesDivergentes = computed(() => props.fretesDetalhados.filter(f => !f.is_correto));
const fretesCorretos = computed(() => props.fretesDetalhados.filter(f => f.is_correto));

const totaisDivergentes = computed(() => {
    let glosa = 0; let ganho = 0;
    fretesDivergentes.value.forEach(f => {
        const dif = Number(f.diferenca) || 0;
        if (dif > 0) glosa += dif;
        else if (dif < 0) ganho += Math.abs(dif);
    });
    return { glosa, ganho };
});

const totaisCorretos = computed(() => {
    let liberado = 0;
    fretesCorretos.value.forEach(f => {
        liberado += Number(f.cobrado) || 0;
    });
    return { liberado };
});

// Controle de Modais
const dossieViagemSelecionado = ref(null); 
const kpiModalAberto = ref(null); 

const fecharTudo = () => { dossieViagemSelecionado.value = null; kpiModalAberto.value = null; };
const abrirDossieMatch = (viagem) => { kpiModalAberto.value = null; dossieViagemSelecionado.value = viagem; };
const abrirDossiePorArquivo = (arquivoXML) => {
    const viagemCompleta = props.cruzamentoViagens.find(v => 
        v.e4log_detalhes.some(e => e.arquivo === arquivoXML) || 
        v.bwt_detalhes.some(b => b.arquivo === arquivoXML)
    );
    if (viagemCompleta) abrirDossieMatch(viagemCompleta);
    else alert('Este documento não pôde ser rastreado no cruzamento completo das viagens.');
};
const abrirKpiModal = (tipo) => { kpiModalAberto.value = tipo; };
</script>

<template>
    <Head title="Painel Executivo - BWT ERP" />

    <ErpLayout>
        <template #header-title>Painel Executivo</template>
        <template #header-subtitle>Inteligência de Custos</template>
        
        <template #header-actions>
            <div class="hidden sm:flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-transparent border-none text-slate-700 text-sm font-bold focus:ring-0 p-0 cursor-pointer outline-none w-48">
                    <option value="">Visão Global (Consolidado)</option>
                    <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
                </select>
            </div>
        </template>

        <div class="animate-fade-in">
            <KpiCardsRow 
                :cruzamentoViagens="cruzamentoViagens"
                :fretesDetalhados="fretesDetalhados"
                :resumoFaturamento="resumoFaturamento"
                :resumoAuditoria="resumoAuditoria"
                :totaisCorretos="totaisCorretos"
                :totaisDivergentes="totaisDivergentes"
                :totaisMatch="totaisMatch"
                @abrir-kpi="abrirKpiModal"
            />

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                <button @click="alternarAba('match')" class="group relative flex justify-between items-start sm:items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99] flex-col sm:flex-row gap-4" :class="abaExpandida === 'match' ? 'border-indigo-400 ring-2 ring-indigo-400/20 bg-indigo-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="bg-indigo-50 text-indigo-500 p-3 rounded-xl transition-colors group-hover:bg-indigo-100 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div class="text-left">
                            <h4 class="font-bold text-slate-800 text-sm">Rentabilidade por Viagem</h4>
                            <p class="text-[10px] text-slate-500 mt-0.5"><span class="font-bold text-indigo-600">{{ cruzamentoViagens.length }}</span> Casamentos (NF-e)</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <div class="flex flex-col items-end gap-1.5">
                            <div class="flex justify-between items-center bg-emerald-50 border border-emerald-100 rounded-md px-1.5 py-0.5 min-w-[110px]">
                                <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest">Lucro</span>
                                <span class="text-[9px] font-black text-emerald-700">{{ formatMoney(totaisMatch.lucro) }}</span>
                            </div>
                            <div class="flex justify-between items-center bg-rose-50 border border-rose-100 rounded-md px-1.5 py-0.5 min-w-[110px]">
                                <span class="text-[8px] font-black text-rose-600 uppercase tracking-widest">Prejuízo</span>
                                <span class="text-[9px] font-black text-rose-700">{{ formatMoney(totaisMatch.prejuizo) }}</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300 hidden sm:block" :class="abaExpandida === 'match' ? 'rotate-180 text-indigo-500' : 'group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </button>

                <button @click="alternarAba('divergentes')" class="group relative flex justify-between items-start sm:items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99] flex-col sm:flex-row gap-4" :class="abaExpandida === 'divergentes' ? 'border-red-400 ring-2 ring-red-400/20 bg-red-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="bg-red-50 text-red-500 p-3 rounded-xl transition-colors group-hover:bg-red-100 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div class="text-left">
                            <h4 class="font-bold text-slate-800 text-sm">Divergências E4LOG</h4>
                            <p class="text-[10px] text-slate-500 mt-0.5"><span class="font-bold text-red-600">{{ fretesDivergentes.length }}</span> XMLs reprovados</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <div class="flex flex-col items-end gap-1.5">
                            <div class="flex justify-between items-center bg-rose-50 border border-rose-100 rounded-md px-1.5 py-0.5 min-w-[110px]">
                                <span class="text-[8px] font-black text-rose-600 uppercase tracking-widest">Glosa (Risco)</span>
                                <span class="text-[9px] font-black text-rose-700">{{ formatMoney(totaisDivergentes.glosa) }}</span>
                            </div>
                            <div class="flex justify-between items-center bg-amber-50 border border-amber-100 rounded-md px-1.5 py-0.5 min-w-[110px]">
                                <span class="text-[8px] font-black text-amber-600 uppercase tracking-widest">Ganho Extra</span>
                                <span class="text-[9px] font-black text-amber-700">{{ formatMoney(totaisDivergentes.ganho) }}</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300 hidden sm:block" :class="abaExpandida === 'divergentes' ? 'rotate-180 text-red-500' : 'group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </button>

                <button @click="alternarAba('corretos')" class="group relative flex justify-between items-start sm:items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99] flex-col sm:flex-row gap-4" :class="abaExpandida === 'corretos' ? 'border-emerald-400 ring-2 ring-emerald-400/20 bg-emerald-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="bg-emerald-50 text-emerald-500 p-3 rounded-xl transition-colors group-hover:bg-emerald-100 shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div class="text-left">
                            <h4 class="font-bold text-slate-800 text-sm">Custos Validados</h4>
                            <p class="text-[10px] text-slate-500 mt-0.5"><span class="font-bold text-emerald-600">{{ fretesCorretos.length }}</span> XMLs aprovados</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <div class="flex flex-col items-end gap-1.5">
                            <div class="flex justify-between items-center bg-emerald-50 border border-emerald-100 rounded-md px-1.5 py-1 min-w-[140px]">
                                <span class="text-[8px] font-black text-emerald-600 uppercase tracking-widest">Lote Aprovado</span>
                                <span class="text-[10px] font-black text-emerald-700">{{ formatMoney(totaisCorretos.liberado) }}</span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-slate-400 transition-transform duration-300 hidden sm:block" :class="abaExpandida === 'corretos' ? 'rotate-180 text-emerald-500' : 'group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </div>
                </button>
            </div>

            <MatchTable v-if="abaExpandida === 'match'" :viagens="cruzamentoViagens" @abrir-dossie="abrirDossieMatch" />
            <DivergenciasTable v-if="abaExpandida === 'divergentes'" :fretes="fretesDivergentes" :viagens="cruzamentoViagens" @abrir-dossie="abrirDossiePorArquivo" />
            <ValidadosTable v-if="abaExpandida === 'corretos'" :fretes="fretesCorretos" :viagens="cruzamentoViagens" @abrir-dossie="abrirDossiePorArquivo" />

        </div>

        <ModalKpi v-if="kpiModalAberto" :tipo="kpiModalAberto" :viagens="cruzamentoViagens" :faturamentos="faturamentosDetalhados" @fechar="fecharTudo" @abrir-dossie="abrirDossieMatch" />
        <SuperModalDacte v-if="dossieViagemSelecionado" :viagem="dossieViagemSelecionado" @fechar="fecharTudo" />
    </ErpLayout>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
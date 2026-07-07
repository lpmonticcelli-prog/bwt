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

const props = defineProps({
    resumoFaturamento: Object,
    resumoAuditoria: Object,
    fechamentos: Array,
    fechamento_id: [String, Number],
    fretesDetalhados: Array,
    faturamentosDetalhados: Array,
    cruzamentoViagens: Array 
});

// Apenas Estado Local e Roteamento!
const filtroSelecionado = ref(props.fechamento_id || '');
const aplicarFiltro = () => router.get('/dashboard', { fechamento_id: filtroSelecionado.value }, { preserveState: true, preserveScroll: true });

const abaExpandida = ref('match'); 
const alternarAba = (aba) => { abaExpandida.value = abaExpandida.value === aba ? null : aba; };

// Controle de Modais
const dossieViagemSelecionado = ref(null); 
const kpiModalAberto = ref(null); 

const fecharTudo = () => { dossieViagemSelecionado.value = null; kpiModalAberto.value = null; };
const abrirDossieMatch = (viagem) => { kpiModalAberto.value = null; dossieViagemSelecionado.value = viagem; };
const abrirKpiModal = (tipo) => { kpiModalAberto.value = tipo; };
</script>

<template>
    <Head title="Painel de Controle - BWT Logística" />

    <ErpLayout>
        <template #header-actions>
            <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm font-bold focus:ring-0 rounded-xl p-2 cursor-pointer w-64">
                <option value="">Visão Global (Consolidado)</option>
                <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
            </select>
        </template>

        <KpiCardsRow 
            :cruzamentoViagens="cruzamentoViagens"
            :fretesDetalhados="fretesDetalhados"
            :resumoFaturamento="resumoFaturamento"
            :resumoAuditoria="resumoAuditoria"
        />

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-5">
           </div>

        <MatchTable v-if="abaExpandida === 'match'" :viagens="cruzamentoViagens" @abrir-dossie="abrirDossieMatch" />
        <DivergenciasTable v-if="abaExpandida === 'divergentes'" :fretes="fretesDetalhados.filter(f => !f.is_correto)" :viagens="cruzamentoViagens" />
        <ValidadosTable v-if="abaExpandida === 'corretos'" :fretes="fretesDetalhados.filter(f => f.is_correto)" :viagens="cruzamentoViagens" />

        <ModalKpi v-if="kpiModalAberto" :tipo="kpiModalAberto" :viagens="cruzamentoViagens" :faturamentos="faturamentosDetalhados" @fechar="fecharTudo" @abrir-dossie="abrirDossieMatch" />
        <SuperModalDacte v-if="dossieViagemSelecionado" :viagem="dossieViagemSelecionado" @fechar="fecharTudo" />
    </ErpLayout>
</template>
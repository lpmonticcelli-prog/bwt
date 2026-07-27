<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import KpiCardsRow from '@/Components/Dashboard/Cards/KpiCardsRow.vue';
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

// Por padrão a tela carrega o filtro "Mês Consolidado (Todas as Notas)"
const filtroSelecionado = ref(props.fechamento_id || '');

const aplicarFiltro = () => {
    if (filtroSelecionado.value !== '' && filtroSelecionado.value !== 'q1' && filtroSelecionado.value !== 'q2') {
        router.get('/faturamento', { fechamento_id: filtroSelecionado.value }, { preserveState: true, preserveScroll: true });
    }
};

const dossieViagemSelecionado = ref(null); 
const kpiModalAberto = ref(null); 

const fecharTudo = () => { dossieViagemSelecionado.value = null; kpiModalAberto.value = null; };
const abrirDossieMatch = (viagem) => { kpiModalAberto.value = null; dossieViagemSelecionado.value = viagem; };
const abrirKpiModal = (tipo) => { kpiModalAberto.value = tipo; };

// =========================================================================
// FILTRO DE DATAS INTELIGENTE (FRONTEND)
// =========================================================================
const fretesGerais = computed(() => {
    let fretes = props.fretesDetalhados || [];

    if (filtroSelecionado.value === 'q1' || filtroSelecionado.value === 'q2') {
        return fretes.filter(frete => {
            if (!frete.data_emissao) return false;
            
            const diaStr = frete.data_emissao.substring(8, 10);
            const dia = parseInt(diaStr, 10);

            if (filtroSelecionado.value === 'q1') {
                return dia >= 1 && dia <= 15;
            } else {
                return dia >= 16;
            }
        });
    }
    
    return fretes;
});

const viagensSeguras = computed(() => props.cruzamentoViagens || []);

const faturadosE4log = computed(() => {
    return fretesGerais.value.filter(f => f.e4log_faturado === true || f.custo_e4log > 0);
});

const fretesDivergentes = computed(() => {
    return fretesGerais.value.filter(f => !f.is_correto);
});

const fretesCorretos = computed(() => {
    return fretesGerais.value.filter(f => f.is_correto || f.e4log_faturado);
});

const formatCurrency = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

// =========================================================================
// MATEMÁTICA DEFINITIVA DOS CARDS
// =========================================================================
const safeNumber = (val) => {
    if (val === null || val === undefined || val === '') return 0;
    let strVal = String(val).replace(',', '.');
    let parsed = Number(strVal);
    return isNaN(parsed) ? 0 : parsed;
};

const totaisDinamicos = computed(() => {
    let receita_teorica = 0;
    let receita_real = 0;
    let custo = 0;

    faturadosE4log.value.forEach(frete => {
        receita_teorica += safeNumber(frete.receita_teorica);
        receita_real += safeNumber(frete.receita_real);
        custo += safeNumber(frete.custo_e4log) || safeNumber(frete.custo_total);
    });
    
    let receitaBaseLucro = receita_real > 0 ? receita_real : receita_teorica;

    return {
        receita_teorica: receita_teorica,
        receita_real: receita_real,
        custo: custo,
        lucro: receitaBaseLucro - custo
    };
});

const viagensDinamicas = computed(() => {
    if (props.cruzamentoViagens && props.cruzamentoViagens.length > 0) {
        return props.cruzamentoViagens;
    }
    
    return faturadosE4log.value.map(frete => ({
        ...frete,
        custo_aprovado: safeNumber(frete.custo_e4log) || safeNumber(frete.custo_total)
    }));
});

// =========================================================================
// MOTOR DA API (AGORA 100% MANUAL PARA POUPAR O SERVIDOR)
// =========================================================================
const isSyncing = ref(false);
const ultimaSincronizacao = ref('Nunca');

const sincronizarBsoft = async (isAuto = false) => {
    if (isSyncing.value) return;
    isSyncing.value = true;

    try {
        const response = await axios.get(`/bsoft/sincronizar-manual`);
        
        if (!isAuto && response.data && response.data.message) {
            alert(response.data.message);
        }
        
        router.reload({ only: ['fretesDetalhados', 'faturamentosDetalhados', 'resumoFaturamento', 'resumoAuditoria'] });
        
        const agora = new Date();
        ultimaSincronizacao.value = agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

    } catch (error) {
        console.error('Falha ao sincronizar E4LOG:', error);
        if (!isAuto) {
            alert("Ocorreu um erro ao comunicar com o servidor Laravel.");
        }
    } finally {
        isSyncing.value = false;
    }
};

// =========================================================================
// REMOVIDO: O INTERVALO DE 1 MINUTO FOI APAGADO DAQUI!
// =========================================================================
onMounted(() => {
    // A tela agora só carrega os dados que já estão no banco de dados.
    // O servidor não fará requisições de fundo.
});

onUnmounted(() => {
    // Nada a limpar.
});
</script>

<template>
    <Head title="Painel de Controle - BWT Logística" />

    <ErpLayout>
        <template #header-title>Painel Executivo</template>
        <template #header-subtitle>Inteligência Financeira e Auditoria</template>

        <template #header-actions>
            <div class="flex items-center gap-4">
                <div class="flex flex-col items-end">
                    <!-- BOTÃO MANUAL MANTIDO -->
                    <button 
                        @click="sincronizarBsoft(false)" 
                        :disabled="isSyncing" 
                        class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded-xl shadow-sm transition-all text-sm"
                    >
                        <svg v-if="isSyncing" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        
                        {{ isSyncing ? 'Baixando NFs E4LOG...' : 'Sincronizar Notas E4LOG (API)' }}
                    </button>
                    <!-- AVISO DE AUTO ATUALIZADO PARA MANUAL -->
                    <span class="text-[10px] text-gray-500 mt-1 font-bold">
                        Última busca: <span class="text-blue-600">{{ ultimaSincronizacao }}</span> (Modo Manual)
                    </span>
                </div>

                <!-- O SELETOR MÁGICO DE DATAS -->
                <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-white border border-slate-200 text-slate-700 text-sm font-bold focus:ring-2 focus:ring-blue-500 rounded-xl p-2 cursor-pointer w-64 shadow-sm transition-all h-10">
                    <optgroup label="Período Atual">
                        <option value="">Visão Mensal (Consolidado)</option>
                        <option value="q1">1ª Quinzena (Dia 01 ao 15)</option>
                        <option value="q2">Fechamento (Dia 16 ao Fim do Mês)</option>
                    </optgroup>
                    
                    <optgroup label="Lotes Fechados" v-if="fechamentos && fechamentos.length > 0">
                        <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
                    </optgroup>
                </select>
            </div>
        </template>

        <KpiCardsRow 
            :cruzamentoViagens="viagensDinamicas"
            :fretesDetalhados="faturadosE4log" 
            :resumoFaturamento="{
                receita_teorica: totaisDinamicos.receita_teorica,
                receita_real: totaisDinamicos.receita_real,
                custo_total: totaisDinamicos.custo,
                lucro: totaisDinamicos.lucro,
                gap_receita: totaisDinamicos.receita_teorica - totaisDinamicos.receita_real
            }"
            :resumoAuditoria="{
                divergencias: fretesDivergentes.length,
                validados: fretesCorretos.length,
                total_documentos: faturadosE4log.length
            }"
            :totaisCorretos="{ 
                liberado: fretesCorretos.length, 
                bloqueado: 0, 
                retido: 0, 
                divergente: 0, 
                total: fretesCorretos.length, 
                valor: totaisDinamicos.custo 
            }"
            :totaisDivergentes="{ 
                liberado: 0, 
                bloqueado: fretesDivergentes.length, 
                retido: 0, 
                divergente: fretesDivergentes.length, 
                total: fretesDivergentes.length, 
                valor: 0 
            }"
            :totaisMatch="{ 
                liberado: viagensDinamicas.length, 
                bloqueado: 0, 
                retido: 0, 
                divergente: 0, 
                total: viagensDinamicas.length, 
                valor: totaisDinamicos.custo 
            }"
        />

        <!-- Radar E4LOG Fixo e Destaque -->
        <div class="mt-8 bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden mb-8">
            <div class="bg-orange-50 p-4 border-b border-orange-200 flex justify-between items-center">
                <div>
                    <h3 class="font-black text-orange-800 text-lg flex items-center gap-2">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        Monitor de Faturamento E4LOG ({{ faturadosE4log.length }})
                    </h3>
                    <p class="text-xs text-orange-600 mt-1">Visão em tempo real das notas capturadas diretamente da API da Bsoft.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-4 font-bold text-gray-800">Nota / CTe Original (BWT)</th>
                            <th class="px-6 py-4 font-bold text-gray-800">Destino</th>
                            <th class="px-6 py-4 font-bold text-gray-800 text-center">Data Emissão</th>
                            <th class="px-6 py-4 font-bold text-gray-800 text-center">Data Entrega</th>
                            <th class="px-6 py-4 font-black text-orange-700 text-right bg-orange-50">Custo Pré-Faturado (E4LOG)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="faturadosE4log.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500 font-medium">Nenhum CT-e da E4LOG detectado na API ainda para este período.</td>
                        </tr>
                        <tr v-for="frete in faturadosE4log" :key="frete.id" class="border-b hover:bg-orange-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900">{{ frete.arquivo || frete.nota_fiscal }}</div>
                                <span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded font-bold uppercase mt-1 inline-block">BWT</span>
                            </td>
                            <td class="px-6 py-4 font-medium">{{ frete.destino }}</td>
                            <td class="px-6 py-4 text-center">{{ frete.data_emissao ? new Date(frete.data_emissao).toLocaleDateString('pt-BR', {timeZone: 'UTC'}) : '-' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="frete.data_entrega" class="text-green-600 font-bold flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    {{ new Date(frete.data_entrega).toLocaleDateString('pt-BR', {timeZone: 'UTC'}) }}
                                </span>
                                <span v-else class="text-gray-400">Em Rota</span>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-orange-600 bg-orange-50/30">
                                {{ formatCurrency(frete.custo_e4log) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalKpi v-if="kpiModalAberto" :tipo="kpiModalAberto" :viagens="viagensDinamicas" :faturamentos="fretesGerais" @fechar="fecharTudo" @abrir-dossie="abrirDossieMatch" />
        <SuperModalDacte v-if="dossieViagemSelecionado" :viagem="dossieViagemSelecionado" @fechar="fecharTudo" />
    </ErpLayout>
</template>
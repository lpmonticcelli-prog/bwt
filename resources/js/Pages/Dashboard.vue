<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    resumoFaturamento: { type: Object, default: () => ({ total_notas: 0, receita_total: 0, receita_teorica: 0, lucro_total: 0 }) },
    resumoAuditoria: { type: Object, default: () => ({ total_notas: 0, custo_cobrado: 0, custo_correto: 0, diferenca_total: 0 }) },
    fechamentos: { type: Array, default: () => [] },
    fechamento_id: { type: [String, Number], default: '' },
    fretesDetalhados: { type: Array, default: () => [] }
});

const formatMoney = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

const formatarData = (dataStr) => {
    if (!dataStr) return 'Aguardando Baixa';
    return new Date(dataStr + 'T00:00:00').toLocaleDateString('pt-BR');
};

const irParaRentabilidade = () => router.visit('/faturamento/solfacil');
const irParaAuditoria = () => router.visit('/auditoria');

const filtroSelecionado = ref(props.fechamento_id || '');
const aplicarFiltro = () => router.get('/dashboard', { fechamento_id: filtroSelecionado.value }, { preserveState: true, preserveScroll: true });

const fechamentoAtual = computed(() => filtroSelecionado.value ? props.fechamentos.find(f => f.id == filtroSelecionado.value) : null);

// =======================================================
// CÁLCULOS FINANCEIROS CRUZADOS (LUCRO PRESUMIDO E FUGA)
// =======================================================
const lucroPresumido = computed(() => {
    return (props.resumoFaturamento.receita_teorica || 0) - (props.resumoAuditoria.custo_correto || 0);
});

const gapLucro = computed(() => {
    return lucroPresumido.value - (props.resumoFaturamento.lucro_total || 0);
});

// KPIS DE PERFORMANCE OPERACIONAL
const margemLiquida = computed(() => props.resumoFaturamento.receita_total === 0 ? 0 : ((props.resumoFaturamento.lucro_total / props.resumoFaturamento.receita_total) * 100).toFixed(1));
const ticketMedio = computed(() => props.resumoFaturamento.total_notas === 0 ? 0 : props.resumoFaturamento.receita_total / props.resumoFaturamento.total_notas);
const custoMedio = computed(() => props.resumoAuditoria.total_notas === 0 ? 0 : props.resumoAuditoria.custo_cobrado / props.resumoAuditoria.total_notas);
const taxaGlosa = computed(() => props.resumoAuditoria.custo_cobrado === 0 ? 0 : ((props.resumoAuditoria.diferenca_total / props.resumoAuditoria.custo_cobrado) * 100).toFixed(1));

// LÓGICA DO RAIO-X E LISTAGEM DE XMLs
const abaExpandida = ref(null); 
const raioXSelecionado = ref(null);

const fretesDivergentes = computed(() => props.fretesDetalhados.filter(f => !f.is_correto));
const fretesCorretos = computed(() => props.fretesDetalhados.filter(f => f.is_correto));

const alternarAba = (aba) => { abaExpandida.value = abaExpandida.value === aba ? null : aba; };
const abrirRaioX = (frete) => { raioXSelecionado.value = frete; };
const fecharRaioX = () => { raioXSelecionado.value = null; };

const descobrirMotivo = (frete) => {
    if (frete.diferenca === 0) return 'Cobrança exata em tabela';
    if (frete.diferenca < 0) return 'E4LOG cobrou a menor (Ganho operacional)';
    
    let motivos = [];
    if (frete.taxasExtras > 0) motivos.push('Taxas extras não combinadas embutidas no XML');
    if (frete.cobrado > (Number(frete.freteBaseCalculado) + Number(frete.tdeCalculado))) {
        if (Number(frete.tdeCalculado) === 0 && !frete.temTde) {
            motivos.push('Valor base por entrega cobrado acima da tabela regional');
        } else {
            motivos.push('TDE cobrada sem justificativa ou acima do combinado (Mínimo R$ 160 ou 20%)');
        }
    }
    return motivos.length > 0 ? motivos.join(' + ') : 'Divergência de cálculo na tabela comercial';
};

const badgeOperacao = (tipo) => {
    if (tipo === 'Reentrega') return 'bg-amber-100 text-amber-800 border-amber-200';
    if (tipo === 'Devolução') return 'bg-slate-200 text-slate-700 border-slate-300';
    if (tipo === 'Complemento') return 'bg-purple-100 text-purple-700 border-purple-200';
    return 'bg-blue-50 text-blue-600 border-blue-100'; 
};

// CONFIGURAÇÕES DOS GRÁFICOS
const faturamentoChartData = computed(() => ({ labels: ['Receita Real', 'Receita Presumida'], datasets: [{ backgroundColor: ['#3b82f6', '#10b981'], data: [props.resumoFaturamento.receita_total, props.resumoFaturamento.receita_teorica] }] }));
const auditoriaChartData = computed(() => ({ labels: ['Cobrado E4LOG', 'Custo Correto', 'Divergência'], datasets: [{ backgroundColor: ['#f97316', '#64748b', '#ef4444'], data: [props.resumoAuditoria.custo_cobrado, props.resumoAuditoria.custo_correto, props.resumoAuditoria.diferenca_total] }] }));
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };
</script>

<template>
    <Head title="ioapps - Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-xl text-slate-800 leading-tight">Painel de Controle</h2>
                    <span class="text-[10px] bg-slate-800 text-white font-black px-3 py-1 rounded uppercase tracking-widest shadow-sm">ioapps</span>
                </div>
                
                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider hidden sm:block">Período:</span>
                        <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-white border-slate-300 text-slate-700 text-sm font-semibold rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:w-64 py-2 px-3 shadow-sm transition">
                            <option value="">Visão Global (Todos os Períodos)</option>
                            <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
                        </select>
                    </div>
                    <Link href="/fechamentos" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2 px-4 rounded-lg shadow-sm transition-colors flex items-center justify-center min-w-[140px]">
                        <svg class="w-4 h-4 mr-2 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>Lançamentos
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-10 bg-slate-100 min-h-screen relative">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div v-if="fechamentoAtual" class="mb-10 animate-fade-in">
                    <div class="bg-slate-900 rounded-2xl shadow-lg p-8 relative overflow-hidden border-b-4 border-b-indigo-500">
                        <div class="absolute -right-20 -top-20 opacity-10 pointer-events-none">
                            <svg class="w-96 h-96 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                        </div>

                        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 mb-8 border-b border-slate-800 pb-6">
                            <div>
                                <span class="text-indigo-400 font-bold text-xs uppercase tracking-widest mb-1 block">Consolidado Técnico de Auditoria</span>
                                <h3 class="text-3xl font-black text-white">{{ fechamentoAtual.titulo }}</h3>
                                <p class="text-slate-400 mt-1 text-sm font-medium">Janela Operacional: {{ formatarData(fechamentoAtual.data_inicio) }} até {{ formatarData(fechamentoAtual.data_fim) }}</p>
                            </div>
                            <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 text-right min-w-[220px]">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Previsão Liquidação Líquida</p>
                                <p class="text-xl font-black text-emerald-400">{{ formatarData(fechamentoAtual.data_vencimento) }}</p>
                            </div>
                        </div>

                        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8 bg-slate-950/40 p-6 rounded-xl border border-slate-800">
                            
                            <div class="lg:col-span-7">
                                <h4 class="text-xs font-black text-blue-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Fluxo de Caixa: Faturamento Sol Fácil</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Receita Real</p>
                                        <p class="text-lg font-black text-white mt-0.5">{{ formatMoney(resumoFaturamento.receita_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Receita Presumida</p>
                                        <p class="text-lg font-black text-slate-300 mt-0.5">{{ formatMoney(resumoFaturamento.receita_teorica) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Gap de Receita</p>
                                        <p class="text-lg font-black text-orange-400 mt-0.5">{{ formatMoney(resumoFaturamento.receita_teorica - resumoFaturamento.receita_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Lucro Real (Líquido)</p>
                                        <p class="text-lg font-black text-emerald-400 mt-0.5">{{ formatMoney(resumoFaturamento.lucro_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Lucro Presumido</p>
                                        <p class="text-lg font-black text-indigo-400 mt-0.5">{{ formatMoney(lucroPresumido) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Fuga de Lucro</p>
                                        <p class="text-lg font-black text-red-400 mt-0.5">{{ formatMoney(gapLucro) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-5 lg:border-l lg:border-slate-800 lg:pl-8">
                                <h4 class="text-xs font-black text-orange-400 uppercase tracking-wider mb-4 border-b border-slate-800 pb-2">Controle de Gastos: Fatura E4LOG</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Custo Real (Cobrado)</p>
                                        <p class="text-lg font-black text-white mt-0.5">{{ formatMoney(resumoAuditoria.custo_cobrado) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Custo Presumido</p>
                                        <p class="text-lg font-black text-slate-300 mt-0.5">{{ formatMoney(resumoAuditoria.custo_correto) }}</p>
                                    </div>
                                    <div class="sm:col-span-2 mt-2">
                                        <p class="text-slate-500 text-[11px] font-bold uppercase">Divergência Operacional</p>
                                        <p class="text-lg font-black text-red-400 mt-0.5">{{ formatMoney(resumoAuditoria.diferenca_total) }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="relative z-10 grid grid-cols-2 lg:grid-cols-4 gap-6 text-sm">
                            <div>
                                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Margem Líquida Real</p>
                                <h4 class="text-2xl font-black mt-1" :class="margemLiquida >= 20 ? 'text-emerald-400' : 'text-amber-400'">{{ margemLiquida }}%</h4>
                            </div>
                            <div class="border-l border-slate-800 pl-6">
                                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Média Faturada / Entrega</p>
                                <h4 class="text-2xl font-black text-white mt-1">{{ formatMoney(ticketMedio) }}</h4>
                            </div>
                            <div class="border-l border-slate-800 pl-6">
                                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Média Custo / Entrega</p>
                                <h4 class="text-2xl font-black text-slate-300 mt-1">{{ formatMoney(custoMedio) }}</h4>
                            </div>
                            <div class="border-l border-slate-800 pl-6">
                                <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Índice de Glosa E4LOG</p>
                                <h4 class="text-2xl font-black text-red-400 mt-1">{{ taxaGlosa }}%</h4>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <button @click="alternarAba('divergentes')" class="flex justify-between items-center bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" :class="abaExpandida === 'divergentes' ? 'border-red-500 ring-1 ring-red-500 bg-red-50/10' : 'border-slate-200'">
                            <div class="flex items-center gap-3">
                                <div class="bg-red-50 text-red-500 p-2 rounded-lg">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800">Analisar Divergências de Faturamento</h4>
                                    <p class="text-xs text-slate-500">{{ fretesDivergentes.length }} notas por entrega com quebra de regras</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="abaExpandida === 'divergentes' ? 'rotate-180 text-red-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <button @click="alternarAba('corretos')" class="flex justify-between items-center bg-white border rounded-xl p-4 shadow-sm hover:shadow-md transition-all" :class="abaExpandida === 'corretos' ? 'border-emerald-500 ring-1 ring-emerald-500 bg-emerald-50/10' : 'border-slate-200'">
                            <div class="flex items-center gap-3">
                                <div class="bg-emerald-50 text-emerald-500 p-2 rounded-lg">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800">Entregas Validadas com Sucesso</h4>
                                    <p class="text-xs text-slate-500">{{ fretesCorretos.length }} notas liquidadas perfeitamente</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform" :class="abaExpandida === 'corretos' ? 'rotate-180 text-emerald-500' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>

                    <div v-if="abaExpandida" class="mt-4 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden animate-fade-in">
                        <div class="overflow-x-auto max-h-[500px]">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                                    <tr>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase">Arquivo XML</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase">Operação</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase">Destino</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase">Cobrado</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase">Valor Correto</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase" :class="abaExpandida === 'divergentes' ? 'text-red-500' : ''">Diferença</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase w-72">Qual motivo da divergência?</th>
                                        <th class="py-3.5 px-4 font-bold text-slate-600 text-xs uppercase text-right">Ação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="frete in (abaExpandida === 'divergentes' ? fretesDivergentes : fretesCorretos)" :key="frete.id" class="hover:bg-slate-50 transition-colors">
                                        <td class="py-3 px-4 text-slate-800 font-medium truncate max-w-[140px]" :title="frete.arquivo">{{ frete.arquivo }}</td>
                                        <td class="py-3 px-4">
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded border uppercase tracking-wider" :class="badgeOperacao(frete.tipo_operacao)">
                                                {{ frete.tipo_operacao || 'Entrega' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-500 font-medium">{{ frete.destino }}</td>
                                        <td class="py-3 px-4 text-slate-800 font-semibold">{{ formatMoney(frete.cobrado) }}</td>
                                        <td class="py-3 px-4 text-emerald-600 font-semibold">{{ formatMoney(frete.correto) }}</td>
                                        <td class="py-3 px-4 font-black" :class="frete.diferenca > 0 ? 'text-red-600' : 'text-slate-400'">{{ formatMoney(frete.diferenca) }}</td>
                                        <td class="py-3 px-4">
                                            <span class="text-[11px] font-bold px-2 py-1 rounded shadow-sm inline-block" :class="abaExpandida === 'divergentes' ? 'bg-red-50 text-red-600 max-w-[260px] truncate' : 'bg-emerald-50 text-emerald-600'">
                                                {{ descobrirMotivo(frete) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <button @click="abrirRaioX(frete)" class="text-indigo-600 hover:text-indigo-800 font-bold text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition-colors inline-flex items-center shadow-sm">
                                                Detalhes da Cobrança
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="raioXSelecionado" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4 animate-fade-in" @click.self="fecharRaioX">
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-200">
                        <div class="bg-slate-900 p-6 flex justify-between items-center border-b-4" :class="raioXSelecionado.is_correto ? 'border-b-emerald-500' : 'border-b-red-500'">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Módulo Auditoria ioapps &bull; Raio-X Executivo</span>
                                <h3 class="text-lg font-black text-white truncate max-w-md">{{ raioXSelecionado.arquivo }}</h3>
                            </div>
                            <button @click="fecharRaioX" class="text-slate-400 hover:text-white bg-slate-800 p-2 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 md:col-span-2">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Destino Final</p>
                                    <p class="font-black text-slate-800 text-sm">{{ raioXSelecionado.destino }}</p>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Emissão do XML</p>
                                    <p class="font-black text-slate-800 text-sm">{{ formatarData(raioXSelecionado.data_emissao) }}</p>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Baixa / Entrega</p>
                                    <p class="font-black text-slate-800 text-sm">{{ formatarData(raioXSelecionado.data_entrega) }}</p>
                                </div>
                            </div>

                            <h4 class="text-sm font-bold text-slate-800 border-b pb-2 mb-4">Cruzamento Logístico Contratual (Tabela E4LOG: {{ raioXSelecionado.regra }})</h4>
                            
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between items-center text-slate-600">
                                    <span>Valor da Nota de Carga</span>
                                    <span class="font-medium text-slate-800">{{ formatMoney(raioXSelecionado.valorNF) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600">
                                    <span>Acordo Base por Entrega (Fixo Comercial)</span>
                                    <span class="font-medium text-slate-800">{{ formatMoney(raioXSelecionado.freteBaseCalculado) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600">
                                    <span class="flex items-center gap-2">Taxas Regulamentadas (TDE / Rural) <span v-if="raioXSelecionado.temTde" class="bg-indigo-100 text-indigo-700 text-[10px] px-1.5 py-0.5 rounded font-black">Ativa</span></span>
                                    <span class="font-medium text-slate-800">{{ formatMoney(raioXSelecionado.tdeCalculado) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600 border-t pt-2 mt-2 border-dashed">
                                    <span class="text-red-500 font-semibold">Excedentes e Adicionais Livres (Não Contratados)</span>
                                    <span class="font-medium text-red-600">{{ formatMoney(raioXSelecionado.taxasExtras) }}</span>
                                </li>
                            </ul>

                            <div class="mt-6 p-4 rounded-xl flex justify-between items-center border" :class="raioXSelecionado.is_correto ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                                <div>
                                    <p class="text-xs font-bold uppercase mb-1" :class="raioXSelecionado.is_correto ? 'text-emerald-600' : 'text-red-600'">Custo Cobrado E4LOG</p>
                                    <p class="text-3xl font-black text-slate-800">{{ formatMoney(raioXSelecionado.cobrado) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-500 uppercase mb-1">Custo Combinado Comercial</p>
                                    <p class="text-2xl font-black text-indigo-600">{{ formatMoney(raioXSelecionado.correto) }}</p>
                                </div>
                            </div>
                            
                            <div v-if="!raioXSelecionado.is_correto" class="mt-4 text-center">
                                <p class="text-sm font-bold text-red-600 bg-white border border-red-200 py-2 px-4 rounded-lg shadow-sm inline-block">
                                    ⚠️ Perda Logística Identificada: Cobrança indevida de <span class="font-black underline">{{ formatMoney(raioXSelecionado.diferenca) }}</span> detectada na auditoria da entrega.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-8 mt-10">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Faturamento Sol Fácil</h3>
                        <span class="text-xs font-bold text-slate-400">{{ resumoFaturamento.total_notas }} Notas Históricas</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 border-l-4 border-l-blue-500"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Receita Real</p><h3 class="text-2xl font-black text-slate-800 mt-1">{{ formatMoney(resumoFaturamento.receita_total) }}</h3></div>
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 border-l-4 border-l-emerald-500"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Receita Presumida</p><h3 class="text-2xl font-black text-slate-800 mt-1">{{ formatMoney(resumoFaturamento.receita_teorica) }}</h3></div>
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 border-l-4 border-l-orange-400"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Gap de Receita</p><h3 class="text-2xl font-black text-orange-500 mt-1">{{ formatMoney(resumoFaturamento.receita_teorica - resumoFaturamento.receita_total) }}</h3></div>
                        <div class="bg-slate-800 rounded-xl shadow-md p-6 border-l-4 border-l-indigo-400"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Lucro Líquido Global</p><h3 class="text-2xl font-black text-white mt-1">{{ formatMoney(resumoFaturamento.lucro_total) }}</h3></div>
                    </div>
                </div>
                
                <div class="mb-10">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-bold text-slate-500 uppercase tracking-widest">Auditoria E4LOG</h3>
                        <span class="text-xs font-bold text-slate-400">{{ resumoAuditoria.total_notas }} Conciliações Totais</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 border-l-4 border-l-slate-400"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Custo Real (Cobrado)</p><h3 class="text-2xl font-black text-slate-800 mt-1">{{ formatMoney(resumoAuditoria.custo_cobrado) }}</h3></div>
                        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 border-l-4 border-l-indigo-500"><p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Custo Presumido (Correto)</p><h3 class="text-2xl font-black text-slate-800 mt-1">{{ formatMoney(resumoAuditoria.custo_correto) }}</h3></div>
                        <div class="bg-red-50 rounded-xl shadow-sm border border-red-200 p-6 border-l-4 border-l-red-600"><p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">Divergências Financeiras</p><h3 class="text-2xl font-black text-red-700 mt-1">{{ formatMoney(resumoAuditoria.diferenca_total) }}</h3></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div @click="irParaRentabilidade" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 cursor-pointer hover:shadow-md hover:border-blue-300 transition-all">
                        <h4 class="text-base font-black text-slate-800 tracking-tight">Receita Real vs Presumida</h4>
                        <div class="h-64 mt-6"><Bar :data="faturamentoChartData" :options="barOptions" /></div>
                    </div>
                    <div @click="irParaAuditoria" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 cursor-pointer hover:shadow-md hover:border-indigo-300 transition-all">
                        <h4 class="text-base font-black text-slate-800 tracking-tight">Distribuição de Custos e Perdas</h4>
                        <div class="h-64 mt-6 relative flex items-center justify-center"><Doughnut :data="auditoriaChartData" :options="doughnutOptions" /></div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(-5px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
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
    if (tipo === 'Reentrega') return 'bg-amber-50 text-amber-600 border-amber-200';
    if (tipo === 'Devolução') return 'bg-slate-100 text-slate-600 border-slate-200';
    if (tipo === 'Complemento') return 'bg-fuchsia-50 text-fuchsia-600 border-fuchsia-200';
    return 'bg-blue-50/50 text-blue-600 border-blue-100'; 
};

// CONFIGURAÇÕES DOS GRÁFICOS (Cores Premium)
const faturamentoChartData = computed(() => ({ labels: ['Receita Real', 'Receita Presumida'], datasets: [{ backgroundColor: ['#2563eb', '#10b981'], borderRadius: 6, data: [props.resumoFaturamento.receita_total, props.resumoFaturamento.receita_teorica] }] }));
const auditoriaChartData = computed(() => ({ labels: ['Cobrado E4LOG', 'Custo Correto', 'Divergência'], datasets: [{ backgroundColor: ['#f97316', '#cbd5e1', '#ef4444'], borderWidth: 0, hoverOffset: 4, data: [props.resumoAuditoria.custo_cobrado, props.resumoAuditoria.custo_correto, props.resumoAuditoria.diferenca_total] }] }));
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8, titleFont: { size: 13 } } }, scales: { y: { border: { display: false }, grid: { color: '#f1f5f9', drawBorder: false } }, x: { border: { display: false }, grid: { display: false } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Inter', sans-serif", size: 12 } } }, tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 } } };
</script>

<template>
    <Head title="Painel de Controle - BWT Logística" />

    <AuthenticatedLayout>
        
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5 w-full">
                
                <div class="flex items-center gap-4">
                    <img src="/images/logo.png" alt="BWT Logística" class="h-9 object-contain hidden sm:block drop-shadow-sm" onerror="this.outerHTML='<span class=\'text-2xl font-black text-slate-900 tracking-tight\'>BWT</span>'" />
                    <div class="h-10 w-px bg-slate-200 hidden sm:block"></div>
                    <div>
                        <h2 class="font-bold text-xl text-slate-900 leading-tight tracking-tight">Painel de Controle</h2>
                        <p class="text-[11px] font-bold text-blue-600 uppercase tracking-[0.2em] mt-0.5 opacity-80">Torre de Auditoria</p>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto">
                    <div class="flex items-center gap-3 w-full sm:w-auto bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hidden sm:block pt-0.5">Competência:</span>
                        <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-transparent border-none text-slate-800 text-sm font-semibold focus:ring-0 w-full sm:w-64 p-0 cursor-pointer outline-none">
                            <option value="">Visão Global (Consolidado)</option>
                            <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
                        </select>
                    </div>
                    
                    <Link href="/fechamentos" class="group bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-sm font-bold py-2.5 px-5 rounded-xl shadow-[0_4px_14px_0_rgb(37,99,235,0.39)] transition-all flex items-center justify-center min-w-[140px]">
                        <svg class="w-4 h-4 mr-2 hidden sm:block opacity-70 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Lançamentos
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-10 bg-[#f8fafc] min-h-screen relative font-sans selection:bg-blue-500 selection:text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div v-if="fechamentoAtual" class="mb-12 animate-fade-in">
                    <div class="bg-slate-900 rounded-[24px] shadow-xl p-8 lg:p-10 relative overflow-hidden">
                        
                        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-blue-900"></div>
                        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>
                        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500 rounded-full mix-blend-multiply filter blur-[128px] opacity-40 pointer-events-none"></div>

                        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 mb-10 border-b border-slate-700/50 pb-8">
                            <div>
                                <span class="inline-flex items-center gap-1.5 text-blue-400 font-bold text-[10px] uppercase tracking-[0.2em] mb-3 px-2 py-1 bg-blue-500/10 rounded border border-blue-500/20">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                                    Consolidado Técnico
                                </span>
                                <h3 class="text-3xl sm:text-4xl font-black text-white tracking-tight">{{ fechamentoAtual.titulo }}</h3>
                                <p class="text-slate-400 mt-2 text-sm font-medium">Janela Operacional: <span class="text-slate-300">{{ formatarData(fechamentoAtual.data_inicio) }}</span> até <span class="text-slate-300">{{ formatarData(fechamentoAtual.data_fim) }}</span></p>
                            </div>
                            <div class="bg-slate-800/80 backdrop-blur-sm border border-slate-700 rounded-2xl p-5 text-right min-w-[240px] shadow-inner">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 flex items-center justify-end gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Previsão de Liquidação
                                </p>
                                <p class="text-2xl font-black text-emerald-400">{{ formatarData(fechamentoAtual.data_vencimento) }}</p>
                            </div>
                        </div>

                        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 mb-10 bg-slate-950/30 backdrop-blur-md p-6 lg:p-8 rounded-2xl border border-slate-700/50">
                            
                            <div class="lg:col-span-7">
                                <h4 class="text-[11px] font-black text-blue-400 uppercase tracking-[0.15em] mb-5 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Fluxo de Caixa: Faturamento Sol Fácil
                                </h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-6 gap-x-4">
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Receita Real</p>
                                        <p class="text-xl font-black text-white mt-1">{{ formatMoney(resumoFaturamento.receita_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Receita Presumida</p>
                                        <p class="text-xl font-black text-slate-300 mt-1">{{ formatMoney(resumoFaturamento.receita_teorica) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Gap de Receita</p>
                                        <p class="text-xl font-black text-amber-400 mt-1">{{ formatMoney(resumoFaturamento.receita_teorica - resumoFaturamento.receita_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Lucro Real Líquido</p>
                                        <p class="text-xl font-black text-emerald-400 mt-1">{{ formatMoney(resumoFaturamento.lucro_total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Lucro Presumido</p>
                                        <p class="text-xl font-black text-blue-400 mt-1">{{ formatMoney(lucroPresumido) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Fuga de Lucro</p>
                                        <p class="text-xl font-black text-red-400 mt-1">{{ formatMoney(gapLucro) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-5 lg:border-l lg:border-slate-700/50 lg:pl-8 pt-8 lg:pt-0 border-t border-slate-700/50 lg:border-t-0">
                                <h4 class="text-[11px] font-black text-amber-500 uppercase tracking-[0.15em] mb-5 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    Controle de Gastos: Fatura E4LOG
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4">
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Custo Real Cobrado</p>
                                        <p class="text-xl font-black text-white mt-1">{{ formatMoney(resumoAuditoria.custo_cobrado) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">Custo Presumido</p>
                                        <p class="text-xl font-black text-slate-300 mt-1">{{ formatMoney(resumoAuditoria.custo_correto) }}</p>
                                    </div>
                                    <div class="sm:col-span-2 bg-red-500/10 border border-red-500/20 p-4 rounded-xl">
                                        <p class="text-red-400 text-[10px] font-bold uppercase tracking-wider">Divergência Operacional Detectada</p>
                                        <p class="text-2xl font-black text-red-400 mt-0.5">{{ formatMoney(resumoAuditoria.diferenca_total) }}</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="relative z-10 grid grid-cols-2 lg:grid-cols-4 gap-6 text-sm divide-x divide-slate-700/50 border-t border-slate-700/50 pt-8 mt-2">
                            <div class="pl-0 lg:pl-0">
                                <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.1em] mb-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> Margem Líquida Real</p>
                                <h4 class="text-2xl font-black mt-1" :class="margemLiquida >= 20 ? 'text-emerald-400' : 'text-amber-400'">{{ margemLiquida }}%</h4>
                            </div>
                            <div class="pl-6">
                                <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.1em] mb-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg> Média Faturada / Frete</p>
                                <h4 class="text-2xl font-black text-white mt-1">{{ formatMoney(ticketMedio) }}</h4>
                            </div>
                            <div class="pl-6">
                                <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.1em] mb-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg> Média Custo / Frete</p>
                                <h4 class="text-2xl font-black text-slate-300 mt-1">{{ formatMoney(custoMedio) }}</h4>
                            </div>
                            <div class="pl-6">
                                <p class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.1em] mb-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg> Taxa de Glosa E4LOG</p>
                                <h4 class="text-2xl font-black text-red-400 mt-1">{{ taxaGlosa }}%</h4>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <button @click="alternarAba('divergentes')" class="group flex justify-between items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99]" :class="abaExpandida === 'divergentes' ? 'border-red-400 ring-2 ring-red-400/20 bg-red-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                            <div class="flex items-center gap-4">
                                <div class="bg-red-50 text-red-500 p-3 rounded-xl transition-colors group-hover:bg-red-100">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800 text-base">Analisar Divergências de Frete</h4>
                                    <p class="text-xs text-slate-500 mt-0.5"><span class="font-bold text-red-600">{{ fretesDivergentes.length }} XMLs</span> apresentaram quebra de regra comercial</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="abaExpandida === 'divergentes' ? 'rotate-180 text-red-500' : 'group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <button @click="alternarAba('corretos')" class="group flex justify-between items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99]" :class="abaExpandida === 'corretos' ? 'border-emerald-400 ring-2 ring-emerald-400/20 bg-emerald-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                            <div class="flex items-center gap-4">
                                <div class="bg-emerald-50 text-emerald-500 p-3 rounded-xl transition-colors group-hover:bg-emerald-100">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div class="text-left">
                                    <h4 class="font-bold text-slate-800 text-base">Entregas Validadas e Aprovadas</h4>
                                    <p class="text-xs text-slate-500 mt-0.5"><span class="font-bold text-emerald-600">{{ fretesCorretos.length }} XMLs</span> validados com sucesso pela plataforma</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform duration-300" :class="abaExpandida === 'corretos' ? 'rotate-180 text-emerald-500' : 'group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>

                    <div v-if="abaExpandida" class="mt-5 bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.04)] border border-slate-200 overflow-hidden animate-fade-in">
                        <div class="overflow-x-auto max-h-[500px] scrollbar-thin scrollbar-thumb-slate-200">
                            <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                                <thead class="bg-slate-50/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-10">
                                    <tr>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Chave do XML</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Operação</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Destino</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Valor Cobrado</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Tabela Correta</th>
                                        <th class="py-4 px-5 font-bold text-[10px] uppercase tracking-wider" :class="abaExpandida === 'divergentes' ? 'text-red-500' : 'text-slate-500'">Diferença</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider w-72">Motivo do Sistema</th>
                                        <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="frete in (abaExpandida === 'divergentes' ? fretesDivergentes : fretesCorretos)" :key="frete.id" class="hover:bg-blue-50/30 transition-colors group">
                                        <td class="py-3.5 px-5 text-slate-800 font-semibold text-xs truncate max-w-[140px]" :title="frete.arquivo">{{ frete.arquivo }}</td>
                                        <td class="py-3.5 px-5">
                                            <span class="text-[9px] font-bold px-2 py-0.5 rounded border uppercase tracking-wider inline-flex items-center" :class="badgeOperacao(frete.tipo_operacao)">
                                                {{ frete.tipo_operacao || 'Entrega' }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 text-slate-500 text-xs font-medium">{{ frete.destino }}</td>
                                        <td class="py-3.5 px-5 text-slate-700 font-semibold">{{ formatMoney(frete.cobrado) }}</td>
                                        <td class="py-3.5 px-5 text-emerald-600 font-semibold">{{ formatMoney(frete.correto) }}</td>
                                        <td class="py-3.5 px-5 font-black text-sm" :class="frete.diferenca > 0 ? 'text-red-600' : 'text-slate-400'">{{ formatMoney(frete.diferenca) }}</td>
                                        <td class="py-3.5 px-5">
                                            <span class="text-[10px] font-bold px-2 py-1 rounded shadow-sm inline-block" :class="abaExpandida === 'divergentes' ? 'bg-red-50 border border-red-100 text-red-600 max-w-[260px] truncate' : 'bg-emerald-50 border border-emerald-100 text-emerald-600'">
                                                {{ descobrirMotivo(frete) }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-5 text-right">
                                            <button @click="abrirRaioX(frete)" class="text-blue-600 hover:text-white font-bold text-[10px] uppercase tracking-wider bg-white hover:bg-blue-600 border border-blue-200 hover:border-blue-600 px-3 py-1.5 rounded-lg transition-all inline-flex items-center shadow-sm opacity-60 group-hover:opacity-100">
                                                Raio-X
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="raioXSelecionado" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 animate-fade-in">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="fecharRaioX"></div>
                    <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        
                        <div class="bg-slate-900 px-6 py-5 flex justify-between items-center border-b-4 shrink-0" :class="raioXSelecionado.is_correto ? 'border-b-emerald-500' : 'border-b-red-500'">
                            <div>
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.2em] block mb-1">Auditoria Inteligente BWT</span>
                                <h3 class="text-base font-black text-white truncate max-w-md">{{ raioXSelecionado.arquivo }}</h3>
                            </div>
                            <button @click="fecharRaioX" class="text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 p-2 rounded-xl transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                        <div class="p-6 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-200">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 md:col-span-2">
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Destino Final</p>
                                    <p class="font-black text-slate-800 text-sm truncate">{{ raioXSelecionado.destino }}</p>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Emissão (NF)</p>
                                    <p class="font-black text-slate-800 text-sm">{{ formatarData(raioXSelecionado.data_emissao) }}</p>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mb-0.5">Baixa / Sefaz</p>
                                    <p class="font-black text-slate-800 text-sm">{{ formatarData(raioXSelecionado.data_entrega) }}</p>
                                </div>
                            </div>

                            <h4 class="text-xs font-black text-slate-800 border-b border-slate-200 pb-3 mb-4 uppercase tracking-wider">
                                Cruzamento Contratual 
                                <span class="ml-2 px-2 py-0.5 bg-blue-50 text-blue-600 border border-blue-100 rounded text-[10px]">Tabela E4LOG: {{ raioXSelecionado.regra }}</span>
                            </h4>
                            
                            <ul class="space-y-3.5 text-sm mb-6">
                                <li class="flex justify-between items-center text-slate-600 bg-slate-50/50 p-2 rounded-lg">
                                    <span class="font-medium text-xs">Valor da Nota de Mercadoria (Solfácil)</span>
                                    <span class="font-bold text-slate-800">{{ formatMoney(raioXSelecionado.valorNF) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600 p-2">
                                    <span class="font-medium text-xs">Frete Base Contratual (Gatilho Fixo ou %)</span>
                                    <span class="font-bold text-slate-800">{{ formatMoney(raioXSelecionado.freteBaseCalculado) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600 p-2">
                                    <span class="flex items-center gap-2 font-medium text-xs">Taxas Regulamentadas (TDE / Rural) <span v-if="raioXSelecionado.temTde" class="bg-blue-100 text-blue-700 text-[9px] px-1.5 py-0.5 rounded font-black tracking-widest uppercase">Ativa</span></span>
                                    <span class="font-bold text-slate-800">{{ formatMoney(raioXSelecionado.tdeCalculado) }}</span>
                                </li>
                                <li class="flex justify-between items-center text-slate-600 border-t pt-3 mt-3 border-dashed border-slate-200 p-2">
                                    <span class="text-amber-600 font-bold text-xs">Pedágios, GRIS e Componentes Extra-tabela</span>
                                    <span class="font-bold text-amber-600">{{ formatMoney(raioXSelecionado.taxasExtras) }}</span>
                                </li>
                            </ul>

                            <div class="p-5 rounded-2xl flex justify-between items-center border" :class="raioXSelecionado.is_correto ? 'bg-emerald-50/50 border-emerald-200' : 'bg-red-50/50 border-red-200 shadow-sm'">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider mb-1" :class="raioXSelecionado.is_correto ? 'text-emerald-600' : 'text-red-600'">Custo Faturado E4LOG</p>
                                    <p class="text-3xl font-black text-slate-900 tracking-tight">{{ formatMoney(raioXSelecionado.cobrado) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Sistema: Valor Correto</p>
                                    <p class="text-2xl font-black text-blue-600 tracking-tight">{{ formatMoney(raioXSelecionado.correto) }}</p>
                                </div>
                            </div>
                            
                            <div v-if="!raioXSelecionado.is_correto" class="mt-5 text-center bg-white border border-red-100 p-4 rounded-xl shadow-sm">
                                <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-red-100 text-red-500 mb-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <p class="text-sm font-bold text-slate-800">
                                    Alerta de Fuga de Caixa Detetada
                                </p>
                                <p class="text-xs text-slate-500 mt-1">O fornecedor cobrou <span class="font-black text-red-600 underline">{{ formatMoney(raioXSelecionado.diferenca) }}</span> a mais do que o estipulado na tabela regional da BWT.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-12 mt-12">
                    <div class="flex items-center justify-between mb-5 border-b border-slate-200 pb-3">
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-blue-500"></div> Faturamento Sol Fácil
                        </h3>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-white px-2 py-1 rounded border border-slate-200">{{ resumoFaturamento.total_notas }} Notas no Histórico</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] border border-slate-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            <div class="absolute right-0 top-0 w-16 h-16 bg-blue-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Receita Real Emitida</p>
                            <h3 class="text-3xl font-black text-slate-800 mt-2 tracking-tight">{{ formatMoney(resumoFaturamento.receita_total) }}</h3>
                        </div>
                        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] border border-slate-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            <div class="absolute right-0 top-0 w-16 h-16 bg-emerald-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Projeção do Sistema</p>
                            <h3 class="text-3xl font-black text-slate-800 mt-2 tracking-tight">{{ formatMoney(resumoFaturamento.receita_teorica) }}</h3>
                        </div>
                        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] border border-slate-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            <div class="absolute right-0 top-0 w-16 h-16 bg-amber-500/5 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Gap Financeiro</p>
                            <h3 class="text-3xl font-black text-amber-500 mt-2 tracking-tight">{{ formatMoney(resumoFaturamento.receita_teorica - resumoFaturamento.receita_total) }}</h3>
                        </div>
                        <div class="bg-slate-900 rounded-2xl shadow-lg p-6 relative overflow-hidden group border border-slate-800">
                            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/20 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                            <p class="text-[10px] font-bold text-blue-400 uppercase tracking-wider">EBITDA (Lucro Líquido)</p>
                            <h3 class="text-3xl font-black text-white mt-2 tracking-tight">{{ formatMoney(resumoFaturamento.lucro_total) }}</h3>
                        </div>
                    </div>
                </div>
                
                <div class="mb-12">
                    <div class="flex items-center justify-between mb-5 border-b border-slate-200 pb-3">
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-orange-500"></div> Auditoria E4LOG
                        </h3>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-white px-2 py-1 rounded border border-slate-200">{{ resumoAuditoria.total_notas }} CTe's Conciliados</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] border border-slate-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Fatura Cobrada (Real)</p>
                            <h3 class="text-3xl font-black text-slate-800 mt-2 tracking-tight">{{ formatMoney(resumoAuditoria.custo_cobrado) }}</h3>
                        </div>
                        <div class="bg-white rounded-2xl shadow-[0_2px_12px_rgba(0,0,0,0.03)] border border-slate-100 p-6 relative overflow-hidden group hover:shadow-md transition-shadow">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Aprovação do Sistema (Correto)</p>
                            <h3 class="text-3xl font-black text-blue-600 mt-2 tracking-tight">{{ formatMoney(resumoAuditoria.custo_correto) }}</h3>
                        </div>
                        <div class="bg-red-50/50 rounded-2xl shadow-sm border border-red-100 p-6 relative overflow-hidden group">
                            <div class="absolute right-0 top-0 w-16 h-16 bg-red-500/10 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                            <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">Divergências Encontradas</p>
                            <h3 class="text-3xl font-black text-red-600 mt-2 tracking-tight">{{ formatMoney(resumoAuditoria.diferenca_total) }}</h3>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-12">
                    <div @click="irParaRentabilidade" class="bg-white rounded-2xl border border-slate-100 shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 sm:p-8 cursor-pointer group hover:shadow-lg hover:border-blue-200 transition-all duration-300">
                        <div class="flex justify-between items-center mb-8">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">Performance de Receita</h4>
                            <span class="text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg></span>
                        </div>
                        <div class="h-64"><Bar :data="faturamentoChartData" :options="barOptions" /></div>
                    </div>
                    <div @click="irParaAuditoria" class="bg-white rounded-2xl border border-slate-100 shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 sm:p-8 cursor-pointer group hover:shadow-lg hover:border-blue-200 transition-all duration-300">
                        <div class="flex justify-between items-center mb-8">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">Composição de Custos e Fugas</h4>
                            <span class="text-blue-500 opacity-0 group-hover:opacity-100 transition-opacity"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg></span>
                        </div>
                        <div class="h-64 relative flex items-center justify-center"><Doughnut :data="auditoriaChartData" :options="doughnutOptions" /></div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { Bar, Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';

import ModalAnaliseFinanceira from '@/Components/ModalAnaliseFinanceira.vue';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    resumoFaturamento: { type: Object, default: () => ({ total_notas: 0, receita_total: 0, receita_teorica: 0, lucro_total: 0 }) },
    resumoAuditoria: { type: Object, default: () => ({ total_notas: 0, custo_cobrado: 0, custo_correto: 0, diferenca_total: 0 }) },
    fechamentos: { type: Array, default: () => [] },
    fechamento_id: { type: [String, Number], default: '' },
    fretesDetalhados: { type: Array, default: () => [] },
    faturamentosDetalhados: { type: Array, default: () => [] },
    cruzamentoViagens: { type: Array, default: () => [] } 
});

const formatMoney = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

const formatarData = (dataStr) => {
    if (!dataStr) return 'Sem Data';
    return new Date(dataStr + 'T00:00:00').toLocaleDateString('pt-BR');
};

const extrairNumeroNFe = (chave) => {
    if (!chave || String(chave).length !== 44) return null;
    return Number(String(chave).substring(25, 34)); 
};

const linkSefazCTe = (chave) => {
    if (!chave || chave === 'N/A') return '#';
    const c = String(chave).replace(/\D/g, '');
    return `https://nfe.fazenda.sp.gov.br/CTeConsulta/qrCode?chCTe=${c}&tpAmb=1`;
};

const consultarNFe = (chave) => {
    if (!chave || chave === 'N/A') return;
    const c = String(chave).replace(/\D/g, '');
    navigator.clipboard.writeText(c).then(() => {
        alert(`✅ CHAVE COPIADA: ${c}\n\nCole esta chave na página da SEFAZ que vai abrir agora para consultar a nota.`);
        window.open('https://www.nfe.fazenda.gov.br/portal/consultaResumo.aspx?tipoConsulta=resumo&tipoConteudo=d09RSxZq/aA=', '_blank');
    }).catch(() => {
        window.open('https://www.nfe.fazenda.gov.br/portal/consultaResumo.aspx?tipoConsulta=resumo&tipoConteudo=d09RSxZq/aA=', '_blank');
    });
};

const extrairFimChave = (chave) => {
    if (!chave) return 'N/A';
    const strChave = String(chave).trim();
    if (strChave.length < 15) return strChave;
    return '...' + strChave.slice(-15);
};

const temComplementoE4log = (viagem) => {
    if (!viagem || !Array.isArray(viagem.e4log_detalhes)) return false;
    return viagem.e4log_detalhes.some(f => f.tipo_operacao === 'Complemento' || f.chave_complementada);
};

const explicarCalculoFrete = (empresa, regra, valorCarga, freteBase, tipoOperacao) => {
    const fBase = Number(freteBase) || 0;
    const vCarga = Number(valorCarga) || 0;

    if (tipoOperacao === 'Complemento') {
        if (fBase === 0) return `Documento complementar exclusivo para taxas/impostos. Sem frete base.`;
        return `Documento complementar. Valor base aprovado: ${formatMoney(fBase)}.`;
    }

    if (vCarga === 0) return `Base de cálculo da mercadoria ausente no XML. Valor fixado pelo sistema: ${formatMoney(fBase)}.`;

    const percentualReal = (fBase / vCarga) * 100;

    if (percentualReal >= 1 && percentualReal <= 15) {
        return `O valor da nota (${formatMoney(vCarga)}) ultrapassou o piso mínimo. A matemática aplicada foi: ${formatMoney(vCarga)} x ${percentualReal.toFixed(2)}% = ${formatMoney(fBase)}.`;
    } else {
        return `O sistema aplicou o Piso Mínimo ou Taxa Fixa da tabela: ${formatMoney(fBase)}. A mercadoria não atingiu o gatilho percentual.`;
    }
};

const gerarRelatorioDedoDuro = (viagem) => {
    if(!viagem) return [];
    let alertas = [];
    let difE4log = 0; let temComplE4log = false; let tdePagaE4log = 0;

    if (viagem.e4log_detalhes && viagem.e4log_detalhes.length > 0) {
        viagem.e4log_detalhes.forEach(f => {
            if (!f.is_correto) difE4log += Number(f.diferenca) || 0;
            if (f.tipo_operacao === 'Complemento') temComplE4log = true;
            tdePagaE4log += Number(f.tdeCalculado) || 0;
        });
    }

    if (difE4log > 0) {
        alertas.push({
            tipo: 'critico', icone: '🚨', titulo: 'Cobrança Indevida da Transportadora (E4LOG)',
            texto: `A E4LOG embutiu exatamente ${formatMoney(difE4log)} a mais nos XMLs desta viagem além da tabela acordada. Isto significa a adição de pedágios não combinados, taxas extras ou um frete complementar injustificado.`
        });
    }

    if (temComplE4log) {
        alertas.push({
            tipo: 'alerta', icone: '⚠️', titulo: 'CT-e Complementar Emitido pela E4LOG',
            texto: `A E4LOG enviou uma cobrança dividida (um Frete Original + um Complemento). É crucial validar se nós também emitimos um CT-e de Complemento contra a Sol Fácil. Se nós não repassarmos essa cobrança dupla, quem assume 100% desse custo somos nós.`
        });
    }

    let tdeCobradaBwt = 0; let gapBwt = 0;
    if (viagem.bwt_detalhes && viagem.bwt_detalhes.length > 0) {
        viagem.bwt_detalhes.forEach(f => { tdeCobradaBwt += Number(f.receita_tde) || 0; gapBwt += Number(f.gap_individual) || 0; });
    } else {
        alertas.push({
            tipo: 'critico', icone: '💀', titulo: 'Fuga Total de Faturamento (Prejuízo Absoluto)',
            texto: `ERRO GRAVE: A E4LOG já cobrou o custo desta viagem, mas o sistema detetou que a BWT AINDA NÃO EMITIU FATURA para a Sol Fácil. Nós fizemos este transporte de graça. Providencie a emissão do CT-e o quanto antes.`
        });
    }

    if (gapBwt > 0) {
        alertas.push({
            tipo: 'alerta', icone: '💸', titulo: 'Subfaturamento de Tabela (Deixámos Dinheiro na Mesa)',
            texto: `O nosso negociador estipulou uma tabela, mas o nosso faturista emitiu o CT-e com um valor mais baixo. Deixámos de faturar ${formatMoney(gapBwt)} que eram nosso direito nesta carga.`
        });
    }

    if (tdePagaE4log > 0 && tdeCobradaBwt === 0 && viagem.bwt_detalhes && viagem.bwt_detalhes.length > 0) {
        alertas.push({
            tipo: 'critico', icone: '🎯', titulo: 'ERRO HUMANO DETETADO: FALHA NO REPASSE DA TDE',
            texto: `O sistema aprova o pagamento de TDE para a E4LOG, MAS reparou que a BWT não cobrou a TDE ao cliente Sol Fácil. Ação Obrigatória: Emitir IMEDIATAMENTE um CT-e de Complemento contra a Sol Fácil cobrando a TDE.`
        });
    }

    if (alertas.length === 0) {
        alertas.push({
            tipo: 'sucesso', icone: '✅', titulo: 'Auditoria 100% Limpa e Fechada',
            texto: `Nenhuma falha detetada. O sistema varreu as regras e confirma que cobrámos o valor exato à Sol Fácil e pagámos o valor exato à E4LOG, sem taxas ocultas.`
        });
    }

    return alertas;
};

const obterReceitaAssociada = (frete) => {
    const viagem = props.cruzamentoViagens.find(v => v.e4log_detalhes.some(e => e.id === frete.id));
    return viagem ? viagem.receita : 0;
};

const obterLucroAssociado = (frete) => {
    const viagem = props.cruzamentoViagens.find(v => v.e4log_detalhes.some(e => e.id === frete.id));
    return viagem ? viagem.lucro : (0 - frete.cobrado);
};

const filtroSelecionado = ref(props.fechamento_id || '');
const aplicarFiltro = () => router.get('/dashboard', { fechamento_id: filtroSelecionado.value }, { preserveState: true, preserveScroll: true });

const fechamentoAtual = computed(() => filtroSelecionado.value ? props.fechamentos.find(f => f.id == filtroSelecionado.value) : null);
const lucroPresumido = computed(() => (props.resumoFaturamento.receita_teorica || 0) - (props.resumoAuditoria.custo_correto || 0));
const gapLucro = computed(() => lucroPresumido.value - (props.resumoFaturamento.lucro_total || 0));
const margemLiquida = computed(() => props.resumoFaturamento.receita_total === 0 ? 0 : ((props.resumoFaturamento.lucro_total / props.resumoFaturamento.receita_total) * 100).toFixed(1));

// ==============================================================================
// GESTÃO DE ESTADO DOS MODAIS E OVERLAYS
// ==============================================================================
const dossieViagemSelecionado = ref(null); 
const kpiModalAberto = ref(null); 

const fecharTudo = () => {
    dossieViagemSelecionado.value = null;
    kpiModalAberto.value = null;
    document.body.style.overflow = 'auto';
};

const abrirDossieMatch = (viagem) => { 
    kpiModalAberto.value = null; 
    dossieViagemSelecionado.value = viagem; 
    document.body.style.overflow = 'hidden'; 
};

const abrirDossiePorArquivo = (arquivoXML) => {
    const viagemCompleta = props.cruzamentoViagens.find(v => 
        v.e4log_detalhes.some(e => e.arquivo === arquivoXML) || 
        v.bwt_detalhes.some(b => b.arquivo === arquivoXML)
    );
    if (viagemCompleta) {
        abrirDossieMatch(viagemCompleta);
    } else {
        alert('Este documento não pôde ser rastreado no cruzamento completo das viagens.');
    }
};

const abrirKpiModal = (tipo) => {
    kpiModalAberto.value = tipo;
    document.body.style.overflow = 'hidden';
};

const handleKeydown = (e) => { if (e.key === 'Escape') fecharTudo(); };
onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));

// ==============================================================================
// ALGORITMO DE AUDITORIA PERFEITA ("NÍVEL GOD") - FUSÃO ESTRATÉGICA
// ==============================================================================
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

const abaExpandida = ref('match'); 
const alternarAba = (aba) => { abaExpandida.value = abaExpandida.value === aba ? null : aba; };

const descobrirMotivo = (frete) => {
    if (frete.is_correto) return 'Cobrança exata em tabela comercial';
    if (frete.regra === '⚠️ CIDADE NÃO MAPEADA') return 'Cidade não existe no cadastro de Regiões.';
    
    const dif = Number(frete.diferenca) || 0;
    if (Math.abs(dif) <= 0.50) return 'Cobrança exata em tabela comercial';
    if (dif < 0) return 'E4LOG cobrou a menor (Ganho operacional)';
    
    let motivos = [];
    if (Number(frete.taxasExtras) > 0) motivos.push('Taxas extras não combinadas embutidas no XML');
    
    const baseECalculada = Number(frete.freteBaseCalculado) + Number(frete.tdeCalculado);
    if (Number(frete.cobrado) > (baseECalculada + 0.50)) {
        if (Number(frete.tdeCalculado) === 0 && !frete.temTde) motivos.push('Valor base cobrado acima da tabela regional');
        else motivos.push('TDE cobrada sem justificativa ou acima do combinado');
    }
    
    return motivos.length > 0 ? motivos.join(' + ') : 'Divergência de cálculo na tabela';
};

const badgeOperacao = (tipo) => {
    if (tipo === 'Reentrega') return 'bg-amber-50 text-amber-600 border-amber-200';
    if (tipo === 'Devolução') return 'bg-slate-100 text-slate-600 border-slate-200';
    if (tipo === 'Complemento') return 'bg-fuchsia-50 text-fuchsia-600 border-fuchsia-200';
    return 'bg-blue-50/50 text-blue-600 border-blue-100'; 
};

const faturamentoChartData = computed(() => ({ labels: ['Receita Real', 'Receita Presumida'], datasets: [{ backgroundColor: ['#2563eb', '#10b981'], borderRadius: 6, data: [props.resumoFaturamento.receita_total, props.resumoFaturamento.receita_teorica] }] }));
const auditoriaChartData = computed(() => ({ labels: ['Cobrado E4LOG', 'Custo Correto', 'Divergência'], datasets: [{ backgroundColor: ['#f97316', '#cbd5e1', '#ef4444'], borderWidth: 0, hoverOffset: 4, data: [props.resumoAuditoria.custo_cobrado, props.resumoAuditoria.custo_correto, props.resumoAuditoria.diferenca_total] }] }));
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8, titleFont: { size: 13 } } }, scales: { y: { border: { display: false }, grid: { color: '#f1f5f9', drawBorder: false } }, x: { border: { display: false }, grid: { display: false } } } };
const doughnutOptions = { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: "'Inter', sans-serif", size: 12 } } }, tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8 } } };

// Controle do Menu Mobile
const sidebarOpen = ref(false);
const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };
</script>

<template>
    <Head title="Painel de Controle - BWT Logística" />

    <div class="flex h-screen bg-slate-50 font-sans selection:bg-blue-500 selection:text-white overflow-hidden">
        
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 shadow-2xl transition-transform duration-300 md:translate-x-0 md:static md:flex md:flex-col overflow-y-auto">
            <div class="flex items-center justify-center h-20 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center text-white font-black text-xl shadow-[0_0_15px_rgba(59,130,246,0.5)]">B</div>
                    <span class="text-xl font-black text-white tracking-tight">BWT <span class="text-blue-400 font-light">Auditor</span></span>
                </div>
            </div>

            <div class="flex-1 px-4 py-6 space-y-8">
                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Visão Geral</p>
                    <Link href="/dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-blue-600 text-white shadow-[0_4px_12px_rgba(37,99,235,0.4)] transition-all">
                        <svg class="w-5 h-5 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span class="text-sm font-semibold">Dashboard</span>
                    </Link>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3">Auditoria & Operação</p>
                    <div class="space-y-1">
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition-colors group">
                            <svg class="w-5 h-5 text-slate-500 group-hover:text-amber-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <span class="text-sm font-medium">Auditoria E4LOG</span>
                        </Link>
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition-colors group">
                            <svg class="w-5 h-5 text-slate-500 group-hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span class="text-sm font-medium">Faturamento Sol Fácil</span>
                        </Link>
                        <Link href="/fechamentos" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800 transition-colors group">
                            <svg class="w-5 h-5 text-slate-500 group-hover:text-emerald-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                            <span class="text-sm font-medium">Lançamentos (Upload)</span>
                        </Link>
                    </div>
                </div>

                <div>
                    <p class="px-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3 flex items-center gap-2">Módulos PRO <span class="bg-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded text-[8px]">NOVO</span></p>
                    <div class="space-y-1">
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group opacity-70 hover:opacity-100">
                            <span class="text-lg">⏱️</span> <span class="text-sm font-medium">Simulador de Contratos</span>
                        </Link>
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group opacity-70 hover:opacity-100">
                            <span class="text-lg">⏳</span> <span class="text-sm font-medium">Auditoria de SLA (Atrasos)</span>
                        </Link>
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group opacity-70 hover:opacity-100">
                            <span class="text-lg">📱</span> <span class="text-sm font-medium">Robô de Alertas (WPP)</span>
                        </Link>
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group opacity-70 hover:opacity-100">
                            <span class="text-lg">🤝</span> <span class="text-sm font-medium">Portal do Parceiro (E4LOG)</span>
                        </Link>
                        <Link href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors group opacity-70 hover:opacity-100">
                            <span class="text-lg">🏦</span> <span class="text-sm font-medium">Conciliação Bancária</span>
                        </Link>
                    </div>
                </div>

            </div>
            
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-white font-bold text-xs">DI</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">Diretoria BWT</p>
                        <p class="text-[10px] text-slate-500 truncate">diretoria@bwt.com.br</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            
            <header class="bg-white border-b border-slate-200 shadow-sm z-30 h-20 flex-shrink-0 flex items-center justify-between px-4 sm:px-8">
                <div class="flex items-center gap-4">
                    <button @click="toggleSidebar" class="md:hidden text-slate-500 hover:text-slate-700 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div>
                        <h1 class="text-xl font-black text-slate-900 tracking-tight">Painel Executivo</h1>
                        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest hidden sm:block">Centro de Inteligência Logística</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 sm:gap-6">
                    <div class="hidden sm:flex items-center gap-3 bg-slate-50 border border-slate-200 px-4 py-2 rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <select v-model="filtroSelecionado" @change="aplicarFiltro" class="bg-transparent border-none text-slate-700 text-sm font-bold focus:ring-0 p-0 cursor-pointer outline-none w-48">
                            <option value="">Visão Global (Consolidado)</option>
                            <option v-for="f in fechamentos" :key="f.id" :value="f.id">{{ f.titulo }}</option>
                        </select>
                    </div>

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    <button class="relative p-2 text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-slate-50 p-4 sm:p-8">
                
                <div class="grid grid-cols-1 md:grid-cols-5 gap-5 mb-10">
                    
                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-16 h-16 bg-blue-500/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg shadow-inner">🚛</div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> 12%
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Total de Viagens</p>
                            <h3 class="text-3xl font-black text-slate-800 tracking-tight">{{ cruzamentoViagens.length }} <span class="text-sm font-medium text-slate-400 tracking-normal">CT-es Casados</span></h3>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-16 h-16 bg-emerald-500/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg shadow-inner">📈</div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-emerald-50 text-emerald-600 border border-emerald-100">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> 5%
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Faturado Sol Fácil</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate" :title="formatMoney(resumoFaturamento.receita_total)">{{ formatMoney(resumoFaturamento.receita_total) }}</h3>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-16 h-16 bg-orange-500/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-lg shadow-inner">📉</div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg> 0%
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-1">Liberado p/ Pagto (E4LOG)</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate" :title="formatMoney(totaisCorretos.liberado)">{{ formatMoney(totaisCorretos.liberado) }}</h3>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-16 h-16 bg-purple-500/5 rounded-bl-[100px] -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg shadow-inner">🛡️</div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-emerald-50 text-emerald-600 border border-emerald-100" title="Menos erros da transportadora este mês">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg> 2%
                            </span>
                        </div>
                        <div>
                            <p class="text-[11px] font-black text-purple-500 uppercase tracking-wider mb-1">Glosas Intercetadas</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight truncate" :title="formatMoney(totaisDivergentes.glosa)">{{ formatMoney(totaisDivergentes.glosa) }}</h3>
                        </div>
                    </div>

                    <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800 shadow-xl hover:shadow-2xl transition-shadow relative overflow-hidden group">
                        <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/20 rounded-bl-[100px] -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div class="w-10 h-10 rounded-xl bg-white/10 text-white flex items-center justify-center text-lg backdrop-blur-sm border border-white/5">💎</div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded bg-blue-500 text-white border border-blue-400 shadow-sm">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg> 8%
                            </span>
                        </div>
                        <div class="relative z-10">
                            <p class="text-[11px] font-black text-blue-400 uppercase tracking-wider mb-1">EBITDA Real Líquido</p>
                            <h3 class="text-2xl font-black text-white tracking-tight truncate" :title="formatMoney(totaisMatch.lucro - totaisMatch.prejuizo)">{{ formatMoney(totaisMatch.lucro - totaisMatch.prejuizo) }}</h3>
                        </div>
                    </div>

                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                    <button @click="alternarAba('match')" class="group relative flex justify-between items-start sm:items-center bg-white rounded-2xl p-5 shadow-[0_2px_12px_rgba(0,0,0,0.03)] border transition-all duration-300 active:scale-[0.99] flex-col sm:flex-row gap-4" :class="abaExpandida === 'match' ? 'border-indigo-400 ring-2 ring-indigo-400/20 bg-indigo-50/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-md'">
                        <div class="flex items-center gap-4 w-full sm:w-auto">
                            <div class="bg-indigo-50 text-indigo-500 p-3 rounded-xl transition-colors group-hover:bg-indigo-100 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div class="text-left">
                                <h4 class="font-bold text-slate-800 text-sm">Rentabilidade por Viagem</h4>
                                <p class="text-[10px] text-slate-500 mt-0.5"><span class="font-bold text-indigo-600">{{ cruzamentoViagens.length }}</span> Viagens (Casamento NF-e)</p>
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

                <div v-if="abaExpandida === 'match'" class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.04)] border border-slate-200 overflow-hidden animate-fade-in mb-8">
                    <div class="overflow-x-auto max-h-[600px] scrollbar-thin scrollbar-thumb-slate-200">
                        <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                            <thead class="bg-slate-50/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-10">
                                <tr>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Status</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Carga / Nota Base</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Destino</th>
                                    <th class="py-4 px-5 font-bold text-blue-600 text-[10px] uppercase tracking-wider">Receita BWT</th>
                                    <th class="py-4 px-5 font-bold text-orange-500 text-[10px] uppercase tracking-wider">Custo E4LOG</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider text-right">Faturado</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider text-right">Custo Pago</th>
                                    <th class="py-4 px-5 font-black text-slate-800 text-[10px] uppercase tracking-wider text-right">Lucro Líquido</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="viagem in cruzamentoViagens" :key="viagem.id" @click="abrirDossieMatch(viagem)" class="cursor-pointer hover:bg-indigo-50/40 transition-colors group" :class="viagem.status === 'sem_receita' ? 'bg-rose-50/20 hover:bg-rose-100/40' : ''">
                                    <td class="py-3.5 px-5">
                                        <span v-if="viagem.status === 'casada'" class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-emerald-200">🟢 Casada (Match)</span>
                                        <span v-else-if="viagem.status === 'sem_receita'" class="bg-rose-100 text-rose-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-rose-200 shadow-sm animate-pulse">🔴 Sem Receita (Órfã)</span>
                                        <span v-else class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-amber-200">🟡 Aguardando Custo</span>
                                    </td>
                                    
                                    <td class="py-3.5 px-5">
                                        <div v-if="extrairNumeroNFe(viagem.nfe_chave)" class="font-black text-slate-800 text-xs mb-0.5 group-hover:text-indigo-600 transition-colors flex items-center gap-2">
                                            NF-e {{ extrairNumeroNFe(viagem.nfe_chave) }}
                                        </div>
                                        <div v-else class="font-bold text-slate-400 text-xs italic mb-0.5 group-hover:text-indigo-600 transition-colors">
                                            COMPLEMENTO
                                        </div>
                                        <div class="text-slate-400 font-mono text-[9px] truncate max-w-[150px]" :title="viagem.nfe_chave">{{ viagem.nfe_chave }}</div>
                                    </td>

                                    <td class="py-3.5 px-5 text-slate-600 text-xs font-bold">{{ viagem.destino }}</td>
                                    <td class="py-3.5 px-5 text-blue-600 font-bold text-xs">
                                        <div v-for="cte in viagem.cte_bwt" :key="cte" class="truncate max-w-[150px]" :title="cte">{{ cte }}</div>
                                        <span v-if="viagem.cte_bwt.length === 0 || (viagem.cte_bwt.length===1 && viagem.cte_bwt[0]==='NÃO FATURADO')" class="text-[10px] text-slate-400 font-bold italic">Não Faturado</span>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <div v-for="cte in viagem.ctes_e4log" :key="cte" class="text-xs font-bold text-orange-600 truncate max-w-[150px]" :title="cte">{{ cte }}</div>
                                        <span v-if="viagem.ctes_e4log.length === 0" class="text-[10px] text-slate-400 font-bold italic">Nenhum CT-e associado</span>
                                    </td>
                                    <td class="py-3.5 px-5 text-right text-emerald-600 font-bold">{{ formatMoney(viagem.receita) }}</td>
                                    <td class="py-3.5 px-5 text-right text-rose-600 font-bold">{{ formatMoney(viagem.custo) }}</td>
                                    <td class="py-3.5 px-5 text-right font-black text-sm" :class="viagem.lucro > 0 ? 'text-slate-800' : 'text-rose-600'">{{ formatMoney(viagem.lucro) }}</td>
                                </tr>
                                <tr v-if="cruzamentoViagens.length === 0">
                                    <td colspan="8" class="text-center py-10 text-slate-500 font-medium">Nenhum cruzamento encontrado no fechamento atual.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="abaExpandida === 'divergentes' || abaExpandida === 'corretos'" class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.04)] border border-slate-200 overflow-hidden animate-fade-in mb-8">
                    <div class="overflow-x-auto max-h-[500px] scrollbar-thin scrollbar-thumb-slate-200">
                        <table class="w-full text-left text-sm border-collapse whitespace-nowrap">
                            <thead class="bg-slate-50/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-10">
                                <tr>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Chave do XML (CT-e)</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Operação</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Custo E4LOG</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider">Tabela Certa</th>
                                    <th class="py-4 px-5 font-bold text-blue-600 text-[10px] uppercase tracking-wider">Receita Sol Fácil</th>
                                    <th class="py-4 px-5 font-bold text-[10px] uppercase tracking-wider text-right">Diferença (Lucro Líquido)</th>
                                    <th class="py-4 px-5 font-bold text-slate-500 text-[10px] uppercase tracking-wider w-72">Motivo do Sistema</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="frete in (abaExpandida === 'divergentes' ? fretesDivergentes : fretesCorretos)" :key="frete.id" @click="abrirDossiePorArquivo(frete.arquivo)" class="cursor-pointer hover:bg-indigo-50/30 transition-colors group">
                                    <td class="py-3.5 px-5">
                                        <span class="text-slate-800 font-semibold text-xs truncate max-w-[150px] block" :title="frete.arquivo">{{ frete.arquivo }}</span>
                                        <span class="text-slate-400 font-medium text-[9px] mt-0.5 block">Emissão: {{ formatarData(frete.data_emissao) }}</span>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded border uppercase tracking-wider inline-flex items-center" :class="badgeOperacao(frete.tipo_operacao)">
                                            {{ frete.tipo_operacao || 'Entrega' }}
                                        </span>
                                        <span class="text-slate-500 text-[10px] font-bold block mt-1">{{ frete.destino }}</span>
                                    </td>
                                    <td class="py-3.5 px-5 text-slate-700 font-semibold">{{ formatMoney(frete.cobrado) }}</td>
                                    <td class="py-3.5 px-5 text-emerald-600 font-semibold">{{ formatMoney(frete.correto) }}</td>
                                    <td class="py-3.5 px-5 text-blue-600 font-bold">{{ formatMoney(obterReceitaAssociada(frete)) }}</td>
                                    <td class="py-3.5 px-5 font-black text-sm text-right" :class="obterLucroAssociado(frete) > 0 ? 'text-emerald-500' : (obterLucroAssociado(frete) < 0 ? 'text-rose-500' : 'text-slate-500')">
                                        {{ formatMoney(obterLucroAssociado(frete)) }}
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <span class="text-[10px] font-bold px-2 py-1 rounded shadow-sm inline-block" :class="abaExpandida === 'divergentes' ? 'bg-red-50 border border-red-100 text-red-600 max-w-[260px] truncate' : 'bg-emerald-50 border border-emerald-100 text-emerald-600'">
                                            {{ descobrirMotivo(frete) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="(abaExpandida === 'divergentes' && fretesDivergentes.length === 0) || (abaExpandida === 'corretos' && fretesCorretos.length === 0)">
                                    <td colspan="7" class="text-center py-10 text-slate-500 font-medium">Nenhum registo encontrado para esta categoria.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-12">
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 sm:p-8 cursor-pointer group hover:shadow-lg hover:border-blue-200 transition-all duration-300">
                        <div class="flex justify-between items-center mb-8">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">Performance de Receita</h4>
                        </div>
                        <div class="h-64"><Bar :data="faturamentoChartData" :options="barOptions" /></div>
                    </div>
                    <div class="bg-white rounded-2xl border border-slate-100 shadow-[0_2px_12px_rgba(0,0,0,0.03)] p-6 sm:p-8 cursor-pointer group hover:shadow-lg hover:border-blue-200 transition-all duration-300">
                        <div class="flex justify-between items-center mb-8">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-wider">Composição de Custos e Fugas</h4>
                        </div>
                        <div class="h-64 relative flex items-center justify-center"><Doughnut :data="auditoriaChartData" :options="doughnutOptions" /></div>
                    </div>
                </div>

            </main>
        </div>

        <transition name="modal-overlay">
            <div v-if="kpiModalAberto" class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-6" style="font-family: 'Inter', sans-serif;">
                <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click.self="fecharTudo"></div>
                <div class="relative w-full max-w-6xl bg-white flex flex-col max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up">
                    
                    <div class="px-6 py-5 flex justify-between items-center border-b shrink-0 z-20" :class="{ 'bg-red-50 border-red-200': kpiModalAberto === 'fuga' || kpiModalAberto === 'divergencia', 'bg-amber-50 border-amber-200': kpiModalAberto === 'gap' }">
                        <div class="flex items-center gap-4">
                            <span class="text-3xl" v-if="kpiModalAberto === 'fuga'">💸</span>
                            <span class="text-3xl" v-if="kpiModalAberto === 'gap'">⚠️</span>
                            <span class="text-3xl" v-if="kpiModalAberto === 'divergencia'">🚨</span>
                            <div>
                                <h3 class="text-xl font-black text-slate-800">
                                    {{ kpiModalAberto === 'fuga' ? 'Relatório de Fugas de Lucro (Prejuízos Reais)' : '' }}
                                    {{ kpiModalAberto === 'gap' ? 'Gap de Receita (Subfaturamento)' : '' }}
                                    {{ kpiModalAberto === 'divergencia' ? 'Divergências Operacionais da E4LOG' : '' }}
                                </h3>
                                <p class="text-xs text-slate-600 font-medium">Clique em qualquer linha abaixo para abrir o Extrato DACTE detalhado da viagem.</p>
                            </div>
                        </div>
                        <button @click="fecharTudo" class="text-slate-400 hover:text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 p-2 rounded-xl transition-colors shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-slate-300 p-6 bg-slate-50/50 flex-1">
                        
                        <table v-if="kpiModalAberto === 'fuga'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
                            <thead class="bg-red-50 border-b border-red-100">
                                <tr>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider">Nota Fiscal / DNA</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider">Destino</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider text-right">Recebido Cliente</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider text-right">Pago E4LOG</th>
                                    <th class="py-3 px-4 font-black text-red-900 text-[10px] uppercase tracking-wider text-right">Prejuízo Líquido</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="v in cruzamentoViagens.filter(x => x.lucro < 0)" :key="v.id" @click="abrirDossieMatch(v)" class="cursor-pointer hover:bg-red-50/40 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-800">{{ extrairNumeroNFe(v.nfe_chave) ? 'NF-e ' + extrairNumeroNFe(v.nfe_chave) : 'Compl. S/ Lastro' }}</td>
                                    <td class="py-3 px-4 text-slate-600 text-xs">{{ v.destino }}</td>
                                    <td class="py-3 px-4 text-emerald-600 font-bold text-right">{{ formatMoney(v.receita) }}</td>
                                    <td class="py-3 px-4 text-rose-600 font-bold text-right">{{ formatMoney(v.custo) }}</td>
                                    <td class="py-3 px-4 text-red-600 font-black text-right text-base">{{ formatMoney(v.lucro) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-if="kpiModalAberto === 'gap'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
                            <thead class="bg-amber-50 border-b border-amber-100">
                                <tr>
                                    <th class="py-3 px-4 font-bold text-amber-800 text-[10px] uppercase tracking-wider">Documento Faturado (CT-e)</th>
                                    <th class="py-3 px-4 font-bold text-amber-800 text-[10px] uppercase tracking-wider">Destino</th>
                                    <th class="py-3 px-4 font-bold text-amber-800 text-[10px] uppercase tracking-wider text-right">Faturado (Real)</th>
                                    <th class="py-3 px-4 font-bold text-amber-800 text-[10px] uppercase tracking-wider text-right">Tabela (Deveria)</th>
                                    <th class="py-3 px-4 font-black text-amber-900 text-[10px] uppercase tracking-wider text-right">Dinheiro Perdido (Gap)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="f in faturamentosDetalhados" :key="f.id" @click="abrirDossiePorArquivo(f.arquivo)" class="cursor-pointer hover:bg-amber-50/40 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-800 text-xs truncate max-w-[200px]" :title="f.arquivo">{{ f.arquivo }}</td>
                                    <td class="py-3 px-4 text-slate-600 text-xs">{{ f.destino }}</td>
                                    <td class="py-3 px-4 text-blue-600 font-bold text-right">{{ formatMoney(f.receita_real) }}</td>
                                    <td class="py-3 px-4 text-emerald-600 font-bold text-right">{{ formatMoney(f.receita_teorica) }}</td>
                                    <td class="py-3 px-4 text-amber-600 font-black text-right text-base">- {{ formatMoney(f.gap_individual) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-if="kpiModalAberto === 'divergencia'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
                            <thead class="bg-red-50 border-b border-red-100">
                                <tr>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider">CT-e Cobrado pela E4LOG</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider">Destino</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider text-right">Cobrado</th>
                                    <th class="py-3 px-4 font-bold text-red-800 text-[10px] uppercase tracking-wider text-right">Tabela Correta</th>
                                    <th class="py-3 px-4 font-black text-red-900 text-[10px] uppercase tracking-wider text-right">Excesso Cobrado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="f in fretesDivergentes" :key="f.id" @click="abrirDossiePorArquivo(f.arquivo)" class="cursor-pointer hover:bg-red-50/40 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-800 text-xs truncate max-w-[200px]" :title="f.arquivo">{{ f.arquivo }}</td>
                                    <td class="py-3 px-4 text-slate-600 text-xs">{{ f.destino }}</td>
                                    <td class="py-3 px-4 text-rose-600 font-bold text-right">{{ formatMoney(f.cobrado) }}</td>
                                    <td class="py-3 px-4 text-emerald-600 font-bold text-right">{{ formatMoney(f.correto) }}</td>
                                    <td class="py-3 px-4 text-red-600 font-black text-right text-base">+ {{ formatMoney(f.diferenca) }}</td>
                                </tr>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </transition>

        <transition name="modal-overlay">
            <div v-if="dossieViagemSelecionado" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" style="font-family: 'Inter', sans-serif;">
                
                <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click.self="fecharTudo"></div>

                <div class="relative w-full max-w-7xl bg-slate-50 flex flex-col max-h-[95vh] rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-slate-700">
                    
                    <div class="bg-indigo-950 px-8 py-6 flex justify-between items-center border-b-4 shrink-0 z-20" :class="dossieViagemSelecionado.status === 'casada' ? 'border-b-emerald-500' : 'border-b-rose-500'">
                        <div class="flex items-center gap-5">
                            <div class="w-14 h-14 rounded-full flex items-center justify-center shadow-inner" :class="dossieViagemSelecionado.status === 'casada' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'">
                                <svg v-if="dossieViagemSelecionado.status === 'casada'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <svg v-else class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-indigo-300 uppercase tracking-[0.2em] block mb-1">Extrato de Auditoria DACTE Virtual</span>
                                <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                    NF-e {{ extrairNumeroNFe(dossieViagemSelecionado.nfe_chave) || 'Complemento' }} 
                                    <span class="text-sm font-semibold text-indigo-200 bg-indigo-900 px-3 py-1 rounded-full border border-indigo-800">Destino: {{ dossieViagemSelecionado.destino }}</span>
                                </h3>
                            </div>
                        </div>
                        <button @click="fecharTudo" class="text-indigo-300 hover:text-white bg-indigo-900 hover:bg-indigo-800 p-2.5 rounded-xl transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <div class="p-8 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-300 flex flex-col gap-6 relative z-10">

                        <div class="bg-slate-900 rounded-3xl p-6 shadow-inner text-white flex flex-col md:flex-row items-center gap-6">
                            <div class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-blue-400 font-black text-[10px] uppercase tracking-[0.2em] mb-2">Como o sistema cruzou estes dados? (Rastreabilidade de DNA)</h4>
                                <p class="text-sm text-slate-300 leading-relaxed font-mono">
                                    ⚓ <strong>Âncora de Busca:</strong> O sistema isolou a Nota Fiscal <strong class="text-white">NF-e {{ extrairNumeroNFe(dossieViagemSelecionado.nfe_chave) }}</strong>.<br>
                                    🔍 <strong>Fase 1:</strong> Encontrou <strong>{{ dossieViagemSelecionado.bwt_detalhes ? dossieViagemSelecionado.bwt_detalhes.length : 0 }} CT-e(s)</strong> da BWT e <strong>{{ dossieViagemSelecionado.e4log_detalhes.length }} CT-e(s)</strong> da E4LOG contendo esta chave.<br>
                                    🔗 <strong>Fase 2:</strong> Vasculhou a SEFAZ e anexou automaticamente as "Notas Órfãs" (Complementos de TDE, Reentregas) que apontavam para os CT-es principais.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col lg:flex-row gap-8">
                            <div class="flex-1 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-w-0">
                                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                        <h4 class="font-black text-slate-800 text-lg tracking-tight truncate">Receita BWT (Sol Fácil)</h4>
                                    </div>
                                    <span class="bg-white border border-slate-200 px-3 py-1 rounded-full text-[10px] font-bold text-slate-500 uppercase tracking-wider shadow-sm shrink-0 whitespace-nowrap">{{ dossieViagemSelecionado.bwt_detalhes ? dossieViagemSelecionado.bwt_detalhes.length : 0 }} CT-e(s)</span>
                                </div>

                                <div v-if="dossieViagemSelecionado.bwt_detalhes && dossieViagemSelecionado.bwt_detalhes.length > 0" class="p-6 flex-1 flex flex-col">
                                    <div v-for="(fat, index) in dossieViagemSelecionado.bwt_detalhes" :key="index" class="mb-8 border border-slate-200 rounded-2xl overflow-hidden shadow-sm relative">
                                        
                                        <div class="bg-slate-100/50 border-b border-slate-200 px-4 py-3 flex justify-between items-center gap-2">
                                            <div class="flex flex-col min-w-0">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nº do Documento XML <span class="text-slate-400 mx-1">•</span> Emissão: <span class="text-slate-600 font-bold">{{ formatarData(fat.data_emissao) }}</span></span>
                                                <span class="text-xs font-bold text-slate-700 truncate" :title="fat.arquivo">{{ fat.arquivo }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded border border-blue-200 bg-white text-blue-600 whitespace-nowrap">REGRA: {{ fat.regra }}</span>
                                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded whitespace-nowrap" :class="badgeOperacao(fat.tipo_operacao)">{{ fat.tipo_operacao || 'Entrega' }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="p-4">
                                            <div class="bg-indigo-50/50 border border-indigo-100 rounded-lg p-3 mb-5 text-xs text-slate-600 font-mono space-y-2">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-bold text-slate-400 whitespace-nowrap mr-2">CHAVE DO CT-E BWT:</span>
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="font-bold text-indigo-700 truncate" :title="fat.cte_chave">{{ extrairFimChave(fat.cte_chave) }}</span>
                                                        <a :href="linkSefazCTe(fat.cte_chave)" target="_blank" title="Consultar no Portal SEFAZ" class="bg-indigo-100 text-indigo-600 hover:bg-indigo-600 hover:text-white p-1 rounded transition-colors shrink-0">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center border-t border-indigo-100/50 pt-2">
                                                    <span v-if="fat.chave_complementada" class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 CT-E PAI:</span>
                                                    <span v-else class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 LASTRO (NF-E):</span>
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="font-bold text-indigo-700 truncate" :title="fat.chave_complementada || fat.nfe_chave">{{ fat.chave_complementada ? extrairFimChave(fat.chave_complementada) : extrairFimChave(fat.nfe_chave) }}</span>
                                                        <button v-if="!fat.chave_complementada" @click.prevent="consultarNFe(fat.nfe_chave)" title="Copiar Chave e Consultar NF-e na SEFAZ" class="bg-indigo-100 text-indigo-600 hover:bg-indigo-600 hover:text-white p-1 rounded transition-colors cursor-pointer shrink-0">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-5 bg-amber-50/50 border-l-4 border-amber-400 p-3 rounded-r-lg">
                                                <span class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1">Observações impressas no XML</span>
                                                <p class="text-xs text-slate-700 italic leading-relaxed">{{ fat.observacoes || 'Sem observações adicionais no documento.' }}</p>
                                            </div>

                                            <div class="bg-slate-100/50 rounded-xl p-4 font-mono text-xs border border-slate-200">
                                                <p class="font-bold text-slate-700 mb-3 border-b border-slate-200 pb-2">MATEMÁTICA DO FATURAMENTO (BWT)</p>
                                                <ul class="space-y-3 text-slate-600">
                                                    <li class="flex justify-between items-start">
                                                        <div>
                                                            <span class="block">1. Frete Base</span>
                                                            <span class="block text-[9px] font-bold text-blue-500 mt-0.5">{{ explicarCalculoFrete('BWT', fat.regra, fat.valor_carga, fat.tipo_operacao === 'Complemento' ? 0 : fat.receita_frete_base, fat.tipo_operacao) }}</span>
                                                        </div>
                                                        <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.tipo_operacao === 'Complemento' ? 0 : fat.receita_frete_base)}}</span>
                                                    </li>
                                                    <li class="flex justify-between items-center" v-if="fat.receita_tde > 0">
                                                        <span>2. TDE (Taxa de Dificuldade) Cobrada</span>
                                                        <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.receita_tde)}}</span>
                                                    </li>
                                                    <li class="flex justify-between items-center">
                                                        <span>{{ fat.receita_tde > 0 ? '3.' : '2.' }} Projeção de Imposto (ICMS 12% Dentro)</span>
                                                        <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.receita_icms)}}</span>
                                                    </li>
                                                    <li class="flex justify-between border-t border-slate-300 pt-3 mt-1 text-blue-800">
                                                        <span>= TOTAL LIDO NO ARQUIVO XML DA BWT</span>
                                                        <span class="font-black text-sm shrink-0 ml-2">{{formatMoney(fat.receita_real)}}</span>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div v-if="fat.gap_individual > 0" class="mt-4 p-3 bg-amber-100 text-amber-700 rounded-lg text-xs border border-amber-200">
                                                <strong class="font-black uppercase block mb-1">⚠️ Gap (Erro de Faturamento):</strong>
                                                De acordo com a tabela comercial, deveríamos ter cobrado {{formatMoney(fat.receita_teorica)}} neste serviço. Cobrámos {{formatMoney(fat.gap_individual)}} a menos do que a regra permite!
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-6 bg-slate-50">
                                    <div class="w-12 h-12 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></div>
                                    <h5 class="font-bold text-slate-700">CT-e de Faturamento Ausente</h5>
                                    <p class="text-xs text-slate-500 mt-2">A BWT não faturou esta carga para a Sol Fácil neste lote.</p>
                                </div>

                                <div class="bg-slate-50 border-t border-slate-200 p-6 shrink-0 flex justify-between items-center mt-auto">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Soma Total Faturada BWT</p>
                                        <p class="text-xs text-slate-500 font-medium">Soma de todos os CT-es acima</p>
                                    </div>
                                    <p class="text-3xl font-black text-blue-700 tracking-tight">{{ formatMoney(dossieViagemSelecionado.receita) }}</p>
                                </div>
                            </div>

                            <div class="flex-1 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-w-0">
                                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                        <h4 class="font-black text-slate-800 text-lg tracking-tight truncate">Custo E4LOG (Auditoria)</h4>
                                    </div>
                                    <span class="bg-white border border-slate-200 px-3 py-1 rounded-full text-[10px] font-bold text-slate-500 uppercase tracking-wider shadow-sm shrink-0 whitespace-nowrap">{{ dossieViagemSelecionado.e4log_detalhes.length }} CT-e(s)</span>
                                </div>

                                <div v-if="dossieViagemSelecionado.e4log_detalhes.length > 0" class="p-6 flex-1 flex flex-col">
                                    <div v-for="(frete, index) in dossieViagemSelecionado.e4log_detalhes" :key="index" class="mb-8 border border-slate-200 rounded-2xl overflow-hidden shadow-sm relative">
                                        
                                        <div class="bg-slate-100/50 border-b border-slate-200 px-4 py-3 flex justify-between items-center gap-2" :class="frete.is_correto ? '' : 'bg-rose-50/50 border-rose-200'">
                                            <div class="flex flex-col min-w-0">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nº do Documento XML <span class="text-slate-400 mx-1">•</span> Emissão: <span class="text-slate-600 font-bold">{{ formatarData(frete.data_emissao) }}</span></span>
                                                <span class="text-xs font-bold text-slate-700 truncate" :title="frete.arquivo">{{ frete.arquivo }}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded border border-orange-200 bg-white text-orange-600 whitespace-nowrap">REGRA: {{ frete.regra }}</span>
                                                <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded whitespace-nowrap" :class="badgeOperacao(frete.tipo_operacao)">{{ frete.tipo_operacao || 'Entrega' }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="p-4">
                                            <div class="bg-orange-50/50 border border-orange-100 rounded-lg p-3 mb-5 text-xs text-slate-600 font-mono space-y-2">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-bold text-slate-400 whitespace-nowrap mr-2">CHAVE DO CT-E E4LOG:</span>
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="font-bold text-orange-700 truncate" :title="frete.cte_chave">{{ extrairFimChave(frete.cte_chave) }}</span>
                                                        <a :href="linkSefazCTe(frete.cte_chave)" target="_blank" title="Consultar no Portal SEFAZ" class="bg-orange-100 text-orange-600 hover:bg-orange-600 hover:text-white p-1 rounded transition-colors shrink-0">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="flex justify-between items-center border-t border-orange-100/50 pt-2">
                                                    <span v-if="frete.chave_complementada" class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 CT-E PAI:</span>
                                                    <span v-else class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 LASTRO (NF-E):</span>
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <span class="font-bold text-orange-700 truncate" :title="frete.chave_complementada || frete.nfe_chave">{{ frete.chave_complementada ? extrairFimChave(frete.chave_complementada) : extrairFimChave(frete.nfe_chave) }}</span>
                                                        <button v-if="!frete.chave_complementada" @click.prevent="consultarNFe(frete.nfe_chave)" title="Copiar Chave e Consultar NF-e na SEFAZ" class="bg-orange-100 text-orange-600 hover:bg-orange-600 hover:text-white p-1 rounded transition-colors cursor-pointer shrink-0">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-5 bg-amber-50/50 border-l-4 border-amber-400 p-3 rounded-r-lg">
                                                <span class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1">Observações impressas no XML</span>
                                                <p class="text-xs text-slate-700 italic leading-relaxed">{{ frete.observacoes || 'Sem observações adicionais no documento.' }}</p>
                                            </div>

                                            <div class="bg-slate-100/50 rounded-xl p-4 font-mono text-xs border border-slate-200">
                                                <p class="font-bold text-slate-700 mb-3 border-b border-slate-200 pb-2">MATEMÁTICA DA AUDITORIA (E4LOG)</p>
                                                <ul class="space-y-3 text-slate-600">
                                                    <li class="flex justify-between items-start">
                                                        <div>
                                                            <span class="block">1. Frete Base da Tabela</span>
                                                            <span class="block text-[9px] font-bold text-orange-500 mt-0.5">{{ explicarCalculoFrete('E4LOG', frete.regra, frete.valorNF, frete.tipo_operacao === 'Complemento' ? 0 : frete.freteBaseCalculado, frete.tipo_operacao) }}</span>
                                                        </div>
                                                        <span class="font-bold shrink-0 ml-2">+ {{formatMoney(frete.tipo_operacao === 'Complemento' ? 0 : frete.freteBaseCalculado)}}</span>
                                                    </li>
                                                    <li class="flex justify-between items-center">
                                                        <span>2. Valor Complementar/Taxas {{ frete.temTde ? 'Aprovado' : 'Aprovado' }}</span>
                                                        <span class="font-bold shrink-0 ml-2">+ {{formatMoney(frete.tipo_operacao === 'Complemento' ? frete.correto : frete.tdeCalculado)}}</span>
                                                    </li>
                                                    <li class="flex justify-between border-t border-slate-300 pt-3 mt-1 text-slate-800">
                                                        <span>= TETO JUSTO APROVADO PELO SISTEMA</span>
                                                        <span class="font-black shrink-0 ml-2">{{formatMoney(frete.correto)}}</span>
                                                    </li>
                                                    <li class="flex justify-between mt-2 pt-2 text-orange-700 border-t border-dashed border-orange-200">
                                                        <span>VALOR LIDO NO ARQUIVO XML DA E4LOG</span>
                                                        <span class="font-black text-sm shrink-0 ml-2">{{formatMoney(frete.cobrado)}}</span>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div v-if="!frete.is_correto" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-xs border border-red-200">
                                                <strong class="font-black uppercase block mb-1">🚨 Auditoria Reprovada (Cobrança em Excesso):</strong>
                                                A E4LOG embutiu {{formatMoney(frete.diferenca)}} a mais neste CT-e de forma não autorizada pela regra. O pagamento deste extra fica bloqueado para averiguação.
                                            </div>
                                            <div v-else class="mt-4 p-3 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200">
                                                ✅ AUDITORIA APROVADA: O XML está exato.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-6 bg-slate-50">
                                    <div class="w-12 h-12 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>
                                    <h5 class="font-bold text-slate-700">Custo Não Encontrado</h5>
                                    <p class="text-xs text-slate-500 mt-2">A E4LOG ainda não enviou as cobranças referentes a esta viagem.</p>
                                </div>

                                <div class="bg-slate-50 border-t border-slate-200 p-6 shrink-0 flex justify-between items-center mt-auto">
                                    <div>
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Soma Total Custo E4LOG</p>
                                        <p class="text-xs text-slate-500 font-medium">Soma de todos os CT-es e Compl.</p>
                                    </div>
                                    <p class="text-3xl font-black text-orange-600 tracking-tight">{{ formatMoney(dossieViagemSelecionado.custo) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm mt-4">
                            <div class="bg-slate-50 border-b border-slate-200 px-8 py-5 flex items-center gap-4">
                                <div class="w-10 h-10 bg-slate-800 text-white rounded-full flex items-center justify-center font-black text-lg">🤖</div>
                                <div>
                                    <h3 class="text-slate-900 font-black text-lg tracking-tight">O Veredito Oficial do Auditor (Dedo-Duro)</h3>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Tradução e Diagnóstico Direto da Viagem</p>
                                </div>
                            </div>
                            <div class="p-8">
                                <ul class="space-y-4">
                                    <li v-for="(alerta, idx) in gerarRelatorioDedoDuro(dossieViagemSelecionado)" :key="idx" 
                                        class="flex gap-4 items-start p-5 rounded-xl border shadow-sm"
                                        :class="alerta.tipo === 'critico' ? 'bg-rose-50 border-rose-200 text-rose-800' : (alerta.tipo === 'alerta' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800')"
                                    >
                                        <span class="text-3xl mt-1 drop-shadow-sm">{{ alerta.icone }}</span>
                                        <div>
                                            <h5 class="font-black text-base uppercase tracking-wider mb-1.5">{{ alerta.titulo }}</h5>
                                            <p class="text-sm font-medium leading-relaxed opacity-90">{{ alerta.texto }}</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </div>

                    <div class="bg-slate-900 px-8 py-6 shrink-0 flex flex-col md:flex-row justify-between items-center gap-4 relative z-20 mt-auto rounded-b-2xl">
                        <div>
                            <h4 class="text-sm font-black text-white uppercase tracking-wider mb-1">Resultado Financeiro Final</h4>
                            <p class="text-xs text-slate-400 font-medium">Balanço das contas bancárias (Recebemos da Sol Fácil - Pagámos à E4LOG).</p>
                        </div>
                        <div class="bg-black/50 px-8 py-4 rounded-2xl border border-white/10 flex items-center gap-6">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right leading-tight">Lucro Livre<br>da Viagem</span>
                            <span class="text-4xl font-black drop-shadow-lg" :class="dossieViagemSelecionado.lucro > 0 ? 'text-emerald-400' : 'text-rose-500'">{{ formatMoney(dossieViagemSelecionado.lucro) }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </transition>

    </div>
</template>

<style>
.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(10px); } 100% { opacity: 1; transform: translateY(0); } }

.modal-overlay-enter-active,
.modal-overlay-leave-active {
  transition: opacity 0.3s ease;
}
.modal-overlay-enter-from,
.modal-overlay-leave-to {
  opacity: 0;
}
.modal-overlay-enter-active > div,
.modal-overlay-leave-active > div {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.modal-overlay-enter-from > div {
  transform: translateY(20px);
  opacity: 0;
}
.modal-overlay-leave-to > div {
  transform: translateY(20px);
  opacity: 0;
}

/* Esconde a barra de rolagem mas permite o scroll */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
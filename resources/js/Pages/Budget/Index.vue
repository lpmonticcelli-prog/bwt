<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    budgetId: Number,
    budgetAno: Number,
    budgetVersao: String,
    budgetStatus: String,
    budgetCategories: Array,
    dadosReais: Array
});

// ==========================================
// TRAVA DE SEGURANCA
// ==========================================
const isUnlocked = ref(false);
const inputSenha = ref('');
const erroSenha = ref('');
const isProcessing = ref(false);
const SENHA_MESTRE = '1234';

const desbloquearPagina = () => {
    if (inputSenha.value === SENHA_MESTRE) {
        isUnlocked.value = true;
        erroSenha.value = '';
        sessionStorage.setItem('budget_unlocked', 'true');
    } else {
        erroSenha.value = 'Senha incorreta!';
        inputSenha.value = '';
    }
};

onMounted(() => {
    if (sessionStorage.getItem('budget_unlocked') === 'true') {
        isUnlocked.value = true;
    }
});

// ==========================================
// FATIAMENTO DE DADOS (FILIAL, FORNECEDOR, EVENTO)
// ==========================================
const getFilial = (nome) => {
    const match = (nome || '').match(/^\[(.*?)\]/);
    return match ? match[1] : 'GERAL';
};

const getCleanName = (nome) => {
    return (nome || '').replace(/^\[.*?\]\s*/, '').trim();
};

const getFornecedor = (nome) => {
    let clean = getCleanName(nome);
    const parts = clean.split(' - ');
    return parts[0] || clean;
};

const getEvento = (nome) => {
    let clean = getCleanName(nome);
    const parts = clean.split(' - ');
    if (parts.length > 1) {
        return parts.slice(1).join(' - ').trim();
    }
    return 'DIVERSOS';
};

const getFilialColor = (filial) => {
    if (filial === 'MTZ') return 'bg-blue-100 text-blue-800 border-blue-200';
    if (filial === 'IND') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (filial === 'FRT') return 'bg-orange-100 text-orange-800 border-orange-200';
    if (filial === 'SJK') return 'bg-purple-100 text-purple-800 border-purple-200';
    if (filial === 'GERAL') return 'bg-gray-200 text-gray-700 border-gray-300';
    if (filial === 'SISTEMA') return 'bg-gray-800 text-white border-gray-900';
    return 'bg-indigo-50 text-indigo-700 border-indigo-200';
};

const sortItens = (itens) => {
    if (!itens) return [];
    const order = { 'MTZ': 1, 'IND': 2, 'FRT': 3, 'SJK': 4, 'GERAL': 5, 'SISTEMA': 6 };
    
    return [...itens].sort((a, b) => {
        const filialA = getFilial(a.nome);
        const filialB = getFilial(b.nome);
        const rankA = order[filialA] || 99;
        const rankB = order[filialB] || 99;

        if (rankA !== rankB) return rankA - rankB;
        return getCleanName(a.nome).localeCompare(getCleanName(b.nome));
    });
};

// ==========================================
// SINCRONIZACAO SSW
// ==========================================
const isSyncingSsw = ref(false);

const syncSsw = () => {
    if (props.budgetStatus === 'Congelado') {
        alert('Este orcamento esta congelado e nao pode ser alterado.');
        return;
    }
    if (confirm('Deseja conectar a SSW e importar os dados mais recentes agora?')) {
        isSyncingSsw.value = true;
        router.post(route('budget.ssw-sync'), {}, {
            preserveScroll: true,
            onFinish: () => { isSyncingSsw.value = false; }
        });
    }
};

const exportarPdf = (tipo) => {
    window.open(route('budget.exportar-pdf') + '?tipo=' + tipo, '_blank');
};

const adicionarLinha = (categoriaSelecionada) => {
    if (props.budgetStatus === 'Congelado') return;
    const nomeNovaConta = prompt(`Digite o nome da nova conta para a categoria [${categoriaSelecionada}]:\n(Ex: EMPRESA X - COMPRA DE MATERIAL)`);
    
    if (nomeNovaConta && nomeNovaConta.trim() !== '') {
        isProcessing.value = true;
        router.post(route('budget.item.store', props.budgetId), {
            categoria: categoriaSelecionada,
            nome: '[GERAL] ' + nomeNovaConta.toUpperCase()
        }, {
            preserveScroll: true,
            onSuccess: () => { categoriasExpandidas.value[categoriaSelecionada] = true; },
            onFinish: () => { isProcessing.value = false; }
        });
    }
};

const deleteItem = (item) => {
    if (props.budgetStatus === 'Congelado') return;
    if (confirm(`ALERTA DE EXCLUSAO\nTem certeza que deseja APAGAR a linha [${getCleanName(item.nome)}] do ano inteiro?`)) {
        router.delete(route('budget.item.destroy', item.id), { preserveScroll: true });
    }
};

const vFocus = {
    mounted: (el) => {
        el.focus();
        setTimeout(() => el.select(), 10);
    }
};

// ==========================================
// CALCULOS E GAVETAS
// ==========================================
const normalizeString = (str) => {
    return (str || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
};

const validCategories = computed(() => {
    return (props.budgetCategories || []).filter(c => {
        const name = normalizeString(c.categoria);
        return name !== 'custo fixo' && name !== 'custo variavel';
    });
});

const categoriasExpandidas = ref({});

const initCategorias = () => {
    validCategories.value.forEach(cat => {
        const name = (cat.categoria || '').toLowerCase();
        if (categoriasExpandidas.value[cat.categoria] === undefined) {
            categoriasExpandidas.value[cat.categoria] = name.includes('venda') || name.includes('receita');
        }
    });
};

onMounted(initCategorias);
watch(() => props.budgetCategories, initCategorias, { deep: true });

const toggleCategoria = (categoria) => {
    categoriasExpandidas.value[categoria] = !categoriasExpandidas.value[categoria];
};

const meses = [
    { num: 1, nome: 'Jan' }, { num: 2, nome: 'Fev' }, { num: 3, nome: 'Mar' },
    { num: 4, nome: 'Abr' }, { num: 5, nome: 'Mai' }, { num: 6, nome: 'Jun' },
    { num: 7, nome: 'Jul' }, { num: 8, nome: 'Ago' }, { num: 9, nome: 'Set' },
    { num: 10, nome: 'Out' }, { num: 11, nome: 'Nov' }, { num: 12, nome: 'Dez' }
];

const trimestres = [
    { id: 1, nome: '1 Trim', meses: [1, 2, 3] },
    { id: 2, nome: '2 Trim', meses: [4, 5, 6] },
    { id: 3, nome: '3 Trim', meses: [7, 8, 9] },
    { id: 4, nome: '4 Trim', meses: [10, 11, 12] }
];

const trimestresExpandidos = ref({ 1: false, 2: false, 3: false, 4: false });

const toggleTrimestre = (id) => {
    const isCurrentlyOpen = trimestresExpandidos.value[id];
    Object.keys(trimestresExpandidos.value).forEach(key => trimestresExpandidos.value[key] = false);
    if (!isCurrentlyOpen) trimestresExpandidos.value[id] = true;
};

const formatCurrency = (value) => {
    if (!value || value === 0) return '-';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const formatPercent = (value) => {
    if (!value || isNaN(value)) return '0,00%';
    return new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 2 }).format(value / 100);
};

const matchCategorias = (keywords) => {
    return validCategories.value.filter(c => {
        const catName = normalizeString(c.categoria);
        return keywords.some(k => catName.includes(k));
    });
};

const calcTotal = (categoriasArr, mesNum) => {
    return categoriasArr.reduce((acc, cat) => {
        const items = cat.itens || [];
        return acc + items.reduce((sum, item) => sum + (item.valores ? (parseFloat(item.valores[mesNum]) || 0) : 0), 0);
    }, 0);
};

const getCategoriaTotal = (categoriaNome, mesNum) => {
    const categoria = validCategories.value.find(c => c.categoria === categoriaNome);
    if (!categoria || !categoria.itens) return 0;
    return categoria.itens.reduce((acc, item) => acc + (item.valores ? (parseFloat(item.valores[mesNum]) || 0) : 0), 0);
};

const getCategoriaTotalTrimestre = (categoriaNome, trimId) => {
    const trim = trimestres.find(t => t.id === trimId);
    if (!trim) return 0;
    return trim.meses.reduce((acc, m) => acc + getCategoriaTotal(categoriaNome, m), 0);
};

const getCategoriaTotalAno = (categoriaNome) => trimestres.reduce((acc, trim) => acc + getCategoriaTotalTrimestre(categoriaNome, trim.id), 0);

const getItemTotalTrimestre = (item, trimId) => {
    const trim = trimestres.find(t => t.id === trimId);
    if (!item.valores || !trim) return 0;
    return trim.meses.reduce((acc, m) => acc + (parseFloat(item.valores[m]) || 0), 0);
};

const getItemTotalAno = (item) => trimestres.reduce((acc, trim) => acc + getItemTotalTrimestre(item, trim.id), 0);
const calcTotalTrimestre = (categoriasArr, trimId) => trimestres.find(t => t.id === trimId).meses.reduce((acc, m) => acc + calcTotal(categoriasArr, m), 0);
const calcTotalAno = (categoriasArr) => trimestres.reduce((acc, trim) => acc + calcTotalTrimestre(categoriasArr, trim.id), 0);

const getReceitaBruta = (mesNum) => calcTotal(matchCategorias(['venda', 'receita', 'faturamento', 'entrada']), mesNum);
const getReceitaBrutaTrim = (trimId) => calcTotalTrimestre(matchCategorias(['venda', 'receita', 'faturamento', 'entrada']), trimId);
const getReceitaBrutaAno = () => calcTotalAno(matchCategorias(['venda', 'receita', 'faturamento', 'entrada']));

const getImpostos = (mesNum) => calcTotal(matchCategorias(['imposto', 'tributo', 'taxa', 'deducao', 'simples']), mesNum);
const getImpostosTrim = (trimId) => calcTotalTrimestre(matchCategorias(['imposto', 'tributo', 'taxa', 'deducao', 'simples']), trimId);
const getImpostosAno = () => calcTotalAno(matchCategorias(['imposto', 'tributo', 'taxa', 'deducao', 'simples']));

const getReceitaLiquida = (mesNum) => getReceitaBruta(mesNum) - getImpostos(mesNum);
const getReceitaLiquidaTrim = (trimId) => getReceitaBrutaTrim(trimId) - getImpostosTrim(trimId);
const getReceitaLiquidaAno = () => getReceitaBrutaAno() - getImpostosAno();

const getCostCategories = () => {
    const excludedNames = matchCategorias(['venda', 'receita', 'faturamento', 'entrada', 'imposto', 'tributo', 'taxa', 'deducao', 'simples']).map(c => c.categoria);
    return validCategories.value.filter(c => !excludedNames.includes(c.categoria));
};

const getCustoTotal = (mesNum) => calcTotal(getCostCategories(), mesNum);
const getCustoTotalTrim = (trimId) => calcTotalTrimestre(getCostCategories(), trimId);
const getCustoTotalAno = () => calcTotalAno(getCostCategories());

const getResultado = (mesNum) => getReceitaLiquida(mesNum) - getCustoTotal(mesNum);
const getResultadoTrim = (trimId) => getReceitaLiquidaTrim(trimId) - getCustoTotalTrim(trimId);
const getResultadoAno = () => getReceitaLiquidaAno() - getCustoTotalAno();

const getMargem = (mesNum) => { const rec = getReceitaBruta(mesNum); return rec === 0 ? 0 : (getResultado(mesNum) / rec) * 100; };
const getMargemTrim = (trimId) => { const rec = getReceitaBrutaTrim(trimId); return rec === 0 ? 0 : (getResultadoTrim(trimId) / rec) * 100; };
const getMargemAno = () => { const rec = getReceitaBrutaAno(); return rec === 0 ? 0 : (getResultadoAno() / rec) * 100; };

// ==========================================
// EDICAO E ACOES
// ==========================================
const editingCell = ref(null);
const editValueStr = ref("0,00");

const startEditing = (item, mesNum, currentValue) => {
    if (props.budgetStatus === 'Congelado') return;
    if (editingCell.value?.id === item.id && editingCell.value?.mes === mesNum) return;

    editingCell.value = { id: item.id, mes: mesNum };
    let numericValue = currentValue ? parseFloat(currentValue) : 0;
    let formatted = numericValue.toFixed(2).replace('.', ',');
    formatted = formatted.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    editValueStr.value = formatted;
};

const addValorSomado = (item, mesNum) => {
    if (props.budgetStatus === 'Congelado') return;
    const currentValue = item.valores && item.valores[mesNum] ? parseFloat(item.valores[mesNum]) : 0;
    const currentFormatted = formatCurrency(currentValue);
    
    const input = prompt(`SOMA DE GASTOS NA CONTA: [${getCleanName(item.nome)}]\n\nJa existe ${currentFormatted} lancado neste mes.\n\nDigite o valor ADICIONAL que deseja SOMAR a isso:`);
    
    if (input && input.trim() !== '') {
        let cleanInput = input.trim();
        if (cleanInput.includes(',')) cleanInput = cleanInput.replace(/\./g, '').replace(',', '.');
        let numericInput = parseFloat(cleanInput);
        if (!isNaN(numericInput) && numericInput !== 0) {
            router.put(route('budget.item.update', item.id), { mes: mesNum, valor: (currentValue + numericInput) }, { preserveScroll: true });
        }
    }
};

const clearValue = (item, mesNum) => {
    if (props.budgetStatus === 'Congelado') return;
    if (confirm(`ALERTA DE EXCLUSAO\nTem certeza que deseja ZERAR o mes especifico de [${getCleanName(item.nome)}]?`)) {
        router.put(route('budget.item.update', item.id), { mes: mesNum, valor: 0 }, { preserveScroll: true });
    }
};

const handleInput = (event) => {
    let value = event.target.value.replace(/\D/g, "");
    if (!value) value = "0";
    value = (parseInt(value, 10) / 100).toFixed(2);
    value = value.replace(".", ",");
    value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    editValueStr.value = value;
};

const saveEdit = (item, mesNum) => {
    if (editingCell.value && props.budgetStatus !== 'Congelado') {
        let rawVal = editValueStr.value.replace(/\./g, '').replace(',', '.');
        let numericVal = parseFloat(rawVal) || 0;
        router.put(route('budget.item.update', item.id), { mes: mesNum, valor: numericVal }, {
            preserveScroll: true,
            onSuccess: () => { editingCell.value = null; }
        });
    }
};

const freezeBudget = () => {
    if (confirm('ATENCAO DIRETORIA: Deseja CONGELAR este Orcamento?')) {
        isProcessing.value = true;
        router.post(route('budget.congelar', props.budgetId), {}, { preserveScroll: true, onFinish: () => { isProcessing.value = false; }});
    }
};

const unfreezeBudget = () => {
    if (confirm('ALERTA: Deseja DESTRAVAR este Orcamento?')) {
        isProcessing.value = true;
        router.post(route('budget.descongelar', props.budgetId), {}, { preserveScroll: true, onFinish: () => { isProcessing.value = false; }});
    }
};

// ==========================================
// MODAL RAIO-X SSW
// ==========================================
const showSswModal = ref(false);
const sswExtrato = ref([]);
const isFetchingSsw = ref(false);
const sswModalInfo = ref({});

const openSswRaioX = async (item, mesNum) => {
    showSswModal.value = true;
    isFetchingSsw.value = true;
    sswExtrato.value = [];
    
    sswModalInfo.value = {
        linha: getCleanName(item.nome),
        filial: getFilial(item.nome),
        mes: meses.find(m => m.num === mesNum).nome,
        ano: props.budgetAno
    };

    try {
        const response = await axios.get(route('budget.ssw-extrato'), {
            params: { ano: props.budgetAno, mes: mesNum, linha_nome: item.nome }
        });
        sswExtrato.value = response.data;
    } catch (error) {
        console.error("Erro ao buscar extrato SSW:", error);
        alert("Ocorreu um erro ao buscar os dados da SSW.");
    } finally {
        isFetchingSsw.value = false;
    }
};

const formatDataSsw = (dataString) => {
    if (!dataString) return '-';
    const d = new Date(dataString);
    return d.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
};
</script>

<template>
    <Head title="Budget Financeiro" />

    <ErpLayout>
        
        <div v-if="!isUnlocked" class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-2xl shadow-xl border border-gray-200 text-center">
                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight">Area Restrita: Budget Financeiro</h2>
                    <p class="text-sm text-gray-500 mt-2">Digite a senha de seguranca autorizada para visualizar o DRE e os indicadores.</p>
                </div>

                <form @submit.prevent="desbloquearPagina" class="mt-8 space-y-4">
                    <div>
                        <input type="password" v-model="inputSenha" placeholder="Digite a senha..." class="w-full text-center text-xl tracking-widest px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-blue-600 font-bold" autofocus>
                    </div>
                    <p v-if="erroSenha" class="text-xs text-red-600 font-bold bg-red-50 py-2 rounded-lg">{{ erroSenha }}</p>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                        Acessar Budget
                    </button>
                </form>
            </div>
        </div>

        <div v-else class="py-4 md:py-8 printable-area">
            <div class="max-w-screen-3xl mx-auto px-2 sm:px-6 lg:px-8 space-y-4 md:space-y-6">
                
                <!-- CABECALHO RESPONSIVO -->
                <div class="bg-white p-4 md:p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col xl:flex-row justify-between items-start xl:items-center no-print gap-4">
                    <div>
                        <h2 class="text-xl md:text-2xl font-black text-gray-800 tracking-tight flex items-center gap-2">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            Centro de Comando: Budget Financeiro {{ budgetAno }}
                        </h2>
                        <p class="text-xs md:text-sm text-gray-500 mt-1 ml-7 md:ml-8">Gerenciamento e Projecoes de Receitas e Custos Oficiais</p>
                    </div>
                    
                    <div class="flex items-center gap-2 flex-wrap w-full xl:w-auto">
                        <button @click="syncSsw" :disabled="isSyncingSsw || budgetStatus === 'Congelado'" class="flex-1 md:flex-none justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-3 md:px-4 rounded-lg shadow flex items-center gap-2 transition-all disabled:opacity-50 text-xs md:text-sm">
                            <svg v-if="isSyncingSsw" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            <span>{{ isSyncingSsw ? 'Puxando...' : 'Puxar SSW' }}</span>
                        </button>
                        
                        <div class="flex gap-2 flex-1 md:flex-none border-l-0 md:border-l border-gray-200 md:pl-2">
                            <button @click="exportarPdf('trimestral')" class="flex-1 justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-2 md:px-3 rounded-lg shadow flex items-center gap-1 transition-all text-[11px] md:text-sm">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Trimestral</span>
                            </button>
                            <button @click="exportarPdf('mensal')" class="flex-1 justify-center bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-2 md:px-3 rounded-lg shadow flex items-center gap-1 transition-all text-[11px] md:text-sm">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span>Mensal</span>
                            </button>
                        </div>
                        
                        <button v-if="budgetStatus !== 'Congelado'" @click="freezeBudget" :disabled="isProcessing" class="w-full md:w-auto justify-center bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow flex items-center gap-2 transition-all text-xs md:text-sm mt-2 md:mt-0">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            {{ isProcessing ? 'Aguarde...' : 'Congelar Budget' }}
                        </button>
                    </div>
                </div>
                
                <!-- CARDS RESPONSIVOS -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">
                    <div class="col-span-2 lg:col-span-1 bg-white overflow-hidden shadow-sm rounded-lg p-4 md:p-6 border-l-4" :class="budgetStatus === 'Congelado' ? 'border-green-600 bg-green-50' : 'border-blue-500'">
                        <div class="text-xs md:text-sm uppercase font-bold tracking-wider" :class="budgetStatus === 'Congelado' ? 'text-green-700' : 'text-gray-500'">Status</div>
                        <div class="text-lg md:text-xl font-black mt-1 flex items-center" :class="budgetStatus === 'Congelado' ? 'text-green-800' : 'text-gray-800'">
                            <span v-if="budgetStatus === 'Congelado'" class="flex items-center gap-1"><svg class="w-4 h-4 md:w-5 md:h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> AUDITADO</span>
                            <span v-else class="flex items-center"><span class="w-2.5 h-2.5 bg-blue-500 rounded-full mr-2"></span> Rascunho</span>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4 md:p-6 border-l-4 border-blue-500">
                        <div class="text-[10px] md:text-sm text-gray-500 uppercase font-bold tracking-wider truncate">Receita Prevista</div>
                        <div class="text-base md:text-xl font-black text-gray-800 mt-1 truncate">{{ formatCurrency(getReceitaBrutaAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4 md:p-6 border-l-4 border-red-500">
                        <div class="text-[10px] md:text-sm text-gray-500 uppercase font-bold tracking-wider truncate">Custo Previsto</div>
                        <div class="text-base md:text-xl font-black text-gray-800 mt-1 truncate">{{ formatCurrency(getCustoTotalAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4 md:p-6 border-l-4 border-green-500">
                        <div class="text-[10px] md:text-sm text-gray-500 uppercase font-bold tracking-wider truncate">Resultado Anual</div>
                        <div class="text-base md:text-xl font-black mt-1 truncate" :class="getResultadoAno() >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(getResultadoAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4 md:p-6 border-l-4 border-purple-500">
                        <div class="text-[10px] md:text-sm text-gray-500 uppercase font-bold tracking-wider truncate">Margem Media</div>
                        <div class="text-base md:text-xl font-black text-gray-800 mt-1 truncate">{{ formatPercent(getMargemAno()) }}</div>
                    </div>
                </div>

                <!-- TABELA RESPONSIVA COM SCROLL HORIZONTAL (DRE) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-3 md:p-4 bg-gray-50 border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center no-print gap-2">
                        <h3 class="text-base md:text-lg font-bold text-gray-700">Previsto (Budget) vs Realizado</h3>
                        <span class="text-[10px] md:text-xs text-blue-800 font-bold bg-blue-100 px-2 py-1 md:px-3 md:py-1.5 rounded-lg shadow-sm flex items-center gap-1 w-full sm:w-auto">
                            <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Espelho SSW: Lupa para Raio-X, [+] Somar, [Lixeira] Apagar.
                        </span>
                    </div>
                    
                    <div class="overflow-x-auto w-full pb-4 scroll-smooth">
                        <table class="w-full text-xs md:text-sm text-left text-gray-500 border-collapse">
                            <thead class="text-[10px] md:text-xs text-gray-700 uppercase bg-gray-100 border-b">
                                <tr>
                                    <!-- HEADER ESTENDIDO E STICKY -->
                                    <th scope="col" class="p-0 min-w-[280px] md:min-w-[550px] bg-gray-50 sticky left-0 z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)]">
                                        <div class="flex items-stretch h-full">
                                            <div class="px-2 md:px-4 py-3 md:py-4 flex-grow text-left flex items-center">Categoria / Fornecedor</div>
                                            <div class="px-1 md:px-2 py-3 md:py-4 w-24 md:w-44 border-l border-gray-200 flex items-center justify-center text-center font-black">EVENTO / HIST.</div>
                                            <div class="px-1 md:px-2 py-3 md:py-4 w-12 md:w-20 border-l border-gray-200 flex items-center justify-center text-center font-black">FILIAL</div>
                                        </div>
                                    </th>
                                    
                                    <template v-for="trim in trimestres" :key="'th-trim-'+trim.id">
                                        <th scope="col" class="px-2 md:px-4 py-3 md:py-4 text-right cursor-pointer transition-colors border-l-2 border-gray-300 select-none shadow-sm"
                                            :class="trimestresExpandidos[trim.id] ? 'bg-blue-100 text-blue-900 border-blue-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'"
                                            @click="toggleTrimestre(trim.id)">
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="whitespace-nowrap">{{ trim.nome }}</span>
                                                <span class="text-sm leading-none no-print">{{ trimestresExpandidos[trim.id] ? 'v' : '▸' }}</span>
                                            </div>
                                        </th>
                                        
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <th scope="col" class="px-2 md:px-4 py-3 md:py-4 text-right bg-white border-l border-gray-100 text-gray-500 min-w-[90px]" v-for="mesNum in trim.meses" :key="'th-mes-'+mesNum">
                                                {{ meses.find(m => m.num === mesNum).nome }}
                                            </th>
                                        </template>
                                    </template>
                                    
                                    <th scope="col" class="px-3 md:px-6 py-3 md:py-4 text-right bg-blue-50 text-blue-900 font-black border-l-2 border-blue-300 shadow-[inset_2px_0_5px_-2px_rgba(0,0,0,0.05)] min-w-[100px]">TOTAL ANO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="grupo in validCategories" :key="grupo.categoria">
                                    <tr class="bg-gray-200/60 border-b">
                                        <td class="p-0 sticky left-0 z-10 bg-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)] cursor-pointer hover:bg-gray-300" @click="toggleCategoria(grupo.categoria)">
                                            <div class="flex items-center justify-between px-2 md:px-4 py-2 md:py-3 h-full">
                                                <div class="flex items-center gap-1 md:gap-2 font-black text-gray-800 uppercase text-[11px] md:text-sm">
                                                    <span class="truncate max-w-[150px] md:max-w-[300px]">{{ grupo.categoria }}</span>
                                                    <button v-if="budgetStatus !== 'Congelado'" @click.stop="adicionarLinha(grupo.categoria)" title="Adicionar nova conta" class="text-[9px] md:text-[10px] bg-blue-600 hover:bg-blue-700 text-white px-1.5 py-0.5 rounded shadow-sm no-print transition-colors shrink-0">
                                                        + Linha
                                                    </button>
                                                </div>
                                                <span class="text-gray-500 text-base md:text-lg no-print font-normal">{{ categoriasExpandidas[grupo.categoria] ? 'v' : '▸' }}</span>
                                            </div>
                                        </td>
                                        
                                        <template v-for="trim in trimestres" :key="'cat-trim-'+trim.id">
                                            <td class="px-2 md:px-4 py-2 md:py-3 text-right font-black border-l-2 border-gray-300 cursor-pointer select-none"
                                                :class="trimestresExpandidos[trim.id] ? 'bg-blue-50/50 text-blue-900 border-blue-200' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'"
                                                @click="toggleTrimestre(trim.id)">
                                                {{ formatCurrency(getCategoriaTotalTrimestre(grupo.categoria, trim.id)) }}
                                            </td>
                                            <template v-if="trimestresExpandidos[trim.id]">
                                                <td class="px-2 md:px-4 py-2 md:py-3 text-right font-bold text-gray-600 bg-gray-100/50 border-l border-gray-200" v-for="mesNum in trim.meses" :key="'cat-mes-'+mesNum">
                                                    {{ formatCurrency(getCategoriaTotal(grupo.categoria, mesNum)) }}
                                                </td>
                                            </template>
                                        </template>

                                        <td class="px-3 md:px-6 py-2 md:py-3 text-right font-black text-blue-900 bg-blue-100/50 border-l-2 border-blue-300">{{ formatCurrency(getCategoriaTotalAno(grupo.categoria)) }}</td>
                                    </tr>
                                    
                                    <!-- MAGICA DA ORDENACAO: sortItens(grupo.itens) -->
                                    <tr v-show="categoriasExpandidas[grupo.categoria]" v-for="item in sortItens(grupo.itens)" :key="item.id" class="bg-white border-b hover:bg-gray-50 group/row transition-colors">
                                        
                                        <!-- DESIGN TRIPLO RESPONSIVO: FORNECEDOR + EVENTO + FILIAL -->
                                        <td class="p-0 font-medium text-gray-600 sticky left-0 z-10 bg-white group-hover/row:bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)] transition-colors">
                                            <div class="flex items-stretch h-full">
                                                <div class="px-2 md:px-4 py-2 md:py-3 pl-4 md:pl-8 flex-grow flex items-center justify-between border-b border-transparent">
                                                    <span class="truncate max-w-[140px] md:max-w-[240px] font-bold text-gray-700 text-[10px] md:text-sm" :title="getFornecedor(item.nome)">{{ getFornecedor(item.nome) }}</span>
                                                    <button v-if="budgetStatus !== 'Congelado'" @click.stop="deleteItem(item)" class="opacity-100 xl:opacity-0 xl:group-hover/row:opacity-100 bg-red-50 text-red-500 hover:bg-red-600 hover:text-white rounded px-1.5 py-1 text-xs transition-all shadow-sm ml-1 md:ml-2 shrink-0" title="Apagar esta linha inteira">
                                                        <svg class="w-3 md:w-3.5 h-3 md:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </div>
                                                <div class="px-1 md:px-2 py-2 md:py-3 w-24 md:w-44 border-l border-gray-100 flex items-center justify-center bg-gray-50/30">
                                                    <span class="text-[9px] md:text-[10px] leading-tight text-center text-gray-500 line-clamp-2 w-full px-1" :title="getEvento(item.nome)">{{ getEvento(item.nome) }}</span>
                                                </div>
                                                <div class="px-1 md:px-2 py-2 md:py-3 w-12 md:w-20 border-l border-gray-100 flex items-center justify-center bg-gray-50/50">
                                                    <span class="px-1 md:px-1.5 py-0.5 md:py-1 text-[8px] md:text-[10px] font-black rounded shadow-sm text-center w-full truncate" :class="getFilialColor(getFilial(item.nome))" :title="getFilial(item.nome)">
                                                        {{ getFilial(item.nome) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <template v-for="trim in trimestres" :key="'item-trim-'+trim.id">
                                            <td class="px-2 md:px-4 py-2 md:py-3 text-right font-bold border-l-2 border-gray-200" :class="trimestresExpandidos[trim.id] ? 'bg-blue-50/20 text-blue-800' : 'bg-gray-50 text-gray-700'">
                                                {{ formatCurrency(getItemTotalTrimestre(item, trim.id)) }}
                                            </td>
                                            <template v-if="trimestresExpandidos[trim.id]">
                                                <td class="px-2 md:px-4 py-2 md:py-3 text-right whitespace-nowrap border-l border-gray-100 group/cell relative" v-for="mesNum in trim.meses" :key="'item-mes-'+mesNum">
                                                    
                                                    <div v-if="editingCell?.id === item.id && editingCell?.mes === mesNum" class="flex justify-end">
                                                        <input 
                                                            type="text" 
                                                            v-model="editValueStr" 
                                                            @input="handleInput"
                                                            @keyup.enter="saveEdit(item, mesNum)" 
                                                            @blur="saveEdit(item, mesNum)" 
                                                            @click.stop
                                                            v-focus 
                                                            class="w-20 md:w-24 px-1 md:px-2 py-1 text-xs md:text-sm border-blue-500 rounded focus:ring-blue-500 focus:border-blue-500 text-right shadow-sm font-bold text-gray-900"
                                                        >
                                                    </div>
                                                    
                                                    <div v-else class="flex justify-end items-center w-full h-full relative">
                                                        <!-- BOTOES FLUTUANTES INTELIGENTES COM BACKDROP E SOMBRA PARA NAO SOBREPOR -->
                                                        <div class="absolute right-[95%] mr-1 flex items-center gap-1 opacity-0 group-hover/cell:opacity-100 transition-all bg-white/90 backdrop-blur shadow-sm p-1 rounded-md z-20 pointer-events-none group-hover/cell:pointer-events-auto">
                                                            <button @click.stop="openSswRaioX(item, mesNum)" class="bg-blue-100 text-blue-700 hover:bg-blue-600 hover:text-white rounded p-1.5 text-xs font-black shadow-sm" title="Raio-X: Ver Extrato SSW">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                                            </button>
                                                            
                                                            <button v-if="budgetStatus !== 'Congelado' && (item.valores ? parseFloat(item.valores[mesNum]) : 0) !== 0" @click.stop="clearValue(item, mesNum)" class="bg-red-100 text-red-700 hover:bg-red-600 hover:text-white rounded p-1.5 text-xs font-black shadow-sm" title="Apagar lancamento apenas deste mes">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>

                                                            <button v-if="budgetStatus !== 'Congelado'" @click.stop="addValorSomado(item, mesNum)" class="bg-emerald-100 text-emerald-700 hover:bg-emerald-600 hover:text-white rounded px-2 py-1 text-xs font-black shadow-sm" title="Somar um valor extra">
                                                                +
                                                            </button>
                                                        </div>
                                                        
                                                        <div @click="startEditing(item, mesNum, item.valores ? item.valores[mesNum] : 0)" class="font-semibold transition-colors flex justify-end items-center w-full min-w-[60px] md:min-w-[80px]" :class="budgetStatus === 'Congelado' ? 'text-gray-500 cursor-not-allowed' : 'text-blue-600 cursor-pointer hover:bg-blue-100 px-1 py-1 rounded'" :title="budgetStatus === 'Congelado' ? 'Protegido' : 'Clique para substituir'">
                                                            {{ formatCurrency(item.valores ? item.valores[mesNum] : 0) }}
                                                        </div>
                                                    </div>
                                                    
                                                </td>
                                            </template>
                                        </template>

                                        <td class="px-3 md:px-6 py-2 md:py-3 text-right font-bold text-gray-800 bg-blue-50/30 border-l-2 border-blue-200">{{ formatCurrency(getItemTotalAno(item)) }}</td>
                                    </tr>
                                </template>
                            </tbody>

                            <tfoot class="bg-gray-800 text-white shadow-inner text-[11px] md:text-sm">
                                <tr class="border-b border-gray-700">
                                    <td class="px-2 md:px-4 py-2 md:py-3 font-bold sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)]">Receita Liquida</td>
                                    <template v-for="trim in trimestres" :key="'foot1-trim-'+trim.id">
                                        <td class="px-2 md:px-4 py-2 md:py-3 text-right font-bold border-l-2 border-gray-600" :class="trimestresExpandidos[trim.id] ? 'bg-gray-700 text-blue-200' : 'bg-gray-800'">{{ formatCurrency(getReceitaLiquidaTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-2 md:px-4 py-2 md:py-3 text-right font-medium bg-gray-800 border-l border-gray-700" v-for="mesNum in trim.meses" :key="'foot1-mes-'+mesNum">{{ formatCurrency(getReceitaLiquida(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-3 md:px-6 py-2 md:py-3 text-right font-black text-blue-300 bg-gray-900 border-l-2 border-gray-600">{{ formatCurrency(getReceitaLiquidaAno()) }}</td>
                                </tr>
                                <tr class="border-b border-gray-700 bg-gray-900/50">
                                    <td class="px-2 md:px-4 py-2 md:py-3 font-bold sticky left-0 z-10 bg-gray-900 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-red-300">Custo Total</td>
                                    <template v-for="trim in trimestres" :key="'foot2-trim-'+trim.id">
                                        <td class="px-2 md:px-4 py-2 md:py-3 text-right font-bold border-l-2 border-gray-600 text-red-300" :class="trimestresExpandidos[trim.id] ? 'bg-gray-800' : 'bg-gray-900'">{{ formatCurrency(getCustoTotalTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-2 md:px-4 py-2 md:py-3 text-right font-medium bg-gray-900 border-l border-gray-700 text-red-300" v-for="mesNum in trim.meses" :key="'foot2-mes-'+mesNum">{{ formatCurrency(getCustoTotal(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-3 md:px-6 py-2 md:py-3 text-right font-black text-red-400 bg-gray-900 border-l-2 border-gray-600">{{ formatCurrency(getCustoTotalAno()) }}</td>
                                </tr>
                                <tr class="border-b border-gray-700 text-base md:text-lg">
                                    <td class="px-2 md:px-4 py-3 md:py-4 font-black sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-green-400">Resultado</td>
                                    <template v-for="trim in trimestres" :key="'foot3-trim-'+trim.id">
                                        <td class="px-2 md:px-4 py-3 md:py-4 text-right font-black border-l-2 border-gray-600" :class="[trimestresExpandidos[trim.id] ? 'bg-gray-700' : 'bg-gray-800', getResultadoTrim(trim.id) >= 0 ? 'text-green-400' : 'text-red-500']">{{ formatCurrency(getResultadoTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-2 md:px-4 py-3 md:py-4 text-right font-bold bg-gray-800 border-l border-gray-700" :class="getResultado(mesNum) >= 0 ? 'text-green-400' : 'text-red-500'" v-for="mesNum in trim.meses" :key="'foot3-mes-'+mesNum">{{ formatCurrency(getResultado(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-3 md:px-6 py-3 md:py-4 text-right font-black bg-gray-900 border-l-2 border-gray-600" :class="getResultadoAno() >= 0 ? 'text-green-400' : 'text-red-500'">{{ formatCurrency(getResultadoAno()) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-2 md:px-4 py-2 font-bold sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-gray-400">Margem %</td>
                                    <template v-for="trim in trimestres" :key="'foot4-trim-'+trim.id">
                                        <td class="px-2 md:px-4 py-2 text-right font-bold text-gray-400 border-l-2 border-gray-600" :class="trimestresExpandidos[trim.id] ? 'bg-gray-700' : 'bg-gray-800'"><span class="bg-gray-700 px-1 md:px-2 py-1 rounded text-[10px] md:text-xs">{{ formatPercent(getMargemTrim(trim.id)) }}</span></td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-2 md:px-4 py-2 text-right font-bold text-gray-400 bg-gray-800 border-l border-gray-700" v-for="mesNum in trim.meses" :key="'foot4-mes-'+mesNum"><span class="bg-gray-700 px-1 md:px-2 py-1 rounded text-[10px] md:text-xs">{{ formatPercent(getMargem(mesNum)) }}</span></td>
                                        </template>
                                    </template>
                                    <td class="px-3 md:px-6 py-2 text-right font-bold text-blue-300 bg-gray-900 border-l-2 border-gray-600"><span class="bg-gray-700 px-1 md:px-2 py-1 rounded text-[10px] md:text-xs">{{ formatPercent(getMargemAno()) }}</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        
        <!-- ========================================== -->
        <!-- MODAL RAIO-X SSW -->
        <!-- ========================================== -->
        <div v-if="showSswModal" class="fixed inset-0 z-50 overflow-y-auto no-print" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" @click="showSswModal = false"></div>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full border border-gray-300">
                    
                    <div class="bg-blue-900 px-4 md:px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg md:text-xl font-black text-white flex items-center gap-2" id="modal-title">
                            <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            <span class="truncate max-w-[200px] md:max-w-full">RAIO-X: [{{ sswModalInfo.filial }}] {{ sswModalInfo.linha }} ({{ sswModalInfo.mes }}/{{ sswModalInfo.ano }})</span>
                        </h3>
                        <button @click="showSswModal = false" class="text-blue-300 hover:text-white transition-colors p-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>

                    <div class="bg-white px-4 md:px-6 pt-5 pb-6">
                        <div v-if="isFetchingSsw" class="flex flex-col items-center justify-center py-12">
                            <svg class="animate-spin h-8 w-8 md:h-10 md:w-10 text-blue-600 mb-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <p class="text-gray-500 font-bold text-sm md:text-lg">Buscando extrato oficial na base de dados...</p>
                        </div>
                        
                        <div v-else-if="sswExtrato.length === 0" class="text-gray-500 font-bold bg-gray-50 border border-gray-200 p-6 md:p-8 rounded-lg text-center flex flex-col items-center">
                            <svg class="w-10 h-10 md:w-12 md:h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <span class="text-sm md:text-base">Nenhuma despesa ou receita da SSW mapeada para esta conta neste mes.</span>
                            <span class="text-xs md:text-sm font-normal text-gray-400 mt-1">Isso significa que o valor atual desta linha foi inserido manualmente ou zerado.</span>
                        </div>
                        
                        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 shadow-sm w-full">
                            <table class="min-w-full divide-y divide-gray-200 text-xs md:text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-left font-black text-gray-600 uppercase tracking-wider">Inclusao</th>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-left font-black text-gray-600 uppercase tracking-wider">Filial</th>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-left font-black text-gray-600 uppercase tracking-wider">Fornecedor</th>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-left font-black text-gray-600 uppercase tracking-wider">Historico</th>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-center font-black text-gray-600 uppercase tracking-wider">Situacao</th>
                                        <th class="px-3 md:px-4 py-2 md:py-3 text-right font-black text-gray-600 uppercase tracking-wider">Valor (R$)</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <tr v-for="l in sswExtrato" :key="l.lancamento" class="hover:bg-blue-50 transition-colors group">
                                        <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-gray-500 font-medium">{{ formatDataSsw(l.inclusao) }}</td>
                                        <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap font-black text-gray-700">{{ l.filial }}</td>
                                        <td class="px-3 md:px-4 py-2 md:py-3 text-gray-800 font-semibold group-hover:text-blue-700">{{ l.fornecedor }}</td>
                                        <td class="px-3 md:px-4 py-2 md:py-3 text-gray-500 text-[10px] md:text-xs truncate max-w-[150px] md:max-w-xs" :title="l.historico">{{ l.historico }}</td>
                                        <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-center">
                                            <span class="px-2 py-1 text-[9px] md:text-[10px] uppercase tracking-wider rounded-md font-black shadow-sm" :class="l.situacao === 'Liquidado' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-yellow-100 text-yellow-800 border border-yellow-200'">
                                                {{ l.situacao }}
                                            </span>
                                        </td>
                                        <td class="px-3 md:px-4 py-2 md:py-3 whitespace-nowrap text-right font-black" :class="l.situacao === 'Liquidado' ? 'text-gray-900' : 'text-yellow-600'">
                                            {{ formatCurrency(l.valor) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-100 border-t-2 border-gray-300 font-black">
                                    <tr>
                                        <td colspan="5" class="px-3 md:px-4 py-3 text-right text-gray-600 uppercase tracking-wider text-[10px] md:text-xs">Total SSW (Liquidados + Pendentes):</td>
                                        <td class="px-3 md:px-4 py-3 text-right text-blue-700 text-sm md:text-base">
                                            {{ formatCurrency(sswExtrato.reduce((a,b) => a + parseFloat(b.valor), 0)) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 md:px-6 py-3 md:py-4 flex flex-col sm:flex-row-reverse rounded-b-lg border-t border-gray-200">
                        <button type="button" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-3 md:py-2.5 bg-gray-800 text-sm font-bold text-white hover:bg-gray-900 transition-colors sm:ml-3 sm:w-auto" @click="showSswModal = false">
                            Fechar Raio-X
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </ErpLayout>
</template>

<style>
@media print {
    .no-print, nav, header, aside, button { display: none !important; }
    body { background-color: #ffffff !important; font-size: 10px !important; }
    @page { size: landscape; margin: 1cm; }
    .printable-area { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    table { width: 100% !important; border-collapse: collapse !important; }
    th, td { padding: 4px 6px !important; font-size: 10px !important; }
}
/* Scrollbar customizada para deixar a tabela mais bonita */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
::-webkit-scrollbar-track {
    background: #f1f5f9; 
    border-radius: 4px;
}
::-webkit-scrollbar-thumb {
    background: #cbd5e1; 
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; 
}
</style>
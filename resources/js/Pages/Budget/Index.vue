<script setup>
import { ref, onMounted, watch } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    budgetId: Number,
    budgetAno: Number,
    budgetVersao: String,
    budgetStatus: String,
    budgetCategories: Array,
    dadosReais: Array
});

// ==========================================
// CONTROLE DE EXPANSÃO (LINHAS E COLUNAS)
// ==========================================
const categoriasExpandidas = ref({});

const initCategorias = () => {
    if (props.budgetCategories) {
        props.budgetCategories.forEach(cat => {
            const name = (cat.categoria || '').toLowerCase();
            // Evita resetar se a pessoa já abriu/fechou a aba
            if (categoriasExpandidas.value[cat.categoria] === undefined) {
                // Abre as vendas e receitas por padrão
                categoriasExpandidas.value[cat.categoria] = name.includes('venda') || name.includes('receita');
            }
        });
    }
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
    { id: 1, nome: '1º Trimestre', meses: [1, 2, 3] },
    { id: 2, nome: '2º Trimestre', meses: [4, 5, 6] },
    { id: 3, nome: '3º Trimestre', meses: [7, 8, 9] },
    { id: 4, nome: '4º Trimestre', meses: [10, 11, 12] }
];

const trimestresExpandidos = ref({ 1: false, 2: false, 3: false, 4: false });

const toggleTrimestre = (id) => {
    const isCurrentlyOpen = trimestresExpandidos.value[id];
    Object.keys(trimestresExpandidos.value).forEach(key => trimestresExpandidos.value[key] = false);
    if (!isCurrentlyOpen) trimestresExpandidos.value[id] = true;
};

// ==========================================
// FORMATADORES
// ==========================================
const formatCurrency = (value) => {
    if (!value || value === 0) return '-';
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const formatPercent = (value) => {
    if (!value || isNaN(value)) return '0,00%';
    return new Intl.NumberFormat('pt-BR', { style: 'percent', minimumFractionDigits: 2 }).format(value / 100);
};

// ==========================================
// CÁLCULOS DAS LINHAS (CATEGORIAS E ITENS)
// ==========================================
const getCategoriaTotal = (categoriaNome, mesNum) => {
    const categoria = (props.budgetCategories || []).find(c => c.categoria === categoriaNome);
    if (!categoria || !categoria.itens) return 0;
    return categoria.itens.reduce((acc, item) => acc + (item.valores ? (parseFloat(item.valores[mesNum]) || 0) : 0), 0);
};

const getCategoriaTotalTrimestre = (categoriaNome, trimId) => {
    const trim = trimestres.find(t => t.id === trimId);
    if (!trim) return 0;
    return trim.meses.reduce((acc, m) => acc + getCategoriaTotal(categoriaNome, m), 0);
};

const getCategoriaTotalAno = (categoriaNome) => {
    return trimestres.reduce((acc, trim) => acc + getCategoriaTotalTrimestre(categoriaNome, trim.id), 0);
};

const getItemTotalTrimestre = (item, trimId) => {
    const trim = trimestres.find(t => t.id === trimId);
    if (!item.valores || !trim) return 0;
    return trim.meses.reduce((acc, m) => acc + (parseFloat(item.valores[m]) || 0), 0);
};

const getItemTotalAno = (item) => trimestres.reduce((acc, trim) => acc + getItemTotalTrimestre(item, trim.id), 0);

// ==========================================
// MOTOR DE BUSCA INTELIGENTE (SEM ENGESSAMENTO)
// ==========================================
const matchCategorias = (keywords) => {
    return (props.budgetCategories || []).filter(c => {
        const catName = (c.categoria || '').toLowerCase();
        return keywords.some(k => catName.includes(k));
    });
};

const calcTotal = (categoriasArr, mesNum) => {
    return categoriasArr.reduce((acc, cat) => {
        const items = cat.itens || [];
        return acc + items.reduce((sum, item) => sum + (item.valores ? (parseFloat(item.valores[mesNum]) || 0) : 0), 0);
    }, 0);
};

const calcTotalTrimestre = (categoriasArr, trimId) => {
    const trim = trimestres.find(t => t.id === trimId);
    if (!trim) return 0;
    return trim.meses.reduce((acc, m) => acc + calcTotal(categoriasArr, m), 0);
};

const calcTotalAno = (categoriasArr) => {
    return trimestres.reduce((acc, trim) => acc + calcTotalTrimestre(categoriasArr, trim.id), 0);
};

// ==========================================
// CÁLCULOS TOTAIS GLOBAIS (RODAPÉ E CARDS)
// ==========================================

// RECEITAS (Busca Venda, Receita ou Faturamento)
const getReceitaBruta = (mesNum) => calcTotal(matchCategorias(['venda', 'receita', 'faturamento']), mesNum);
const getReceitaBrutaTrim = (trimId) => calcTotalTrimestre(matchCategorias(['venda', 'receita', 'faturamento']), trimId);
const getReceitaBrutaAno = () => calcTotalAno(matchCategorias(['venda', 'receita', 'faturamento']));

// IMPOSTOS (Busca Imposto ou Tributo)
const getImpostos = (mesNum) => calcTotal(matchCategorias(['imposto', 'tributo']), mesNum);
const getImpostosTrim = (trimId) => calcTotalTrimestre(matchCategorias(['imposto', 'tributo']), trimId);
const getImpostosAno = () => calcTotalAno(matchCategorias(['imposto', 'tributo']));

// RECEITA LÍQUIDA
const getReceitaLiquida = (mesNum) => getReceitaBruta(mesNum) - getImpostos(mesNum);
const getReceitaLiquidaTrim = (trimId) => getReceitaBrutaTrim(trimId) - getImpostosTrim(trimId);
const getReceitaLiquidaAno = () => getReceitaBrutaAno() - getImpostosAno();

// CUSTOS (Inteligência: Tudo que NÃO for Venda e NÃO for Imposto é somado no Custo!)
const getCostCategories = () => {
    const excludedCats = matchCategorias(['venda', 'receita', 'faturamento', 'imposto', 'tributo']);
    const excludedNames = excludedCats.map(c => c.categoria);
    return (props.budgetCategories || []).filter(c => !excludedNames.includes(c.categoria));
};

const getCustoTotal = (mesNum) => calcTotal(getCostCategories(), mesNum);
const getCustoTotalTrim = (trimId) => calcTotalTrimestre(getCostCategories(), trimId);
const getCustoTotalAno = () => calcTotalAno(getCostCategories());

// RESULTADOS E MARGENS
const getResultado = (mesNum) => getReceitaLiquida(mesNum) - getCustoTotal(mesNum);
const getResultadoTrim = (trimId) => getReceitaLiquidaTrim(trimId) - getCustoTotalTrim(trimId);
const getResultadoAno = () => getReceitaLiquidaAno() - getCustoTotalAno();

const getMargem = (mesNum) => {
    const rec = getReceitaBruta(mesNum);
    return rec === 0 ? 0 : (getResultado(mesNum) / rec) * 100;
};
const getMargemTrim = (trimId) => {
    const rec = getReceitaBrutaTrim(trimId);
    return rec === 0 ? 0 : (getResultadoTrim(trimId) / rec) * 100;
};
const getMargemAno = () => {
    const rec = getReceitaBrutaAno();
    return rec === 0 ? 0 : (getResultadoAno() / rec) * 100;
};

// ==========================================
// EDIÇÃO INLINE E TRAVA ANTIFRAUDE
// ==========================================
const editingCell = ref(null);
const editValue = ref(0);
const isProcessing = ref(false);

const startEditing = (item, mesNum, currentValue) => {
    if (props.budgetStatus === 'Congelado') return;
    editingCell.value = { id: item.id, mes: mesNum };
    editValue.value = currentValue ? parseFloat(currentValue) : 0;
};

const saveEdit = (item, mesNum) => {
    if (editingCell.value && props.budgetStatus !== 'Congelado') {
        router.put(route('budget.item.update', item.id), { mes: mesNum, valor: editValue.value }, {
            preserveScroll: true,
            onSuccess: () => { editingCell.value = null; }
        });
    }
};

// ==========================================
// AÇÕES DOS BOTÕES MÁGICOS
// ==========================================
const freezeBudget = () => {
    if (confirm('🔒 ATENÇÃO DIRETORIA: Tem certeza que deseja CONGELAR este Orçamento?\n\nApós o congelamento, NENHUM valor poderá ser editado.')) {
        isProcessing.value = true;
        router.post(route('budget.congelar', props.budgetId), {}, { preserveScroll: true, onFinish: () => { isProcessing.value = false; }});
    }
};

const unfreezeBudget = () => {
    if (confirm('⚠️ ALERTA DE SEGURANÇA: Tem certeza que deseja DESTRAVAR este Orçamento?\n\nIsso reabrirá o documento oficial para edições.')) {
        isProcessing.value = true;
        router.post(route('budget.descongelar', props.budgetId), {}, { preserveScroll: true, onFinish: () => { isProcessing.value = false; }});
    }
};

const runAI = () => {
    if (confirm('✨ Deseja rodar o Motor Preditivo?\n\nO sistema vai analisar as contas e calculará o restante do ano usando regressão estatística.')) {
        isProcessing.value = true;
        router.post(route('budget.predict', props.budgetId), {}, { preserveScroll: true, onFinish: () => { isProcessing.value = false; }});
    }
};

const undoAI = () => {
    if (confirm('⏪ ALERTA: Deseja RESTAURAR o orçamento para o estado original?\n\nIsso apagará todas as projeções e edições manuais feitas, retornando a tabela exatamente para os números do início.')) {
        isProcessing.value = true;
        router.post(route('budget.predict.undo', props.budgetId), {}, { 
            preserveScroll: true, 
            onFinish: () => { isProcessing.value = false; }
        });
    }
};
</script>

<template>
    <Head title="Budget Financeiro" />

    <ErpLayout>
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- CABEÇALHO -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-black text-gray-800 tracking-tight flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            Centro de Comando: Budget Financeiro {{ budgetAno }}
                        </h2>
                        <p class="text-sm text-gray-500 mt-1 ml-8">Gerenciamento e Projeções de Receitas e Custos Oficiais</p>
                    </div>
                    
                    <div class="flex items-center gap-3 mt-4 md:mt-0">
                        <!-- BOTÃO DESFAZER / RESTAURAR (Cinza) -->
                        <button v-if="budgetStatus !== 'Congelado'" @click="undoAI" :disabled="isProcessing" title="Restaurar Valores Originais"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg shadow flex items-center gap-2 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                            <span class="hidden md:inline">Restaurar Original</span>
                        </button>

                        <button v-if="budgetStatus !== 'Congelado'" @click="runAI" :disabled="isProcessing"
                            class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg shadow flex items-center gap-2 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            {{ isProcessing ? 'Calculando...' : 'Motor Preditivo' }}
                        </button>

                        <button v-if="budgetStatus !== 'Congelado'" @click="freezeBudget" :disabled="isProcessing"
                            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg shadow flex items-center gap-2 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            {{ isProcessing ? 'Aguarde...' : 'Congelar Orçamento' }}
                        </button>

                        <button v-if="budgetStatus === 'Congelado'" @click="unfreezeBudget" :disabled="isProcessing"
                            class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-lg shadow flex items-center gap-2 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                            {{ isProcessing ? 'Aguarde...' : 'Destravar Orçamento' }}
                        </button>
                    </div>
                </div>
                
                <!-- CARDS DE KPIS -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4" :class="budgetStatus === 'Congelado' ? 'border-green-600 bg-green-50' : 'border-blue-500'">
                        <div class="text-sm uppercase font-bold tracking-wider" :class="budgetStatus === 'Congelado' ? 'text-green-700' : 'text-gray-500'">Status</div>
                        <div class="text-xl font-black mt-1 flex items-center" :class="budgetStatus === 'Congelado' ? 'text-green-800' : 'text-gray-800'">
                            <span v-if="budgetStatus === 'Congelado'" class="flex items-center gap-1"><svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg> AUDITADO</span>
                            <span v-else class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span> Rascunho</span>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                        <div class="text-sm text-gray-500 uppercase font-bold tracking-wider">Receita Prevista</div>
                        <div class="text-xl font-black text-gray-800 mt-1">{{ formatCurrency(getReceitaBrutaAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                        <div class="text-sm text-gray-500 uppercase font-bold tracking-wider">Custo Previsto</div>
                        <div class="text-xl font-black text-gray-800 mt-1">{{ formatCurrency(getCustoTotalAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                        <div class="text-sm text-gray-500 uppercase font-bold tracking-wider">Resultado Anual</div>
                        <div class="text-xl font-black mt-1" :class="getResultadoAno() >= 0 ? 'text-green-600' : 'text-red-600'">{{ formatCurrency(getResultadoAno()) }}</div>
                    </div>
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-500">
                        <div class="text-sm text-gray-500 uppercase font-bold tracking-wider">Margem Média</div>
                        <div class="text-xl font-black text-gray-800 mt-1">{{ formatPercent(getMargemAno()) }}</div>
                    </div>
                </div>

                <!-- TABELA INTELIGENTE -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-700">Previsto (Budget) vs Realizado</h3>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-blue-800 font-bold bg-blue-100 px-3 py-1.5 rounded-full shadow-sm flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                Dica: Clique nos Trimestres para expandir os meses
                            </span>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto pb-4">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100 border-b">
                                <tr>
                                    <th scope="col" class="px-4 py-4 w-64 bg-gray-50 sticky left-0 z-20 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">Categoria / Conta</th>
                                    
                                    <template v-for="trim in trimestres" :key="'th-trim-'+trim.id">
                                        <th scope="col" class="px-4 py-4 text-right cursor-pointer transition-colors border-l-2 border-gray-300 select-none shadow-sm"
                                            :class="trimestresExpandidos[trim.id] ? 'bg-blue-100 text-blue-900 border-blue-300' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'"
                                            @click="toggleTrimestre(trim.id)" title="Clique para expandir/recolher">
                                            <div class="flex items-center justify-end gap-1">
                                                <span>{{ trim.nome }}</span>
                                                <span class="text-base leading-none">{{ trimestresExpandidos[trim.id] ? '▾' : '▸' }}</span>
                                            </div>
                                        </th>
                                        
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <th scope="col" class="px-4 py-4 text-right bg-white border-l border-gray-100 text-gray-500" v-for="mesNum in trim.meses" :key="'th-mes-'+mesNum">
                                                {{ meses.find(m => m.num === mesNum).nome }}
                                            </th>
                                        </template>
                                    </template>
                                    
                                    <th scope="col" class="px-6 py-4 text-right bg-blue-50 text-blue-900 font-black border-l-2 border-blue-300 shadow-[inset_2px_0_5px_-2px_rgba(0,0,0,0.05)]">TOTAL ANO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="grupo in budgetCategories" :key="grupo.categoria">
                                    <tr class="bg-gray-200/60 border-b">
                                        <td class="px-4 py-3 font-bold text-gray-800 uppercase flex items-center justify-between sticky left-0 z-10 bg-gray-200 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-pointer hover:bg-gray-300" @click="toggleCategoria(grupo.categoria)">
                                            <span>{{ grupo.categoria }}</span>
                                            <span class="text-gray-500 text-lg">{{ categoriasExpandidas[grupo.categoria] ? '▾' : '▸' }}</span>
                                        </td>
                                        
                                        <template v-for="trim in trimestres" :key="'cat-trim-'+trim.id">
                                            <td class="px-4 py-3 text-right font-black border-l-2 border-gray-300 cursor-pointer select-none"
                                                :class="trimestresExpandidos[trim.id] ? 'bg-blue-50/50 text-blue-900 border-blue-200' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'"
                                                @click="toggleTrimestre(trim.id)">
                                                {{ formatCurrency(getCategoriaTotalTrimestre(grupo.categoria, trim.id)) }}
                                            </td>
                                            <template v-if="trimestresExpandidos[trim.id]">
                                                <td class="px-4 py-3 text-right font-bold text-gray-600 bg-gray-100/50 border-l border-gray-200" v-for="mesNum in trim.meses" :key="'cat-mes-'+mesNum">
                                                    {{ formatCurrency(getCategoriaTotal(grupo.categoria, mesNum)) }}
                                                </td>
                                            </template>
                                        </template>

                                        <td class="px-6 py-3 text-right font-black text-blue-900 bg-blue-100/50 border-l-2 border-blue-300">{{ formatCurrency(getCategoriaTotalAno(grupo.categoria)) }}</td>
                                    </tr>
                                    
                                    <tr v-show="categoriasExpandidas[grupo.categoria]" v-for="item in grupo.itens" :key="item.id" class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-4 py-3 pl-8 font-medium text-gray-600 sticky left-0 z-10 bg-white shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]">{{ item.nome }}</td>
                                        
                                        <template v-for="trim in trimestres" :key="'item-trim-'+trim.id">
                                            <td class="px-4 py-3 text-right font-bold border-l-2 border-gray-200" :class="trimestresExpandidos[trim.id] ? 'bg-blue-50/20 text-blue-800' : 'bg-gray-50 text-gray-700'">
                                                {{ formatCurrency(getItemTotalTrimestre(item, trim.id)) }}
                                            </td>
                                            <template v-if="trimestresExpandidos[trim.id]">
                                                <td class="px-4 py-3 text-right whitespace-nowrap border-l border-gray-100" v-for="mesNum in trim.meses" :key="'item-mes-'+mesNum" @dblclick="startEditing(item, mesNum, item.valores ? item.valores[mesNum] : 0)">
                                                    <div v-if="editingCell?.id === item.id && editingCell?.mes === mesNum">
                                                        <input type="number" step="0.01" v-model="editValue" @keyup.enter="saveEdit(item, mesNum)" @blur="saveEdit(item, mesNum)" class="w-24 px-2 py-1 text-sm border-blue-500 rounded focus:ring-blue-500 focus:border-blue-500 text-right shadow-sm" autofocus>
                                                    </div>
                                                    <div v-else class="font-semibold transition-colors flex justify-end items-center gap-1" :class="budgetStatus === 'Congelado' ? 'text-gray-500 cursor-not-allowed' : 'text-blue-600 cursor-pointer hover:bg-blue-100 px-2 py-1 rounded'" :title="budgetStatus === 'Congelado' ? 'Protegido' : 'Duplo clique para editar'">
                                                        <svg v-if="budgetStatus === 'Congelado'" class="w-3 h-3 text-green-600 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                        {{ formatCurrency(item.valores ? item.valores[mesNum] : 0) }}
                                                    </div>
                                                </td>
                                            </template>
                                        </template>

                                        <td class="px-6 py-3 text-right font-bold text-gray-800 bg-blue-50/30 border-l-2 border-blue-200">{{ formatCurrency(getItemTotalAno(item)) }}</td>
                                    </tr>
                                </template>
                            </tbody>

                            <tfoot class="bg-gray-800 text-white shadow-inner">
                                <tr class="border-b border-gray-700">
                                    <td class="px-4 py-3 font-bold sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)]">Receita Líquida</td>
                                    <template v-for="trim in trimestres" :key="'foot1-trim-'+trim.id">
                                        <td class="px-4 py-3 text-right font-bold border-l-2 border-gray-600" :class="trimestresExpandidos[trim.id] ? 'bg-gray-700 text-blue-200' : 'bg-gray-800'">{{ formatCurrency(getReceitaLiquidaTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-4 py-3 text-right font-medium bg-gray-800 border-l border-gray-700" v-for="mesNum in trim.meses" :key="'foot1-mes-'+mesNum">{{ formatCurrency(getReceitaLiquida(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-6 py-3 text-right font-black text-blue-300 bg-gray-900 border-l-2 border-gray-600">{{ formatCurrency(getReceitaLiquidaAno()) }}</td>
                                </tr>
                                <tr class="border-b border-gray-700 bg-gray-900/50">
                                    <td class="px-4 py-3 font-bold sticky left-0 z-10 bg-gray-900 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-red-300">Custo Total</td>
                                    <template v-for="trim in trimestres" :key="'foot2-trim-'+trim.id">
                                        <td class="px-4 py-3 text-right font-bold border-l-2 border-gray-600 text-red-300" :class="trimestresExpandidos[trim.id] ? 'bg-gray-800' : 'bg-gray-900'">{{ formatCurrency(getCustoTotalTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-4 py-3 text-right font-medium bg-gray-900 border-l border-gray-700 text-red-300" v-for="mesNum in trim.meses" :key="'foot2-mes-'+mesNum">{{ formatCurrency(getCustoTotal(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-6 py-3 text-right font-black text-red-400 bg-gray-900 border-l-2 border-gray-600">{{ formatCurrency(getCustoTotalAno()) }}</td>
                                </tr>
                                <tr class="border-b border-gray-700 text-lg">
                                    <td class="px-4 py-4 font-black sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-green-400">Resultado</td>
                                    <template v-for="trim in trimestres" :key="'foot3-trim-'+trim.id">
                                        <td class="px-4 py-4 text-right font-black border-l-2 border-gray-600" :class="[trimestresExpandidos[trim.id] ? 'bg-gray-700' : 'bg-gray-800', getResultadoTrim(trim.id) >= 0 ? 'text-green-400' : 'text-red-500']">{{ formatCurrency(getResultadoTrim(trim.id)) }}</td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-4 py-4 text-right font-bold bg-gray-800 border-l border-gray-700" :class="getResultado(mesNum) >= 0 ? 'text-green-400' : 'text-red-500'" v-for="mesNum in trim.meses" :key="'foot3-mes-'+mesNum">{{ formatCurrency(getResultado(mesNum)) }}</td>
                                        </template>
                                    </template>
                                    <td class="px-6 py-4 text-right font-black bg-gray-900 border-l-2 border-gray-600" :class="getResultadoAno() >= 0 ? 'text-green-400' : 'text-red-500'">{{ formatCurrency(getResultadoAno()) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-bold sticky left-0 z-10 bg-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.3)] text-gray-400">Margem %</td>
                                    <template v-for="trim in trimestres" :key="'foot4-trim-'+trim.id">
                                        <td class="px-4 py-2 text-right font-bold text-gray-400 border-l-2 border-gray-600" :class="trimestresExpandidos[trim.id] ? 'bg-gray-700' : 'bg-gray-800'"><span class="bg-gray-700 px-2 py-1 rounded text-xs">{{ formatPercent(getMargemTrim(trim.id)) }}</span></td>
                                        <template v-if="trimestresExpandidos[trim.id]">
                                            <td class="px-4 py-2 text-right font-bold text-gray-400 bg-gray-800 border-l border-gray-700" v-for="mesNum in trim.meses" :key="'foot4-mes-'+mesNum"><span class="bg-gray-700 px-2 py-1 rounded text-xs">{{ formatPercent(getMargem(mesNum)) }}</span></td>
                                        </template>
                                    </template>
                                    <td class="px-6 py-2 text-right font-bold text-blue-300 bg-gray-900 border-l-2 border-gray-600"><span class="bg-gray-700 px-2 py-1 rounded text-xs">{{ formatPercent(getMargemAno()) }}</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </ErpLayout>
</template>
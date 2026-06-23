<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    itens: { type: Array, default: () => [] }
});

defineEmits(['detalhar']);

const filtroStatus = ref('todos'); // 'todos', 'corretos', 'divergentes'

const listagemFiltrada = computed(() => {
    if (filtroStatus.value === 'corretos') {
        return props.itens.filter(i => i.is_correto);
    }
    if (filtroStatus.value === 'divergentes') {
        return props.itens.filter(i => !i.is_correto);
    }
    return props.itens;
});

const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="sm:flex sm:items-center sm:justify-between mb-6">
            <div>
                <h2 class="text-lg font-bold text-gray-800">Relatório Consolidado de Notas</h2>
                <p class="text-xs text-gray-500 mt-0.5">Clique nas linhas com divergência para verificar a quebra matemática.</p>
            </div>
            <div class="mt-3 sm:mt-0 inline-flex rounded-lg border border-gray-200 p-1 bg-gray-50">
                <button 
                    @click="filtroStatus = 'todos'"
                    :class="['px-3 py-1.5 text-xs font-semibold rounded-md transition-all', filtroStatus === 'todos' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-500 hover:text-gray-800']"
                >
                    Todos ({{ itens.length }})
                </button>
                <button 
                    @click="filtroStatus = 'divergentes'"
                    :class="['px-3 py-1.5 text-xs font-semibold rounded-md transition-all', filtroStatus === 'divergentes' ? 'bg-white text-red-700 shadow-sm' : 'text-gray-500 hover:text-red-700']"
                >
                    Divergências
                </button>
                <button 
                    @click="filtroStatus = 'corretos'"
                    :class="['px-3 py-1.5 text-xs font-semibold rounded-md transition-all', filtroStatus === 'corretos' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-emerald-700']"
                >
                    Corretos
                </button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-100">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/70">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Arquivo XML</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider">Destino</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-400 uppercase tracking-wider">Cobrado (E4LOG)</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-400 uppercase tracking-wider">Valor Correto</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-400 uppercase tracking-wider">Diferença</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-400 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <tr v-for="item in listagemFiltrada" :key="item.id" class="hover:bg-gray-50/80 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span :class="['inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold tracking-wide', item.is_correto ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700']">
                                <span :class="['w-1.5 h-1.5 rounded-full mr-1.5', item.is_correto ? 'bg-emerald-500' : 'bg-red-500']"></span>
                                {{ item.is_correto ? 'Confirmado' : 'Divergente' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700 font-mono">{{ item.arquivo }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-semibold flex items-center gap-1.5">
                            {{ item.destino }}
                            <span v-if="item.temTde" class="bg-amber-100 text-amber-800 text-[10px] font-black px-1.5 py-0.5 rounded uppercase">Com TDE</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">{{ formatarMoeda(item.cobrado) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-emerald-600 text-right">{{ formatarMoeda(item.correto) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-right" :class="item.diferenca > 0 ? 'text-red-600' : (item.diferenca < 0 ? 'text-amber-600' : 'text-gray-400')">
                            {{ item.diferenca > 0 ? '+' : '' }}{{ formatarMoeda(item.diferenca) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <button @click="$emit('detalhar', item)" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100/80 px-3 py-1 rounded-md transition text-xs font-bold">
                                Analisar Raio-X
                            </button>
                        </td>
                    </tr>
                    <tr v-if="listagemFiltrada.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400 font-medium">Nenhum registro encontrado para o filtro selecionado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
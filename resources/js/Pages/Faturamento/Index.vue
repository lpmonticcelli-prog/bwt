<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import UploadXML from '@/Components/UploadXML.vue';
import ModalRaioXFaturamento from '@/Components/ModalRaioXFaturamento.vue';

const props = defineProps({
    faturamentosProcessados: { type: Array, default: () => [] }
});

const totais = computed(() => {
    let receita = 0;
    let custo = 0;
    let lucro = 0;
    
    // Variáveis para separar os mundos
    let lucroPositivo = 0;
    let lucroNegativo = 0;
    
    props.faturamentosProcessados.forEach(item => {
        let r = parseFloat(item.receita_real) || 0;
        let c = parseFloat(item.custo_total) || 0;
        let l = parseFloat(item.lucro) || 0;

        receita += r;
        custo += c;
        lucro += l;

        // Separa quem deu dinheiro de quem deu prejuízo
        if (l >= 0) {
            lucroPositivo += l;
        } else {
            lucroNegativo += l;
        }
    });
    
    let margem = receita > 0 ? ((lucro / receita) * 100).toFixed(1) : 0;
    
    return { receita, custo, lucro, margem, lucroPositivo, lucroNegativo };
});

const formatMoney = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const itemSelecionado = ref(null);
const abrirRaioX = (item) => itemSelecionado.value = item;
const fecharRaioX = () => itemSelecionado.value = null;
</script>

<template>
    <Head title="Rentabilidade - 123fretei" />

    <div class="min-h-screen bg-slate-50 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- NAVEGAÇÃO: BOTÃO VOLTAR -->
            <div class="mb-4">
                <Link href="/dashboard" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors group">
                    <svg class="w-4 h-4 mr-1 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Voltar para o Dashboard
                </Link>
            </div>

            <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Painel de Rentabilidade</h1>
                    <p class="text-sm text-slate-500 mt-1">Comparativo direto entre o Faturamento Sol Fácil (BWT) e a Tabela de Custos (E4LOG).</p>
                </div>
            </div>

            <div v-if="faturamentosProcessados.length > 0">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <p class="text-xs font-bold text-slate-400 tracking-wider">RECEITA BWT (FATURADO)</p>
                        <h3 class="text-3xl font-black text-blue-600 mt-2">{{ formatMoney(totais.receita) }}</h3>
                        <p class="text-xs text-slate-500 mt-1">Valor emitido nos CT-es</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <p class="text-xs font-bold text-slate-400 tracking-wider">CUSTO E4LOG (ESTIMADO)</p>
                        <h3 class="text-3xl font-black text-red-500 mt-2">{{ formatMoney(totais.custo) }}</h3>
                        <p class="text-xs text-slate-500 mt-1">Projeção da tabela oficial</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <p class="text-xs font-bold text-slate-400 tracking-wider">LUCRO LÍQUIDO FINAL</p>
                        <h3 :class="totais.lucro >= 0 ? 'text-emerald-500' : 'text-red-500'" class="text-3xl font-black mt-2">
                            {{ formatMoney(totais.lucro) }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Margem da operação: <span class="font-bold text-slate-700">{{ totais.margem }}%</span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-emerald-50 rounded-xl border border-emerald-100 shadow-sm p-5 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">💰 Soma das Operações c/ Lucro</p>
                            <h3 class="text-2xl font-black text-emerald-700 mt-1">+ {{ formatMoney(totais.lucroPositivo) }}</h3>
                            <p class="text-[10px] text-emerald-600 mt-1">Ganhos acumulados nas notas positivas</p>
                        </div>
                        <div class="p-3 bg-emerald-100 rounded-full text-emerald-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        </div>
                    </div>

                    <div class="bg-red-50 rounded-xl border border-red-100 shadow-sm p-5 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-red-600 uppercase tracking-wider">⚠️ Soma das Operações c/ Prejuízo</p>
                            <h3 class="text-2xl font-black text-red-700 mt-1">{{ formatMoney(totais.lucroNegativo) }}</h3>
                            <p class="text-[10px] text-red-600 mt-1">Dinheiro perdido nas notas subsidiadas</p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full text-red-600">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                
                <div class="lg:col-span-2">
                    <div v-if="faturamentosProcessados.length > 0" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-slate-50 border-b text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="py-4 px-6 font-semibold">Resultado</th>
                                        <th class="py-4 px-6 font-semibold">CT-e Emitido</th>
                                        <th class="py-4 px-6 font-semibold">Destino</th>
                                        <th class="py-4 px-6 font-semibold">Faturado</th>
                                        <th class="py-4 px-6 font-semibold">Custo</th>
                                        <th class="py-4 px-6 font-semibold text-right">Margem Líquida</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm">
                                    <tr v-for="item in faturamentosProcessados" :key="item.id" @click="abrirRaioX(item)" class="hover:bg-slate-100 transition cursor-pointer">
                                        <td class="py-3 px-6">
                                            <span v-if="item.lucro >= 0" class="px-2 py-1 bg-emerald-100 text-emerald-700 font-bold rounded-md text-xs">Lucro</span>
                                            <span v-else class="px-2 py-1 bg-red-100 text-red-700 font-bold rounded-md text-xs">Prejuízo</span>
                                        </td>
                                        <td class="py-3 px-6 font-medium text-slate-700">{{ item.arquivo.substring(0, 15) }}...</td>
                                        <td class="py-3 px-6 text-slate-600">{{ item.destino }}</td>
                                        <td class="py-3 px-6 font-semibold text-blue-600">{{ formatMoney(item.receita_real) }}</td>
                                        <td class="py-3 px-6 font-semibold text-red-500">{{ formatMoney(item.custo_total) }}</td>
                                        <td :class="item.lucro >= 0 ? 'text-emerald-600' : 'text-red-600'" class="py-3 px-6 text-right font-bold">
                                            {{ formatMoney(item.lucro) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-xl border border-dashed border-slate-300 p-10 text-center">
                        <p class="text-slate-500">Nenhum CT-e da BWT foi processado ainda.</p>
                    </div>
                </div>

                <div class="lg:col-span-1 relative">
                    <!-- O componente de Fila (Chunk) entra aqui sozinho, cuidando de todo o processo de envio e carregamento -->
                    <UploadXML />
                </div>
            </div>
        </div>
    </div>

    <ModalRaioXFaturamento :item="itemSelecionado" @fechar="fecharRaioX" />
</template>
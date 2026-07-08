<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';

// Controla qual aba está aberta
const abaAtiva = ref('e4log');

// Dados preenchidos com o seu arquivo "LOGICA DO NEGOCIO.txt"
const form = useForm({
    icms_percentual: 12.00,
    e4log: {
        regioes: [
            { nome: 'Região 1 (Campinas)', valor_minimo: 200.00, percentual_excedente: 2.00 },
            { nome: 'Região 2', valor_minimo: 250.00, percentual_excedente: 3.00 },
            { nome: 'Região 3', valor_minimo: 350.00, percentual_excedente: 3.00 },
            { nome: 'Região 4', valor_minimo: 420.00, percentual_excedente: 4.00 },
        ],
        tde: { valor_minimo: 160.00, percentual_frete: 20.00 }
    },
    sol_facil: {
        regioes: [
            { nome: 'Região 1 (Campinas)', valor_minimo: 350.00, percentual_excedente: 3.00 },
            { nome: 'Região 2', valor_minimo: 350.00, percentual_excedente: 4.00 },
            { nome: 'Região 3', valor_minimo: 550.00, percentual_excedente: 6.00 },
            { nome: 'Região 4', valor_minimo: 600.00, percentual_excedente: 3.00 },
        ],
        tde: { valor_minimo: 200.00, percentual_frete: 30.00 }
    }
});

const guardarRegras = () => {
    alert("✅ Regras comerciais atualizadas com sucesso!");
    // Futuramente, enviamos para o Laravel: form.post('/auditoria/regras');
};
</script>

<template>
    <Head title="Motor de Regras - BWT" />

    <ErpLayout>
        <template #header-title>Motor de Regras e Precificação</template>
        <template #header-subtitle>Parametrização de custos E4LOG, receitas Sol Fácil e Impostos.</template>
        
        <template #header-actions>
            <div class="flex items-center gap-3">
                <Link href="/auditoria" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">
                    Voltar
                </Link>
                <button @click="guardarRegras" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-5 rounded-lg shadow-sm transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Salvar Alterações
                </button>
            </div>
        </template>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden max-w-5xl mx-auto">
            
            <div class="flex border-b border-slate-100 bg-slate-50/50">
                <button @click="abaAtiva = 'e4log'" :class="abaAtiva === 'e4log' ? 'border-orange-500 text-orange-600 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'" class="px-6 py-4 border-b-2 font-bold text-sm transition-all">
                    1. Custos (E4LOG)
                </button>
                <button @click="abaAtiva = 'solfacil'" :class="abaAtiva === 'solfacil' ? 'border-blue-500 text-blue-600 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'" class="px-6 py-4 border-b-2 font-bold text-sm transition-all">
                    2. Receitas (Sol Fácil)
                </button>
                <button @click="abaAtiva = 'impostos'" :class="abaAtiva === 'impostos' ? 'border-slate-800 text-slate-900 bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'" class="px-6 py-4 border-b-2 font-bold text-sm transition-all">
                    3. Parâmetros Globais
                </button>
            </div>

            <div class="p-8">
                <div v-if="abaAtiva === 'e4log'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 mb-4">Tabela de Custo por Região</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="(regiao, index) in form.e4log.regioes" :key="index" class="bg-orange-50/30 p-4 rounded-xl border border-orange-100">
                                <h4 class="font-bold text-slate-700 mb-3 text-sm">{{ regiao.nome }}</h4>
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Piso Fixo (R$)</label>
                                        <input type="number" v-model="regiao.valor_minimo" class="w-full rounded-lg border-slate-300 text-sm font-semibold text-slate-800 focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Excedente (%)</label>
                                        <input type="number" v-model="regiao.percentual_excedente" class="w-full rounded-lg border-slate-300 text-sm font-semibold text-slate-800 focus:ring-orange-500 focus:border-orange-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-orange-50 rounded-xl p-6 border border-orange-200 flex items-center justify-between gap-6">
                        <div>
                            <h4 class="font-black text-orange-800 text-lg mb-1">Gatilho da TDE (Custo)</h4>
                            <p class="text-xs text-orange-600 font-medium">A transportadora cobra o maior valor entre o Fixo ou a Porcentagem.</p>
                        </div>
                        <div class="flex gap-4 items-center">
                            <div>
                                <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Mínimo Fixo (R$)</label>
                                <input type="number" v-model="form.e4log.tde.valor_minimo" class="w-24 rounded-lg border-orange-300 text-sm font-bold text-orange-900 bg-white">
                            </div>
                            <span class="font-black text-orange-400">OU</span>
                            <div>
                                <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Carga (%)</label>
                                <input type="number" v-model="form.e4log.tde.percentual_frete" class="w-24 rounded-lg border-orange-300 text-sm font-bold text-orange-900 bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="abaAtiva === 'solfacil'" class="space-y-8">
                    <div>
                        <h3 class="text-lg font-black text-slate-800 mb-4">Tabela de Receita por Região</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div v-for="(regiao, index) in form.sol_facil.regioes" :key="index" class="bg-blue-50/30 p-4 rounded-xl border border-blue-100">
                                <h4 class="font-bold text-slate-700 mb-3 text-sm">{{ regiao.nome }}</h4>
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cobrança Base (R$)</label>
                                        <input type="number" v-model="regiao.valor_minimo" class="w-full rounded-lg border-slate-300 text-sm font-semibold text-slate-800 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Margem (%)</label>
                                        <input type="number" v-model="regiao.percentual_excedente" class="w-full rounded-lg border-slate-300 text-sm font-semibold text-slate-800 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 rounded-xl p-6 border border-blue-200 flex items-center justify-between gap-6">
                        <div>
                            <h4 class="font-black text-blue-800 text-lg mb-1">Gatilho da TDE (Receita)</h4>
                            <p class="text-xs text-blue-600 font-medium">Cobramos da Sol Fácil o maior valor entre o Fixo e a Porcentagem.</p>
                        </div>
                        <div class="flex gap-4 items-center">
                            <div>
                                <label class="block text-[10px] font-bold text-blue-700 uppercase mb-1">Mínimo Fixo (R$)</label>
                                <input type="number" v-model="form.sol_facil.tde.valor_minimo" class="w-24 rounded-lg border-blue-300 text-sm font-bold text-blue-900 bg-white">
                            </div>
                            <span class="font-black text-blue-400">OU</span>
                            <div>
                                <label class="block text-[10px] font-bold text-blue-700 uppercase mb-1">Carga (%)</label>
                                <input type="number" v-model="form.sol_facil.tde.percentual_frete" class="w-24 rounded-lg border-blue-300 text-sm font-bold text-blue-900 bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="abaAtiva === 'impostos'">
                    <div class="max-w-md">
                        <h3 class="font-black text-slate-800 text-xl mb-2">Imposto sobre Serviço (ICMS)</h3>
                        <p class="text-sm text-slate-500 mb-6">Alíquota abatida do cálculo final para apuramento do Lucro Líquido.</p>
                        
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Porcentagem Retida (%)</label>
                        <input type="number" v-model="form.icms_percentual" class="w-48 px-4 py-3 rounded-xl border-slate-300 focus:border-slate-800 focus:ring-slate-800 font-black text-2xl text-slate-800 shadow-sm" step="0.1">
                    </div>
                </div>
            </div>
        </div>
    </ErpLayout>
</template>
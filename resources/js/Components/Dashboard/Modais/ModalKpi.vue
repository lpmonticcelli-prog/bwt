<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    tipo: { type: String, required: true },
    viagens: { type: Array, required: true },
    faturamentos: { type: Array, required: true }
});

const emit = defineEmits(['fechar', 'abrir-dossie']);
const { formatMoney, extrairNumeroNFe } = useFormatters();

// Computa as divergências extraindo-as das viagens para a Aba 3
const fretesDivergentes = computed(() => {
    let divergentes = [];
    props.viagens.forEach(v => {
        if (v.e4log_detalhes) {
            v.e4log_detalhes.forEach(f => {
                if (!f.is_correto) divergentes.push({...f, arquivo_viagem: f.arquivo});
            });
        }
    });
    return divergentes;
});

// Fechar com a tecla ESC
const handleKeydown = (e) => { if (e.key === 'Escape') emit('fechar'); };
onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-6 font-sans">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click.self="emit('fechar')"></div>
        <div class="relative w-full max-w-6xl bg-white flex flex-col max-h-[90vh] rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up">
            
            <div class="px-6 py-5 flex justify-between items-center border-b shrink-0 z-20" :class="{ 'bg-red-50 border-red-200': tipo === 'fuga' || tipo === 'divergencia', 'bg-amber-50 border-amber-200': tipo === 'gap' }">
                <div class="flex items-center gap-4">
                    <span class="text-3xl" v-if="tipo === 'fuga'">💸</span>
                    <span class="text-3xl" v-if="tipo === 'gap'">⚠️</span>
                    <span class="text-3xl" v-if="tipo === 'divergencia'">🚨</span>
                    <div>
                        <h3 class="text-xl font-black text-slate-800">
                            <span v-if="tipo === 'fuga'">Relatório de Fugas de Lucro (Prejuízos Reais)</span>
                            <span v-if="tipo === 'gap'">Gap de Receita (Subfaturamento)</span>
                            <span v-if="tipo === 'divergencia'">Divergências Operacionais da E4LOG</span>
                        </h3>
                        <p class="text-xs text-slate-600 font-medium">Clique em qualquer linha abaixo para abrir o Extrato DACTE detalhado da viagem.</p>
                    </div>
                </div>
                <button @click="emit('fechar')" class="text-slate-400 hover:text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 p-2 rounded-xl transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <div class="overflow-y-auto scrollbar-thin scrollbar-thumb-slate-300 p-6 bg-slate-50/50 flex-1">
                
                <table v-if="tipo === 'fuga'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
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
                        <tr v-for="v in viagens.filter(x => x.lucro < 0)" :key="v.id" @click="emit('abrir-dossie', v)" class="cursor-pointer hover:bg-red-50/40 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-800">{{ extrairNumeroNFe(v.nfe_chave) ? 'NF-e ' + extrairNumeroNFe(v.nfe_chave) : 'Compl. S/ Lastro' }}</td>
                            <td class="py-3 px-4 text-slate-600 text-xs">{{ v.destino }}</td>
                            <td class="py-3 px-4 text-emerald-600 font-bold text-right">{{ formatMoney(v.receita) }}</td>
                            <td class="py-3 px-4 text-rose-600 font-bold text-right">{{ formatMoney(v.custo) }}</td>
                            <td class="py-3 px-4 text-red-600 font-black text-right text-base">{{ formatMoney(v.lucro) }}</td>
                        </tr>
                    </tbody>
                </table>

                <table v-if="tipo === 'gap'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
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
                        <tr v-for="f in faturamentos.filter(x => x.gap_individual > 0)" :key="f.id" class="hover:bg-amber-50/40 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-800 text-xs truncate max-w-[200px]" :title="f.arquivo">{{ f.arquivo }}</td>
                            <td class="py-3 px-4 text-slate-600 text-xs">{{ f.destino }}</td>
                            <td class="py-3 px-4 text-blue-600 font-bold text-right">{{ formatMoney(f.receita_real) }}</td>
                            <td class="py-3 px-4 text-emerald-600 font-bold text-right">{{ formatMoney(f.receita_teorica) }}</td>
                            <td class="py-3 px-4 text-amber-600 font-black text-right text-base">- {{ formatMoney(f.gap_individual) }}</td>
                        </tr>
                    </tbody>
                </table>

                <table v-if="tipo === 'divergencia'" class="w-full text-left text-sm border-collapse whitespace-nowrap bg-white rounded-xl shadow-sm border border-slate-200">
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
                        <tr v-for="f in fretesDivergentes" :key="f.id" @click="emit('abrir-dossie', f.viagem_pai)" class="cursor-pointer hover:bg-red-50/40 transition-colors">
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
</template>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }
@keyframes fadeInUp { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
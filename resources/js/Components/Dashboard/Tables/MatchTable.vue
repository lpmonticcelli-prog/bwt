<script setup>
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    viagens: { type: Array, required: true }
});

const emit = defineEmits(['abrir-dossie']);
const { formatMoney, extrairNumeroNFe } = useFormatters();
</script>

<template>
    <div class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.04)] border border-slate-200 overflow-hidden animate-fade-in mb-8">
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
                    <tr v-for="viagem in viagens" :key="viagem.id" @click="emit('abrir-dossie', viagem)" class="cursor-pointer hover:bg-indigo-50/40 transition-colors group" :class="viagem.status === 'sem_receita' ? 'bg-rose-50/20 hover:bg-rose-100/40' : ''">
                        <td class="py-3.5 px-5">
                            <span v-if="viagem.status === 'casada'" class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-emerald-200 shadow-sm">🟢 Casada (Match)</span>
                            <span v-else-if="viagem.status === 'sem_receita'" class="bg-rose-100 text-rose-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-rose-200 shadow-sm animate-pulse">🔴 Sem Receita (Órfã)</span>
                            <span v-else class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-wider border border-amber-200 shadow-sm">🟡 Aguardando Custo</span>
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
                            <span v-if="viagem.cte_bwt.length === 0 || (viagem.cte_bwt.length === 1 && viagem.cte_bwt[0] === 'NÃO FATURADO')" class="text-[10px] text-slate-400 font-bold italic">Não Faturado</span>
                        </td>
                        
                        <td class="py-3.5 px-5">
                            <div v-for="cte in viagem.ctes_e4log" :key="cte" class="text-xs font-bold text-orange-600 truncate max-w-[150px]" :title="cte">{{ cte }}</div>
                            <span v-if="viagem.ctes_e4log.length === 0" class="text-[10px] text-slate-400 font-bold italic">Nenhum CT-e associado</span>
                        </td>
                        
                        <td class="py-3.5 px-5 text-right text-emerald-600 font-bold">{{ formatMoney(viagem.receita) }}</td>
                        <td class="py-3.5 px-5 text-right text-rose-600 font-bold">{{ formatMoney(viagem.custo) }}</td>
                        <td class="py-3.5 px-5 text-right font-black text-sm" :class="viagem.lucro > 0 ? 'text-slate-800' : 'text-rose-600'">{{ formatMoney(viagem.lucro) }}</td>
                    </tr>
                    
                    <tr v-if="viagens.length === 0">
                        <td colspan="8" class="text-center py-10 text-slate-500 font-medium">Nenhum cruzamento de viagem encontrado no fechamento atual.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
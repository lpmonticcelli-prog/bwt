<script setup>
import { useFormatters } from '@/Composables/useFormatters';
import { useAuditoria } from '@/Composables/useAuditoria';

const props = defineProps({
    fretes: { type: Array, required: true },
    viagens: { type: Array, required: true } // Necessário para cruzar lucro/receita via Composable
});

const emit = defineEmits(['abrir-dossie']);

// Carregando as nossas "Mentes" (Composables)
const { formatMoney, formatarData } = useFormatters();
const { badgeOperacao, descobrirMotivo, obterReceitaAssociada, obterLucroAssociado } = useAuditoria();
</script>

<template>
    <div class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.04)] border border-slate-200 overflow-hidden animate-fade-in mb-8">
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
                    <tr v-for="frete in fretes" :key="frete.id" @click="emit('abrir-dossie', frete.arquivo)" class="cursor-pointer hover:bg-red-50/30 transition-colors group">
                        
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
                        <td class="py-3.5 px-5 text-blue-600 font-bold">{{ formatMoney(obterReceitaAssociada(frete, viagens)) }}</td>
                        
                        <td class="py-3.5 px-5 font-black text-sm text-right" :class="obterLucroAssociado(frete, viagens) > 0 ? 'text-emerald-500' : (obterLucroAssociado(frete, viagens) < 0 ? 'text-rose-500' : 'text-slate-500')">
                            {{ formatMoney(obterLucroAssociado(frete, viagens)) }}
                        </td>
                        
                        <td class="py-3.5 px-5">
                            <span class="text-[10px] font-bold px-2 py-1 rounded shadow-sm inline-block bg-red-50 border border-red-100 text-red-600 max-w-[260px] truncate" :title="descobrirMotivo(frete)">
                                {{ descobrirMotivo(frete) }}
                            </span>
                        </td>
                    </tr>
                    
                    <tr v-if="fretes.length === 0">
                        <td colspan="7" class="text-center py-10 text-slate-500 font-medium">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <span class="text-3xl">🎉</span>
                                <span>Nenhuma divergência encontrada! A E4LOG cobrou 100% certo neste lote.</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
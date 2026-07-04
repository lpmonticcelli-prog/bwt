<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: Boolean,
    tipo: String, // 'gap', 'divergencia' ou 'fuga'
    fretes: Array,
    faturamentos: Array
});

const emit = defineEmits(['close', 'abrir-raiox']);

const close = () => {
    emit('close');
};

const formatMoney = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

// ==========================================
// CÉREBRO DETETIVE: TEXTOS DO CABEÇALHO
// ==========================================
const textosDidaticos = computed(() => {
    if (props.tipo === 'gap') {
        return {
            titulo: 'Detetive Operacional: Gap de Receita',
            explicacao: 'O sistema cruzou as viagens que a E4LOG fez com as notas que você faturou para a Sol Fácil. A lista abaixo mostra exatamente onde deixámos dinheiro na mesa (reentregas que não cobrámos, notas esquecidas ou faturamentos abaixo da tabela).',
            corBase: 'text-amber-600',
            corFundo: 'bg-amber-50',
            borda: 'border-amber-200',
            icone: '⚠️'
        };
    } else if (props.tipo === 'divergencia') {
        return {
            titulo: 'Detetive Operacional: Divergência E4LOG',
            explicacao: 'A E4LOG emitiu cobranças que violam a nossa tabela contratual. O sistema bloqueou e listou abaixo os motivos exatos: pedágios inventados, GRIS não combinados ou TDEs acima do limite permitido.',
            corBase: 'text-rose-600',
            corFundo: 'bg-rose-50',
            borda: 'border-rose-200',
            icone: '🚨'
        };
    } else {
        return {
            titulo: 'Raio-X Global: Fuga de Lucro',
            explicacao: 'Este é o resumo de toda a ineficiência do fechamento. Aqui estão as notas onde fomos cobrados a mais pela transportadora e as notas onde faturámos a menos do cliente. Cobre a Sol Fácil e exija o estorno da E4LOG.',
            corBase: 'text-red-600',
            corFundo: 'bg-red-50',
            borda: 'border-red-200',
            icone: '💸'
        };
    }
});

// ==========================================
// CÉREBRO DETETIVE: DIAGNÓSTICO AUTO-EXPLICATIVO
// ==========================================
const badgeOperacao = (tipo) => {
    if (tipo === 'Reentrega') return 'bg-amber-100 text-amber-700 border-amber-300';
    if (tipo === 'Devolução') return 'bg-slate-200 text-slate-700 border-slate-300';
    if (tipo === 'Complemento') return 'bg-fuchsia-100 text-fuchsia-700 border-fuchsia-300';
    return 'bg-blue-50 text-blue-600 border-blue-200'; 
};

// Descobre onde você vacilou ao cobrar a Sol Fácil
const motivoFaturamento = (fat) => {
    const cobrado = Number(fat.receita_real);
    if (cobrado === 0) {
        if (fat.tipo_operacao === 'Reentrega') return '🚨 Reentrega executada pela E4LOG, mas ESQUECEMOS de cobrar da Sol Fácil.';
        if (fat.tipo_operacao === 'Devolução') return '🚨 Devolução cobrada por eles, mas NÃO repassada para a Sol Fácil.';
        return '🚨 CT-e 100% esquecido (Viagem feita, mas não faturada ao cliente).';
    }
    return '⚠️ Faturado a menor (Faltou aplicar TDE, ICMS ou calcularam o peso errado).';
};

// Descobre onde a E4LOG tentou enganar
const motivoFrete = (frete) => {
    let motivos = [];
    if (Number(frete.taxasExtras) > 0) motivos.push('Taxas não contratuais inseridas no XML (Pedágio/GRIS)');
    if (Number(frete.cobrado) > (Number(frete.freteBaseCalculado) + Number(frete.tdeCalculado))) {
        if (Number(frete.tdeCalculado) === 0 && !frete.temTde) {
            motivos.push('Valor Fixo da Tabela foi ignorado (Cobraram a maior)');
        } else {
            motivos.push('TDE cobrada muito acima da regra comercial (20% ou R$ 160)');
        }
    }
    return motivos.length > 0 ? motivos.join(' + ') : 'Erro matemático no fechamento da transportadora';
};
</script>

<template>
    <Modal :show="show" maxWidth="6xl" @close="close">
        <div class="p-6">
            
            <!-- CABEÇALHO DO MODAL -->
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h2 class="text-2xl font-black text-slate-800">{{ textosDidaticos.titulo }}</h2>
                    <div :class="['mt-3 p-4 rounded-lg border text-sm max-w-4xl', textosDidaticos.corFundo, textosDidaticos.corBase, textosDidaticos.borda]">
                        <p class="font-medium leading-relaxed">
                            <span class="mr-2">{{ textosDidaticos.icone }}</span> {{ textosDidaticos.explicacao }}
                        </p>
                    </div>
                </div>
                <button @click="close" class="text-slate-400 hover:text-slate-600 p-2 bg-slate-100 rounded-lg transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- TABELA 1: OS SEUS ESQUECIMENTOS (SOL FÁCIL) -->
            <div v-if="(tipo === 'gap' || tipo === 'fuga') && faturamentos.length > 0" class="mb-10">
                <h3 class="text-lg font-bold text-slate-800 mb-3 flex items-center border-b pb-2">
                    <span class="w-3 h-3 rounded-full bg-amber-400 mr-2"></span>
                    Dinheiro deixado na mesa (Não cobrado da Sol Fácil)
                </h3>
                <div class="overflow-hidden border border-slate-200 rounded-xl shadow-sm">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-black tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-4">Arquivo / Chave</th>
                                <th class="px-5 py-4 text-center">Tipo de Entrega</th>
                                <th class="px-5 py-4">Diagnóstico (Onde Erramos?)</th>
                                <th class="px-5 py-4 text-right">Deveria Cobrar</th>
                                <th class="px-5 py-4 text-right">Cobramos</th>
                                <th class="px-5 py-4 text-right text-amber-600 bg-amber-50">Prejuízo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <!-- AS LINHAS AGORA EMITEM O CLIQUE PARA O RAIX-X -->
                            <tr v-for="fat in faturamentos" :key="fat.id" @click="$emit('abrir-raiox', fat, 'faturamento')" class="cursor-pointer hover:bg-amber-50/40 transition-colors group">
                                <td class="px-5 py-3 text-slate-800 font-bold text-xs truncate max-w-[150px]" :title="fat.arquivo">{{ fat.arquivo }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span :class="badgeOperacao(fat.tipo_operacao)" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-wider rounded-md border shadow-sm">
                                        {{ fat.tipo_operacao || 'Entrega' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-md inline-block max-w-[280px] truncate" :class="Number(fat.receita_real) === 0 ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-amber-50 text-amber-700 border border-amber-200'" :title="motivoFaturamento(fat)">
                                        {{ motivoFaturamento(fat) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-slate-500">{{ formatMoney(fat.receita_teorica) }}</td>
                                <td class="px-5 py-3 text-right font-black text-slate-800">{{ formatMoney(fat.receita_real) }}</td>
                                <td class="px-5 py-3 text-right font-black text-amber-600 bg-amber-50/30 flex justify-end items-center gap-2">
                                    {{ formatMoney(fat.gap_individual) }}
                                    <svg class="w-4 h-4 text-amber-300 group-hover:text-amber-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABELA 2: INVENÇÕES DA TRANSPORTADORA (E4LOG) -->
            <div v-if="(tipo === 'divergencia' || tipo === 'fuga') && fretes.length > 0">
                <h3 class="text-lg font-bold text-slate-800 mb-3 flex items-center border-b pb-2 mt-4">
                    <span class="w-3 h-3 rounded-full bg-rose-500 mr-2"></span>
                    Cobranças indevidas para pedir estorno (E4LOG)
                </h3>
                <div class="overflow-hidden border border-slate-200 rounded-xl shadow-sm">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase font-black tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-4">Arquivo / Chave</th>
                                <th class="px-5 py-4 text-center">Tipo de Entrega</th>
                                <th class="px-5 py-4">Diagnóstico (Onde Eles Erraram?)</th>
                                <th class="px-5 py-4 text-right">Tabela Correta</th>
                                <th class="px-5 py-4 text-right">Eles Cobraram</th>
                                <th class="px-5 py-4 text-right text-rose-600 bg-rose-50">Cobrado a Mais</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <!-- AS LINHAS AGORA EMITEM O CLIQUE PARA O RAIX-X -->
                            <tr v-for="frete in fretes" :key="frete.id" @click="$emit('abrir-raiox', frete, 'frete')" class="cursor-pointer hover:bg-rose-50/40 transition-colors group">
                                <td class="px-5 py-3 text-slate-800 font-bold text-xs truncate max-w-[150px]" :title="frete.arquivo">{{ frete.arquivo }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span :class="badgeOperacao(frete.tipo_operacao)" class="px-2.5 py-1 text-[9px] font-black uppercase tracking-wider rounded-md border shadow-sm">
                                        {{ frete.tipo_operacao || 'Entrega' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="text-[11px] font-bold px-2 py-1 rounded-md inline-block bg-rose-50 text-rose-700 border border-rose-200 max-w-[280px] truncate" :title="motivoFrete(frete)">
                                        🚨 {{ motivoFrete(frete) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right font-bold text-slate-500">{{ formatMoney(frete.correto) }}</td>
                                <td class="px-5 py-3 text-right font-black text-slate-800">{{ formatMoney(frete.cobrado) }}</td>
                                <td class="px-5 py-3 text-right font-black text-rose-600 bg-rose-50/30 flex justify-end items-center gap-2">
                                    {{ formatMoney(frete.diferenca) }}
                                    <svg class="w-4 h-4 text-rose-300 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="tipo === 'gap' && faturamentos.length === 0" class="text-center py-16 text-slate-500 bg-slate-50 rounded-xl mt-4 border border-dashed border-slate-200">
                <span class="text-4xl block mb-3">✅</span>
                <p class="font-bold text-lg text-slate-700">Nenhum Gap de Receita encontrado.</p>
                <p class="text-sm">Vocês faturaram perfeitamente todas as entregas e reentregas deste período.</p>
            </div>
            
            <div v-if="tipo === 'divergencia' && fretes.length === 0" class="text-center py-16 text-slate-500 bg-slate-50 rounded-xl mt-4 border border-dashed border-slate-200">
                <span class="text-4xl block mb-3">🛡️</span>
                <p class="font-bold text-lg text-slate-700">Nenhuma Divergência encontrada.</p>
                <p class="text-sm">A E4LOG cobrou exatamente o valor da tabela em todas as notas operadas.</p>
            </div>

        </div>
    </Modal>
</template>
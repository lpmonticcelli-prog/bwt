<script setup>
import { computed } from 'vue';

const props = defineProps({
    totalCobrado: { type: Number, default: 0 },
    totalAuditado: { type: Number, default: 0 },
    totalDiferenca: { type: Number, default: 0 }
});

// Função para formatar números para Reais (R$)
const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
};

// Se a diferença for negativa (E4LOG cobrou a mais), fica vermelho. Se cobrou a menos, fica verde.
const corDiferenca = computed(() => {
    if (props.totalDiferenca > 0) return 'text-red-600 bg-red-100';
    if (props.totalDiferenca < 0) return 'text-green-600 bg-green-100';
    return 'text-gray-600 bg-gray-100';
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Card: Cobrado pela Transportadora -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Total Cobrado (E4LOG)</span>
            <span class="text-3xl font-bold text-gray-800">{{ formatarMoeda(totalCobrado) }}</span>
            <span class="text-xs text-gray-400 mt-2">Soma do que a transportadora faturou</span>
        </div>

        <!-- Card: Correto Auditado -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Correto (Sistema)</span>
            <span class="text-3xl font-bold text-emerald-600">{{ formatarMoeda(totalAuditado) }}</span>
            <span class="text-xs text-gray-400 mt-2">Valor real segundo a sua tabela</span>
        </div>

        <!-- Card: Diferença / Vazamento -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col">
            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Divergência Total</span>
            <div class="flex items-center gap-3">
                <span class="text-3xl font-bold text-gray-800">{{ formatarMoeda(Math.abs(totalDiferenca)) }}</span>
                <span :class="['px-3 py-1 rounded-full text-xs font-bold', corDiferenca]">
                    {{ totalDiferenca > 0 ? 'Cobrado a Mais' : (totalDiferenca < 0 ? 'Cobrado a Menos' : 'Tudo Certo') }}
                </span>
            </div>
            <span class="text-xs text-gray-400 mt-2">O quanto de dinheiro precisa ser ajustado</span>
        </div>
    </div>
</template>
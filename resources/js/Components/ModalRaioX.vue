<script setup>
defineProps({
    item: { type: Object, default: null }
});

defineEmits(['fechar']);

const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
};
</script>

<template>
    <div v-if="item" class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" @click="$emit('fechar')"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full border border-gray-100">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Raio-X do Frete</h3>
                        <p class="text-xs text-gray-500 font-mono mt-0.5">{{ item.arquivo }}</p>
                    </div>
                    <button @click="$emit('fechar')" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L12 12M12 12l6 6M12 12l6-6M12 12l-6 6"></path></svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4 bg-gray-50 rounded-lg p-4 border border-gray-100">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Destino Final</span>
                            <span class="text-base font-bold text-gray-800">{{ item.destino }}</span>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Regra Aplicada</span>
                            <span class="text-base font-bold text-indigo-600">{{ item.regra }}</span>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Memória de Cálculo (Tabela Oficial)</h4>
                        <div class="space-y-2 text-sm text-gray-600">
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>Valor da Carga (NF Solfácil):</span>
                                <span class="font-medium text-gray-800">{{ formatarMoeda(item.valorNF) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>Frete Peso / Mínimo Fixo:</span>
                                <span class="font-medium text-gray-800">{{ formatarMoeda(item.fixoRegra) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>Ad Valorem calculado ({{ item.percentualRegra }}%):</span>
                                <span class="font-medium text-gray-800">{{ formatarMoeda(item.adValoremCalculado) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-gray-100 bg-emerald-50/50 px-2 rounded font-medium text-emerald-800">
                                <span>Gatilho Maior Valor (Frete Base):</span>
                                <span>{{ formatarMoeda(item.freteBaseCalculado) }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>Taxa Desembaraço/Extras (Tag XML Comp):</span>
                                <span class="font-medium text-gray-800">{{ formatarMoeda(item.taxasExtras) }}</span>
                            </div>
                            <div v-if="item.temTde" class="flex justify-between py-1 border-b border-gray-100 text-amber-700 font-medium bg-amber-50 px-2 rounded">
                                <span>Componente TDE/Rural (20% + R$ 160,00):</span>
                                <span>{{ formatarMoeda(item.tdeCalculado) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5 grid grid-cols-2 gap-4 text-center">
                        <div class="p-3 bg-red-50 rounded-lg border border-red-100 flex flex-col">
                            <span class="text-xs font-bold text-red-700 uppercase tracking-wider mb-1">Cobrado pela E4LOG</span>
                            <span class="text-xl font-black text-red-800">{{ formatarMoeda(item.cobrado) }}</span>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100 flex flex-col">
                            <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider mb-1">Cálculo Correto</span>
                            <span class="text-xl font-black text-emerald-800">{{ formatarMoeda(item.correto) }}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                    <button @click="$emit('fechar')" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold py-2 px-4 rounded-lg shadow-sm transition text-sm">
                        Fechar Janela
                    </button>
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg shadow transition text-sm">
                        Copiar Dados para Contestação
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
const props = defineProps({
    item: Object
});

const emit = defineEmits(['fechar']);

const formatMoney = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
};

// Pega o número da nota a partir da chave (posições 25 a 33 na chave NFe padrão)
const extractNfeNumber = (chave) => {
    if (!chave || chave === 'N/A' || chave.length < 34) return chave;
    return parseInt(chave.substring(25, 34), 10);
};
</script>

<template>
    <div v-if="item" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" @click="emit('fechar')"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="relative inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-6 pt-5 pb-6">
                    
                    <div class="flex justify-between items-center mb-4 border-b pb-3">
                        <h3 class="text-xl leading-6 font-black text-slate-900">Raio-X Completo da Operação</h3>
                        <button @click="emit('fechar')" class="text-slate-400 hover:text-slate-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="mb-6 flex justify-around items-center bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <div class="text-center w-1/2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">NF / Carga</p>
                            <p class="text-lg font-bold text-slate-800">{{ extractNfeNumber(item.nfe_chave) }}</p>
                        </div>
                        <div class="text-center w-1/2 border-l border-slate-200">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Valor do Frete</p>
                            <p class="text-lg font-bold text-slate-800">{{ formatMoney(item.receita_real) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        
                        <div class="border border-red-100 rounded-xl overflow-hidden shadow-sm">
                            <div class="bg-red-50 p-3 border-b border-red-100 text-center">
                                <h4 class="text-sm font-black text-red-600 uppercase tracking-wider">Como a E4LOG Cobrou</h4>
                            </div>
                            <div class="p-4 space-y-3 text-sm text-slate-600">
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span>Frete Tabela:</span>
                                    <span class="font-medium text-slate-900">{{ formatMoney(item.custo_frete_base) }}</span>
                                </div>
                                <div class="flex justify-between items-center border-b border-slate-50 pb-2">
                                    <span>Teve TDE Cobrada?</span>
                                    <span v-if="item.custo_tde > 0" class="font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded">SIM ({{ formatMoney(item.custo_tde) }})</span>
                                    <span v-else class="font-bold text-slate-400">NÃO</span>
                                </div>
                                <div class="flex justify-between mt-2 pt-2 border-t border-slate-200 font-black text-red-600 text-lg">
                                    <span>Seu Custo Total:</span>
                                    <span>{{ formatMoney(item.custo_total) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="border border-blue-100 rounded-xl overflow-hidden shadow-sm">
                            <div class="bg-blue-50 p-3 border-b border-blue-100 text-center">
                                <h4 class="text-sm font-black text-blue-600 uppercase tracking-wider">Como a BWT Deveria Cobrar</h4>
                            </div>
                            <div class="p-4 space-y-3 text-sm text-slate-600">
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span>Frete Tabela (Sol Fácil):</span>
                                    <span class="font-medium text-slate-900">{{ formatMoney(item.receita_frete_base) }}</span>
                                </div>
                                <div class="flex justify-between items-center border-b border-slate-50 pb-2">
                                    <span>Você tem direito a TDE?</span>
                                    <span v-if="item.receita_tde > 0" class="font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded">SIM ({{ formatMoney(item.receita_tde) }})</span>
                                    <span v-else class="font-bold text-slate-400">NÃO</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-50 pb-2">
                                    <span>ICMS (Por dentro):</span>
                                    <span class="font-medium text-slate-900">{{ formatMoney(item.receita_icms) }}</span>
                                </div>
                                <div class="flex justify-between mt-2 pt-2 border-t border-slate-200 font-black text-blue-600 text-lg">
                                    <span>Você devia faturar:</span>
                                    <span>{{ formatMoney(item.receita_teorica) }}</span>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-6 border-t pt-5">
                        <div class="grid grid-cols-3 gap-4 items-center text-center">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">CT-e BWT Emitido no valor de</p>
                                <p class="text-2xl font-black text-blue-600">{{ formatMoney(item.receita_real) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">E4LOG vai te cobrar</p>
                                <p class="text-2xl font-black text-red-500">{{ formatMoney(item.custo_total) }}</p>
                            </div>
                            <div class="p-3 rounded-xl" :class="item.lucro >= 0 ? 'bg-emerald-50 border border-emerald-100 shadow-inner' : 'bg-red-50 border border-red-100 shadow-inner'">
                                <p class="text-xs font-bold uppercase mb-1" :class="item.lucro >= 0 ? 'text-emerald-600' : 'text-red-600'">O que sobra no seu bolso</p>
                                <p class="text-3xl font-black" :class="item.lucro >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ formatMoney(item.lucro) }}</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

const resultadosAuditoria = ref([]);
const currentBatchId = ref(null);

const isProcessing = ref(false);
const progressoAtual = ref('');

const uploadEmLotes = async (files) => {
    if (!files.length) return;

    isProcessing.value = true;
    const chunkSize = 20; 
    let index = 0;
    const totalFiles = files.length;

    try {
        while (index < totalFiles) {
            const chunk = Array.from(files).slice(index, index + chunkSize);
            const formData = new FormData();
            
            chunk.forEach(file => formData.append('files[]', file));
            if (currentBatchId.value) formData.append('batch_id', currentBatchId.value);

            progressoAtual.value = `Processando lote: ${Math.min(index + chunkSize, totalFiles)} de ${totalFiles} ficheiros...`;

            const response = await axios.post('/auditoria-sla/processar', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            resultadosAuditoria.value = response.data.data;
            currentBatchId.value = response.data.batch_id; 

            index += chunkSize;
        }
    } catch (error) {
        console.error("Erro no lote:", error);
        alert(`Ocorreu um erro ao processar o lote de ficheiros.`);
    } finally {
        isProcessing.value = false;
        progressoAtual.value = '';
    }
};

const handleUpload = (event) => {
    uploadEmLotes(event.target.files);
    event.target.value = '';
};

const exportarPDF = () => {
    if (!currentBatchId.value) return;
    window.open(`/auditoria-sla/exportar-pdf/${currentBatchId.value}`, '_blank');
};

const formatCurrency = (value) => {
    return Math.abs(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const totalAnalisados = computed(() => resultadosAuditoria.value.length);
</script>

<template>
    <Head title="Auditoria SLA - Sol Fácil" />

    <ErpLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Auditoria SLA (Faturamento Sol Fácil)</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500 mb-6">
                    <h3 class="text-lg font-bold mb-2 text-blue-700">Validar Faturamento (BWT -> Sol Fácil)</h3>
                    <p class="text-xs text-gray-500 mb-4">
                        Adicione os ficheiros XML. O sistema tem os 638 municípios embutidos na memória. Processa em lote e audita 100% da operação, aplicando autocompletar inteligente se houver discrepância no nome da cidade.
                    </p>
                    
                    <input 
                        type="file" multiple accept=".xml" 
                        @change="handleUpload" 
                        :disabled="isProcessing"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                    />
                    <p v-if="isProcessing" class="mt-2 text-blue-600 font-semibold text-xs animate-pulse">{{ progressoAtual }}</p>
                </div>

                <div class="flex justify-end mb-4" v-if="resultadosAuditoria.length > 0">
                    <PrimaryButton @click="exportarPDF">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Exportar Relatório PDF Completo
                    </PrimaryButton>
                </div>

                <div v-if="resultadosAuditoria.length > 0" class="bg-white p-6 rounded-lg shadow-sm overflow-x-auto">
                    <h3 class="text-lg font-bold mb-4">Análise Consolidada ({{ totalAnalisados }} operações)</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">OPERAÇÃO (FICHEIROS)</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">DESTINO</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">REGIÃO CORRETA</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">REGIÃO FATURADA</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">TDE?</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">V. CARGA</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">SOMA FATURADA</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-blue-600">SOMA SLA</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">DIFERENÇA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(item, index) in resultadosAuditoria" :key="index" :class="{'bg-orange-50': item.tipo_operacao === 'Complemento' && item.arquivos_complemento.length === 0}">
                                
                                <td class="px-4 py-2 text-xs">
                                    <span v-if="item.tipo_operacao === 'Complemento'" class="bg-orange-200 text-orange-800 text-[10px] px-2 py-0.5 rounded font-bold mr-2">COMPL. ÓRFÃO</span>
                                    <span class="font-bold">{{ item.arquivo }}</span>
                                    
                                    <div v-for="(comp, i) in item.arquivos_complemento" :key="i" class="text-[10px] text-orange-600 mt-1 flex items-center">
                                        <span class="bg-orange-200 text-orange-800 px-1 py-0 rounded mr-1 font-bold">COMPL</span>
                                        {{ comp }}
                                    </div>
                                </td>
                                
                                <td class="px-4 py-2 text-xs">{{ item.cidade_destino }}</td>
                                
                                <td class="px-4 py-2 text-xs font-bold text-blue-600">
                                    {{ item.regiao_sistema }} <span class="text-gray-400 font-normal" v-if="item.percentual_sistema !== '-'">({{ item.percentual_sistema }})</span>
                                </td>
                                
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ item.regiao_faturada }} <span v-if="item.percentual_faturado !== '-'">({{ item.percentual_faturado }})</span>
                                </td>
                                
                                <td class="px-4 py-2 text-xs font-bold" :class="item.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">{{ item.tem_tde }}</td>
                                
                                <td class="px-4 py-2 text-sm font-bold">R$ {{ formatCurrency(item.valor_carga) }}</td>
                                
                                <td class="px-4 py-2 text-sm text-info">R$ {{ formatCurrency(item.valor_cobrado) }}</td>
                                <td class="px-4 py-2 text-sm font-bold text-blue-600">R$ {{ formatCurrency(item.valor_sla) }}</td>
                                
                                <td class="px-4 py-2 text-sm font-bold" :class="item.diferenca === 0 ? 'text-gray-400' : (item.diferenca > 0 ? 'text-red-600' : 'text-green-600')">
                                    R$ {{ formatCurrency(item.diferenca) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </ErpLayout>
</template>
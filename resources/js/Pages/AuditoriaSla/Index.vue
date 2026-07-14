<script setup>
import { ref, computed } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { Head } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import axios from 'axios';

// ==========================================
// 1. ESTADO: SOL FÁCIL (SLA) - BWT
// ==========================================
const resultadosSla = ref([]);
const batchSla = ref(null);
const isProcessingSla = ref(false);
const progressoSla = ref('');

const uploadSla = async (event) => {
    const files = event.target.files;
    if (!files.length) return;
    
    isProcessingSla.value = true;
    const chunkSize = 20; 
    let index = 0;
    const totalFiles = files.length;
    
    try {
        while (index < totalFiles) {
            const chunk = Array.from(files).slice(index, index + chunkSize);
            const formData = new FormData();
            chunk.forEach(file => formData.append('files[]', file));
            if (batchSla.value) formData.append('batch_id', batchSla.value);

            progressoSla.value = `Processando SLA: ${Math.min(index + chunkSize, totalFiles)} de ${totalFiles} ficheiros...`;
            
            const response = await axios.post('/auditoria-sla/processar', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            resultadosSla.value = response.data.data;
            batchSla.value = response.data.batch_id; 
            index += chunkSize;
        }
    } catch (error) {
        console.error("Erro no SLA:", error);
        alert(`Ocorreu um erro ao processar o lote SLA.`);
    } finally {
        isProcessingSla.value = false;
        progressoSla.value = 'Concluído!';
        event.target.value = ''; // Limpa o input
    }
};

const exportarPdfSla = () => {
    if (batchSla.value) window.open(`/auditoria-sla/exportar-pdf/${batchSla.value}`, '_blank');
};

const totalSlaAnalisados = computed(() => resultadosSla.value.length);

// ==========================================
// 2. ESTADO: E4LOG (CUSTOS)
// ==========================================
const resultadosE4log = ref([]);
const batchE4log = ref(null);
const isProcessingE4log = ref(false);
const progressoE4log = ref('');

const uploadE4log = async (event) => {
    const files = event.target.files;
    if (!files.length) return;

    isProcessingE4log.value = true;
    const chunkSize = 20; 
    let index = 0;
    const totalFiles = files.length;

    try {
        while (index < totalFiles) {
            const chunk = Array.from(files).slice(index, index + chunkSize);
            const formData = new FormData();
            chunk.forEach(file => formData.append('files[]', file));
            if (batchE4log.value) formData.append('batch_id', batchE4log.value);

            progressoE4log.value = `Processando E4LOG: ${Math.min(index + chunkSize, totalFiles)} de ${totalFiles} ficheiros...`;
            
            const response = await axios.post('/auditoria/e4log/processar', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            resultadosE4log.value = response.data.data;
            batchE4log.value = response.data.batch_id; 
            index += chunkSize;
        }
    } catch (error) {
        console.error("Erro na E4LOG:", error);
        alert(`Ocorreu um erro ao processar o lote E4LOG.`);
    } finally {
        isProcessingE4log.value = false;
        progressoE4log.value = 'Concluído!';
        event.target.value = '';
    }
};

const exportarPdfE4log = () => {
    if (batchE4log.value) window.open(`/auditoria/e4log/exportar-pdf/${batchE4log.value}`, '_blank');
};

const totalE4logAnalisados = computed(() => resultadosE4log.value.length);

// ==========================================
// FUNÇÕES AUXILIARES GERAIS
// ==========================================
const formatCurrency = (value) => {
    if (value === undefined || value === null) return '0,00';
    return Math.abs(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getDiffColor = (diferenca) => {
    if (Math.abs(diferenca) <= 0.50) return 'text-gray-400';
    return diferenca < 0 ? 'text-green-600 font-extrabold' : 'text-red-600 font-extrabold';
};

const getDreRowClass = (status) => {
    if (status === 'PREJUÍZO DRE' || status === 'FURO DE RECEITA') return 'bg-red-50';
    if (status === 'CUSTO PENDENTE') return 'bg-blue-50';
    return 'hover:bg-gray-50';
};

const getDreBadgeClass = (status) => {
    if (status === 'PREJUÍZO DRE' || status === 'FURO DE RECEITA') return 'bg-red-100 text-red-800 border border-red-200';
    if (status === 'DIVERGÊNCIA') return 'bg-orange-100 text-orange-800 border border-orange-200';
    if (status === 'CUSTO PENDENTE') return 'bg-blue-100 text-blue-800 border border-blue-200';
    return 'bg-green-100 text-green-800 border border-green-200';
};

// ==========================================
// FUNÇÕES DA DRE (Placeholder)
// ==========================================
const podeGerarDre = computed(() => batchSla.value !== null && batchE4log.value !== null);

const irParaDre = () => {
    alert('Os lotes estão sincronizados! O módulo de DRE receberá a lógica de cruzamento na próxima etapa.');
};
</script>

<template>
    <Head title="Auditoria e DRE" />

    <ErpLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Painel de Auditoria Independente</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- GRID UPLOADS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    
                    <!-- BWT -> Sol Fácil -->
                    <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-blue-500 relative overflow-hidden">
                        <div v-if="batchSla" class="absolute top-2 right-2 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded">LOTE NA MEMÓRIA</div>
                        <h3 class="text-lg font-bold mb-2 text-blue-700">1. Receita (SLA Sol Fácil)</h3>
                        <p class="text-xs text-gray-500 mb-4 h-10">Suba as CT-es emitidas pela BWT para a Sol Fácil.</p>
                        
                        <input 
                            type="file" multiple accept=".xml" 
                            @change="uploadSla" 
                            :disabled="isProcessingSla"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer disabled:opacity-50"
                        />
                        <p v-if="isProcessingSla" class="mt-2 text-blue-600 font-semibold text-xs animate-pulse">{{ progressoSla }}</p>
                        <p v-else-if="progressoSla === 'Concluído!'" class="mt-2 text-green-600 font-bold text-xs">{{ progressoSla }}</p>
                    </div>

                    <!-- E4LOG -> BWT -->
                    <div class="bg-white p-6 rounded-lg shadow-sm border-l-4 border-indigo-500 relative overflow-hidden">
                        <div v-if="batchE4log" class="absolute top-2 right-2 bg-green-100 text-green-700 text-[10px] font-bold px-2 py-1 rounded">LOTE NA MEMÓRIA</div>
                        <h3 class="text-lg font-bold mb-2 text-indigo-700">2. Custos (Faturas E4LOG)</h3>
                        <p class="text-xs text-gray-500 mb-4 h-10">Suba as CT-es emitidas pela E4LOG contra a BWT.</p>
                        
                        <input 
                            type="file" multiple accept=".xml" 
                            @change="uploadE4log" 
                            :disabled="isProcessingE4log"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer disabled:opacity-50"
                        />
                        <p v-if="isProcessingE4log" class="mt-2 text-indigo-600 font-semibold text-xs animate-pulse">{{ progressoE4log }}</p>
                        <p v-else-if="progressoE4log === 'Concluído!'" class="mt-2 text-green-600 font-bold text-xs">{{ progressoE4log }}</p>
                    </div>
                </div>

                <!-- AÇÃO: DRE -->
                <div v-if="podeGerarDre" class="bg-gradient-to-r from-gray-800 to-gray-900 p-6 rounded-lg shadow-lg mb-8 flex justify-between items-center text-white transition-all">
                    <div>
                        <h3 class="text-xl font-bold">Lotes Sincronizados</h3>
                        <p class="text-sm text-gray-400 mt-1">Confronte o faturamento da Sol Fácil com a cobrança da E4LOG pelo número da NFe (Placas) e extraia a DRE exata.</p>
                    </div>
                    <button 
                        @click="irParaDre" 
                        class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 px-6 rounded-lg shadow-md transition-all flex items-center">
                        Gerar DRE da Operação
                    </button>
                </div>

                <!-- ============================================== -->
                <!-- RESULTADOS DA AUDITORIA SLA (BWT) -->
                <!-- ============================================== -->
                <div v-if="resultadosSla.length > 0" class="mb-10">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-black text-gray-800 border-l-4 border-blue-500 pl-3">Auditoria Receita: BWT -> Sol Fácil ({{ totalSlaAnalisados }} notas)</h3>
                        <PrimaryButton @click="exportarPdfSla" class="bg-blue-600 hover:bg-blue-700">
                            Exportar PDF Receita
                        </PrimaryButton>
                    </div>
                    
                    <div class="bg-white p-4 rounded-lg shadow-sm overflow-x-auto border border-blue-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-blue-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">OPERAÇÃO (FICHEIROS)</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">DESTINO</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">REGIÃO CORRETA</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">REGIÃO FATURADA</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">TDE?</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">V. CARGA</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">SOMA FATURADA</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">SOMA SLA</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">DIFERENÇA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in resultadosSla" :key="'sla-'+index" :class="{'bg-orange-50': item.tipo_operacao === 'Complemento' && item.arquivos_complemento.length === 0}">
                                    
                                    <td class="px-4 py-2 text-xs">
                                        <span v-if="item.tipo_operacao === 'Complemento'" class="bg-orange-200 text-orange-800 text-[10px] px-2 py-0.5 rounded font-bold mr-2">COMPL. ÓRFÃO</span>
                                        <span class="font-bold text-gray-800">{{ item.arquivo }}</span>
                                        
                                        <div v-for="(comp, i) in item.arquivos_complemento" :key="i" class="text-[10px] text-orange-600 mt-1 flex items-center">
                                            <span class="bg-orange-200 text-orange-800 px-1 py-0 rounded mr-1 font-bold">COMPL</span>
                                            {{ comp }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-gray-700">{{ item.cidade_destino }}</td>
                                    
                                    <td class="px-4 py-2 text-xs font-bold text-blue-600">
                                        {{ item.regiao_sistema }} <span class="text-gray-400 font-normal" v-if="item.percentual_sistema !== '-'">({{ item.percentual_sistema }})</span>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        {{ item.regiao_faturada }} <span v-if="item.percentual_faturado !== '-'">({{ item.percentual_faturado }})</span>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs font-bold" :class="item.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">{{ item.tem_tde }}</td>
                                    
                                    <td class="px-4 py-2 text-sm font-bold text-gray-600">R$ {{ formatCurrency(item.valor_carga) }}</td>
                                    
                                    <td class="px-4 py-2 text-sm text-info">
                                        <div class="font-bold text-gray-800">R$ {{ formatCurrency(item.valor_cobrado) }}</div>
                                        <div v-if="item.valor_tde_cobrado > 0" class="text-[10px] text-gray-500 mt-1 leading-tight">
                                            Frt: R$ {{ formatCurrency(item.valor_frete_cobrado) }}<br>
                                            <span class="text-orange-500">TDE: R$ {{ formatCurrency(item.valor_tde_cobrado) }}</span>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2 text-sm font-bold text-blue-600">R$ {{ formatCurrency(item.valor_sla) }}</td>
                                    
                                    <td class="px-4 py-2 text-sm font-bold" :class="item.diferenca === 0 ? 'text-gray-400' : (item.diferenca > 0 ? 'text-red-600' : 'text-green-600')">
                                        R$ {{ formatCurrency(item.diferenca) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- RESULTADOS DA AUDITORIA E4LOG -->
                <!-- ============================================== -->
                <div v-if="resultadosE4log.length > 0" class="mb-10">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-black text-gray-800 border-l-4 border-indigo-500 pl-3">Auditoria Custos: E4LOG -> BWT ({{ totalE4logAnalisados }} notas)</h3>
                        <PrimaryButton @click="exportarPdfE4log" class="bg-indigo-600 hover:bg-indigo-700">
                            Exportar PDF Custos
                        </PrimaryButton>
                    </div>
                    
                    <div class="bg-white p-4 rounded-lg shadow-sm overflow-x-auto border border-indigo-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-indigo-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">OPERAÇÃO (FICHEIROS)</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">DESTINO</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">REG. MATRIZ</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-bold text-indigo-800 uppercase">TDE?</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">COBRADO</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">MATRIZ SLA</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">DIFERENÇA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in resultadosE4log" :key="'e4log-'+index" :class="item.status === 'Alerta' ? 'bg-yellow-50' : 'hover:bg-gray-50'">
                                    
                                    <td class="px-4 py-2 text-xs">
                                        <span v-if="item.tipo_operacao === 'Complemento'" class="bg-orange-200 text-orange-800 text-[10px] px-2 py-0.5 rounded font-bold mr-2">COMPL. ÓRFÃO</span>
                                        <div class="font-bold text-gray-800">{{ item.arquivo }}</div>
                                        <div v-for="(comp, i) in item.arquivos_complemento" :key="i" class="text-[10px] text-orange-600 mt-1 flex items-center">
                                            <span class="bg-orange-200 text-orange-800 px-1 py-0 rounded mr-1 font-bold">COMPL</span>
                                            {{ comp }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-gray-700">{{ item.cidade_destino }}</td>
                                    
                                    <td class="px-4 py-2 text-xs font-bold text-indigo-600">
                                        {{ item.regiao_sistema }} <span class="text-gray-400 font-normal" v-if="item.percentual_sistema !== '-'">({{ item.percentual_sistema }})</span>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-center font-semibold" :class="item.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">{{ item.tem_tde }}</td>
                                    
                                    <td class="px-4 py-2 text-xs text-right text-info">
                                        <div class="font-bold text-gray-800">R$ {{ formatCurrency(item.valor_cobrado) }}</div>
                                        <div v-if="item.valor_tde_cobrado > 0" class="text-[10px] text-gray-500 mt-1 leading-tight text-right">
                                            Frt: R$ {{ formatCurrency(item.valor_frete_cobrado) }}<br>
                                            <span class="text-orange-500">TDE: R$ {{ formatCurrency(item.valor_tde_cobrado) }}</span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-right font-bold text-indigo-600">R$ {{ formatCurrency(item.valor_sla) }}</td>
                                    
                                    <td class="px-4 py-2 text-xs text-right font-bold" :class="getDiffColor(item.diferenca)" :title="item.motivo">
                                        {{ item.diferenca < 0 ? '-' : (item.diferenca > 0 ? '+' : '') }}R$ {{ formatCurrency(item.diferenca) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </ErpLayout>
</template>
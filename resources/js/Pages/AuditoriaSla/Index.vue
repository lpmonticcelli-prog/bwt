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
        event.target.value = '';
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
// 3. ESTADO: DRE (CONFRONTO) - AGORA ATIVADO!
// ==========================================
const resultadosDre = ref([]);
const resumoDre = ref(null);
const batchDre = ref(null);
const isProcessingDre = ref(false);

const podeGerarDre = computed(() => batchSla.value !== null && batchE4log.value !== null);

const gerarDre = async () => {
    if (!podeGerarDre.value) return;
    
    isProcessingDre.value = true;
    try {
        const response = await axios.post('/dre/confrontar', {
            batch_sla: batchSla.value,
            batch_e4log: batchE4log.value
        });
        
        resultadosDre.value = response.data.data;
        resumoDre.value = response.data.resumo;
        batchDre.value = response.data.batch_dre;
        
    } catch (error) {
        console.error("Erro ao gerar DRE:", error);
        alert(`Erro ao cruzar os dados. Verifique o console.`);
    } finally {
        isProcessingDre.value = false;
    }
};

const exportarPdfDre = () => {
    if (batchDre.value) window.open(`/dre/exportar-pdf/${batchDre.value}`, '_blank');
};

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
    if (status === 'DIVERGÊNCIA') return 'bg-orange-50';
    return 'hover:bg-gray-50';
};

const getDreBadgeClass = (status) => {
    if (status === 'PREJUÍZO DRE' || status === 'FURO DE RECEITA') return 'bg-red-100 text-red-800 border border-red-200';
    if (status === 'DIVERGÊNCIA') return 'bg-orange-100 text-orange-800 border border-orange-200';
    if (status === 'CUSTO PENDENTE') return 'bg-blue-100 text-blue-800 border border-blue-200';
    return 'bg-green-100 text-green-800 border border-green-200';
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
                        @click="gerarDre" 
                        :disabled="isProcessingDre"
                        class="bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 px-6 rounded-lg shadow-md transition-all flex items-center disabled:opacity-50">
                        <svg v-if="!isProcessingDre" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        <svg v-else class="animate-spin w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ isProcessingDre ? 'Cruzando Matrizes...' : 'Gerar DRE da Operação' }}
                    </button>
                </div>

                <!-- ============================================== -->
                <!-- RESULTADOS DA DRE (A SUPER TABELA) -->
                <!-- ============================================== -->
                <div v-if="resultadosDre.length > 0" class="mb-10 animate-fade-in-up">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-black text-gray-800">DRE: Raio-X Detalhado por NF-e</h3>
                        <PrimaryButton @click="exportarPdfDre" class="bg-gray-800 hover:bg-gray-700">
                            Exportar DRE (PDF)
                        </PrimaryButton>
                    </div>

                    <!-- Resumo Financeiro Master -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="bg-white p-4 rounded-lg border-l-4 border-gray-400 shadow-sm">
                            <p class="text-xs font-bold text-gray-500 uppercase">Alertas Contratuais</p>
                            <p class="text-xl font-black text-orange-600 mt-1">{{ resumoDre?.qtd_alertas || 0 }} Entregas</p>
                            <p class="text-[10px] text-gray-400 mt-1">Cargas com divergência ou furo</p>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-lg border-l-4 border-blue-500 shadow-sm">
                            <p class="text-xs font-bold text-blue-800 uppercase">Receita Total (BWT)</p>
                            <p class="text-xl font-black text-blue-600 mt-1">R$ {{ formatCurrency(resumoDre?.total_receita_real || 0) }}</p>
                            <p class="text-[10px] text-blue-500 mt-1 font-bold">Ideal: R$ {{ formatCurrency(resumoDre?.total_receita_ideal || 0) }}</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg border-l-4 border-red-500 shadow-sm">
                            <p class="text-xs font-bold text-red-800 uppercase">Custo Total (E4LOG)</p>
                            <p class="text-xl font-black text-red-600 mt-1">R$ {{ formatCurrency(resumoDre?.total_custo_real || 0) }}</p>
                            <p class="text-[10px] text-red-500 mt-1 font-bold">Ideal: R$ {{ formatCurrency(resumoDre?.total_custo_ideal || 0) }}</p>
                        </div>
                        <div class="bg-gray-900 text-white p-4 rounded-lg border-l-4 border-emerald-500 shadow-sm">
                            <p class="text-xs font-bold uppercase text-gray-400">Spread Bruto Real</p>
                            <p class="text-2xl font-black mt-1" :class="(resumoDre?.lucro_bruto_real || 0) < 0 ? 'text-red-400' : 'text-emerald-400'">
                                R$ {{ formatCurrency(resumoDre?.lucro_bruto_real || 0) }}
                            </p>
                            <p class="text-[10px] text-gray-400 mt-1 font-bold">
                                Ideal: <span class="text-white">R$ {{ formatCurrency(resumoDre?.lucro_bruto_ideal || 0) }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Tabela DRE Analítica -->
                    <div class="bg-white rounded-lg shadow-sm overflow-x-auto border border-gray-200">
                        <table class="min-w-max divide-y divide-gray-200 text-xs">
                            <thead>
                                <tr>
                                    <th colspan="4" class="px-4 py-2 bg-gray-100 text-center font-black text-gray-700 border-r-2 border-gray-300">DADOS DA CARGA E ARQUIVOS</th>
                                    <th colspan="4" class="px-4 py-2 bg-blue-100 text-center font-black text-blue-800 border-r-2 border-blue-300">RECEITA (BWT ➔ SOL FÁCIL)</th>
                                    <th colspan="4" class="px-4 py-2 bg-red-100 text-center font-black text-red-800 border-r-2 border-red-300">CUSTO (E4LOG ➔ BWT)</th>
                                    <th colspan="2" class="px-4 py-2 bg-gray-800 text-center font-black text-white">SPREAD (DRE)</th>
                                </tr>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 text-left font-bold text-gray-500 uppercase">NFe / Arquivos Associados</th>
                                    <th class="px-3 py-2 text-left font-bold text-gray-500 uppercase">Destino</th>
                                    <th class="px-3 py-2 text-center font-bold text-gray-500 uppercase">TDE?</th>
                                    <th class="px-3 py-2 text-right font-bold text-gray-500 uppercase border-r-2 border-gray-300">V. Carga</th>
                                    
                                    <!-- Receita -->
                                    <th class="px-3 py-2 text-left font-bold text-blue-800 bg-blue-50">Região Base</th>
                                    <th class="px-3 py-2 text-right font-bold text-blue-800 bg-blue-50">Receita Real</th>
                                    <th class="px-3 py-2 text-right font-bold text-blue-800 bg-blue-50">SLA Matriz</th>
                                    <th class="px-3 py-2 text-right font-bold text-blue-800 bg-blue-50 border-r-2 border-blue-300">Desvio</th>

                                    <!-- Custo -->
                                    <th class="px-3 py-2 text-left font-bold text-red-800 bg-red-50">Região Base</th>
                                    <th class="px-3 py-2 text-right font-bold text-red-800 bg-red-50">Custo Pago</th>
                                    <th class="px-3 py-2 text-right font-bold text-red-800 bg-red-50">SLA Matriz</th>
                                    <th class="px-3 py-2 text-right font-bold text-red-800 bg-red-50 border-r-2 border-red-300">Desvio</th>

                                    <!-- Final -->
                                    <th class="px-3 py-2 text-right font-bold text-gray-700 bg-gray-100">Lucro Bruto R$</th>
                                    <th class="px-3 py-2 text-center font-bold text-gray-700 bg-gray-100">Status Opr.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(dre, index) in resultadosDre" :key="'dre-'+index" class="group transition-colors" :class="getDreRowClass(dre.status)">
                                    
                                    <!-- CHAVE E ARQUIVOS -->
                                    <td class="px-3 py-2 border-r align-top w-56">
                                        <div class="font-mono text-[9px] text-gray-800 font-black truncate max-w-[200px] mb-2" :title="dre.chave_nfe">{{ dre.chave_nfe }}</div>
                                        
                                        <!-- ARQUIVOS BWT -->
                                        <div v-if="dre.arquivo_bwt !== 'NÃO LOCALIZADO NO LOTE (FURO DE RECEITA)'" class="mb-2">
                                            <div class="text-[8px] text-blue-700 break-words max-w-[200px]" :title="dre.arquivo_bwt">
                                                <span class="font-bold">BWT:</span> {{ dre.arquivo_bwt }}
                                            </div>
                                            <!-- COMPLEMENTOS EM LARANJA NA DRE -->
                                            <div v-if="dre.arquivos_bwt_compl && dre.arquivos_bwt_compl.length > 0" class="mt-1 pt-1 border-t border-gray-200">
                                                <span class="text-[8px] text-orange-600 font-bold uppercase">+ COMPL. BWT:</span>
                                                <div v-for="comp in dre.arquivos_bwt_compl" :key="comp" class="text-[8px] text-orange-600 font-bold break-words max-w-[200px] mt-0.5" :title="comp">
                                                    {{ comp }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="mb-2">
                                            <span class="bg-red-100 text-red-800 text-[8px] font-bold px-1 rounded border border-red-300">BWT NÃO LOCALIZADO</span>
                                        </div>

                                        <!-- ARQUIVOS E4LOG -->
                                        <div v-if="dre.arquivo_e4log !== 'CUSTO PENDENTE'">
                                            <div class="text-[8px] text-red-700 break-words max-w-[200px]" :title="dre.arquivo_e4log">
                                                <span class="font-bold">E4L:</span> {{ dre.arquivo_e4log }}
                                            </div>
                                            <!-- COMPLEMENTOS EM LARANJA NA DRE -->
                                            <div v-if="dre.arquivos_e4log_compl && dre.arquivos_e4log_compl.length > 0" class="mt-1 pt-1 border-t border-gray-200">
                                                <span class="text-[8px] text-orange-600 font-bold uppercase">+ COMPL. E4LOG:</span>
                                                <div v-for="comp in dre.arquivos_e4log_compl" :key="comp" class="text-[8px] text-orange-600 font-bold break-words max-w-[200px] mt-0.5" :title="comp">
                                                    {{ comp }}
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else>
                                            <span class="bg-blue-100 text-blue-800 text-[8px] font-bold px-1 rounded border border-blue-300">E4LOG PENDENTE</span>
                                        </div>
                                    </td>

                                    <!-- IDENTIFICAÇÃO DA CARGA -->
                                    <td class="px-3 py-2 font-semibold text-gray-700 align-top">{{ dre.cidade }}</td>
                                    <td class="px-3 py-2 text-center font-bold align-top" :class="dre.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">
                                        <span v-if="dre.tem_tde === 'Sim'" class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-[9px]">SIM</span>
                                        <span v-else>NÃO</span>
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-500 border-r-2 border-gray-300 align-top font-bold">R$ {{ formatCurrency(dre.valor_carga) }}</td>
                                    
                                    <!-- ========================== -->
                                    <!-- RECEITA BWT -->
                                    <!-- ========================== -->
                                    <td class="px-3 py-2 align-top">
                                        <div class="text-[10px] text-blue-800 font-bold mb-1">{{ dre.receita.matriz }}</div>
                                        <div class="text-[9px] text-gray-500">Fat: {{ dre.receita.faturada }}</div>
                                    </td>
                                    <!-- FATURADO REAL -->
                                    <td class="px-3 py-2 text-right align-top bg-blue-50/30">
                                        <div class="font-black text-blue-700 text-sm mb-0.5">R$ {{ formatCurrency(dre.receita.real) }}</div>
                                        <div class="text-[9px] text-gray-500 leading-tight font-semibold">
                                            Frt: R$ {{ formatCurrency(dre.receita.real_frete) }}<br>
                                            <span class="text-orange-500 font-bold">TDE: R$ {{ formatCurrency(dre.receita.real_tde) }}</span>
                                        </div>
                                    </td>
                                    <!-- SLA MATRIZ IDEAL -->
                                    <td class="px-3 py-2 text-right align-top">
                                        <div class="font-black text-gray-600 text-sm mb-0.5">R$ {{ formatCurrency(dre.receita.ideal) }}</div>
                                        <div class="text-[9px] text-gray-500 leading-tight font-semibold">
                                            Frt: R$ {{ formatCurrency(dre.receita.ideal_frete) }}<br>
                                            <span class="text-orange-500 font-bold">TDE: R$ {{ formatCurrency(dre.receita.ideal_tde) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right border-r-2 border-blue-300 font-bold align-top" :class="getDiffColor(dre.receita.diferenca)">
                                        {{ dre.receita.diferenca < 0 ? '-' : (dre.receita.diferenca > 0 ? '+' : '') }}R$ {{ formatCurrency(dre.receita.diferenca) }}
                                    </td>

                                    <!-- ========================== -->
                                    <!-- CUSTO E4LOG -->
                                    <!-- ========================== -->
                                    <td class="px-3 py-2 align-top">
                                        <div class="text-[10px] text-red-800 font-bold mb-1">{{ dre.custo.matriz }}</div>
                                        <div class="text-[9px] text-gray-500">Cob: {{ dre.custo.faturada }}</div>
                                    </td>
                                    <!-- CUSTO REAL PAGO -->
                                    <td class="px-3 py-2 text-right align-top bg-red-50/30">
                                        <div class="font-black text-red-700 text-sm mb-0.5">R$ {{ formatCurrency(dre.custo.real) }}</div>
                                        <div class="text-[9px] text-gray-500 leading-tight font-semibold">
                                            Frt: R$ {{ formatCurrency(dre.custo.real_frete) }}<br>
                                            <span class="text-orange-500 font-bold">TDE: R$ {{ formatCurrency(dre.custo.real_tde) }}</span>
                                        </div>
                                    </td>
                                    <!-- SLA MATRIZ IDEAL -->
                                    <td class="px-3 py-2 text-right align-top">
                                        <div class="font-black text-gray-600 text-sm mb-0.5">R$ {{ formatCurrency(dre.custo.ideal) }}</div>
                                        <div class="text-[9px] text-gray-500 leading-tight font-semibold">
                                            Frt: R$ {{ formatCurrency(dre.custo.ideal_frete) }}<br>
                                            <span class="text-orange-500 font-bold">TDE: R$ {{ formatCurrency(dre.custo.ideal_tde) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right border-r-2 border-red-300 font-bold align-top" :class="getDiffColor(dre.custo.diferenca * -1)">
                                        {{ dre.custo.diferenca < 0 ? '-' : (dre.custo.diferenca > 0 ? '+' : '') }}R$ {{ formatCurrency(dre.custo.diferenca) }}
                                    </td>

                                    <!-- SPREAD DRE -->
                                    <td class="px-3 py-2 text-right font-black align-top bg-gray-50/50" :class="dre.dre.lucro_real < 0 ? 'text-red-600' : 'text-emerald-600'">
                                        <div class="text-sm">{{ dre.dre.lucro_real < 0 ? '-' : '+' }} R$ {{ formatCurrency(dre.dre.lucro_real) }}</div>
                                        <div class="text-[9px] mt-1 text-gray-400">Margem: <span :class="dre.dre.margem_real < 0 ? 'text-red-400' : 'text-emerald-500'">{{ dre.dre.margem_real }}%</span></div>
                                    </td>
                                    <td class="px-3 py-2 text-center align-top bg-gray-50/50">
                                        <span class="px-2 py-1 rounded font-bold text-[9px] shadow-sm" :class="getDreBadgeClass(dre.status)">
                                            {{ dre.status }}
                                        </span>
                                    </td>

                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SEPARADOR VISUAL -->
                <div v-if="resultadosSla.length > 0 || resultadosE4log.length > 0" class="flex items-center my-8 opacity-50">
                    <div class="flex-grow border-t border-gray-400"></div>
                    <span class="flex-shrink-0 mx-4 text-gray-500 text-[10px] font-bold uppercase tracking-widest">Tabelas de Validação Individuais (Matriz x Fatura)</span>
                    <div class="flex-grow border-t border-gray-400"></div>
                </div>

                <!-- ============================================== -->
                <!-- RESULTADOS DA AUDITORIA SLA (BWT) INDIVIDUAL -->
                <!-- ============================================== -->
                <div v-if="resultadosSla.length > 0" class="mb-10 opacity-75 hover:opacity-100 transition-opacity">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-black text-gray-600 pl-2 border-l-4 border-blue-400">Auditoria BWT -> Sol Fácil ({{ totalSlaAnalisados }} notas)</h3>
                        <button @click="exportarPdfSla" class="text-blue-600 hover:text-blue-800 text-xs font-bold underline">Exportar PDF</button>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm overflow-x-auto border border-blue-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-blue-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">ARQUIVO / NFE / COMPL</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">DESTINO</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-blue-800 uppercase">REGIÃO CORRETA</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-bold text-blue-800 uppercase">TDE?</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-blue-800 uppercase">COBRADO BWT</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-blue-800 uppercase">SLA MATRIZ</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-blue-800 uppercase">DIFERENÇA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in resultadosSla" :key="'slaind-'+index" class="hover:bg-gray-50">
                                    
                                    <!-- COLUNA DE ARQUIVOS COM COMPLEMENTOS EM LARANJA -->
                                    <td class="px-4 py-2 text-xs align-top">
                                        <span v-if="item.tipo_operacao === 'Complemento'" class="bg-orange-100 text-orange-800 border border-orange-300 px-1 py-0.5 rounded text-[8px] font-bold mb-1 inline-block">COMPL. ÓRFÃO</span>
                                        <div class="font-bold text-gray-800 break-words w-48" :title="item.arquivo">{{ item.arquivo }}</div>
                                        <div class="text-[9px] text-gray-500 mt-1">CTe: {{ item.chave_cte }}</div>
                                        <div class="text-[9px] text-gray-500">NFe: {{ item.chave_nfe }}</div>
                                        
                                        <div v-if="item.arquivos_complemento && item.arquivos_complemento.length > 0" class="mt-2 pt-1 border-t border-gray-200">
                                            <span class="text-[9px] text-orange-600 font-bold uppercase">+ COMPLEMENTO VINCULADO:</span>
                                            <div v-for="comp in item.arquivos_complemento" :key="comp" class="text-[9px] font-bold text-orange-600 break-words w-48 mt-0.5">
                                                {{ comp }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2 text-xs text-gray-700 align-top">{{ item.cidade_destino }}</td>
                                    <td class="px-4 py-2 text-xs font-bold text-blue-600 align-top">{{ item.regiao_sistema }}</td>
                                    <td class="px-4 py-2 text-xs text-center font-semibold align-top" :class="item.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">{{ item.tem_tde }}</td>
                                    
                                    <!-- SOMA FATURADA (SEMPRE COM QUEBRA) -->
                                    <td class="px-4 py-2 text-xs text-right align-top">
                                        <div class="font-bold text-blue-600 text-sm mb-0.5">R$ {{ formatCurrency(item.valor_cobrado) }}</div>
                                        <div class="text-[9px] text-gray-500">Frt: R$ {{ formatCurrency(item.valor_frete_cobrado) }}</div>
                                        <div class="text-[9px] text-orange-500 font-semibold">TDE: R$ {{ formatCurrency(item.valor_tde_cobrado) }}</div>
                                    </td>
                                    
                                    <!-- SOMA SLA (SEMPRE COM QUEBRA) -->
                                    <td class="px-4 py-2 text-xs text-right align-top">
                                        <div class="font-bold text-blue-600 text-sm mb-0.5">R$ {{ formatCurrency(item.valor_sla) }}</div>
                                        <div class="text-[9px] text-gray-500">Frt: R$ {{ formatCurrency(item.valor_frete_sla) }}</div>
                                        <div class="text-[9px] text-orange-500 font-semibold">TDE: R$ {{ formatCurrency(item.valor_tde_sla) }}</div>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-right font-bold align-top" :class="getDiffColor(item.diferenca)">
                                        R$ {{ formatCurrency(item.diferenca) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- RESULTADOS DA AUDITORIA E4LOG INDIVIDUAL -->
                <!-- ============================================== -->
                <div v-if="resultadosE4log.length > 0" class="mb-10 opacity-75 hover:opacity-100 transition-opacity">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm font-black text-gray-600 pl-2 border-l-4 border-indigo-400">Auditoria E4LOG -> BWT ({{ totalE4logAnalisados }} notas)</h3>
                        <button @click="exportarPdfE4log" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold underline">Exportar PDF</button>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm overflow-x-auto border border-indigo-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-indigo-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">ARQUIVO / NFE / COMPL</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">DESTINO</th>
                                    <th class="px-4 py-2 text-left text-[10px] font-bold text-indigo-800 uppercase">REG. MATRIZ</th>
                                    <th class="px-4 py-2 text-center text-[10px] font-bold text-indigo-800 uppercase">TDE?</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">COBRADO E4LOG</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">SLA MATRIZ</th>
                                    <th class="px-4 py-2 text-right text-[10px] font-bold text-indigo-800 uppercase">DIFERENÇA</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(item, index) in resultadosE4log" :key="'e4ind-'+index" class="hover:bg-gray-50">
                                    
                                    <!-- COLUNA DE ARQUIVOS COM COMPLEMENTOS EM LARANJA -->
                                    <td class="px-4 py-2 text-xs align-top">
                                        <span v-if="item.tipo_operacao === 'Complemento'" class="bg-orange-100 text-orange-800 border border-orange-300 px-1 py-0.5 rounded text-[8px] font-bold mb-1 inline-block">COMPL. ÓRFÃO</span>
                                        <div class="font-bold text-gray-800 break-words w-48" :title="item.arquivo">{{ item.arquivo }}</div>
                                        <div class="text-[9px] text-gray-500 mt-1">CTe: {{ item.chave_cte }}</div>
                                        <div class="text-[9px] text-gray-500">NFe: {{ item.chave_nfe }}</div>
                                        
                                        <div v-if="item.arquivos_complemento && item.arquivos_complemento.length > 0" class="mt-2 pt-1 border-t border-gray-200">
                                            <span class="text-[9px] text-orange-600 font-bold uppercase">+ COMPLEMENTO VINCULADO:</span>
                                            <div v-for="comp in item.arquivos_complemento" :key="comp" class="text-[9px] font-bold text-orange-600 break-words w-48 mt-0.5">
                                                {{ comp }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-2 text-xs text-gray-700 align-top">{{ item.cidade_destino }}</td>
                                    <td class="px-4 py-2 text-xs font-bold text-indigo-600 align-top">{{ item.regiao_sistema }}</td>
                                    <td class="px-4 py-2 text-xs text-center font-semibold align-top" :class="item.tem_tde === 'Sim' ? 'text-green-600' : 'text-gray-400'">{{ item.tem_tde }}</td>
                                    
                                    <!-- SOMA FATURADA (SEMPRE COM QUEBRA) -->
                                    <td class="px-4 py-2 text-xs text-right align-top">
                                        <div class="font-bold text-blue-600 text-sm mb-0.5">R$ {{ formatCurrency(item.valor_cobrado) }}</div>
                                        <div class="text-[9px] text-gray-500">Frt: R$ {{ formatCurrency(item.valor_frete_cobrado) }}</div>
                                        <div class="text-[9px] text-orange-500 font-semibold">TDE: R$ {{ formatCurrency(item.valor_tde_cobrado) }}</div>
                                    </td>
                                    
                                    <!-- SOMA SLA (SEMPRE COM QUEBRA) -->
                                    <td class="px-4 py-2 text-xs text-right align-top">
                                        <div class="font-bold text-blue-600 text-sm mb-0.5">R$ {{ formatCurrency(item.valor_sla) }}</div>
                                        <div class="text-[9px] text-gray-500">Frt: R$ {{ formatCurrency(item.valor_frete_sla) }}</div>
                                        <div class="text-[9px] text-orange-500 font-semibold">TDE: R$ {{ formatCurrency(item.valor_tde_sla) }}</div>
                                    </td>
                                    
                                    <td class="px-4 py-2 text-xs text-right font-bold align-top" :class="getDiffColor(item.diferenca)">
                                        R$ {{ formatCurrency(item.diferenca) }}
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
<style scoped>
.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
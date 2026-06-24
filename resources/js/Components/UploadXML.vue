<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const emit = defineEmits(['arquivos-selecionados']);
const isDragging = ref(false);
const fileInput = ref(null);

const isUploading = ref(false);
const totalFiles = ref(0);
const processedFiles = ref(0);
const hasError = ref(false);
const errorMessage = ref('');

const progressPercentage = computed(() => {
    if (totalFiles.value === 0) return 0;
    return Math.round((processedFiles.value / totalFiles.value) * 100);
});

const triggerSelect = () => {
    if (!isUploading.value) fileInput.value.click();
};

const processFilesQueue = async (files) => {
    if (files.length === 0 || isUploading.value) return;

    const fileArray = Array.from(files);
    totalFiles.value = fileArray.length;
    processedFiles.value = 0;
    isUploading.value = true;
    hasError.value = false;
    errorMessage.value = '';

    // FATIAMENTO: Envia lotes de 50 XMLs
    const CHUNK_SIZE = 15;
    const chunks = [];
    for (let i = 0; i < fileArray.length; i += CHUNK_SIZE) {
        chunks.push(fileArray.slice(i, i + CHUNK_SIZE));
    }

    // Identifica dinamicamente em qual tela o componente está (Faturamento ou Auditoria)
    const endpoint = window.location.pathname.includes('faturamento') 
        ? '/faturamento/processar' 
        : '/auditoria/processar';

    try {
        for (const chunk of chunks) {
            const formData = new FormData();
            chunk.forEach(file => {
                formData.append('xml_files[]', file);
            });

            await axios.post(endpoint, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json'
                }
            });

            processedFiles.value += chunk.length;
        }

        router.reload({ only: ['faturamentosProcessados', 'fretesProcessados', 'errors'] });
        emit('arquivos-selecionados'); // Dispara evento de conclusão
        
        setTimeout(() => {
            isUploading.value = false;
            totalFiles.value = 0;
            processedFiles.value = 0;
        }, 1500);

    } catch (error) {
        hasError.value = true;
        errorMessage.value = error.response?.data?.error || 'O servidor recusou alguns arquivos.';
        isUploading.value = false;
    }
    
    if (fileInput.value) fileInput.value.value = '';
};

const onDrop = (e) => {
    isDragging.value = false;
    processFilesQueue(e.dataTransfer.files);
};

const onChange = (e) => {
    processFilesQueue(e.target.files);
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6 h-full flex flex-col justify-between relative overflow-hidden">
        <div v-if="isUploading" class="absolute inset-0 bg-white/60 backdrop-blur-sm z-10"></div>
        <div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Processar Novo Lote</h3>
            <p class="text-xs text-slate-500 mb-4">Aguarde o envio em lotes para processar grandes quantidades de CT-e.</p>
        </div>
        
        <div 
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="triggerSelect"
            :class="[
                'border-2 border-dashed rounded-xl p-6 flex flex-col items-center justify-center transition-all cursor-pointer min-h-[180px]',
                isDragging ? 'border-indigo-500 bg-indigo-50/50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100/70',
                isUploading ? 'opacity-50 cursor-not-allowed' : ''
            ]"
        >
            <svg class="w-10 h-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            <p class="text-sm font-semibold text-slate-700 text-center">Arraste os XMLs aqui</p>
            <p class="text-xs text-slate-400 mt-1 text-center">Suporta milhares de notas</p>
            <input ref="fileInput" type="file" multiple accept=".xml" class="hidden" @change="onChange" :disabled="isUploading" />
        </div>
        
        <div v-if="isUploading || (processedFiles === totalFiles && totalFiles > 0)" class="mt-4 p-4 border border-indigo-100 bg-indigo-50 rounded-lg relative z-20">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-bold text-indigo-800">Enviando e Processando...</span>
                <span class="text-sm font-bold text-indigo-600">{{ progressPercentage }}%</span>
            </div>
            <div class="w-full bg-indigo-200 rounded-full h-2.5">
                <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300 ease-out" :style="{ width: progressPercentage + '%' }"></div>
            </div>
            <p class="text-xs text-indigo-500 mt-2 font-medium text-center">{{ processedFiles }} de {{ totalFiles }} notas processadas</p>
        </div>

        <div v-if="hasError" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg relative z-20">
            <p class="text-xs text-red-600 font-bold text-center">{{ errorMessage }}</p>
        </div>
    </div>
</template>
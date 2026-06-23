<script setup>
import { ref } from 'vue';

const emit = defineEmits(['arquivos-selecionados']);
const isDragging = ref(false);
const fileInput = ref(null);

const triggerSelect = () => {
    fileInput.value.click();
};

const handleFiles = (files) => {
    if (files.length > 0) {
        emit('arquivos-selecionados', files);
    }
};

const onDrop = (e) => {
    isDragging.value = false;
    handleFiles(e.dataTransfer.files);
};

const onChange = (e) => {
    handleFiles(e.target.files);
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 h-full flex flex-col justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-800 mb-1">Processar Novo Lote</h3>
            <p class="text-xs text-gray-500 mb-4">Importe múltiplos arquivos CT-e de uma só vez para auditoria instantânea.</p>
        </div>
        
        <div 
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            @click="triggerSelect"
            :class="[
                'border-2 border-dashed rounded-xl p-6 flex flex-col items-center justify-center transition-all cursor-pointer min-h-[180px]',
                isDragging ? 'border-indigo-500 bg-indigo-50/50' : 'border-gray-200 bg-gray-50 hover:bg-gray-100/70'
            ]"
        >
            <svg class="w-10 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p class="text-sm font-semibold text-gray-700 text-center">Arraste os XMLs aqui</p>
            <p class="text-xs text-gray-400 mt-1 text-center">ou clique para navegar nas pastas</p>
            
            <input 
                ref="fileInput"
                type="file" 
                multiple 
                accept=".xml" 
                class="hidden" 
                @change="onChange"
            />
        </div>
        
        <div class="mt-4 bg-amber-50 rounded-lg p-3 border border-amber-100">
            <div class="flex gap-2">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-xs text-amber-800 font-medium">As cidades de destino serão normalizadas e validadas conforme as regras ativas no banco de dados.</p>
            </div>
        </div>
    </div>
</template>
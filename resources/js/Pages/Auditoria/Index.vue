<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import TermometroFinanceiro from '@/Components/TermometroFinanceiro.vue';
import TabelaDivergencias from '@/Components/TabelaDivergencias.vue';
import UploadXML from '@/Components/UploadXML.vue';
import ModalRaioX from '@/Components/ModalRaioX.vue';

// Recebendo os dados reais processados pelo Laravel
const props = defineProps({
    fretesProcessados: { type: Array, default: () => [] }
});

const isProcessing = ref(false);

const totais = computed(() => {
    let cobrado = 0;
    let correto = 0;
    let diferenca = 0;
    
    props.fretesProcessados.forEach(item => {
        // O parseFloat força o JS a tratar o texto que veio do banco como matemática pura
        cobrado += parseFloat(item.cobrado) || 0;
        correto += parseFloat(item.correto) || 0;
        diferenca += parseFloat(item.diferenca) || 0;
    });
    
    return { cobrado, correto, diferenca };
});

const itemSelecionado = ref(null);

const abrirRaioX = (item) => itemSelecionado.value = item;
const fecharRaioX = () => itemSelecionado.value = null;

// O mensageiro oficial do Inertia (muito mais seguro para arquivos)
const form = useForm({
    xml_files: []
});

const enviarArquivosParaServidor = (arquivos) => {
    if (!arquivos || arquivos.length === 0) return;
    
    isProcessing.value = true;
    form.xml_files = Array.from(arquivos);
    
    form.post('/auditoria/processar', {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            isProcessing.value = false;
        },
        onError: (erros) => {
            // Este alerta vai jogar na tela exatamente o motivo da recusa do servidor
            alert("O Servidor recusou o envio:\n\n" + Object.values(erros).join('\n'));
        }
    });
};
</script>

<template>
    <Head title="Auditoria de Fretes - 123fretei" />

    <div class="min-h-screen bg-gray-50/50 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
                <div>
                    <h1 class="text-3xl font-black text-gray-950 tracking-tight">Painel de Auditoria</h1>
                    <p class="text-sm text-gray-500 mt-1">Validação automatizada de faturas e identificação de quebras de custos logísticos.</p>
                </div>
                <div class="flex gap-3">
                    <button v-if="fretesProcessados.length > 0" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm transition text-sm">
                        Exportar Relatório Geral
                    </button>
                </div>
            </div>

            <TermometroFinanceiro 
                v-if="fretesProcessados.length > 0"
                :totalCobrado="totais.cobrado"
                :totalAuditado="totais.correto"
                :totalDiferenca="totais.diferenca"
            />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <div class="lg:col-span-2">
                    <TabelaDivergencias :itens="fretesProcessados" @detalhar="abrirRaioX" />
                </div>
                <div class="lg:col-span-1 relative">
                    <div v-if="isProcessing" class="absolute inset-0 z-10 bg-white/80 backdrop-blur-sm rounded-xl flex flex-col items-center justify-center">
                        <svg class="animate-spin h-8 w-8 text-indigo-600 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-bold text-gray-700">Processando Notas...</span>
                    </div>
                    
                    <UploadXML @arquivos-selecionados="enviarArquivosParaServidor" />
                </div>
            </div>
        </div>
    </div>

    <ModalRaioX :item="itemSelecionado" @fechar="fecharRaioX" />
</template>
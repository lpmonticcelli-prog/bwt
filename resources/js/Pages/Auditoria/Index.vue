<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import TermometroFinanceiro from '@/Components/TermometroFinanceiro.vue';
import TabelaDivergencias from '@/Components/TabelaDivergencias.vue';
import UploadXML from '@/Components/UploadXML.vue';
import ModalRaioX from '@/Components/ModalRaioX.vue';

// Recebendo os dados reais processados pelo Laravel
const props = defineProps({
    fretesProcessados: { type: Array, default: () => [] }
});

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
</script>

<template>
    <Head title="Auditoria de Fretes - 123fretei" />

    <div class="min-h-screen bg-gray-50/50 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-4">
                <Link href="/dashboard" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-indigo-600 transition-colors group">
                    <svg class="w-4 h-4 mr-1 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Voltar para o Dashboard
                </Link>
            </div>
            
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
                    <UploadXML />
                </div>
            </div>
        </div>
    </div>

    <ModalRaioX :item="itemSelecionado" @fechar="fecharRaioX" />
</template>
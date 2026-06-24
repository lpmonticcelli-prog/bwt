<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UploadXML from '@/Components/UploadXML.vue';

const props = defineProps({
    fechamento: { type: Object, required: true }
});

const formatarData = (dataStr) => {
    if (!dataStr) return '-';
    return new Date(dataStr + 'T00:00:00').toLocaleDateString('pt-BR');
};
</script>

<template>
    <Head :title="`Fechamento: ${fechamento.titulo} - ioapps`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/fechamentos" class="text-slate-400 hover:text-indigo-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </Link>
                <h2 class="font-bold text-xl text-slate-800 leading-tight">Área de Lançamento</h2>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-l-4 border-l-slate-800">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <span v-if="fechamento.status === 'aberto'" class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-1 rounded font-bold uppercase tracking-wider">Em Aberto</span>
                            <h3 class="text-2xl font-black text-slate-800">{{ fechamento.titulo }}</h3>
                        </div>
                        <p class="text-sm text-slate-500 font-medium">Período de processamento: {{ formatarData(fechamento.data_inicio) }} até {{ formatarData(fechamento.data_fim) }}</p>
                    </div>

                    <div class="flex gap-4 items-center">
                        <div class="text-right hidden sm:block border-r pr-4 border-slate-200">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Previsão Financeira</p>
                            <p class="text-sm font-black text-indigo-600">{{ formatarData(fechamento.data_vencimento) }}</p>
                        </div>
                        <Link :href="`/dashboard?fechamento_id=${fechamento.id}`" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm transition text-sm flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            Ver Dashboard do Período
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-orange-50 border-b border-orange-100 p-4">
                            <div class="flex items-center gap-2 text-orange-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                                <h4 class="font-bold uppercase tracking-wider text-sm">Passo 1: Custos E4LOG</h4>
                            </div>
                        </div>
                        <div class="p-6">
                            <UploadXML 
                                endpoint="/auditoria/processar"
                                :fechamentoId="fechamento.id"
                                titulo="Inserir Fatura E4LOG"
                                descricao="Arraste os XMLs da transportadora referentes a este período para calcular os custos e divergências."
                            />
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-blue-50 border-b border-blue-100 p-4">
                            <div class="flex items-center gap-2 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <h4 class="font-bold uppercase tracking-wider text-sm">Passo 2: Receita Sol Fácil</h4>
                            </div>
                        </div>
                        <div class="p-6">
                            <UploadXML 
                                endpoint="/faturamento/processar"
                                :fechamentoId="fechamento.id"
                                titulo="Inserir CT-es BWT"
                                descricao="Arraste os XMLs faturados para a Sol Fácil. O sistema fará a ligação automática com a tabela E4LOG."
                            />
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
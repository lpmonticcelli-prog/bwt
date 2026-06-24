<script setup>
import { Head, Link } from '@inertiajs/vue3';
// Se você usar um layout padrão do Breeze, importe-o aqui. Exemplo:
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    resumoFaturamento: { type: Object, default: () => ({ total_notas: 0, receita_total: 0, lucro_total: 0 }) },
    resumoAuditoria: { type: Object, default: () => ({ total_notas: 0, custo_cobrado: 0, diferenca_total: 0 }) }
});

const formatMoney = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
};
</script>

<template>
    <Head title="Dashboard - 123fretei" />

    <div class="min-h-screen bg-slate-50 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-8">
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Visão Geral</h1>
                <p class="text-sm text-slate-500 mt-1">Acompanhe a saúde financeira e logística da sua operação.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 bg-blue-50 text-blue-500 p-2 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Receita Total BWT</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">{{ formatMoney(resumoFaturamento.receita_total) }}</h3>
                    <p class="text-xs text-slate-500 mt-1 font-medium">{{ resumoFaturamento.total_notas }} notas faturadas</p>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4" :class="resumoFaturamento.lucro_total >= 0 ? 'bg-emerald-50 text-emerald-500' : 'bg-red-50 text-red-500'" style="padding: 0.5rem; border-radius: 0.5rem;">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Lucro Líquido Global</p>
                    <h3 class="text-2xl font-black mt-2" :class="resumoFaturamento.lucro_total >= 0 ? 'text-emerald-600' : 'text-red-600'">
                        {{ formatMoney(resumoFaturamento.lucro_total) }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 font-medium">Após desconto E4LOG</p>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 bg-orange-50 text-orange-500 p-2 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Custo E4LOG Cobrado</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-2">{{ formatMoney(resumoAuditoria.custo_cobrado) }}</h3>
                    <p class="text-xs text-slate-500 mt-1 font-medium">{{ resumoAuditoria.total_notas }} faturas recebidas</p>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 mt-4 mr-4 bg-red-50 text-red-500 p-2 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Divergências Encontradas</p>
                    <h3 class="text-2xl font-black text-red-600 mt-2">{{ formatMoney(resumoAuditoria.diferenca_total) }}</h3>
                    <p class="text-xs text-slate-500 mt-1 font-medium">Cobranças indevidas</p>
                </div>
            </div>

            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-800">Módulos do Sistema</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <Link href="/faturamento" class="block group">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all p-6 h-full border-l-4 border-l-blue-500 hover:border-l-blue-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-black text-slate-800 group-hover:text-blue-600 transition-colors">Painel de Rentabilidade</h3>
                                <p class="text-sm text-slate-500 mt-2">Analise o lucro real da operação comparando os CT-es emitidos pela BWT para a Sol Fácil com os custos fixos da E4LOG.</p>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-full group-hover:bg-blue-50 transition-colors">
                                <svg class="w-8 h-8 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center text-sm font-bold text-blue-600">
                            Acessar Módulo
                            <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </div>
                    </div>
                </Link>

                <Link href="/auditoria" class="block group">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all p-6 h-full border-l-4 border-l-indigo-500 hover:border-l-indigo-600">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xl font-black text-slate-800 group-hover:text-indigo-600 transition-colors">Painel de Auditoria</h3>
                                <p class="text-sm text-slate-500 mt-2">Valide as faturas recebidas da E4LOG contra a sua tabela comercial. Encontre divergências, TDEs mal aplicadas e ruralidades.</p>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-full group-hover:bg-indigo-50 transition-colors">
                                <svg class="w-8 h-8 text-slate-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center text-sm font-bold text-indigo-600">
                            Acessar Módulo
                            <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </div>
                    </div>
                </Link>

            </div>
        </div>
    </div>
</template>
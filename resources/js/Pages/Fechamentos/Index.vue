<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';

const props = defineProps({
    fechamentos: { type: Array, default: () => [] }
});

const mostrarFormulario = ref(false);

const form = useForm({
    titulo: '',
    data_inicio: '',
    data_fim: ''
});

const criarFechamento = () => {
    form.post('/fechamentos', {
        onSuccess: () => {
            mostrarFormulario.value = false;
            form.reset();
        }
    });
};

const formatarData = (dataStr) => {
    if (!dataStr) return '-';
    // Adiciona o timezone zero para evitar que o JS atrase um dia no Brasil
    return new Date(dataStr + 'T00:00:00').toLocaleDateString('pt-BR');
};

// NOVO: Lógica de Sincronização por Card
const syncingId = ref(null);

const sincronizarBsoft = async (fechamentoId) => {
    syncingId.value = fechamentoId;
    try {
        const response = await axios.post(`/fechamentos/${fechamentoId}/sincronizar`);
        alert(response.data.message); // Avisa ao usuário quantas baixas foram feitas
        router.reload(); // Atualiza a tela discretamente
    } catch (error) {
        alert("Erro de conexão com a API da Bsoft. Verifique suas credenciais.");
    } finally {
        syncingId.value = null;
    }
};
</script>

<template>
    <Head title="Fechamentos - ioapps" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-xl text-slate-800 leading-tight">Gestão de Fechamentos</h2>
                <button @click="mostrarFormulario = !mostrarFormulario" class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold py-2 px-4 rounded-lg shadow-sm transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Novo Fechamento
                </button>
            </div>
        </template>

        <div class="py-10 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div v-if="mostrarFormulario" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8 border-t-4 border-t-indigo-500 animate-fade-in-down">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-widest mb-4">Abertura de Competência</h3>
                    
                    <form @submit.prevent="criarFechamento" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Título do Fechamento</label>
                            <input v-model="form.titulo" type="text" placeholder="Ex: Janeiro - 1ª Quinzena" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Data Inicial</label>
                            <input v-model="form.data_inicio" type="date" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Data Final</label>
                            <input v-model="form.data_fim" type="date" class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        </div>

                        <div class="md:col-span-4 flex justify-end gap-3 mt-2">
                            <button type="button" @click="mostrarFormulario = false" class="px-4 py-2 text-sm font-bold text-slate-500 hover:text-slate-700 transition">Cancelar</button>
                            <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-bold shadow-sm transition disabled:opacity-50">
                                {{ form.processing ? 'Salvando...' : 'Criar e Calcular Vencimento' }}
                            </button>
                        </div>
                    </form>
                </div>

                <div v-if="fechamentos.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="fechamento in fechamentos" :key="fechamento.id" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-shadow group flex flex-col">
                        <div class="p-6 flex-grow">
                            <div class="flex justify-between items-start mb-4">
                                <span v-if="fechamento.status === 'aberto'" class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-1 rounded font-bold uppercase tracking-wider">Em Aberto</span>
                                <span v-else class="bg-slate-200 text-slate-600 text-[10px] px-2 py-1 rounded font-bold uppercase tracking-wider">Fechado</span>
                                
                                <span class="text-xs font-bold text-slate-400">ID: #{{ fechamento.id }}</span>
                            </div>
                            
                            <h3 class="text-xl font-black text-slate-800 mb-1">{{ fechamento.titulo }}</h3>
                            <p class="text-sm text-slate-500 font-medium mb-4">
                                {{ formatarData(fechamento.data_inicio) }} até {{ formatarData(fechamento.data_fim) }}
                            </p>

                            <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 mb-4">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Previsão de Vencimento (+30 dias)</p>
                                <p class="text-base font-bold text-indigo-600">{{ formatarData(fechamento.data_vencimento) }}</p>
                            </div>

                            <div class="flex gap-4 text-sm text-slate-500 font-medium">
                                <div class="flex items-center gap-1" title="Notas BWT Faturadas">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                    {{ fechamento.faturamentos_count }} BWT
                                </div>
                                <div class="flex items-center gap-1" title="Notas E4LOG Auditadas">
                                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
                                    {{ fechamento.fretes_count }} E4LOG
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 border-t border-slate-200 p-4 flex flex-col gap-2">
                            <button @click="sincronizarBsoft(fechamento.id)" :disabled="syncingId === fechamento.id" class="w-full flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 font-bold py-2 rounded-lg text-xs transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg v-if="syncingId === fechamento.id" class="animate-spin w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                {{ syncingId === fechamento.id ? 'Buscando Baixas...' : 'Sincronizar Baixas (Bsoft)' }}
                            </button>

                            <Link :href="`/fechamentos/${fechamento.id}`" class="block w-full text-center bg-white border border-slate-300 hover:border-indigo-500 text-slate-700 hover:text-indigo-600 font-bold py-2 rounded-lg text-sm transition-colors">
                                Abrir Painel de Lançamentos
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-white rounded-xl border border-dashed border-slate-300 p-16 text-center">
                    <div class="mx-auto w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-lg font-black text-slate-800">Nenhum fechamento registrado</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">Comece abrindo uma nova competência (quinzenal ou mensal) para organizar os uploads de faturamento e auditoria.</p>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style>
.animate-fade-in-down {
    animation: fadeInDown 0.3s ease-out;
}
@keyframes fadeInDown {
    0% { opacity: 0; transform: translateY(-10px); }
    100% { opacity: 1; transform: translateY(0); }
}
</style>
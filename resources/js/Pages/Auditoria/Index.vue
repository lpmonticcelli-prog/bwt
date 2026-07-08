<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue'; 

// Recebendo os dados REAIS do banco de dados (enviados pelo AuditController)
const props = defineProps({
    resumo: { type: Object, required: true },
    heatmapData: { type: Array, required: true }
});

const formatarMoeda = (valor) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);

// ==========================================
// SIMULADOR WAR ROOM: Inteligência Real
// ==========================================

// O lucro base agora é o lucro real extraído do seu banco de dados
const lucroBaseAtual = computed(() => props.resumo.lucro_atual || 0);

const sim = ref({
    icms: 12.0,
    e4log_excedente: 3.0,
    solfacil_excedente: 4.0,
    tde_bwt_fixo: 200,
});

// A mágica acontece aqui: Calculamos o impacto usando 1% do Faturamento ou 1% do Volume Real das Notas Fiscais
const impactoICMS = computed(() => (12.0 - sim.value.icms) * (props.resumo.faturamento_total / 100)); 
const impactoE4log = computed(() => (3.0 - sim.value.e4log_excedente) * (props.resumo.valor_nf_total / 100));
const impactoSolfacil = computed(() => (sim.value.solfacil_excedente - 4.0) * (props.resumo.valor_nf_total / 100));
const impactoTDE = computed(() => (sim.value.tde_bwt_fixo - 200) * props.resumo.qtd_notas);

// Lucro Líquido Projetado
const lucroProjetado = computed(() => {
    return lucroBaseAtual.value + impactoICMS.value + impactoE4log.value + impactoSolfacil.value + impactoTDE.value;
});

const variacaoSimulacao = computed(() => lucroProjetado.value - lucroBaseAtual.value);

const exportarPDF = () => window.print();
</script>

<template>
    <Head title="War Room & Inteligência - BWT" />

    <ErpLayout>
        
        <template #header-title>War Room & Inteligência Geográfica</template>
        <template #header-subtitle>Mapa de rentabilidade e teste de estresse de contratos em tempo real.</template>
        
        <template #header-actions>
            <div class="flex items-center gap-4 no-print">
                <Link href="/auditoria/regras" class="bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-bold py-2.5 px-4 rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Regras Base
                </Link>

                <button @click="exportarPDF" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Exportar Relatório
                </button>
            </div>
        </template>

        <div class="space-y-6 print-container mt-4">
            
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center bg-slate-50 gap-4">
                    <div>
                        <h3 class="font-black text-slate-800 text-xl flex items-center gap-2">
                            <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                            Mapa de Calor Geográfico
                        </h3>
                        <p class="text-xs text-slate-500 font-medium mt-1">
                            Análise baseada em <strong>{{ props.resumo.qtd_notas }} notas</strong> importadas.
                        </p>
                    </div>
                    <div class="flex gap-4 text-xs font-bold">
                        <span class="flex items-center gap-1 text-slate-500"><span class="w-3 h-3 rounded-full bg-emerald-400"></span> Alta Margem</span>
                        <span class="flex items-center gap-1 text-slate-500"><span class="w-3 h-3 rounded-full bg-yellow-400"></span> Margem Baixa</span>
                        <span class="flex items-center gap-1 text-slate-500"><span class="w-3 h-3 rounded-full bg-red-400"></span> Prejuízo Operacional</span>
                    </div>
                </div>

                <div class="p-6 bg-slate-50/30">
                    <div v-if="props.heatmapData.length === 0" class="text-center py-10">
                        <p class="text-slate-400 font-bold">Nenhum dado importado no laboratório.</p>
                        <p class="text-xs text-slate-400 mt-1">Utilize a tela de Lançamentos para subir arquivos para a área de teste.</p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                        <div v-for="geo in heatmapData" :key="geo.cidade"
                             class="rounded-2xl p-5 border transition-all hover:scale-[1.02] cursor-default"
                             :class="{
                                'bg-red-50 border-red-200 shadow-[0_4px_15px_rgba(239,68,68,0.15)]': geo.status === 'perigo',
                                'bg-yellow-50 border-yellow-200 shadow-[0_4px_15px_rgba(234,179,8,0.1)]': geo.status === 'alerta',
                                'bg-emerald-50 border-emerald-200 shadow-[0_4px_15px_rgba(16,185,129,0.1)]': geo.status === 'lucro'
                             }">
                             <h4 class="font-black text-slate-800 text-sm mb-4 border-b pb-2 flex items-center justify-between"
                                 :class="geo.status === 'perigo' ? 'border-red-200' : (geo.status === 'lucro' ? 'border-emerald-200' : 'border-yellow-200')">
                                 <span class="truncate pr-2">📍 {{ geo.cidade }}</span>
                                 <span class="text-xs px-2 py-0.5 rounded-full"
                                       :class="geo.status === 'perigo' ? 'bg-red-200 text-red-700' : (geo.status === 'lucro' ? 'bg-emerald-200 text-emerald-700' : 'bg-yellow-200 text-yellow-700')">
                                     {{ geo.notas }} nfs
                                 </span>
                             </h4>
                             
                             <div class="space-y-2">
                                 <div class="flex justify-between items-center">
                                     <span class="text-[10px] font-bold text-slate-500 uppercase">Receita Sol Fácil</span>
                                     <span class="text-xs font-black text-slate-700">{{ formatarMoeda(geo.faturamento) }}</span>
                                 </div>
                                 <div class="flex justify-between items-center">
                                     <span class="text-[10px] font-bold text-slate-500 uppercase">Custo E4LOG</span>
                                     <span class="text-xs font-black text-slate-700">{{ formatarMoeda(geo.custo) }}</span>
                                 </div>
                             </div>
                             
                             <div class="flex justify-between items-center mt-4 pt-3 border-t"
                                  :class="geo.status === 'perigo' ? 'border-red-200' : (geo.status === 'lucro' ? 'border-emerald-200' : 'border-yellow-200')">
                                 <span class="text-[10px] font-black uppercase tracking-wider"
                                       :class="geo.status === 'perigo' ? 'text-red-600' : (geo.status === 'lucro' ? 'text-emerald-600' : 'text-yellow-600')">
                                     Lucro Líquido
                                 </span>
                                 <span class="text-sm font-black"
                                       :class="geo.status === 'perigo' ? 'text-red-600' : (geo.status === 'lucro' ? 'text-emerald-600' : 'text-yellow-600')">
                                     {{ formatarMoeda(geo.faturamento - geo.custo) }}
                                 </span>
                             </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 rounded-3xl shadow-xl border border-slate-800 overflow-hidden no-print flex flex-col lg:flex-row relative">
                <div class="absolute -right-32 -bottom-32 w-96 h-96 bg-blue-600 rounded-full blur-[100px] opacity-20 pointer-events-none"></div>

                <div class="w-full lg:w-3/5 p-8 lg:p-10 border-b lg:border-b-0 lg:border-r border-slate-800 z-10">
                    <h3 class="text-2xl font-black text-white flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                        Simulador War Room
                    </h3>
                    <p class="text-slate-400 text-sm mb-10">Deslize as taxas para projetar o impacto financeiro de uma renegociação em cima do volume real de carga importado (Base de R$ {{ formatarMoeda(props.resumo.valor_nf_total) }}).</p>

                    <div class="space-y-8">
                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-xs font-bold text-slate-300 uppercase tracking-widest">Alíquota de ICMS (%)</label>
                                <span class="text-white font-black">{{ sim.icms }}%</span>
                            </div>
                            <input type="range" v-model="sim.icms" min="0" max="25" step="0.5" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-xs font-bold text-orange-300 uppercase tracking-widest">Custo - Excedente Pago E4LOG (%)</label>
                                <span class="text-orange-400 font-black">{{ sim.e4log_excedente }}%</span>
                            </div>
                            <input type="range" v-model="sim.e4log_excedente" min="1" max="10" step="0.5" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-orange-500">
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-xs font-bold text-emerald-300 uppercase tracking-widest">Receita - Excedente Cobrado Sol Fácil (%)</label>
                                <span class="text-emerald-400 font-black">{{ sim.solfacil_excedente }}%</span>
                            </div>
                            <input type="range" v-model="sim.solfacil_excedente" min="1" max="15" step="0.5" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-emerald-500">
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <label class="text-xs font-bold text-blue-300 uppercase tracking-widest">TDE - Mínimo Fixo Faturado (R$)</label>
                                <span class="text-blue-400 font-black">R$ {{ sim.tde_bwt_fixo }},00</span>
                            </div>
                            <input type="range" v-model="sim.tde_bwt_fixo" min="100" max="400" step="10" class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                        </div>
                    </div>
                </div>

                <div class="w-full lg:w-2/5 p-8 lg:p-10 flex flex-col justify-center bg-slate-800/30 z-10">
                    <p class="text-slate-400 font-bold text-sm uppercase tracking-widest mb-1 text-center">Lucro Líquido Projetado</p>
                    <p class="text-5xl lg:text-6xl font-black text-white text-center mb-8 drop-shadow-[0_0_10px_rgba(255,255,255,0.2)]">
                        {{ formatarMoeda(lucroProjetado) }}
                    </p>

                    <div class="bg-slate-900 rounded-2xl p-5 border border-slate-700/50">
                        <p class="text-[10px] text-slate-500 font-black uppercase text-center mb-4">Impacto da Simulação vs Realidade</p>
                        
                        <div class="flex items-center justify-center gap-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center font-black text-xl"
                                 :class="variacaoSimulacao >= 0 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                                {{ variacaoSimulacao >= 0 ? '↑' : '↓' }}
                            </div>
                            <div>
                                <p class="text-2xl font-black" :class="variacaoSimulacao >= 0 ? 'text-emerald-400' : 'text-red-400'">
                                    {{ variacaoSimulacao >= 0 ? '+' : '' }}{{ formatarMoeda(variacaoSimulacao) }}
                                </p>
                                <p class="text-xs text-slate-400 font-medium mt-1">
                                    {{ variacaoSimulacao >= 0 ? 'Aumento na rentabilidade projetada' : 'Prejuízo na renegociação' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </ErpLayout>
</template>

<style>
input[type=range]::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 20px;
  width: 20px;
  border-radius: 50%;
  background: currentColor;
  cursor: pointer;
  box-shadow: 0 0 10px rgba(0,0,0,0.5);
}

@media print {
    body { background-color: white !important; }
    .no-print { display: none !important; }
    aside, header { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
    .print-container { width: 100% !important; max-width: 100% !important; display: block !important; }
}
</style>
<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue'; 

// Auxiliar de formatação de moeda
const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor || 0);
};

// =========================================================================
// PARÂMETROS DA OPERAÇÃO (Variáveis Reativas Modificáveis)
// =========================================================================
const sim = ref({
    // Abas de Financiamento / War Room
    valor_emprestimo: 1000000, // Ajustado para o cenário de 1 Milhão
    taxa_juros_mes: 3.0,      
    prazo_meses: 12,          

    // Indicadores da Carreta e Coleta
    kits_por_carreta: 30,
    custo_carreta_avista: 22000,
    custo_coleta_kit: 78.50,

    // Receitas e Parcerias em Destino
    valor_frete_xml: 1662.00, // Preço médio cobrado
    receita_solfacil: 1662.00, 
    
    // Alíquotas e Taxas
    icms_percentual: 7.0, // ICMS
    outros_impostos: 6.0, // PIS/COFINS/IR
    comissao_parceiro_pct: 30.0, // Parceiro Recife

    // Volume Operacional
    volume_mensal_kits: 1320,
    meta_lucro_desejada_pct: 15.0 
});

// =========================================================================
// INTELIGÊNCIA MATEMÁTICA DO SIMULADOR (Calculados automaticamente)
// =========================================================================

const parcelaMensalBanco = computed(() => {
    const P = sim.value.valor_emprestimo;
    const i = sim.value.taxa_juros_mes / 100;
    const n = sim.value.prazo_meses;
    if (i === 0) return P / n;
    return (P * i * Math.pow(1 + i, n)) / (Math.pow(1 + i, n) - 1);
});

const custoTransferenciaPorKit = computed(() => sim.value.custo_carreta_avista / sim.value.kits_por_carreta);
const impostoPorKit = computed(() => sim.value.receita_solfacil * ((sim.value.icms_percentual + sim.value.outros_impostos) / 100)); 
const comissaoParceiroPorKit = computed(() => sim.value.receita_solfacil * (sim.value.comissao_parceiro_pct / 100)); 

const custoTotalPorKit = computed(() => {
    return custoTransferenciaPorKit.value + impostoPorKit.value + comissaoParceiroPorKit.value + sim.value.custo_coleta_kit;
});

const lucroPorKit = computed(() => sim.value.receita_solfacil - custoTotalPorKit.value);
const lucroOperacionalMensal = computed(() => lucroPorKit.value * sim.value.volume_mensal_kits);
const margemLucroRealPct = computed(() => (lucroPorKit.value / sim.value.receita_solfacil) * 100);

const mesesParaQuitarDivida = computed(() => {
    if (lucroOperacionalMensal.value <= 0) return 999;
    let saldoDevedor = sim.value.valor_emprestimo;
    let meses = 0;
    const taxa = sim.value.taxa_juros_mes / 100;

    while (saldoDevedor > 0 && meses < 120) {
        saldoDevedor = (saldoDevedor * (1 + taxa)) - lucroOperacionalMensal.value;
        meses++;
    }
    return meses;
});

const kitsPorDiaParaMetaLucro = computed(() => {
    if (lucroPorKit.value <= 0) return 0;
    const volumeMensalNecessario = (sim.value.valor_emprestimo * (sim.value.meta_lucro_desejada_pct/100)) / lucroPorKit.value;
    return Math.ceil(volumeMensalNecessario / 22); // 22 dias úteis
});

const pontoEquilibrioMensalKits = computed(() => {
    if (lucroPorKit.value <= 0) return 0;
    return Math.ceil(parcelaMensalBanco.value / lucroPorKit.value);
});

const exportarPDF = () => window.print();
</script>

<template>
    <Head title="Simulador de Contratos - BWT" />

    <ErpLayout>
        
        <template #header-title>Simulador de Contratos & Viabilidade Logística</template>
        <template #header-subtitle>Análise preditiva de margens, amortização de capital de giro e estresse operacional para Recife.</template>
        
        <template #header-actions>
            <div class="flex items-center gap-4 no-print">
                <button @click="exportarPDF" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Imprimir Relatório
                </button>
            </div>
        </template>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6 print-container">
            
            <div class="space-y-6 no-print">
                <div class="bg-slate-900 rounded-3xl shadow-xl border border-slate-800 p-6 space-y-6 text-white relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-24 h-24 bg-blue-500 rounded-full blur-2xl opacity-10"></div>
                    
                    <div>
                        <h3 class="font-black text-lg flex items-center gap-2 tracking-tight">
                            <span class="w-1.5 h-5 bg-blue-500 rounded-full"></span>
                            Engenharia do Contrato
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">Ajuste os valores da sua operação para estressar a margem.</p>
                    </div>

                    <div class="space-y-4 pt-2 border-t border-slate-800">
                        <h4 class="text-xs font-black text-blue-400 uppercase tracking-widest">Aporte Financeiro Inicial</h4>
                        <div>
                            <div class="flex justify-between text-xs text-slate-300 mb-1 font-medium">
                                <span>Capital Solicitado</span>
                                <span class="font-bold text-white">{{ formatarMoeda(sim.valor_emprestimo) }}</span>
                            </div>
                            <input type="range" v-model="sim.valor_emprestimo" min="100000" max="2000000" step="50000" class="w-full h-1.5 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Juros ao Mês (%)</label>
                                <input type="number" v-model="sim.taxa_juros_mes" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-blue-500 focus:ring-0" step="0.1">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Prazo (Meses)</label>
                                <input type="number" v-model="sim.prazo_meses" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-blue-500 focus:ring-0">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-800">
                        <h4 class="text-xs font-black text-orange-400 uppercase tracking-widest">Custos e Transbordo</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Frete Carreta (R$)</label>
                                <input type="number" v-model="sim.custo_carreta_avista" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-orange-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kits p/ Carreta</label>
                                <input type="number" v-model="sim.kits_por_carreta" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-orange-500 focus:ring-0">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Comissão Destino (%)</label>
                            <input type="number" v-model="sim.comissao_parceiro_pct" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-orange-500 focus:ring-0" step="0.5">
                        </div>
                        <div>
                            <div class="flex justify-between text-xs text-slate-300 mb-1 font-medium">
                                <span>Capacidade de Escoamento / Mês</span>
                                <span class="font-bold text-white">{{ sim.volume_mensal_kits }} kits</span>
                            </div>
                            <input type="range" v-model="sim.volume_mensal_kits" min="50" max="2000" step="10" class="w-full h-1.5 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-orange-500">
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-800">
                        <h4 class="text-xs font-black text-emerald-400 uppercase tracking-widest">Faturamento e Tributos</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Frete Médio (R$)</label>
                                <input type="number" v-model="sim.receita_solfacil" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-emerald-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Impostos Totais (%)</label>
                                <input type="number" :value="sim.icms_percentual + sim.outros_impostos" @input="sim.icms_percentual = $event.target.value - sim.outros_impostos" class="w-full bg-slate-800 border-slate-700 rounded-xl text-sm font-bold text-white focus:border-emerald-500 focus:ring-0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200 flex flex-col justify-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Parcela Fixa (Banco)</span>
                        <span class="text-2xl font-black text-slate-800">{{ formatarMoeda(parcelaMensalBanco) }}</span>
                        <span class="text-[11px] text-slate-400 font-medium mt-1">Calculado via Amortização Price</span>
                    </div>

                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200 flex flex-col justify-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Lucro Operacional Líquido</span>
                        <span class="text-2xl font-black text-emerald-600">{{ formatarMoeda(lucroOperacionalMensal) }}</span>
                        <span class="text-[11px] text-slate-400 font-medium mt-1">Geração de caixa mensal da linha</span>
                    </div>

                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200 flex flex-col justify-center"
                         :class="margemLucroRealPct >= sim.meta_lucro_desejada_pct ? 'bg-emerald-50/20 border-emerald-200' : 'bg-amber-50/20 border-amber-200'">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Margem Real por Kit</span>
                        <span class="text-2xl font-black" :class="margemLucroRealPct >= sim.meta_lucro_desejada_pct ? 'text-emerald-600' : 'text-amber-600'">
                            {{ margemLucroRealPct.toFixed(2) }}%
                        </span>
                        <span class="text-[11px] text-slate-400 font-medium mt-1">Meta desejada de {{ sim.meta_lucro_desejada_pct }}%</span>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 space-y-6">
                    <div>
                        <h4 class="font-black text-slate-800 text-lg">Composição Analítica por Kit Solar</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Entenda para onde vai cada real faturado na transferência Campinas ➔ Recife.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <span class="text-xs font-bold text-slate-600">Faturamento Bruto por Kit</span>
                                <span class="text-sm font-black text-slate-800">{{ formatarMoeda(sim.receita_solfacil) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 text-xs text-slate-500 font-medium pl-6">
                                <span>(-) Impostos Deduzidos ({{ sim.icms_percentual + sim.outros_impostos }}%)</span>
                                <span class="text-red-500 font-bold">- {{ formatarMoeda(impostoPorKit) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 text-xs text-slate-500 font-medium pl-6">
                                <span>(-) Comissão do Parceiro Destino ({{ sim.comissao_parceiro_pct }}%)</span>
                                <span class="text-red-500 font-bold">- {{ formatarMoeda(comissaoParceiroPorKit) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 text-xs text-slate-500 font-medium pl-6">
                                <span>(-) Custo de Carreta Carregada</span>
                                <span class="text-red-500 font-bold">- {{ formatarMoeda(custoTransferenciaPorKit) }}</span>
                            </div>
                            <div class="flex justify-between items-center p-3 text-xs text-slate-500 font-medium pl-6">
                                <span>(-) Custo Operacional de Coleta</span>
                                <span class="text-red-500 font-bold">- {{ formatarMoeda(sim.custo_coleta_kit) }}</span>
                            </div>
                            <div class="flex justify-between items-center bg-emerald-50 p-3 rounded-xl border border-emerald-100 mt-2">
                                <span class="text-xs font-bold text-emerald-800">Lucro Líquido por Kit</span>
                                <span class="text-sm font-black text-emerald-600">{{ formatarMoeda(lucroPorKit) }}</span>
                            </div>
                        </div>

                        <div class="bg-slate-900 rounded-2xl p-6 text-white flex flex-col justify-between relative overflow-hidden">
                            <div class="absolute -right-16 -bottom-16 w-44 h-44 bg-emerald-500 rounded-full blur-3xl opacity-10"></div>
                            
                            <div class="space-y-4">
                                <h5 class="text-xs font-black text-emerald-400 uppercase tracking-widest">Indicadores de Sustentabilidade</h5>
                                
                                <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                                    <span class="text-xs text-slate-400 font-medium">Ponto de Equilíbrio / Mês</span>
                                    <span class="text-sm font-black text-white">{{ pontoEquilibrioMensalKits }} Kits</span>
                                </div>

                                <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                                    <span class="text-xs text-slate-400 font-medium">Quitação Acelerada (Aporte Total)</span>
                                    <span class="text-sm font-black text-white">{{ mesesParaQuitarDivida > 120 ? '+ de 10 anos' : mesesParaQuitarDivida + ' Meses' }}</span>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400 font-medium">Kits p/ Dia p/ Meta {{ sim.meta_lucro_desejada_pct }}%</span>
                                    <span class="text-sm font-black text-amber-400">{{ kitsPorDiaParaMetaLucro }} Kits / dia</span>
                                </div>
                            </div>

                            <div class="mt-6 bg-slate-950 rounded-xl p-4 border border-slate-800 flex items-center justify-between"
                                 :class="lucroOperacionalMensal - parcelaMensalBanco < 0 ? 'border-red-500/50 bg-red-500/10' : ''">
                                <span class="text-xs text-slate-400 font-bold uppercase">Sobram no Caixa / Mês</span>
                                <span class="text-lg font-black" :class="lucroOperacionalMensal - parcelaMensalBanco < 0 ? 'text-red-400' : 'text-emerald-400'">
                                    {{ formatarMoeda(lucroOperacionalMensal - parcelaMensalBanco) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 text-blue-900 p-5 rounded-3xl text-sm flex gap-4">
                    <span class="text-2xl mt-0.5">💡</span>
                    <div class="space-y-1">
                        <h4 class="font-black text-blue-950">Nota Estratégica</h4>
                        <p class="text-xs text-blue-900 leading-relaxed font-medium">
                            Como todos os seus custos são proporcionais por unidade, o aumento de volume diário isolado não engorda a sua margem percentual. Para expandir de {{ margemLucroRealPct.toFixed(2) }}% para os {{ sim.meta_lucro_desejada_pct }}% desejados, mexa nos sliders ao lado testando reduções na comissão de destino, aumento na cubagem da carreta ou repasse no frete do cliente.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </ErpLayout>
</template>

<style scoped>
input[type=range]::-webkit-slider-thumb {
  -webkit-appearance: none;
  height: 16px;
  width: 16px;
  border-radius: 50%;
  background: #ffffff;
  cursor: pointer;
  box-shadow: 0 0 8px rgba(0, 0, 0, 0.5);
  transition: transform 0.1s;
}
input[type=range]::-webkit-slider-thumb:hover {
  transform: scale(1.2);
}

@media print {
    body { background-color: white !important; }
    .no-print { display: none !important; }
    aside, header { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
    .print-container { width: 100% !important; max-width: 100% !important; display: block !important; }
}
</style>
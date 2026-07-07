<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useAuditoria } from '@/Composables/useAuditoria';

const props = defineProps({
    viagem: { type: Object, required: true }
});

const emit = defineEmits(['fechar']);

// Importação das funções extraídas para a pasta Composables
const { formatMoney, formatarData, extrairNumeroNFe, extrairFimChave } = useFormatters();
const { badgeOperacao, explicarCalculoFrete, gerarRelatorioDedoDuro } = useAuditoria();

// Funções exclusivas deste modal para abrir links externos
const linkSefazCTe = (chave) => {
    if (!chave || chave === 'N/A') return '#';
    const c = String(chave).replace(/\D/g, '');
    return `https://nfe.fazenda.sp.gov.br/CTeConsulta/qrCode?chCTe=${c}&tpAmb=1`;
};

const consultarNFe = (chave) => {
    if (!chave || chave === 'N/A') return;
    const c = String(chave).replace(/\D/g, '');
    navigator.clipboard.writeText(c).then(() => {
        alert(`✅ CHAVE COPIADA: ${c}\n\nCole esta chave na página da SEFAZ que vai abrir agora para consultar a nota.`);
        window.open('https://www.nfe.fazenda.gov.br/portal/consultaResumo.aspx?tipoConsulta=resumo&tipoConteudo=d09RSxZq/aA=', '_blank');
    }).catch(() => {
        window.open('https://www.nfe.fazenda.gov.br/portal/consultaResumo.aspx?tipoConsulta=resumo&tipoConteudo=d09RSxZq/aA=', '_blank');
    });
};

// Fechar com a tecla ESC
const handleKeydown = (e) => { if (e.key === 'Escape') emit('fechar'); };
onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 font-sans">
        
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity" @click.self="emit('fechar')"></div>

        <div class="relative w-full max-w-7xl bg-slate-50 flex flex-col max-h-[95vh] rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-slate-700 overflow-hidden animate-fade-in-up">
            
            <div class="bg-indigo-950 px-8 py-6 flex justify-between items-center border-b-4 shrink-0 z-20" :class="viagem.status === 'casada' ? 'border-b-emerald-500' : 'border-b-rose-500'">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center shadow-inner" :class="viagem.status === 'casada' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'">
                        <svg v-if="viagem.status === 'casada'" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <svg v-else class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-indigo-300 uppercase tracking-[0.2em] block mb-1">Extrato de Auditoria DACTE Virtual</span>
                        <h3 class="text-2xl font-black text-white flex items-center gap-3">
                            NF-e {{ extrairNumeroNFe(viagem.nfe_chave) || 'Complemento' }} 
                            <span class="text-sm font-semibold text-indigo-200 bg-indigo-900 px-3 py-1 rounded-full border border-indigo-800">Destino: {{ viagem.destino }}</span>
                        </h3>
                    </div>
                </div>
                <button @click="emit('fechar')" class="text-indigo-300 hover:text-white bg-indigo-900 hover:bg-indigo-800 p-2.5 rounded-xl transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-8 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-300 flex flex-col gap-6 relative z-10">

                <div class="bg-slate-900 rounded-3xl p-6 shadow-inner text-white flex flex-col md:flex-row items-center gap-6">
                    <div class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div>
                        <h4 class="text-blue-400 font-black text-[10px] uppercase tracking-[0.2em] mb-2">Como o sistema cruzou estes dados? (Rastreabilidade de DNA)</h4>
                        <p class="text-sm text-slate-300 leading-relaxed font-mono">
                            ⚓ <strong>Âncora de Busca:</strong> O sistema isolou a Nota Fiscal <strong class="text-white">NF-e {{ extrairNumeroNFe(viagem.nfe_chave) }}</strong>.<br>
                            🔍 <strong>Fase 1:</strong> Encontrou <strong>{{ viagem.bwt_detalhes ? viagem.bwt_detalhes.length : 0 }} CT-e(s)</strong> da BWT e <strong>{{ viagem.e4log_detalhes.length }} CT-e(s)</strong> da E4LOG contendo esta chave.<br>
                            🔗 <strong>Fase 2:</strong> Vasculhou a SEFAZ e anexou automaticamente as "Notas Órfãs" (Complementos de TDE, Reentregas) que apontavam para os CT-es principais.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-8">
                    <div class="flex-1 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-w-0">
                        <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                <h4 class="font-black text-slate-800 text-lg tracking-tight truncate">Receita BWT (Sol Fácil)</h4>
                            </div>
                            <span class="bg-white border border-slate-200 px-3 py-1 rounded-full text-[10px] font-bold text-slate-500 uppercase tracking-wider shadow-sm shrink-0 whitespace-nowrap">{{ viagem.bwt_detalhes ? viagem.bwt_detalhes.length : 0 }} CT-e(s)</span>
                        </div>

                        <div v-if="viagem.bwt_detalhes && viagem.bwt_detalhes.length > 0" class="p-6 flex-1 flex flex-col">
                            <div v-for="(fat, index) in viagem.bwt_detalhes" :key="index" class="mb-8 border border-slate-200 rounded-2xl overflow-hidden shadow-sm relative">
                                
                                <div class="bg-slate-100/50 border-b border-slate-200 px-4 py-3 flex justify-between items-center gap-2">
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nº do Documento XML <span class="text-slate-400 mx-1">•</span> Emissão: <span class="text-slate-600 font-bold">{{ formatarData(fat.data_emissao) }}</span></span>
                                        <span class="text-xs font-bold text-slate-700 truncate" :title="fat.arquivo">{{ fat.arquivo }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded border border-blue-200 bg-white text-blue-600 whitespace-nowrap">REGRA: {{ fat.regra }}</span>
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded whitespace-nowrap" :class="badgeOperacao(fat.tipo_operacao)">{{ fat.tipo_operacao || 'Entrega' }}</span>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <div class="bg-indigo-50/50 border border-indigo-100 rounded-lg p-3 mb-5 text-xs text-slate-600 font-mono space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-slate-400 whitespace-nowrap mr-2">CHAVE DO CT-E BWT:</span>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-indigo-700 truncate" :title="fat.cte_chave">{{ extrairFimChave(fat.cte_chave) }}</span>
                                                <a :href="linkSefazCTe(fat.cte_chave)" target="_blank" title="Consultar no Portal SEFAZ" class="bg-indigo-100 text-indigo-600 hover:bg-indigo-600 hover:text-white p-1 rounded transition-colors shrink-0">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center border-t border-indigo-100/50 pt-2">
                                            <span v-if="fat.chave_complementada" class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 CT-E PAI:</span>
                                            <span v-else class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 LASTRO (NF-E):</span>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-indigo-700 truncate" :title="fat.chave_complementada || fat.nfe_chave">{{ fat.chave_complementada ? extrairFimChave(fat.chave_complementada) : extrairFimChave(fat.nfe_chave) }}</span>
                                                <button v-if="!fat.chave_complementada" @click.prevent="consultarNFe(fat.nfe_chave)" title="Copiar Chave e Consultar NF-e na SEFAZ" class="bg-indigo-100 text-indigo-600 hover:bg-indigo-600 hover:text-white p-1 rounded transition-colors cursor-pointer shrink-0">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-5 bg-amber-50/50 border-l-4 border-amber-400 p-3 rounded-r-lg">
                                        <span class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1">Observações impressas no XML</span>
                                        <p class="text-xs text-slate-700 italic leading-relaxed">{{ fat.observacoes || 'Sem observações adicionais no documento.' }}</p>
                                    </div>

                                    <div class="bg-slate-100/50 rounded-xl p-4 font-mono text-xs border border-slate-200">
                                        <p class="font-bold text-slate-700 mb-3 border-b border-slate-200 pb-2">MATEMÁTICA DO FATURAMENTO (BWT)</p>
                                        <ul class="space-y-3 text-slate-600">
                                            <li class="flex justify-between items-start">
                                                <div>
                                                    <span class="block">1. Frete Base</span>
                                                    <span class="block text-[9px] font-bold text-blue-500 mt-0.5">{{ explicarCalculoFrete('BWT', fat.regra, fat.valor_carga, fat.tipo_operacao === 'Complemento' ? 0 : fat.receita_frete_base, fat.tipo_operacao) }}</span>
                                                </div>
                                                <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.tipo_operacao === 'Complemento' ? 0 : fat.receita_frete_base)}}</span>
                                            </li>
                                            <li class="flex justify-between items-center" v-if="fat.receita_tde > 0">
                                                <span>2. TDE (Taxa de Dificuldade) Cobrada</span>
                                                <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.receita_tde)}}</span>
                                            </li>
                                            <li class="flex justify-between items-center">
                                                <span>{{ fat.receita_tde > 0 ? '3.' : '2.' }} Projeção de Imposto (ICMS 12% Dentro)</span>
                                                <span class="font-bold shrink-0 ml-2">+ {{formatMoney(fat.receita_icms)}}</span>
                                            </li>
                                            <li class="flex justify-between border-t border-slate-300 pt-3 mt-1 text-blue-800">
                                                <span>= TOTAL LIDO NO ARQUIVO XML DA BWT</span>
                                                <span class="font-black text-sm shrink-0 ml-2">{{formatMoney(fat.receita_real)}}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="fat.gap_individual > 0" class="mt-4 p-3 bg-amber-100 text-amber-700 rounded-lg text-xs border border-amber-200">
                                        <strong class="font-black uppercase block mb-1">⚠️ Gap (Erro de Faturamento):</strong>
                                        De acordo com a tabela comercial, deveríamos ter cobrado {{formatMoney(fat.receita_teorica)}} neste serviço. Cobrámos {{formatMoney(fat.gap_individual)}} a menos do que a regra permite!
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-6 bg-slate-50">
                            <div class="w-12 h-12 bg-rose-100 text-rose-500 rounded-full flex items-center justify-center mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></div>
                            <h5 class="font-bold text-slate-700">CT-e de Faturamento Ausente</h5>
                            <p class="text-xs text-slate-500 mt-2">A BWT não faturou esta carga para a Sol Fácil neste lote.</p>
                        </div>

                        <div class="bg-slate-50 border-t border-slate-200 p-6 shrink-0 flex justify-between items-center mt-auto">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Soma Total Faturada BWT</p>
                                <p class="text-xs text-slate-500 font-medium">Soma de todos os CT-es acima</p>
                            </div>
                            <p class="text-3xl font-black text-blue-700 tracking-tight">{{ formatMoney(viagem.receita) }}</p>
                        </div>
                    </div>

                    <div class="flex-1 bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col min-w-0">
                        <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                                <h4 class="font-black text-slate-800 text-lg tracking-tight truncate">Custo E4LOG (Auditoria)</h4>
                            </div>
                            <span class="bg-white border border-slate-200 px-3 py-1 rounded-full text-[10px] font-bold text-slate-500 uppercase tracking-wider shadow-sm shrink-0 whitespace-nowrap">{{ viagem.e4log_detalhes.length }} CT-e(s)</span>
                        </div>

                        <div v-if="viagem.e4log_detalhes.length > 0" class="p-6 flex-1 flex flex-col">
                            <div v-for="(frete, index) in viagem.e4log_detalhes" :key="index" class="mb-8 border border-slate-200 rounded-2xl overflow-hidden shadow-sm relative">
                                
                                <div class="bg-slate-100/50 border-b border-slate-200 px-4 py-3 flex justify-between items-center gap-2" :class="frete.is_correto ? '' : 'bg-rose-50/50 border-rose-200'">
                                    <div class="flex flex-col min-w-0">
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Nº do Documento XML <span class="text-slate-400 mx-1">•</span> Emissão: <span class="text-slate-600 font-bold">{{ formatarData(frete.data_emissao) }}</span></span>
                                        <span class="text-xs font-bold text-slate-700 truncate" :title="frete.arquivo">{{ frete.arquivo }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded border border-orange-200 bg-white text-orange-600 whitespace-nowrap">REGRA: {{ frete.regra }}</span>
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-1 rounded whitespace-nowrap" :class="badgeOperacao(frete.tipo_operacao)">{{ frete.tipo_operacao || 'Entrega' }}</span>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <div class="bg-orange-50/50 border border-orange-100 rounded-lg p-3 mb-5 text-xs text-slate-600 font-mono space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-slate-400 whitespace-nowrap mr-2">CHAVE DO CT-E E4LOG:</span>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-orange-700 truncate" :title="frete.cte_chave">{{ extrairFimChave(frete.cte_chave) }}</span>
                                                <a :href="linkSefazCTe(frete.cte_chave)" target="_blank" title="Consultar no Portal SEFAZ" class="bg-orange-100 text-orange-600 hover:bg-orange-600 hover:text-white p-1 rounded transition-colors shrink-0">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="flex justify-between items-center border-t border-orange-100/50 pt-2">
                                            <span v-if="frete.chave_complementada" class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 CT-E PAI:</span>
                                            <span v-else class="font-bold text-slate-400 whitespace-nowrap mr-2">🔗 LASTRO (NF-E):</span>
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="font-bold text-orange-700 truncate" :title="frete.chave_complementada || frete.nfe_chave">{{ frete.chave_complementada ? extrairFimChave(frete.chave_complementada) : extrairFimChave(frete.nfe_chave) }}</span>
                                                <button v-if="!frete.chave_complementada" @click.prevent="consultarNFe(frete.nfe_chave)" title="Copiar Chave e Consultar NF-e na SEFAZ" class="bg-orange-100 text-orange-600 hover:bg-orange-600 hover:text-white p-1 rounded transition-colors cursor-pointer shrink-0">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-5 bg-amber-50/50 border-l-4 border-amber-400 p-3 rounded-r-lg">
                                        <span class="block text-[9px] font-black text-amber-600 uppercase tracking-widest mb-1">Observações impressas no XML</span>
                                        <p class="text-xs text-slate-700 italic leading-relaxed">{{ frete.observacoes || 'Sem observações adicionais no documento.' }}</p>
                                    </div>

                                    <div class="bg-slate-100/50 rounded-xl p-4 font-mono text-xs border border-slate-200">
                                        <p class="font-bold text-slate-700 mb-3 border-b border-slate-200 pb-2">MATEMÁTICA DA AUDITORIA (E4LOG)</p>
                                        <ul class="space-y-3 text-slate-600">
                                            <li class="flex justify-between items-start">
                                                <div>
                                                    <span class="block">1. Frete Base da Tabela</span>
                                                    <span class="block text-[9px] font-bold text-orange-500 mt-0.5">{{ explicarCalculoFrete('E4LOG', frete.regra, frete.valorNF, frete.tipo_operacao === 'Complemento' ? 0 : frete.freteBaseCalculado, frete.tipo_operacao) }}</span>
                                                </div>
                                                <span class="font-bold shrink-0 ml-2">+ {{formatMoney(frete.tipo_operacao === 'Complemento' ? 0 : frete.freteBaseCalculado)}}</span>
                                            </li>
                                            <li class="flex justify-between items-center">
                                                <span>2. Valor Complementar/Taxas {{ frete.temTde ? 'Aprovado' : 'Aprovado' }}</span>
                                                <span class="font-bold shrink-0 ml-2">+ {{formatMoney(frete.tipo_operacao === 'Complemento' ? frete.correto : frete.tdeCalculado)}}</span>
                                            </li>
                                            <li class="flex justify-between border-t border-slate-300 pt-3 mt-1 text-slate-800">
                                                <span>= TETO JUSTO APROVADO PELO SISTEMA</span>
                                                <span class="font-black shrink-0 ml-2">{{formatMoney(frete.correto)}}</span>
                                            </li>
                                            <li class="flex justify-between mt-2 pt-2 text-orange-700 border-t border-dashed border-orange-200">
                                                <span>VALOR LIDO NO ARQUIVO XML DA E4LOG</span>
                                                <span class="font-black text-sm shrink-0 ml-2">{{formatMoney(frete.cobrado)}}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div v-if="!frete.is_correto" class="mt-4 p-3 bg-red-100 text-red-700 rounded-lg text-xs border border-red-200">
                                        <strong class="font-black uppercase block mb-1">🚨 Auditoria Reprovada (Cobrança em Excesso):</strong>
                                        A E4LOG embutiu {{formatMoney(frete.diferenca)}} a mais neste CT-e de forma não autorizada pela regra. O pagamento deste extra fica bloqueado para averiguação.
                                    </div>
                                    <div v-else class="mt-4 p-3 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-bold border border-emerald-200">
                                        ✅ AUDITORIA APROVADA: O XML está exato.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex-1 flex flex-col items-center justify-center text-center p-6 bg-slate-50">
                            <div class="w-12 h-12 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></div>
                            <h5 class="font-bold text-slate-700">Custo Não Encontrado</h5>
                            <p class="text-xs text-slate-500 mt-2">A E4LOG ainda não enviou as cobranças referentes a esta viagem.</p>
                        </div>

                        <div class="bg-slate-50 border-t border-slate-200 p-6 shrink-0 flex justify-between items-center mt-auto">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Soma Total Custo E4LOG</p>
                                <p class="text-xs text-slate-500 font-medium">Soma de todos os CT-es e Compl.</p>
                            </div>
                            <p class="text-3xl font-black text-orange-600 tracking-tight">{{ formatMoney(viagem.custo) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm mt-4">
                    <div class="bg-slate-50 border-b border-slate-200 px-8 py-5 flex items-center gap-4">
                        <div class="w-10 h-10 bg-slate-800 text-white rounded-full flex items-center justify-center font-black text-lg">🤖</div>
                        <div>
                            <h3 class="text-slate-900 font-black text-lg tracking-tight">O Veredito Oficial do Auditor (Dedo-Duro)</h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Tradução e Diagnóstico Direto da Viagem</p>
                        </div>
                    </div>
                    <div class="p-8">
                        <ul class="space-y-4">
                            <li v-for="(alerta, idx) in gerarRelatorioDedoDuro(viagem)" :key="idx" 
                                class="flex gap-4 items-start p-5 rounded-xl border shadow-sm"
                                :class="alerta.tipo === 'critico' ? 'bg-rose-50 border-rose-200 text-rose-800' : (alerta.tipo === 'alerta' ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800')"
                            >
                                <span class="text-3xl mt-1 drop-shadow-sm">{{ alerta.icone }}</span>
                                <div>
                                    <h5 class="font-black text-base uppercase tracking-wider mb-1.5">{{ alerta.titulo }}</h5>
                                    <p class="text-sm font-medium leading-relaxed opacity-90">{{ alerta.texto }}</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>

            <div class="bg-slate-900 px-8 py-6 shrink-0 flex flex-col md:flex-row justify-between items-center gap-4 relative z-20 mt-auto rounded-b-2xl">
                <div>
                    <h4 class="text-sm font-black text-white uppercase tracking-wider mb-1">Resultado Financeiro Final</h4>
                    <p class="text-xs text-slate-400 font-medium">Balanço das contas bancárias (Recebemos da Sol Fácil - Pagámos à E4LOG).</p>
                </div>
                <div class="bg-black/50 px-8 py-4 rounded-2xl border border-white/10 flex items-center gap-6">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right leading-tight">Lucro Livre<br>da Viagem</span>
                    <span class="text-4xl font-black drop-shadow-lg" :class="viagem.lucro > 0 ? 'text-emerald-400' : 'text-rose-500'">{{ formatMoney(viagem.lucro) }}</span>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
@keyframes fadeInUp { 0% { opacity: 0; transform: translateY(30px); } 100% { opacity: 1; transform: translateY(0); } }
</style>
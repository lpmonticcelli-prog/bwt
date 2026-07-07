import { useFormatters } from './useFormatters';

export function useAuditoria() {
    const { formatMoney } = useFormatters();

    // 1. Gera as cores das "pílulas" de operação
    const badgeOperacao = (tipo) => {
        if (tipo === 'Reentrega') return 'bg-amber-50 text-amber-600 border-amber-200';
        if (tipo === 'Devolução') return 'bg-slate-100 text-slate-600 border-slate-200';
        if (tipo === 'Complemento') return 'bg-fuchsia-50 text-fuchsia-600 border-fuchsia-200';
        return 'bg-blue-50/50 text-blue-600 border-blue-100'; 
    };

    // 2. Explica matematicamente como o Back-end chegou ao valor (Matemática Reversa)
    const explicarCalculoFrete = (empresa, regra, valorCarga, freteBase, tipoOperacao) => {
        const fBase = Number(freteBase) || 0;
        const vCarga = Number(valorCarga) || 0;

        if (tipoOperacao === 'Complemento') {
            if (fBase === 0) return `Documento complementar exclusivo para taxas/impostos. Sem frete base.`;
            return `Documento complementar. Valor base aprovado: ${formatMoney(fBase)}.`;
        }

        if (vCarga === 0) return `Base de cálculo ausente no XML. Valor fixado pelo sistema: ${formatMoney(fBase)}.`;

        const percentualReal = (fBase / vCarga) * 100;

        if (percentualReal >= 1 && percentualReal <= 15) {
            return `O valor da carga (${formatMoney(vCarga)}) ultrapassou o piso mínimo. A matemática aplicada foi: ${formatMoney(vCarga)} x ${percentualReal.toFixed(2)}% = ${formatMoney(fBase)}.`;
        } else {
            return `O sistema aplicou o Piso Mínimo ou Taxa Fixa da tabela: ${formatMoney(fBase)}. A mercadoria não atingiu o gatilho percentual.`;
        }
    };

    // 3. Descobre o motivo do erro da Transportadora (Para a Tabela de Divergências)
    const descobrirMotivo = (frete) => {
        if (frete.is_correto) return 'Cobrança exata em tabela comercial';
        if (frete.regra === '⚠️ CIDADE NÃO MAPEADA') return 'Cidade não existe no cadastro de Regiões.';
        
        const dif = Number(frete.diferenca) || 0;
        if (Math.abs(dif) <= 0.50) return 'Cobrança exata em tabela comercial';
        if (dif < 0) return 'E4LOG cobrou a menor (Ganho operacional)';
        
        let motivos = [];
        if (Number(frete.taxasExtras) > 0) motivos.push('Taxas extras não combinadas embutidas no XML');
        
        const baseECalculada = Number(frete.freteBaseCalculado) + Number(frete.tdeCalculado);
        if (Number(frete.cobrado) > (baseECalculada + 0.50)) {
            if (Number(frete.tdeCalculado) === 0 && !frete.temTde) motivos.push('Valor base cobrado acima da tabela regional');
            else motivos.push('TDE cobrada sem justificativa ou acima do combinado');
        }
        
        return motivos.length > 0 ? motivos.join(' + ') : 'Divergência de cálculo na tabela';
    };

    // 4. O "Dedo-Duro" (Gera o Array de Alertas para o Super Modal DACTE)
    const gerarRelatorioDedoDuro = (viagem) => {
        if(!viagem) return [];
        let alertas = [];
        let difE4log = 0; let temComplE4log = false; let tdePagaE4log = 0;

        if (viagem.e4log_detalhes && viagem.e4log_detalhes.length > 0) {
            viagem.e4log_detalhes.forEach(f => {
                if (!f.is_correto) difE4log += Number(f.diferenca) || 0;
                if (f.tipo_operacao === 'Complemento') temComplE4log = true;
                tdePagaE4log += Number(f.tdeCalculado) || 0;
            });
        }

        if (difE4log > 0) {
            alertas.push({
                tipo: 'critico', icone: '🚨', titulo: 'Cobrança Indevida da Transportadora (E4LOG)',
                texto: `A E4LOG embutiu exatamente ${formatMoney(difE4log)} a mais nos XMLs desta viagem além da tabela acordada. Isto significa a adição de pedágios não combinados, taxas extras ou um frete complementar injustificado.`
            });
        }

        if (temComplE4log) {
            alertas.push({
                tipo: 'alerta', icone: '⚠️', titulo: 'CT-e Complementar Emitido pela E4LOG',
                texto: `A E4LOG enviou uma cobrança dividida (um Frete Original + um Complemento). É crucial validar se nós também emitimos um CT-e de Complemento contra a Sol Fácil. Se nós não repassarmos essa cobrança dupla, quem assume 100% desse custo somos nós.`
            });
        }

        let tdeCobradaBwt = 0; let gapBwt = 0;
        if (viagem.bwt_detalhes && viagem.bwt_detalhes.length > 0) {
            viagem.bwt_detalhes.forEach(f => { tdeCobradaBwt += Number(f.receita_tde) || 0; gapBwt += Number(f.gap_individual) || 0; });
        } else {
            alertas.push({
                tipo: 'critico', icone: '💀', titulo: 'Fuga Total de Faturamento (Prejuízo Absoluto)',
                texto: `ERRO GRAVE: A E4LOG já cobrou o custo desta viagem, mas o sistema detetou que a BWT AINDA NÃO EMITIU FATURA para a Sol Fácil. Nós fizemos este transporte de graça. Providencie a emissão do CT-e o quanto antes.`
            });
        }

        if (gapBwt > 0) {
            alertas.push({
                tipo: 'alerta', icone: '💸', titulo: 'Subfaturamento de Tabela (Deixámos Dinheiro na Mesa)',
                texto: `O nosso negociador estipulou uma tabela, mas o nosso faturista emitiu o CT-e com um valor mais baixo. Deixámos de faturar ${formatMoney(gapBwt)} que eram nosso direito nesta carga.`
            });
        }

        if (tdePagaE4log > 0 && tdeCobradaBwt === 0 && viagem.bwt_detalhes && viagem.bwt_detalhes.length > 0) {
            alertas.push({
                tipo: 'critico', icone: '🎯', titulo: 'ERRO HUMANO DETETADO: FALHA NO REPASSE DA TDE',
                texto: `O sistema aprova o pagamento de TDE para a E4LOG, MAS reparou que a BWT não cobrou a TDE ao cliente Sol Fácil. Ação Obrigatória: Emitir IMEDIATAMENTE um CT-e de Complemento contra a Sol Fácil cobrando a TDE.`
            });
        }

        if (alertas.length === 0) {
            alertas.push({
                tipo: 'sucesso', icone: '✅', titulo: 'Auditoria 100% Limpa e Fechada',
                texto: `Nenhuma falha detetada. O sistema varreu as regras e confirma que cobrámos o valor exato à Sol Fácil e pagámos o valor exato à E4LOG, sem taxas ocultas.`
            });
        }

        return alertas;
    };

    // 5. Funções de Busca Inversa (Para as Tabelas de XML solto)
    const obterReceitaAssociada = (frete, cruzamentoViagens) => {
        if(!cruzamentoViagens) return 0;
        const viagem = cruzamentoViagens.find(v => v.e4log_detalhes.some(e => e.id === frete.id));
        return viagem ? viagem.receita : 0;
    };

    const obterLucroAssociado = (frete, cruzamentoViagens) => {
        if(!cruzamentoViagens) return 0;
        const viagem = cruzamentoViagens.find(v => v.e4log_detalhes.some(e => e.id === frete.id));
        return viagem ? viagem.lucro : (0 - frete.cobrado);
    };

    return { 
        badgeOperacao, 
        explicarCalculoFrete, 
        descobrirMotivo, 
        gerarRelatorioDedoDuro, 
        obterReceitaAssociada, 
        obterLucroAssociado 
    };
}
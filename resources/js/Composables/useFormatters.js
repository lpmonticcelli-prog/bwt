export function useFormatters() {
    const formatMoney = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    };

    const formatarData = (dataStr) => {
        if (!dataStr) return 'Sem Data';
        return new Date(dataStr + 'T00:00:00').toLocaleDateString('pt-BR');
    };

    const extrairNumeroNFe = (chave) => {
        if (!chave || String(chave).length !== 44) return null;
        return Number(String(chave).substring(25, 34)); 
    };

    const extrairFimChave = (chave) => {
        if (!chave) return 'N/A';
        const strChave = String(chave).trim();
        if (strChave.length < 15) return strChave;
        return '...' + strChave.slice(-15);
    };

    return {
        formatMoney,
        formatarData,
        extrairNumeroNFe,
        extrairFimChave
    };
}
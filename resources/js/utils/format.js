// Utilitários de formatação (padrão Brasil).

/** "2026-07-05" ou "2026-07-05T00:00:00.000000Z" → "05/07/2026" (sem shift de fuso). */
export function fmtData(iso) {
    if (!iso) return null;
    return iso.slice(0, 10).split('-').reverse().join('/');
}

/** ISO datetime → "17/07/2026 às 10:58" (fuso America/Sao_Paulo). */
export function fmtDataHora(iso) {
    if (!iso) return null;
    const dt = new Date(iso);
    if (Number.isNaN(dt.getTime())) return null;
    return (
        dt.toLocaleString('pt-BR', {
            timeZone: 'America/Sao_Paulo',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).replace(',', ' às')
    );
}

/** "14:00:00" → "14:00". */
export function fmtHora(hora) {
    if (!hora) return null;
    return String(hora).slice(0, 5);
}

/** Iniciais de um nome: "João Pedro" → "JP". */
export function iniciais(nome) {
    if (!nome) return '?';
    const partes = nome.trim().split(/\s+/);
    const primeira = partes[0]?.[0] ?? '';
    const ultima = partes.length > 1 ? partes[partes.length - 1][0] : '';
    return (primeira + ultima).toUpperCase();
}

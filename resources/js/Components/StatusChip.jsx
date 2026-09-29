import { Chip } from '@mui/material';

const configuracoes = {
    na_unidade: { label: 'Na unidade', color: 'success', variant: 'filled' },
    evadido: { label: 'Evadido', color: 'warning', variant: 'outlined' },
    internado: { label: 'Internado', color: 'info', variant: 'outlined' },
    desacolhido: { label: 'Desacolhido', color: 'default', variant: 'outlined' },
};

/** Situação derivada do episódio; dados legados nunca são apresentados como estado canônico. */
export default function StatusChip({ situacao, fonte = 'episodio', size = 'small' }) {
    const config = fonte === 'legado'
        ? { label: 'Dados anteriores a conferir', color: 'warning', variant: 'outlined' }
        : fonte === 'nenhum'
            ? { label: 'Ingresso ainda não registrado', color: 'default', variant: 'outlined' }
            : configuracoes[situacao] ?? { label: 'Situação indisponível', color: 'default', variant: 'outlined' };

    return (
        <Chip
            size={size}
            label={config.label}
            color={config.color}
            variant={config.variant}
            sx={{ fontWeight: 600 }}
        />
    );
}

import { Link } from '@inertiajs/react';
import { Avatar, Box, Typography } from '@mui/material';
import CriancaAvatar from './CriancaAvatar';
import { fmtData } from '@/utils/format';
import StatusChip from './StatusChip';

/** Cartão de criança usado em listagens e resultados de busca. */
export default function CriancaCard({ crianca, detalhesExtras }) {
    return (
        <Box
            component={Link}
            href={route('criancas.show', crianca.id)}
            sx={{
                display: 'flex', gap: 1.5, alignItems: 'center', p: 2,
                bgcolor: 'background.paper', borderRadius: 3, textDecoration: 'none', color: 'inherit',
                border: '1px solid #e2e8f0', transition: 'box-shadow .2s, transform .2s',
                '&:hover': { boxShadow: 3, transform: 'translateY(-2px)' },
            }}
        >
            <CriancaAvatar crianca={crianca} />
            <Box sx={{ minWidth: 0, flex: 1 }}>
                <Typography variant="subtitle2" noWrap sx={{ fontWeight: 700 }}>
                    {crianca.nome_completo}
                </Typography>
                <Typography variant="caption" color="text.secondary" display="block" noWrap>
                    {[
                        crianca.processo_numero ? `Proc. ${crianca.processo_numero}` : null,
                        crianca.data_nascimento ? `Nasc. ${fmtData(crianca.data_nascimento)}` : null,
                    ].filter(Boolean).join(' · ') || 'Sem dados adicionais'}
                </Typography>
                {detalhesExtras}
            </Box>
            <StatusChip status={crianca.status} />
        </Box>
    );
}

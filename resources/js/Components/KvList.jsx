import { Box, Typography } from '@mui/material';

/** Lista chave/valor (identificação) — ignora valores vazios. */
export default function KvList({ dados }) {
    const entradas = Object.entries(dados ?? {}).filter(([, v]) => v !== null && v !== '');

    if (entradas.length === 0) {
        return <Typography variant="body2" color="text.secondary">Nenhuma informação preenchida.</Typography>;
    }

    return (
        <Box
            sx={{
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                gap: 2,
            }}
        >
            {entradas.map(([rotulo, valor]) => (
                <Box key={rotulo}>
                    <Typography variant="caption" color="text.secondary" sx={{ textTransform: 'uppercase', letterSpacing: 0.4 }}>
                        {rotulo}
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 500, whiteSpace: 'pre-line' }}>{valor}</Typography>
                </Box>
            ))}
        </Box>
    );
}

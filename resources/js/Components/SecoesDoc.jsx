import { Box, Divider, Typography } from '@mui/material';

/** Renderiza as seções preenchidas de um documento (objeto titulo => texto). */
export default function SecoesDoc({ secoes }) {
    const entradas = Object.entries(secoes ?? {});

    if (entradas.length === 0) {
        return <Typography variant="body2" color="text.secondary">Nenhuma seção preenchida.</Typography>;
    }

    return (
        <Box>
            {entradas.map(([titulo, texto], i) => (
                <Box key={titulo} sx={{ mb: 2.5 }}>
                    {i > 0 && <Divider sx={{ mb: 2.5 }} />}
                    <Typography
                        variant="overline"
                        color="primary.dark"
                        sx={{ fontWeight: 700, display: 'block', mb: 0.5 }}
                    >
                        {titulo}
                    </Typography>
                    <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                        {texto}
                    </Typography>
                </Box>
            ))}
        </Box>
    );
}

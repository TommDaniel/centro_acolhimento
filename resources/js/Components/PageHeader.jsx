import { Box, Typography } from '@mui/material';

/** Cabeçalho de página: título + subtítulo opcional + ações à direita. */
export default function PageHeader({ titulo, subtitulo, acoes }) {
    return (
        <Box
            sx={{
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: { xs: 'flex-start', sm: 'center' },
                gap: 1.5,
                mb: 3,
            }}
        >
            <Box sx={{ flex: 1, minWidth: 200 }}>
                <Typography variant="h5" component="h1">{titulo}</Typography>
                {subtitulo && (
                    <Typography variant="body2" component="div" color="text.secondary" sx={{ mt: 0.5 }}>
                        {subtitulo}
                    </Typography>
                )}
            </Box>
            {acoes && <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>{acoes}</Box>}
        </Box>
    );
}

import { Box, Typography } from '@mui/material';
import { SearchOff as VazioIcon } from '@mui/icons-material';

/** Estado vazio de listas. */
export default function EmptyState({ titulo, mensagem, acao, icone: Icone = VazioIcon }) {
    return (
        <Box sx={{ textAlign: 'center', py: 6, px: 2 }}>
            <Icone sx={{ fontSize: 48, color: 'text.disabled', mb: 1 }} />
            <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>{titulo}</Typography>
            {mensagem && (
                <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5, mb: 2 }}>
                    {mensagem}
                </Typography>
            )}
            {acao}
        </Box>
    );
}

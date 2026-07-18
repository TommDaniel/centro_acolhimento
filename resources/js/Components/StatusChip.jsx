import { Chip } from '@mui/material';

/** Chip de status da criança. */
export default function StatusChip({ status, size = 'small' }) {
    const acolhida = status === 'acolhida';
    return (
        <Chip
            size={size}
            label={acolhida ? 'Acolhida' : 'Desligada'}
            color={acolhida ? 'success' : 'default'}
            variant={acolhida ? 'filled' : 'outlined'}
            sx={{ fontWeight: 600 }}
        />
    );
}

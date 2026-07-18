import { Stack, Typography } from '@mui/material';
import { fmtDataHora } from '@/utils/format';

/** Linha de auditoria: quem registrou, quando e o setor. */
export default function DocMeta({ doc }) {
    const partes = [];
    if (doc?.criador?.name) partes.push(`Registrado por ${doc.criador.name}`);
    if (doc?.created_at) partes.push(`em ${fmtDataHora(doc.created_at)}`);
    if (doc?.setor?.nome) partes.push(`Setor: ${doc.setor.nome}`);

    if (partes.length === 0) return null;

    return (
        <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
            <Typography variant="caption" color="text.secondary">
                {partes.join(' · ')}
            </Typography>
        </Stack>
    );
}

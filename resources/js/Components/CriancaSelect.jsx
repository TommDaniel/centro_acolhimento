import { Autocomplete, Box, TextField, Typography } from '@mui/material';
import StatusChip from '@/Components/StatusChip';

function textoSituacao(crianca) {
    if (!crianca) return null;
    if (crianca.acolhimento_fonte === 'legado') return 'Dados anteriores a conferir';
    if (crianca.acolhimento_fonte === 'nenhum') return 'Ingresso ainda não registrado';

    const rotulos = {
        na_unidade: 'Na unidade',
        evadido: 'Evadido',
        internado: 'Internado',
        desacolhido: 'Desacolhido',
    };

    return rotulos[crianca.acolhimento_situacao] ?? 'Situação indisponível';
}

/** Seleção de criança/adolescente com busca (usada em todos os formulários de documento). */
export default function CriancaSelect({ criancas, value, onChange, error, helperText, disabled = false, label = 'Criança/adolescente *' }) {
    const selecionada = criancas.find((c) => c.id === Number(value)) ?? null;

    return (
        <Autocomplete
            options={criancas}
            getOptionLabel={(c) => c.nome_completo}
            value={selecionada}
            onChange={(_, nova) => onChange(nova ? nova.id : '')}
            disabled={disabled}
            renderOption={(props, crianca) => (
                <Box component="li" {...props} sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
                    <Box sx={{ flex: 1, minWidth: 0 }}>
                        <Typography variant="body2" noWrap>{crianca.nome_completo}</Typography>
                        <Typography variant="caption" color="text.secondary">{textoSituacao(crianca)}</Typography>
                    </Box>
                    <StatusChip
                        situacao={crianca.acolhimento_situacao}
                        fonte={crianca.acolhimento_fonte}
                    />
                </Box>
            )}
            renderInput={(params) => (
                <TextField
                    {...params}
                    label={label}
                    error={Boolean(error)}
                    helperText={error || helperText || textoSituacao(selecionada)}
                />
            )}
            isOptionEqualToValue={(a, b) => a.id === b.id}
            noOptionsText="Nenhuma criança encontrada"
        />
    );
}

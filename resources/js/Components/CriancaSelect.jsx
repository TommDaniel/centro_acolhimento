import { Autocomplete, TextField } from '@mui/material';

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
            renderInput={(params) => (
                <TextField
                    {...params}
                    label={label}
                    error={Boolean(error)}
                    helperText={error || helperText}
                />
            )}
            isOptionEqualToValue={(a, b) => a.id === b.id}
            noOptionsText="Nenhuma criança encontrada"
        />
    );
}

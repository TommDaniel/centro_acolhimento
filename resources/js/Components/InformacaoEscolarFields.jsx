import { Alert, MenuItem, TextField } from '@mui/material';

export function novaChaveIdempotencia() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function informacaoEscolarVazia() {
    return {
        situacao_codigo: '',
        situacao_complemento: '',
        escola_nome: '',
        rede_codigo: '',
        rede_complemento: '',
        matricula: '',
        ano_serie: '',
        turma: '',
        turno_codigo: '',
        turno_complemento: '',
        vigente_em: '',
        fonte_codigo: '',
        fonte_complemento: '',
        idempotency_key: novaChaveIdempotencia(),
    };
}

export default function InformacaoEscolarFields({ data, setData, errors, opcoes, errorPrefix = '' }) {
    const alterar = (nome, valor) => {
        if (nome === 'situacao_codigo' && valor === 'nao_informada') {
            setData({
                ...data,
                situacao_codigo: valor,
                situacao_complemento: '',
                escola_nome: '',
                rede_codigo: '',
                rede_complemento: '',
                matricula: '',
                ano_serie: '',
                turma: '',
                turno_codigo: '',
                turno_complemento: '',
                vigente_em: '',
            });
            return;
        }

        if (nome === 'situacao_codigo') {
            setData({ ...data, situacao_codigo: valor, situacao_complemento: valor === 'outra' ? data.situacao_complemento : '' });
            return;
        }

        if (nome === 'rede_codigo') {
            setData({ ...data, rede_codigo: valor, rede_complemento: valor === 'outra' ? data.rede_complemento : '' });
            return;
        }

        if (nome === 'turno_codigo') {
            setData({ ...data, turno_codigo: valor, turno_complemento: valor === 'outro' ? data.turno_complemento : '' });
            return;
        }

        if (nome === 'fonte_codigo') {
            setData({ ...data, fonte_codigo: valor, fonte_complemento: valor === 'outra' ? data.fonte_complemento : '' });
            return;
        }

        setData(nome, valor);
    };

    const campo = (nome) => ({
        value: data[nome] ?? '',
        onChange: (event) => alterar(nome, event.target.value),
        error: Boolean(errors[`${errorPrefix}${nome}`]),
        helperText: errors[`${errorPrefix}${nome}`],
    });
    const possuiInformacao = data.situacao_codigo !== 'nao_informada';

    return (
        <>
            <Alert severity="info" sx={{ gridColumn: '1 / -1' }}>
                Este é um registro provisório para validação com a equipe técnica. Informe somente o que é conhecido;
                campos vazios não significam matrícula ou escola confirmada.
            </Alert>
            <TextField select required label="Situação escolar" {...campo('situacao_codigo')}>
                {Object.entries(opcoes.situacoes).map(([valor, rotulo]) => (
                    <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                ))}
            </TextField>
            {data.situacao_codigo === 'outra' && (
                <TextField required label="Qual situação?" {...campo('situacao_complemento')} />
            )}
            {possuiInformacao && (
                <>
                    <TextField label="Escola" inputProps={{ maxLength: 255 }} {...campo('escola_nome')} />
                    <TextField select label="Rede de ensino" {...campo('rede_codigo')}>
                        <MenuItem value="">Não informada</MenuItem>
                        {Object.entries(opcoes.redes).map(([valor, rotulo]) => (
                            <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                        ))}
                    </TextField>
                    {data.rede_codigo === 'outra' && (
                        <TextField required label="Qual rede?" {...campo('rede_complemento')} />
                    )}
                    <TextField label="Matrícula" inputProps={{ maxLength: 100 }} {...campo('matricula')} />
                    <TextField label="Ano / série" inputProps={{ maxLength: 100 }} {...campo('ano_serie')} />
                    <TextField label="Turma" inputProps={{ maxLength: 100 }} {...campo('turma')} />
                    <TextField select label="Turno" {...campo('turno_codigo')}>
                        <MenuItem value="">Não informado</MenuItem>
                        {Object.entries(opcoes.turnos).map(([valor, rotulo]) => (
                            <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                        ))}
                    </TextField>
                    {data.turno_codigo === 'outro' && (
                        <TextField required label="Qual turno?" {...campo('turno_complemento')} />
                    )}
                    <TextField
                        label="Informação vigente em"
                        type="date"
                        slotProps={{ inputLabel: { shrink: true } }}
                        {...campo('vigente_em')}
                        helperText={errors[`${errorPrefix}vigente_em`] ?? 'Opcional. Não estime uma data desconhecida.'}
                    />
                </>
            )}
            <TextField select required label="Fonte da informação" {...campo('fonte_codigo')}>
                {Object.entries(opcoes.fontes).map(([valor, rotulo]) => (
                    <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                ))}
            </TextField>
            {data.fonte_codigo === 'outra' && (
                <TextField required label="Qual fonte?" {...campo('fonte_complemento')} />
            )}
            <input type="hidden" name="idempotency_key" value={data.idempotency_key} />
        </>
    );
}

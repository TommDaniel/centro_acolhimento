import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import {
    Alert, Box, Button, Card, CardContent, Dialog, DialogActions, DialogContent,
    DialogTitle, Divider, MenuItem, Stack, TextField, Typography,
} from '@mui/material';
import StatusChip from '@/Components/StatusChip';
import { fmtData, fmtDataHora } from '@/utils/format';

const rotulosSituacao = {
    na_unidade: 'Na unidade',
    evadido: 'Evadido',
    internado: 'Internado',
    desacolhido: 'Desacolhido',
};

const rotulosMovimentacao = {
    ingresso: 'Ingresso',
    evasao: 'Evasão',
    retorno: 'Retorno à unidade',
    internacao: 'Internação',
    desacolhimento: 'Desacolhimento',
};

function novaChaveIdempotencia() {
    if (globalThis.crypto?.randomUUID) {
        return globalThis.crypto.randomUUID();
    }

    const bytes = new Uint8Array(16);
    globalThis.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function Campo({ form, nome, helperText, onChange, ...props }) {
    return (
        <TextField
            {...props}
            value={form.data[nome] ?? ''}
            onChange={onChange ?? ((event) => form.setData(nome, event.target.value))}
            error={Boolean(form.errors[nome])}
            helperText={form.errors[nome] ?? helperText}
        />
    );
}

function valorComComplemento(valor, complemento) {
    return complemento ? `${valor} — ${complemento}` : valor;
}

function LinhaContexto({ rotulo, valor }) {
    if (!valor) return null;

    return (
        <Typography variant="body2" sx={{ whiteSpace: 'pre-line' }}>
            <Box component="span" sx={{ fontWeight: 700 }}>{rotulo}:</Box> {valor}
        </Typography>
    );
}

function BotoesContextuais({ situacao, onSelect }) {
    const tipos = situacao === 'na_unidade'
        ? [
            ['evasao', 'Registrar evasão'],
            ['internacao', 'Registrar internação'],
            ['desacolhimento', 'Registrar desacolhimento'],
        ]
        : situacao === 'evadido' || situacao === 'internado'
            ? [
                ['retorno', 'Registrar retorno'],
                ['desacolhimento', 'Registrar desacolhimento'],
            ]
            : [];

    return (
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems="stretch">
            {tipos.map(([tipo, rotulo]) => (
                <Button key={tipo} variant="outlined" onClick={() => onSelect(tipo)}>
                    {rotulo}
                </Button>
            ))}
        </Stack>
    );
}

export default function AcolhimentoPanel({
    criancaId,
    acolhimento,
    linhaDoTempo,
    legadoAConferir,
    opcoes,
}) {
    const [dialogIngresso, setDialogIngresso] = useState(false);
    const [dialogMovimentacao, setDialogMovimentacao] = useState(false);

    const ingressoForm = useForm({
        ingresso_em: '',
        motivo: '',
        fundamento: '',
        origem_codigo: '',
        origem_complemento: '',
        orgao_condutor_codigo: '',
        orgao_condutor_complemento: '',
        pessoa_condutora: '',
        idempotency_key: novaChaveIdempotencia(),
    });
    const movimentacaoForm = useForm({
        tipo: '',
        efetiva_em: '',
        motivo: '',
        fundamento: '',
        local_destino: '',
        observacao: '',
        idempotency_key: novaChaveIdempotencia(),
    });

    const abrirMovimentacao = (tipo) => {
        movimentacaoForm.reset();
        movimentacaoForm.clearErrors();
        movimentacaoForm.setData({
            tipo,
            efetiva_em: '',
            motivo: '',
            fundamento: '',
            local_destino: '',
            observacao: '',
            idempotency_key: novaChaveIdempotencia(),
        });
        setDialogMovimentacao(true);
    };

    const enviarIngresso = (event) => {
        event.preventDefault();
        ingressoForm.post(route('criancas.acolhimentos.store', criancaId), {
            preserveScroll: true,
            onSuccess: () => {
                setDialogIngresso(false);
                ingressoForm.reset();
                ingressoForm.setData('idempotency_key', novaChaveIdempotencia());
            },
        });
    };

    const enviarMovimentacao = (event) => {
        event.preventDefault();
        movimentacaoForm.post(
            route('criancas.acolhimentos.movimentacoes.store', [criancaId, acolhimento.id]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDialogMovimentacao(false);
                    movimentacaoForm.reset();
                    movimentacaoForm.setData('idempotency_key', novaChaveIdempotencia());
                },
            },
        );
    };

    const movimentoAtual = rotulosSituacao[acolhimento?.situacao] ?? 'Situação indisponível';
    const alterarCodigoIngresso = (campoCodigo, campoComplemento) => (event) => {
        const codigo = event.target.value;

        ingressoForm.setData((dados) => ({
            ...dados,
            [campoCodigo]: codigo,
            [campoComplemento]: codigo === 'outro' ? dados[campoComplemento] : '',
        }));

        if (codigo !== 'outro') {
            ingressoForm.clearErrors(campoComplemento);
        }
    };

    return (
        <>
            <Card component="section" aria-labelledby="situacao-acolhimento-titulo">
                <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                    <Stack spacing={2.5}>
                        <Box>
                            <Typography id="situacao-acolhimento-titulo" variant="h6">
                                Situação do acolhimento
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                O cadastro da pessoa é separado do episódio e de cada movimentação.
                            </Typography>
                        </Box>

                        {acolhimento ? (
                            <Box
                                sx={{
                                    bgcolor: '#f0fdfa', border: '1px solid #99f6e4', borderRadius: 2,
                                    p: { xs: 2, sm: 2.5 },
                                }}
                            >
                                <Stack spacing={1.5}>
                                    <Stack
                                        direction={{ xs: 'column', sm: 'row' }}
                                        alignItems={{ xs: 'flex-start', sm: 'center' }}
                                        justifyContent="space-between"
                                        gap={1}
                                    >
                                        <Box>
                                            <Typography variant="h5" component="p" sx={{ color: '#334155' }}>
                                                {movimentoAtual}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                Desde {fmtDataHora(acolhimento.desde)}
                                            </Typography>
                                        </Box>
                                        <StatusChip situacao={acolhimento.situacao} />
                                    </Stack>
                                    <Typography variant="body2">
                                        Registrado por {acolhimento.registrado_por ?? 'Autoria indisponível'} em{' '}
                                        {fmtDataHora(acolhimento.registrado_em) ?? 'horário indisponível'}.
                                    </Typography>
                                    <Box sx={{ borderLeft: '3px solid #0d9488', pl: 2 }}>
                                        <Stack spacing={0.5}>
                                            <LinhaContexto rotulo="Motivo do ingresso" valor={acolhimento.motivo} />
                                            <LinhaContexto rotulo="Fundamento do ingresso" valor={acolhimento.fundamento} />
                                            <LinhaContexto
                                                rotulo="Origem"
                                                valor={valorComComplemento(
                                                    acolhimento.origem,
                                                    acolhimento.origem_complemento,
                                                )}
                                            />
                                            <LinhaContexto
                                                rotulo="Órgão condutor"
                                                valor={valorComComplemento(
                                                    acolhimento.orgao_condutor,
                                                    acolhimento.orgao_condutor_complemento,
                                                )}
                                            />
                                            <LinhaContexto
                                                rotulo="Pessoa condutora"
                                                valor={acolhimento.pessoa_condutora}
                                            />
                                        </Stack>
                                    </Box>
                                    {acolhimento.aberto ? (
                                        <BotoesContextuais
                                            situacao={acolhimento.situacao}
                                            onSelect={abrirMovimentacao}
                                        />
                                    ) : (
                                        <Button
                                            variant="contained"
                                            onClick={() => setDialogIngresso(true)}
                                            sx={{ alignSelf: 'flex-start' }}
                                        >
                                            Registrar novo ingresso
                                        </Button>
                                    )}
                                </Stack>
                            </Box>
                        ) : (
                            <Stack spacing={1.5} alignItems="flex-start">
                                <Alert severity={legadoAConferir ? 'warning' : 'info'} sx={{ width: '100%' }}>
                                    {legadoAConferir
                                        ? 'Não há episódio de acolhimento confirmado. Registre um novo ingresso sem alterar os dados anteriores.'
                                        : 'Ingresso ainda não registrado. Conclua esta etapa para iniciar o episódio e a linha do tempo.'}
                                </Alert>
                                <Button variant="contained" onClick={() => setDialogIngresso(true)}>
                                    {legadoAConferir ? 'Registrar novo ingresso' : 'Registrar ingresso'}
                                </Button>
                            </Stack>
                        )}

                        {legadoAConferir && (
                            <Stack spacing={1.5}>
                                <Alert severity="warning">
                                    Dados anteriores a conferir. Este histórico permanece separado dos episódios
                                    confirmados; o sistema não inferiu horário, evasão, internação ou saída.
                                </Alert>
                                {(legadoAConferir.data || legadoAConferir.motivo) && (
                                    <Box sx={{ borderLeft: '3px solid #f59e0b', pl: 2 }}>
                                        {legadoAConferir.data && (
                                            <Typography variant="body2">
                                                Data anterior informada, sem horário: {fmtData(legadoAConferir.data)}
                                            </Typography>
                                        )}
                                        {legadoAConferir.motivo && (
                                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line' }}>
                                                Motivo anterior informado: {legadoAConferir.motivo}
                                            </Typography>
                                        )}
                                    </Box>
                                )}
                            </Stack>
                        )}

                        <Divider />

                        <Box>
                            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
                                Linha do tempo
                            </Typography>
                            {linhaDoTempo.length === 0 ? (
                                <Typography variant="body2" color="text.secondary">
                                    A linha do tempo começará quando o primeiro ingresso for registrado.
                                </Typography>
                            ) : (
                                <Stack
                                    component="ol"
                                    role="list"
                                    aria-label="Linha do tempo do acolhimento"
                                    spacing={0}
                                    sx={{ listStyle: 'none', p: 0, m: 0 }}
                                >
                                    {linhaDoTempo.map((item, index) => (
                                        <Box
                                            component="li"
                                            key={item.id}
                                            sx={{
                                                display: 'grid', gridTemplateColumns: '18px 1fr', columnGap: 1.5,
                                                pb: index === linhaDoTempo.length - 1 ? 0 : 2,
                                            }}
                                        >
                                            <Box sx={{ position: 'relative', display: 'flex', justifyContent: 'center' }}>
                                                <Box
                                                    aria-hidden="true"
                                                    sx={{ width: 10, height: 10, borderRadius: '50%', bgcolor: '#0d9488', mt: 0.75, zIndex: 1 }}
                                                />
                                                {index !== linhaDoTempo.length - 1 && (
                                                    <Box
                                                        aria-hidden="true"
                                                        sx={{ position: 'absolute', top: 14, bottom: -8, width: 2, bgcolor: '#cbd5e1' }}
                                                    />
                                                )}
                                            </Box>
                                            <Box>
                                                <Stack direction={{ xs: 'column', sm: 'row' }} gap={{ xs: 0.25, sm: 1 }}>
                                                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                                        {rotulosMovimentacao[item.tipo] ?? item.tipo}
                                                    </Typography>
                                                    <Typography variant="body2" color="text.secondary">
                                                        {fmtDataHora(item.efetiva_em)}
                                                    </Typography>
                                                </Stack>
                                                <Typography variant="caption" color="text.secondary" display="block">
                                                    por {item.registrado_por ?? 'Autoria indisponível'} · registrado em{' '}
                                                    {fmtDataHora(item.registrado_em)}
                                                </Typography>
                                                {item.episodio_contexto && (
                                                    <Box sx={{ borderLeft: '3px solid #0d9488', pl: 1.5, mt: 1 }}>
                                                        <Stack spacing={0.5}>
                                                            <Typography variant="caption" sx={{ fontWeight: 700 }}>
                                                                Episódio {item.episodio_ordem}
                                                            </Typography>
                                                            <LinhaContexto
                                                                rotulo="Motivo do ingresso"
                                                                valor={item.episodio_contexto.motivo}
                                                            />
                                                            <LinhaContexto
                                                                rotulo="Fundamento do ingresso"
                                                                valor={item.episodio_contexto.fundamento}
                                                            />
                                                            <LinhaContexto
                                                                rotulo="Origem"
                                                                valor={valorComComplemento(
                                                                    item.episodio_contexto.origem,
                                                                    item.episodio_contexto.origem_complemento,
                                                                )}
                                                            />
                                                            <LinhaContexto
                                                                rotulo="Órgão condutor"
                                                                valor={valorComComplemento(
                                                                    item.episodio_contexto.orgao_condutor,
                                                                    item.episodio_contexto.orgao_condutor_complemento,
                                                                )}
                                                            />
                                                            <LinhaContexto
                                                                rotulo="Pessoa condutora"
                                                                valor={item.episodio_contexto.pessoa_condutora}
                                                            />
                                                        </Stack>
                                                    </Box>
                                                )}
                                                {item.motivo && !item.episodio_contexto && (
                                                    <Typography variant="body2" sx={{ mt: 0.5, whiteSpace: 'pre-line' }}>
                                                        Motivo: {item.motivo}
                                                    </Typography>
                                                )}
                                                {item.fundamento && !item.episodio_contexto && (
                                                    <Typography variant="body2" color="text.secondary" sx={{ whiteSpace: 'pre-line' }}>
                                                        Fundamento: {item.fundamento}
                                                    </Typography>
                                                )}
                                                {item.local_destino && (
                                                    <Typography variant="body2" color="text.secondary">
                                                        Local/destino: {item.local_destino}
                                                    </Typography>
                                                )}
                                                {item.observacao && (
                                                    <Typography variant="body2" color="text.secondary" sx={{ whiteSpace: 'pre-line' }}>
                                                        Observação: {item.observacao}
                                                    </Typography>
                                                )}
                                            </Box>
                                        </Box>
                                    ))}
                                </Stack>
                            )}
                        </Box>
                    </Stack>
                </CardContent>
            </Card>

            <Dialog open={dialogIngresso} onClose={() => setDialogIngresso(false)} maxWidth="sm" fullWidth>
                <Box component="form" onSubmit={enviarIngresso}>
                    <DialogTitle>Registrar ingresso</DialogTitle>
                    <DialogContent>
                        <Stack spacing={2} sx={{ pt: 1 }}>
                            <Campo
                                form={ingressoForm}
                                nome="ingresso_em"
                                label="Data e hora efetivas *"
                                type="datetime-local"
                                slotProps={{ inputLabel: { shrink: true }, htmlInput: { step: 60 } }}
                            />
                            <Campo form={ingressoForm} nome="motivo" label="Motivo *" multiline rows={3} />
                            <Campo form={ingressoForm} nome="fundamento" label="Fundamento" multiline rows={2} />
                            <Campo
                                form={ingressoForm}
                                nome="origem_codigo"
                                label="Origem do encaminhamento *"
                                select
                                onChange={alterarCodigoIngresso('origem_codigo', 'origem_complemento')}
                            >
                                {Object.entries(opcoes.origens).map(([codigo, rotulo]) => (
                                    <MenuItem key={codigo} value={codigo}>{rotulo}</MenuItem>
                                ))}
                            </Campo>
                            {ingressoForm.data.origem_codigo === 'outro' && (
                                <Campo form={ingressoForm} nome="origem_complemento" label="Qual origem? *" />
                            )}
                            <Campo
                                form={ingressoForm}
                                nome="orgao_condutor_codigo"
                                label="Órgão condutor *"
                                select
                                onChange={alterarCodigoIngresso(
                                    'orgao_condutor_codigo',
                                    'orgao_condutor_complemento',
                                )}
                            >
                                {Object.entries(opcoes.orgaos_condutores).map(([codigo, rotulo]) => (
                                    <MenuItem key={codigo} value={codigo}>{rotulo}</MenuItem>
                                ))}
                            </Campo>
                            {ingressoForm.data.orgao_condutor_codigo === 'outro' && (
                                <Campo form={ingressoForm} nome="orgao_condutor_complemento" label="Qual órgão? *" />
                            )}
                            <Campo
                                form={ingressoForm}
                                nome="pessoa_condutora"
                                label="Pessoa condutora *"
                                helperText={ingressoForm.errors.pessoa_condutora ?? 'Informe a pessoa, separada do órgão.'}
                            />
                        </Stack>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2 }}>
                        <Button color="inherit" onClick={() => setDialogIngresso(false)}>Cancelar</Button>
                        <Button type="submit" variant="contained" disabled={ingressoForm.processing}>
                            {ingressoForm.processing ? 'Registrando…' : 'Registrar ingresso'}
                        </Button>
                    </DialogActions>
                </Box>
            </Dialog>

            <Dialog open={dialogMovimentacao} onClose={() => setDialogMovimentacao(false)} maxWidth="sm" fullWidth>
                <Box component="form" onSubmit={enviarMovimentacao}>
                    <DialogTitle>{rotulosMovimentacao[movimentacaoForm.data.tipo] ?? 'Registrar movimentação'}</DialogTitle>
                    <DialogContent>
                        <Stack spacing={2} sx={{ pt: 1 }}>
                            <Campo
                                form={movimentacaoForm}
                                nome="efetiva_em"
                                label="Data e hora efetivas *"
                                type="datetime-local"
                                slotProps={{ inputLabel: { shrink: true }, htmlInput: { step: 60 } }}
                            />
                            {movimentacaoForm.data.tipo !== 'retorno' && (
                                <Campo form={movimentacaoForm} nome="motivo" label="Motivo *" multiline rows={3} />
                            )}
                            <Campo form={movimentacaoForm} nome="fundamento" label="Fundamento" multiline rows={2} />
                            {['internacao', 'desacolhimento'].includes(movimentacaoForm.data.tipo) && (
                                <Campo
                                    form={movimentacaoForm}
                                    nome="local_destino"
                                    label={movimentacaoForm.data.tipo === 'internacao' ? 'Local da internação *' : 'Destino *'}
                                />
                            )}
                            <Campo form={movimentacaoForm} nome="observacao" label="Observação" multiline rows={2} />
                        </Stack>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2 }}>
                        <Button color="inherit" onClick={() => setDialogMovimentacao(false)}>Cancelar</Button>
                        <Button type="submit" variant="contained" disabled={movimentacaoForm.processing}>
                            {movimentacaoForm.processing ? 'Registrando…' : 'Registrar movimentação'}
                        </Button>
                    </DialogActions>
                </Box>
            </Dialog>
        </>
    );
}

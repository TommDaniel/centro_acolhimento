import { useEffect, useRef } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Alert, Box, Button, Card, FormControlLabel, Stack, Switch, TextField, Typography,
} from '@mui/material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaSelect from '@/Components/CriancaSelect';
import { fmtData, fmtDataHora } from '@/utils/format';

/** Monta o texto de "Dados do acolhimento" a partir do cadastro da criança. */
function montarDadosAcolhimento(crianca) {
    if (!crianca) return '';
    const partes = [];
    if (crianca.episodio_aberto_id) {
        partes.push(`Ingresso registrado em ${fmtDataHora(crianca.ingresso_em)}.`);
        if (crianca.motivo_ingresso) {
            partes.push(`Motivo: ${crianca.motivo_ingresso}`);
        }
    } else if (crianca.acolhimento_fonte === 'episodio') {
        partes.push('O último episódio está encerrado; este PIA ficará sem vínculo com episódio.');
    } else {
        partes.push('Ingresso ainda não registrado.');
    }
    if (crianca.legado_a_conferir) {
        partes.push('Dados anteriores a conferir, preservados separadamente do episódio confirmado.');
        if (crianca.legado_a_conferir.data) {
            partes.push(`Data anterior informada, sem horário: ${fmtData(crianca.legado_a_conferir.data)}.`);
        }
        if (crianca.legado_a_conferir.motivo) {
            partes.push(`Motivo anterior informado: ${crianca.legado_a_conferir.motivo}`);
        }
    }
    if (crianca.processo_numero_snapshot) {
        partes.push(
            `Processo nº ${crianca.processo_numero_snapshot}` +
                (crianca.vara_snapshot ? ` — ${crianca.vara_snapshot}` : '') +
                (crianca.comarca_snapshot ? ` / ${crianca.comarca_snapshot}` : '') +
                '.'
        );
    }
    return partes.join('\n');
}

/** Campo multiline padrão do PIA. */
function CampoTexto({ form, campo, label, rows = 4 }) {
    return (
        <TextField
            label={label}
            fullWidth
            multiline
            rows={rows}
            value={form.data[campo]}
            onChange={(e) => form.setData(campo, e.target.value)}
            error={Boolean(form.errors[campo])}
            helperText={form.errors[campo]}
        />
    );
}

export default function Form({ pia, criancas, criancaId }) {
    const editando = Boolean(pia);
    const criancaInicialId = pia?.crianca_id ?? criancaId ?? '';
    const criancaInicial = criancas.find((crianca) => crianca.id === Number(criancaInicialId)) ?? null;
    // true quando o usuário tocou no campo — o autopreenchimento para aí.
    const acolhimentoEditado = useRef(false);

    const form = useForm({
        crianca_id: criancaInicialId,
        ...(!editando && {
            expected_acolhimento_id: criancaInicial?.episodio_aberto_id ?? null,
        }),
        numero_oficio: pia?.numero_oficio ?? '',
        dados_acolhimento: pia?.dados_acolhimento ?? '',
        encaminhado_por: pia?.encaminhado_por ?? '',
        acolhimento_anterior: Boolean(pia?.acolhimento_anterior),
        acolhimento_anterior_detalhes: pia?.acolhimento_anterior_detalhes ?? '',
        especificidades: pia?.especificidades ?? '',
        informacoes_familia: pia?.informacoes_familia ?? '',
        saude: pia?.saude ?? '',
        saude_familiares: pia?.saude_familiares ?? '',
        educacao_menor: pia?.educacao_menor ?? '',
        educacao_familiares: pia?.educacao_familiares ?? '',
        assistencia_social: pia?.assistencia_social ?? '',
        assistencia_social_familiares: pia?.assistencia_social_familiares ?? '',
        esporte_cultura_lazer: pia?.esporte_cultura_lazer ?? '',
        composicao_familiar: pia?.composicao_familiar ?? '',
        consideracoes_tecnicas: pia?.consideracoes_tecnicas ?? '',
        plano_acao: pia?.plano_acao ?? '',
        providencias_judiciario: pia?.providencias_judiciario ?? '',
    });
    const criancaSelecionada = criancas.find((crianca) => crianca.id === Number(form.data.crianca_id)) ?? null;

    const selecionarCrianca = (id) => {
        const crianca = criancas.find((item) => item.id === Number(id)) ?? null;

        acolhimentoEditado.current = false;
        form.clearErrors('expected_acolhimento_id');
        form.setData({
            ...form.data,
            crianca_id: id,
            expected_acolhimento_id: crianca?.episodio_aberto_id ?? null,
            dados_acolhimento: montarDadosAcolhimento(crianca),
        });
    };

    // No create, pré-preenche os dados do acolhimento a partir da criança
    // selecionada (inclusive no mount, quando criancaId vem pela query string).
    // Nunca sobrescreve edição manual nem roda no edit.
    useEffect(() => {
        if (editando || acolhimentoEditado.current) return;
        const crianca = criancas.find((c) => c.id === Number(form.data.crianca_id));
        form.setData('dados_acolhimento', montarDadosAcolhimento(crianca));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.crianca_id]);

    const enviar = (e) => {
        e.preventDefault();

        if (editando) {
            form.transform((dados) => ({ ...dados, _method: 'put' }));
            form.post(route('pias.update', pia.id));
        } else {
            form.post(route('pias.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={editando ? 'Editar PIA' : 'Novo PIA'} />
            <PageHeader
                titulo={editando ? 'Editar PIA' : 'Novo PIA'}
                subtitulo="Plano Individual de Atendimento"
            />

            <Box component="form" onSubmit={enviar}>
                <Stack spacing={2.5}>
                    <Alert severity="info" sx={{ borderRadius: 2 }}>
                        A identificação da criança é puxada automaticamente do cadastro. Campos deixados em branco não
                        aparecem no documento final.
                    </Alert>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Criança e acolhimento
                        </Typography>
                        <Stack spacing={2}>
                            <CriancaSelect
                                criancas={criancas}
                                value={form.data.crianca_id}
                                onChange={selecionarCrianca}
                                error={form.errors.crianca_id}
                                disabled={editando}
                                helperText={editando ? 'A pessoa vinculada ao PIA não pode ser alterada.' : undefined}
                            />
                            {form.errors.expected_acolhimento_id && (
                                <Alert severity="error" role="alert">
                                    {form.errors.expected_acolhimento_id}
                                </Alert>
                            )}
                            {!editando && criancaSelecionada?.episodio_aberto_id && (
                                <Alert severity="info">
                                    Este PIA será vinculado pelo servidor ao episódio aberto com ingresso em{' '}
                                    {fmtDataHora(criancaSelecionada.ingresso_em)}.
                                </Alert>
                            )}
                            {!editando && criancaSelecionada && !criancaSelecionada.episodio_aberto_id && (
                                <Alert severity="warning">
                                    Este PIA ficará sem vínculo com episódio. O sistema não associa automaticamente
                                    um episódio encerrado nem dados anteriores ainda não reconciliados.
                                </Alert>
                            )}
                            {criancaSelecionada?.legado_a_conferir && (
                                <Alert severity="warning">
                                    Dados anteriores a conferir continuam preservados separadamente, inclusive após
                                    um novo ingresso confirmado.
                                </Alert>
                            )}
                            <TextField
                                label="Nº do ofício (opcional — deixe em branco para gerar automaticamente)"
                                fullWidth
                                value={form.data.numero_oficio}
                                onChange={(e) => form.setData('numero_oficio', e.target.value)}
                                error={Boolean(form.errors.numero_oficio)}
                                helperText={form.errors.numero_oficio}
                            />
                            <TextField
                                label="Motivo e circunstâncias do acolhimento"
                                fullWidth
                                multiline
                                rows={4}
                                value={form.data.dados_acolhimento}
                                onChange={(e) => {
                                    acolhimentoEditado.current = true;
                                    form.setData('dados_acolhimento', e.target.value);
                                }}
                                error={Boolean(form.errors.dados_acolhimento)}
                                helperText={form.errors.dados_acolhimento}
                            />
                            <TextField
                                label="Encaminhado por (ex.: Conselho Tutelar, Juízo, MP)"
                                fullWidth
                                value={form.data.encaminhado_por}
                                onChange={(e) => form.setData('encaminhado_por', e.target.value)}
                                error={Boolean(form.errors.encaminhado_por)}
                                helperText={form.errors.encaminhado_por}
                            />
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={form.data.acolhimento_anterior}
                                        onChange={(e) =>
                                            form.setData('acolhimento_anterior', e.target.checked)
                                        }
                                    />
                                }
                                label="Já teve acolhimento institucional anterior?"
                            />
                            {form.data.acolhimento_anterior && (
                                <TextField
                                    label="Detalhes do acolhimento anterior *"
                                    placeholder="Quando? Em qual instituição? Por quanto tempo?"
                                    fullWidth
                                    multiline
                                    rows={2}
                                    value={form.data.acolhimento_anterior_detalhes}
                                    onChange={(e) =>
                                        form.setData('acolhimento_anterior_detalhes', e.target.value)
                                    }
                                    error={Boolean(form.errors.acolhimento_anterior_detalhes)}
                                    helperText={form.errors.acolhimento_anterior_detalhes}
                                />
                            )}
                            <CampoTexto
                                form={form}
                                campo="especificidades"
                                label="Especificidades da criança/adolescente acolhido"
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Família
                        </Typography>
                        <Stack spacing={2}>
                            <Alert severity="info" sx={{ borderRadius: 2 }}>
                                A filiação e a composição familiar são montadas automaticamente a partir dos
                                familiares cadastrados na ficha da criança.
                            </Alert>
                            <CampoTexto
                                form={form}
                                campo="composicao_familiar"
                                label="Observações sobre a composição familiar (opcional)"
                                rows={3}
                            />
                            <CampoTexto
                                form={form}
                                campo="informacoes_familia"
                                label="Informações relevantes sobre a família"
                            />
                            <CampoTexto form={form} campo="saude_familiares" label="Saúde dos familiares" />
                            <CampoTexto
                                form={form}
                                campo="educacao_familiares"
                                label="Educação e profissionalização dos familiares"
                            />
                            <CampoTexto
                                form={form}
                                campo="assistencia_social_familiares"
                                label="Assistência social dos familiares"
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Acompanhamento da criança
                        </Typography>
                        <Stack spacing={2}>
                            <CampoTexto form={form} campo="saude" label="Saúde" />
                            <CampoTexto
                                form={form}
                                campo="educacao_menor"
                                label="Educação e profissionalização do menor"
                            />
                            <CampoTexto form={form} campo="assistencia_social" label="Assistência social" />
                            <CampoTexto
                                form={form}
                                campo="esporte_cultura_lazer"
                                label="Esporte, cultura e lazer"
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Parecer e encaminhamentos
                        </Typography>
                        <Stack spacing={2}>
                            <CampoTexto
                                form={form}
                                campo="consideracoes_tecnicas"
                                label="Considerações técnicas"
                            />
                            <CampoTexto form={form} campo="plano_acao" label="Plano de ação" />
                            <CampoTexto
                                form={form}
                                campo="providencias_judiciario"
                                label="Providências/demandas ao Judiciário"
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Anexos
                        </Typography>
                        <Alert severity="info" sx={{ borderRadius: 2 }}>
                            O envio e o download de anexos estão temporariamente desativados até a adoção de
                            armazenamento privado durável e varredura antimalware.
                        </Alert>
                    </Card>

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {editando ? 'Salvar alterações' : 'Registrar PIA'}
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </AppLayout>
    );
}

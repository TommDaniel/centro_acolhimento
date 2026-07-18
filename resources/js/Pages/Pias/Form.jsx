import { useEffect, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Alert, Box, Button, Card, Chip, FormControlLabel, IconButton, Stack, Switch, TextField, Typography,
} from '@mui/material';
import { Delete as DeleteIcon, UploadFile as UploadIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaSelect from '@/Components/CriancaSelect';
import { fmtData } from '@/utils/format';

/** Monta o texto de "Dados do acolhimento" a partir do cadastro da criança. */
function montarDadosAcolhimento(crianca) {
    if (!crianca) return '';
    const partes = [];
    if (crianca.data_acolhimento) {
        partes.push(`Data do acolhimento: ${fmtData(crianca.data_acolhimento)}.`);
    }
    if (crianca.motivo_acolhimento) {
        partes.push(`Motivo: ${crianca.motivo_acolhimento}`);
    }
    if (crianca.processo_numero) {
        partes.push(
            `Processo nº ${crianca.processo_numero}` +
                (crianca.vara ? ` — ${crianca.vara}` : '') +
                (crianca.comarca ? ` / ${crianca.comarca}` : '') +
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
    // true quando o usuário tocou no campo — o autopreenchimento para aí.
    const acolhimentoEditado = useRef(false);

    const form = useForm({
        crianca_id: pia?.crianca_id ?? criancaId ?? '',
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

    // No create, pré-preenche os dados do acolhimento a partir da criança
    // selecionada (inclusive no mount, quando criancaId vem pela query string).
    // Nunca sobrescreve edição manual nem roda no edit.
    useEffect(() => {
        if (editando || acolhimentoEditado.current) return;
        const crianca = criancas.find((c) => c.id === Number(form.data.crianca_id));
        form.setData('dados_acolhimento', montarDadosAcolhimento(crianca));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.crianca_id]);

    const [anexos, setAnexos] = useState([]);

    const adicionarAnexos = (files) => {
        setAnexos((prev) => [
            ...prev,
            ...Array.from(files).map((file) => ({ file, descricao: '' })),
        ]);
    };

    const removerAnexo = (indice) => {
        setAnexos((prev) => prev.filter((_, i) => i !== indice));
    };

    const atualizarDescricaoAnexo = (indice, descricao) => {
        setAnexos((prev) => prev.map((a, i) => (i === indice ? { ...a, descricao } : a)));
    };

    const erroAnexos = Object.keys(form.errors)
        .filter((k) => k.startsWith('anexos'))
        .map((k) => form.errors[k])[0];

    const enviar = (e) => {
        e.preventDefault();

        const dados = new FormData();
        Object.entries(form.data).forEach(([chave, valor]) => {
            if (valor !== undefined && valor !== null) {
                dados.append(chave, typeof valor === 'boolean' ? (valor ? '1' : '0') : valor);
            }
        });

        anexos.forEach((anexo, i) => {
            dados.append(`anexos[${i}]`, anexo.file);
            if (anexo.descricao) {
                dados.append(`anexos_descricao[${i}]`, anexo.descricao);
            }
        });

        if (editando) {
            form.post(route('pias.update', pia.id), {
                data: dados,
                forceFormData: true,
            });
        } else {
            form.post(route('pias.store'), {
                data: dados,
                forceFormData: true,
            });
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
                                onChange={(id) => form.setData('crianca_id', id)}
                                error={form.errors.crianca_id}
                            />
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
                        <Stack spacing={2}>
                            <Alert severity="info" sx={{ borderRadius: 2 }}>
                                Você pode anexar assinaturas, documentos ou outros arquivos ao PIA. Os arquivos ficam
                                vinculados a este documento.
                            </Alert>

                            <Box>
                                <Button component="label" variant="outlined" startIcon={<UploadIcon />}>
                                    Selecionar arquivos
                                    <input
                                        hidden
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                        onChange={(e) => {
                                            adicionarAnexos(e.target.files ?? []);
                                            e.target.value = '';
                                        }}
                                    />
                                </Button>
                            </Box>

                            {anexos.length > 0 && (
                                <Stack spacing={1.5}>
                                    {anexos.map((anexo, i) => (
                                        <Box key={i} sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                                            <Chip
                                                size="small"
                                                variant="outlined"
                                                label={anexo.file.name}
                                                sx={{ maxWidth: { xs: 140, sm: 260 } }}
                                            />
                                            <TextField
                                                size="small"
                                                placeholder="Descrição (opcional)"
                                                value={anexo.descricao}
                                                onChange={(e) => atualizarDescricaoAnexo(i, e.target.value)}
                                                sx={{ flex: 1 }}
                                            />
                                            <IconButton size="small" color="error" onClick={() => removerAnexo(i)}>
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        </Box>
                                    ))}
                                </Stack>
                            )}

                            {erroAnexos && (
                                <Typography variant="caption" color="error">
                                    {erroAnexos}
                                </Typography>
                            )}
                        </Stack>
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

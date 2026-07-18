import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Box, Button, Card, Chip, Divider, IconButton, Stack, Table, TableBody, TableCell,
    TableHead, TableRow, Typography,
} from '@mui/material';
import {
    ArrowBack as VoltarIcon,
    Delete as ExcluirIcon,
    Download as DownloadIcon,
    Edit as EditarIcon,
    PictureAsPdf as PdfIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import ConfirmDialog from '@/Components/ConfirmDialog';
import DocMeta from '@/Components/DocMeta';
import KvList from '@/Components/KvList';
import SecoesDoc from '@/Components/SecoesDoc';
import CriancaAvatar from '@/Components/CriancaAvatar';
import { fmtData } from '@/utils/format';

const tipoLabels = { genitora: 'Genitora', genitor: 'Genitor', responsavel: 'Responsável' };

export default function Show({ pia, secoes, identificacao, familiares }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const [confirmando, setConfirmando] = useState(false);
    const [excluindo, setExcluindo] = useState(false);
    const [anexoParaExcluir, setAnexoParaExcluir] = useState(null);
    const [excluindoAnexo, setExcluindoAnexo] = useState(false);

    const todosFamiliares = familiares ?? [];
    const anexos = pia.anexos ?? [];
    const filiacao = todosFamiliares.filter((f) => ['genitora', 'genitor', 'responsavel'].includes(f.tipo));

    const excluir = () => {
        router.delete(route('pias.destroy', pia.id), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
        });
    };

    const excluirAnexo = () => {
        if (!anexoParaExcluir) return;
        router.delete(route('pias.anexos.destroy', anexoParaExcluir.id), {
            preserveScroll: true,
            onStart: () => setExcluindoAnexo(true),
            onFinish: () => {
                setExcluindoAnexo(false);
                setAnexoParaExcluir(null);
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`PIA — ${pia.crianca.nome_completo}`} />
            <PageHeader
                titulo={`PIA — ${pia.crianca.nome_completo}`}
                subtitulo={<DocMeta doc={pia} />}
                acoes={
                    <>
                        <Button
                            component="a"
                            href={route('pias.pdf', pia.id)}
                            target="_blank"
                            variant="outlined"
                            startIcon={<PdfIcon />}
                        >
                            PDF
                        </Button>
                        {podeAlterar(pia) && (
                            <>
                                <Button
                                    component={Link}
                                    href={route('pias.edit', pia.id)}
                                    variant="outlined"
                                    startIcon={<EditarIcon />}
                                >
                                    Editar
                                </Button>
                                <Button
                                    color="error"
                                    variant="outlined"
                                    startIcon={<ExcluirIcon />}
                                    onClick={() => setConfirmando(true)}
                                >
                                    Excluir
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            <Stack spacing={2.5}>
                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Identificação
                    </Typography>
                    <Box
                        sx={{
                            display: 'flex',
                            gap: 2,
                            alignItems: 'flex-start',
                            flexDirection: { xs: 'column', sm: 'row' },
                        }}
                    >
                        <CriancaAvatar crianca={pia.crianca} tamanho={56} />
                        <Box sx={{ flex: 1, minWidth: 0, width: '100%' }}>
                            <KvList dados={identificacao} />
                        </Box>
                    </Box>
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Filiação e responsáveis
                    </Typography>
                    {filiacao.length === 0 ? (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum genitor/responsável cadastrado na ficha da criança.
                        </Typography>
                    ) : (
                        <Stack spacing={1.5}>
                            {filiacao.map((f) => (
                                <Box key={f.id}>
                                    <Box
                                        sx={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: 1,
                                            flexWrap: 'wrap',
                                        }}
                                    >
                                        <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                                            {f.nome}
                                        </Typography>
                                        <Chip
                                            size="small"
                                            variant="outlined"
                                            label={tipoLabels[f.tipo] ?? f.tipo}
                                        />
                                    </Box>
                                    <Typography variant="caption" color="text.secondary">
                                        {[
                                            f.rg ? `RG ${f.rg}` : null,
                                            f.cpf ? `CPF ${f.cpf}` : null,
                                            f.telefone,
                                            f.endereco,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </Typography>
                                </Box>
                            ))}
                        </Stack>
                    )}
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Composição familiar
                    </Typography>
                    {todosFamiliares.length === 0 ? (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum familiar cadastrado.
                        </Typography>
                    ) : (
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell>Nome</TableCell>
                                    <TableCell>Parentesco</TableCell>
                                    <TableCell>Nascimento</TableCell>
                                    <TableCell>Idade</TableCell>
                                    <TableCell>Ocupação</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {todosFamiliares.map((f) => (
                                    <TableRow key={f.id}>
                                        <TableCell>{f.nome}</TableCell>
                                        <TableCell>{f.parentesco || '—'}</TableCell>
                                        <TableCell>
                                            {f.data_nascimento ? fmtData(f.data_nascimento) : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {f.idade !== null && f.idade !== undefined
                                                ? `${f.idade} anos`
                                                : '—'}
                                        </TableCell>
                                        <TableCell>{f.ocupacao || '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    {pia.composicao_familiar && (
                        <Box sx={{ mt: 2.5 }}>
                            <Divider sx={{ mb: 2 }} />
                            <Typography
                                variant="overline"
                                color="primary.dark"
                                sx={{ fontWeight: 700, display: 'block', mb: 0.5 }}
                            >
                                Observações
                            </Typography>
                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                                {pia.composicao_familiar}
                            </Typography>
                        </Box>
                    )}
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Dados do acolhimento
                    </Typography>
                    <Stack spacing={1.5}>
                        {pia.dados_acolhimento && (
                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                                {pia.dados_acolhimento}
                            </Typography>
                        )}
                        {pia.encaminhado_por && (
                            <Typography variant="body2">
                                Encaminhado por: {pia.encaminhado_por}
                            </Typography>
                        )}
                        <Box>
                            <Chip
                                size="small"
                                label={
                                    pia.acolhimento_anterior
                                        ? 'Acolhimento anterior: Sim'
                                        : 'Acolhimento anterior: Não'
                                }
                                color={pia.acolhimento_anterior ? 'warning' : 'default'}
                                sx={{ fontWeight: 600 }}
                            />
                        </Box>
                        {pia.acolhimento_anterior && pia.acolhimento_anterior_detalhes && (
                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                                {pia.acolhimento_anterior_detalhes}
                            </Typography>
                        )}
                    </Stack>
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Conteúdo
                    </Typography>
                    <SecoesDoc secoes={secoes} />
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Anexos
                    </Typography>
                    {anexos.length === 0 ? (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum anexo vinculado a este PIA.
                        </Typography>
                    ) : (
                        <Stack divider={<Divider />} spacing={0}>
                            {anexos.map((anexo) => (
                                <Box
                                    key={anexo.id}
                                    sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 1.25 }}
                                >
                                    <Box sx={{ flex: 1, minWidth: 0 }}>
                                        <Typography variant="body2" noWrap sx={{ fontWeight: 600 }}>
                                            {anexo.nome_original}
                                        </Typography>
                                        {anexo.descricao && (
                                            <Typography variant="caption" color="text.secondary">
                                                {anexo.descricao}
                                            </Typography>
                                        )}
                                    </Box>
                                    <IconButton
                                        component="a"
                                        href={anexo.url}
                                        target="_blank"
                                        size="small"
                                        aria-label={`Baixar ${anexo.nome_original}`}
                                    >
                                        <DownloadIcon fontSize="small" />
                                    </IconButton>
                                    {(usuario?.is_admin || anexo.uploaded_by === usuario?.id) && (
                                        <IconButton
                                            size="small"
                                            color="error"
                                            aria-label={`Excluir ${anexo.nome_original}`}
                                            onClick={() => setAnexoParaExcluir(anexo)}
                                        >
                                            <ExcluirIcon fontSize="small" />
                                        </IconButton>
                                    )}
                                </Box>
                            ))}
                        </Stack>
                    )}
                </Card>

                <Box>
                    <Button
                        component={Link}
                        href={route('criancas.show', pia.crianca.id)}
                        startIcon={<VoltarIcon />}
                        size="small"
                    >
                        Voltar para a ficha
                    </Button>
                </Box>
            </Stack>

            <ConfirmDialog
                aberto={confirmando}
                titulo="Excluir PIA"
                mensagem="Tem certeza que deseja excluir este PIA? Esta ação não pode ser desfeita."
                aoCancelar={() => setConfirmando(false)}
                aoConfirmar={excluir}
                processando={excluindo}
            />

            <ConfirmDialog
                aberto={anexoParaExcluir !== null}
                titulo="Excluir anexo"
                mensagem={`Tem certeza que deseja excluir o anexo "${anexoParaExcluir?.nome_original}"? Esta ação não pode ser desfeita.`}
                aoCancelar={() => setAnexoParaExcluir(null)}
                aoConfirmar={excluirAnexo}
                processando={excluindoAnexo}
            />
        </AppLayout>
    );
}

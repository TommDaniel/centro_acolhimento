import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Box, Button, Card, Stack, Typography } from '@mui/material';
import {
    ArrowBack as VoltarIcon,
    Delete as ExcluirIcon,
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

export default function Show({ report, secoes, identificacao }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const [confirmando, setConfirmando] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const titulo = report.titulo || `Ocorrência nº ${report.id}`;

    const excluir = () => {
        router.delete(route('reports.destroy', report.id), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
        });
    };

    return (
        <AppLayout>
            <Head title={titulo} />
            <PageHeader
                titulo={titulo}
                subtitulo={<DocMeta doc={report} />}
                acoes={
                    <>
                        <Button
                            component="a"
                            href={route('reports.pdf', report.id)}
                            target="_blank"
                            variant="outlined"
                            startIcon={<PdfIcon />}
                        >
                            PDF
                        </Button>
                        {podeAlterar(report) && (
                            <>
                                <Button
                                    component={Link}
                                    href={route('reports.edit', report.id)}
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
                        <CriancaAvatar crianca={report.crianca} tamanho={56} />
                        <Box sx={{ flex: 1, minWidth: 0, width: '100%' }}>
                            <KvList dados={identificacao} />
                        </Box>
                    </Box>
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Conteúdo
                    </Typography>
                    <SecoesDoc secoes={secoes} />
                </Card>

                <Box>
                    <Button
                        component={Link}
                        href={route('criancas.show', report.crianca.id)}
                        startIcon={<VoltarIcon />}
                        size="small"
                    >
                        Voltar para a ficha
                    </Button>
                </Box>
            </Stack>

            <ConfirmDialog
                aberto={confirmando}
                titulo="Excluir ocorrência"
                mensagem="Tem certeza que deseja excluir esta ocorrência? Esta ação não pode ser desfeita."
                aoCancelar={() => setConfirmando(false)}
                aoConfirmar={excluir}
                processando={excluindo}
            />
        </AppLayout>
    );
}

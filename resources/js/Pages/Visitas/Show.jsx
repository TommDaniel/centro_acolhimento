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
import { fmtData, fmtHora } from '@/utils/format';

export default function Show({ visita, secoes, identificacao }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const [confirmando, setConfirmando] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const titulo = `Visita técnica — ${fmtData(visita.data_visita)}${
        visita.hora_visita ? ` às ${fmtHora(visita.hora_visita)}` : ''
    }`;

    const excluir = () => {
        router.delete(route('visitas-tecnicas.destroy', visita.id), {
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
                subtitulo={<DocMeta doc={visita} />}
                acoes={
                    <>
                        <Button
                            component="a"
                            href={route('visitas-tecnicas.pdf', visita.id)}
                            target="_blank"
                            variant="outlined"
                            startIcon={<PdfIcon />}
                        >
                            PDF
                        </Button>
                        {podeAlterar(visita) && (
                            <>
                                <Button
                                    component={Link}
                                    href={route('visitas-tecnicas.edit', visita.id)}
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
                        <CriancaAvatar crianca={visita.crianca} tamanho={56} />
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
                        href={route('criancas.show', visita.crianca.id)}
                        startIcon={<VoltarIcon />}
                        size="small"
                    >
                        Voltar para a ficha
                    </Button>
                </Box>
            </Stack>

            <ConfirmDialog
                aberto={confirmando}
                titulo="Excluir visita técnica"
                mensagem="Tem certeza que deseja excluir este registro de visita técnica? Esta ação não pode ser desfeita."
                aoCancelar={() => setConfirmando(false)}
                aoConfirmar={excluir}
                processando={excluindo}
            />
        </AppLayout>
    );
}

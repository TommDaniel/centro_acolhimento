import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Box, Button, Card, Chip, Stack, Table, TableBody, TableCell,
    TableHead, TableRow, Typography,
} from '@mui/material';
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
import CriancaAvatar from '@/Components/CriancaAvatar';
import { fmtData } from '@/utils/format';

export default function Show({ pertence, identificacao }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const [confirmando, setConfirmando] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const itens = Array.isArray(pertence.itens) ? pertence.itens : [];

    const excluir = () => {
        router.delete(route('pertences.destroy', pertence.id), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Termo de pertences — ${pertence.crianca.nome_completo}`} />
            <PageHeader
                titulo={`Termo de pertences — ${pertence.crianca.nome_completo}`}
                subtitulo={<DocMeta doc={pertence} />}
                acoes={
                    <>
                        <Button
                            component="a"
                            href={route('pertences.pdf', pertence.id)}
                            target="_blank"
                            variant="outlined"
                            startIcon={<PdfIcon />}
                        >
                            PDF
                        </Button>
                        {podeAlterar(pertence) && (
                            <>
                                <Button
                                    component={Link}
                                    href={route('pertences.edit', pertence.id)}
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
                        <CriancaAvatar crianca={pertence.crianca} tamanho={56} />
                        <Box sx={{ flex: 1, minWidth: 0, width: '100%' }}>
                            <KvList dados={identificacao} />
                        </Box>
                    </Box>
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Itens recebidos
                    </Typography>
                    {itens.length === 0 ? (
                        <Typography variant="body2" color="text.secondary">
                            Nenhum item registrado.
                        </Typography>
                    ) : (
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell sx={{ width: 48 }}>#</TableCell>
                                    <TableCell>Descrição</TableCell>
                                    <TableCell sx={{ width: 120 }}>Quantidade</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {itens.map((item, i) => (
                                    <TableRow key={i}>
                                        <TableCell>{i + 1}</TableCell>
                                        <TableCell>{item.descricao}</TableCell>
                                        <TableCell>{item.quantidade || '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Entrega
                    </Typography>
                    <KvList
                        dados={{
                            'Data de entrega': fmtData(pertence.data_entrega),
                            'Assinatura do menor': pertence.assinatura_entrega,
                        }}
                    />
                </Card>

                <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                    <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                        Devolução
                    </Typography>
                    {pertence.devolvido ? (
                        <KvList
                            dados={{
                                'Data de devolução': fmtData(pertence.data_devolucao),
                                'Assinatura do menor': pertence.assinatura_devolucao,
                            }}
                        />
                    ) : (
                        <Box>
                            <Chip
                                size="small"
                                color="warning"
                                label="Ainda não devolvido"
                                sx={{ fontWeight: 600 }}
                            />
                            {pertence.observacao_devolucao && (
                                <Typography variant="body2" sx={{ mt: 1.5, whiteSpace: 'pre-line' }}>
                                    {pertence.observacao_devolucao}
                                </Typography>
                            )}
                        </Box>
                    )}
                </Card>

                <Box>
                    <Button
                        component={Link}
                        href={route('criancas.show', pertence.crianca.id)}
                        startIcon={<VoltarIcon />}
                        size="small"
                    >
                        Voltar para a ficha
                    </Button>
                </Box>
            </Stack>

            <ConfirmDialog
                aberto={confirmando}
                titulo="Excluir termo de pertences"
                mensagem="Tem certeza que deseja excluir este termo de pertences? Esta ação não pode ser desfeita."
                aoCancelar={() => setConfirmando(false)}
                aoConfirmar={excluir}
                processando={excluindo}
            />
        </AppLayout>
    );
}

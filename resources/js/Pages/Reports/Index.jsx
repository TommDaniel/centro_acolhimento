import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, Stack, Typography } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import { fmtData } from '@/utils/format';

export default function Index({ reports }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const itens = reports.data ?? [];

    return (
        <AppLayout>
            <Head title="Ocorrências" />
            <PageHeader
                titulo="Ocorrências"
                subtitulo="Relatórios de ocorrência e situações relevantes"
                acoes={
                    <Button
                        component={Link}
                        href={route('reports.create')}
                        variant="contained"
                        startIcon={<AddIcon />}
                    >
                        Nova ocorrência
                    </Button>
                }
            />

            {itens.length === 0 ? (
                <Card variant="outlined" sx={{ borderRadius: 3 }}>
                    <EmptyState
                        titulo="Nenhuma ocorrência registrada"
                        mensagem="Registre ocorrências e situações relevantes do dia a dia da unidade."
                        acao={
                            <Button
                                component={Link}
                                href={route('reports.create')}
                                variant="contained"
                                startIcon={<AddIcon />}
                            >
                                Nova ocorrência
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <Stack spacing={1.5}>
                    {itens.map((report, i) => (
                        <motion.div
                            key={report.id}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, delay: i * 0.04 }}
                        >
                            <Card variant="outlined" sx={{ p: 2, borderRadius: 3 }}>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        flexDirection: { xs: 'column', sm: 'row' },
                                        alignItems: { sm: 'center' },
                                        gap: 1.5,
                                    }}
                                >
                                    <Box sx={{ flex: 1, minWidth: 0 }}>
                                        <Typography
                                            component={Link}
                                            href={route('criancas.show', report.crianca.id)}
                                            variant="subtitle2"
                                            sx={{
                                                fontWeight: 700,
                                                color: 'text.primary',
                                                textDecoration: 'none',
                                                '&:hover': { color: 'primary.main' },
                                            }}
                                        >
                                            {report.crianca.nome_completo}
                                        </Typography>
                                        <Typography variant="caption" color="text.secondary" display="block">
                                            {fmtData(report.created_at)} · {report.criador?.name}
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', gap: 1, flexShrink: 0 }}>
                                        <Button
                                            component={Link}
                                            href={route('reports.show', report.id)}
                                            size="small"
                                            variant="outlined"
                                        >
                                            Ver
                                        </Button>
                                        <Button
                                            component="a"
                                            href={route('reports.pdf', report.id)}
                                            target="_blank"
                                            size="small"
                                            variant="outlined"
                                        >
                                            PDF
                                        </Button>
                                        {podeAlterar(report) && (
                                            <Button
                                                component={Link}
                                                href={route('reports.edit', report.id)}
                                                size="small"
                                                variant="outlined"
                                            >
                                                Editar
                                            </Button>
                                        )}
                                    </Box>
                                </Box>
                            </Card>
                        </motion.div>
                    ))}
                </Stack>
            )}

            <Paginacao links={reports.links} />
        </AppLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, Chip, Stack, Typography } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import { fmtData } from '@/utils/format';

export default function Index({ pertences }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const itens = pertences.data ?? [];

    return (
        <AppLayout>
            <Head title="Pertences pessoais" />
            <PageHeader
                titulo="Pertences pessoais"
                subtitulo="Termos de entrega e devolução de pertences"
                acoes={
                    <Button
                        component={Link}
                        href={route('pertences.create')}
                        variant="contained"
                        startIcon={<AddIcon />}
                    >
                        Novo termo
                    </Button>
                }
            />

            {itens.length === 0 ? (
                <Card variant="outlined" sx={{ borderRadius: 3 }}>
                    <EmptyState
                        titulo="Nenhum termo de pertences registrado"
                        mensagem="Registre os pertences recebidos na entrada da criança e a devolução no desligamento."
                        acao={
                            <Button
                                component={Link}
                                href={route('pertences.create')}
                                variant="contained"
                                startIcon={<AddIcon />}
                            >
                                Novo termo
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <Stack spacing={1.5}>
                    {itens.map((pertence, i) => (
                        <motion.div
                            key={pertence.id}
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
                                            href={route('criancas.show', pertence.crianca.id)}
                                            variant="subtitle2"
                                            sx={{
                                                fontWeight: 700,
                                                color: 'text.primary',
                                                textDecoration: 'none',
                                                '&:hover': { color: 'primary.main' },
                                            }}
                                        >
                                            {pertence.crianca.nome_completo}
                                        </Typography>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 0.25, flexWrap: 'wrap' }}>
                                            <Typography variant="caption" color="text.secondary">
                                                Entrega em {fmtData(pertence.data_entrega)} ·{' '}
                                                {Array.isArray(pertence.itens) ? pertence.itens.length : 0} item(ns)
                                            </Typography>
                                            <Chip
                                                size="small"
                                                label={pertence.devolvido ? 'Devolvido' : 'Pendente'}
                                                color={pertence.devolvido ? 'success' : 'warning'}
                                                variant={pertence.devolvido ? 'filled' : 'outlined'}
                                                sx={{ fontWeight: 600 }}
                                            />
                                        </Box>
                                    </Box>
                                    <Box sx={{ display: 'flex', gap: 1, flexShrink: 0 }}>
                                        <Button
                                            component={Link}
                                            href={route('pertences.show', pertence.id)}
                                            size="small"
                                            variant="outlined"
                                        >
                                            Ver
                                        </Button>
                                        <Button
                                            component="a"
                                            href={route('pertences.pdf', pertence.id)}
                                            target="_blank"
                                            size="small"
                                            variant="outlined"
                                        >
                                            PDF
                                        </Button>
                                        {podeAlterar(pertence) && (
                                            <Button
                                                component={Link}
                                                href={route('pertences.edit', pertence.id)}
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

            <Paginacao links={pertences.links} />
        </AppLayout>
    );
}

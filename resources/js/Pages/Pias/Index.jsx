import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, Stack, Typography } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import { fmtData } from '@/utils/format';

export default function Index({ pias }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const itens = pias.data ?? [];

    return (
        <AppLayout>
            <Head title="PIA" />
            <PageHeader
                titulo="PIA"
                subtitulo="Planos Individuais de Atendimento"
                acoes={
                    <Button
                        component={Link}
                        href={route('pias.create')}
                        variant="contained"
                        startIcon={<AddIcon />}
                    >
                        Novo PIA
                    </Button>
                }
            />

            {itens.length === 0 ? (
                <Card variant="outlined" sx={{ borderRadius: 3 }}>
                    <EmptyState
                        titulo="Nenhum PIA registrado"
                        mensagem="Registre o Plano Individual de Atendimento de uma criança acolhida."
                        acao={
                            <Button
                                component={Link}
                                href={route('pias.create')}
                                variant="contained"
                                startIcon={<AddIcon />}
                            >
                                Novo PIA
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <Stack spacing={1.5}>
                    {itens.map((pia, i) => (
                        <motion.div
                            key={pia.id}
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
                                            href={route('criancas.show', pia.crianca.id)}
                                            variant="subtitle2"
                                            sx={{
                                                fontWeight: 700,
                                                color: 'text.primary',
                                                textDecoration: 'none',
                                                '&:hover': { color: 'primary.main' },
                                            }}
                                        >
                                            {pia.crianca.nome_completo}
                                        </Typography>
                                        <Typography variant="caption" color="text.secondary" display="block">
                                            {fmtData(pia.created_at)} · {pia.criador?.name}
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', gap: 1, flexShrink: 0 }}>
                                        <Button
                                            component={Link}
                                            href={route('pias.show', pia.id)}
                                            size="small"
                                            variant="outlined"
                                        >
                                            Ver
                                        </Button>
                                        <Button
                                            component="a"
                                            href={route('pias.pdf', pia.id)}
                                            target="_blank"
                                            size="small"
                                            variant="outlined"
                                        >
                                            PDF
                                        </Button>
                                        {podeAlterar(pia) && (
                                            <Button
                                                component={Link}
                                                href={route('pias.edit', pia.id)}
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

            <Paginacao links={pias.links} />
        </AppLayout>
    );
}

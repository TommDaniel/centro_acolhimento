import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, Stack, Typography } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import { fmtData } from '@/utils/format';

export default function Index({ visitas }) {
    const usuario = usePage().props.auth?.user;
    const podeAlterar = (doc) => Boolean(usuario?.is_admin) || doc.setor_id === usuario?.setor_id;

    const itens = visitas.data ?? [];

    return (
        <AppLayout>
            <Head title="Visitas técnicas" />
            <PageHeader
                titulo="Visitas técnicas"
                subtitulo="Visitas da família, ao núcleo familiar e de órgãos externos"
                acoes={
                    <Button
                        component={Link}
                        href={route('visitas-tecnicas.create')}
                        variant="contained"
                        startIcon={<AddIcon />}
                    >
                        Nova visita
                    </Button>
                }
            />

            {itens.length === 0 ? (
                <Card variant="outlined" sx={{ borderRadius: 3 }}>
                    <EmptyState
                        titulo="Nenhuma visita técnica registrada"
                        mensagem="Registre visitas da família, ao núcleo familiar ou de órgãos como Conselho Tutelar e Judiciário."
                        acao={
                            <Button
                                component={Link}
                                href={route('visitas-tecnicas.create')}
                                variant="contained"
                                startIcon={<AddIcon />}
                            >
                                Nova visita
                            </Button>
                        }
                    />
                </Card>
            ) : (
                <Stack spacing={1.5}>
                    {itens.map((visita, i) => (
                        <motion.div
                            key={visita.id}
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
                                            href={route('criancas.show', visita.crianca.id)}
                                            variant="subtitle2"
                                            sx={{
                                                fontWeight: 700,
                                                color: 'text.primary',
                                                textDecoration: 'none',
                                                '&:hover': { color: 'primary.main' },
                                            }}
                                        >
                                            {visita.crianca.nome_completo}
                                        </Typography>
                                        <Typography variant="caption" color="text.secondary" display="block">
                                            {fmtData(visita.data_visita)} · {visita.criador?.name}
                                        </Typography>
                                    </Box>
                                    <Box sx={{ display: 'flex', gap: 1, flexShrink: 0 }}>
                                        <Button
                                            component={Link}
                                            href={route('visitas-tecnicas.show', visita.id)}
                                            size="small"
                                            variant="outlined"
                                        >
                                            Ver
                                        </Button>
                                        <Button
                                            component="a"
                                            href={route('visitas-tecnicas.pdf', visita.id)}
                                            target="_blank"
                                            size="small"
                                            variant="outlined"
                                        >
                                            PDF
                                        </Button>
                                        {podeAlterar(visita) && (
                                            <Button
                                                component={Link}
                                                href={route('visitas-tecnicas.edit', visita.id)}
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

            <Paginacao links={visitas.links} />
        </AppLayout>
    );
}

import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Avatar, Box, Button, Card, Chip, IconButton, Typography,
} from '@mui/material';
import { Add as AddIcon, Edit as EditIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import EmptyState from '@/Components/EmptyState';
import { iniciais } from '@/utils/format';

function CardMembro({ user, isAdmin, indice }) {
    const detalhes = [user.cargo, user.email, user.telefone].filter(Boolean);

    return (
        <motion.div
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.2, delay: indice * 0.04 }}
        >
            <Card
                variant="outlined"
                sx={{
                    p: 2, borderRadius: 3, height: '100%',
                    display: 'flex', alignItems: 'center', gap: 1.5,
                    transition: 'box-shadow .2s, transform .2s',
                    '&:hover': { boxShadow: 3, transform: 'translateY(-2px)' },
                }}
            >
                <Avatar sx={{ bgcolor: 'primary.main', fontSize: 14, fontWeight: 700 }}>
                    {iniciais(user.name)}
                </Avatar>
                <Box sx={{ minWidth: 0, flex: 1 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap' }}>
                        <Typography variant="body2" sx={{ fontWeight: 700 }} noWrap>
                            {user.name}
                        </Typography>
                        {user.is_admin && <Chip label="Admin" size="small" color="primary" />}
                    </Box>
                    {detalhes.map((detalhe) => (
                        <Typography key={detalhe} variant="caption" color="text.secondary" display="block" noWrap>
                            {detalhe}
                        </Typography>
                    ))}
                </Box>
                {isAdmin && (
                    <IconButton
                        component={Link}
                        href={route('equipe.edit', user.id)}
                        size="small"
                        aria-label={`Editar ${user.name}`}
                    >
                        <EditIcon fontSize="small" />
                    </IconButton>
                )}
            </Card>
        </motion.div>
    );
}

export default function Index({ grupos }) {
    const isAdmin = Boolean(usePage().props.auth?.user?.is_admin);
    const entradas = Object.entries(grupos ?? {});

    return (
        <AppLayout>
            <Head title="Equipe" />
            <PageHeader
                titulo="Equipe"
                subtitulo="Usuários do sistema por setor"
                acoes={
                    isAdmin && (
                        <Button
                            component={Link}
                            href={route('equipe.create')}
                            variant="contained"
                            startIcon={<AddIcon />}
                        >
                            Novo usuário
                        </Button>
                    )
                }
            />

            {entradas.length === 0 ? (
                <EmptyState titulo="Nenhum usuário cadastrado." />
            ) : (
                entradas.map(([nomeSetor, users]) => (
                    <Box key={nomeSetor} sx={{ mb: 3 }}>
                        <Typography variant="overline" color="text.secondary" sx={{ fontWeight: 700 }}>
                            {nomeSetor}
                        </Typography>
                        <Box
                            sx={{
                                display: 'grid',
                                gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' },
                                gap: 2,
                                mt: 1,
                            }}
                        >
                            {users.map((user, i) => (
                                <CardMembro key={user.id} user={user} isAdmin={isAdmin} indice={i} />
                            ))}
                        </Box>
                    </Box>
                ))
            )}
        </AppLayout>
    );
}

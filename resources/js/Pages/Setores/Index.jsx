import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, TextField, Typography } from '@mui/material';
import { Add as AddIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';

const gradeSetores = {
    display: 'grid',
    gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' },
    gap: 2,
};

function FormNovoSetor() {
    const form = useForm({ nome: '', descricao: '' });

    const enviar = (e) => {
        e.preventDefault();
        form.post(route('setores.store'), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card
            component="form"
            onSubmit={enviar}
            variant="outlined"
            sx={{ p: 2, borderRadius: 3, borderStyle: 'dashed', display: 'flex', flexDirection: 'column', gap: 1.5 }}
        >
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                Novo setor
            </Typography>
            <TextField
                label="Nome *"
                size="small"
                fullWidth
                value={form.data.nome}
                onChange={(e) => form.setData('nome', e.target.value)}
                error={Boolean(form.errors.nome)}
                helperText={form.errors.nome}
            />
            <TextField
                label="Descrição"
                size="small"
                fullWidth
                multiline
                rows={2}
                value={form.data.descricao}
                onChange={(e) => form.setData('descricao', e.target.value)}
                error={Boolean(form.errors.descricao)}
                helperText={form.errors.descricao}
            />
            <Box>
                <Button
                    type="submit"
                    variant="contained"
                    size="small"
                    startIcon={<AddIcon />}
                    disabled={form.processing}
                >
                    Criar setor
                </Button>
            </Box>
        </Card>
    );
}

export default function Index({ setores }) {
    const isAdmin = Boolean(usePage().props.auth?.user?.is_admin);

    return (
        <AppLayout>
            <Head title="Setores" />
            <PageHeader titulo="Setores" subtitulo="Áreas de atuação da equipe" />

            <Box sx={gradeSetores}>
                {setores.map((setor, i) => (
                    <motion.div
                        key={setor.id}
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.2, delay: i * 0.04 }}
                    >
                        <Card
                            variant="outlined"
                            sx={{
                                p: 2, borderRadius: 3, height: '100%',
                                display: 'flex', flexDirection: 'column', gap: 0.5,
                                transition: 'box-shadow .2s, transform .2s',
                                '&:hover': { boxShadow: 3, transform: 'translateY(-2px)' },
                            }}
                        >
                            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>
                                {setor.nome}
                            </Typography>
                            <Typography variant="body2" color="text.secondary" sx={{ flex: 1 }}>
                                {setor.descricao || 'Sem descrição.'}
                            </Typography>
                            <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1 }}>
                                {setor.users_count} {setor.users_count === 1 ? 'membro' : 'membros'}
                            </Typography>
                            <Box sx={{ mt: 1 }}>
                                <Button
                                    component={Link}
                                    href={route('setores.show', setor.id)}
                                    size="small"
                                    variant="outlined"
                                >
                                    Ver setor
                                </Button>
                            </Box>
                        </Card>
                    </motion.div>
                ))}

                {isAdmin && (
                    <motion.div
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.2, delay: setores.length * 0.04 }}
                    >
                        <FormNovoSetor />
                    </motion.div>
                )}
            </Box>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Accordion, AccordionDetails, AccordionSummary, Avatar, Box, Button, Card,
    Chip, Divider, Stack, TextField, Typography,
} from '@mui/material';
import {
    Delete as DeleteIcon,
    ExpandMore as ExpandMoreIcon,
    Save as SaveIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import { fmtData, iniciais } from '@/utils/format';

const secoesDocs = [
    { chave: 'pias', titulo: 'PIAs', rota: 'pias.show', campoData: 'created_at' },
    { chave: 'reports', titulo: 'Ocorrências', rota: 'reports.show', campoData: 'created_at' },
    { chave: 'visitas', titulo: 'Visitas técnicas', rota: 'visitas-tecnicas.show', campoData: 'data_visita' },
    { chave: 'pertences', titulo: 'Pertences', rota: 'pertences.show', campoData: 'created_at' },
];

function FormEditarSetor({ setor }) {
    const form = useForm({ nome: setor.nome ?? '', descricao: setor.descricao ?? '' });

    const enviar = (e) => {
        e.preventDefault();
        form.put(route('setores.update', setor.id), { preserveScroll: true });
    };

    return (
        <Box component="form" onSubmit={enviar} sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
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
                    startIcon={<SaveIcon />}
                    disabled={form.processing}
                >
                    Salvar alterações
                </Button>
            </Box>
        </Box>
    );
}

function CardMembros({ users }) {
    return (
        <Card variant="outlined" sx={{ borderRadius: 3, p: 2 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
                Membros ({users.length})
            </Typography>
            {users.length === 0 ? (
                <EmptyState titulo="Nenhum membro neste setor." />
            ) : (
                <Stack divider={<Divider flexItem />} spacing={1.5}>
                    {users.map((user, i) => (
                        <motion.div
                            key={user.id}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, delay: i * 0.04 }}
                        >
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, pt: i === 0 ? 0 : 1.5 }}>
                                <Avatar sx={{ bgcolor: 'primary.main', fontSize: 14, fontWeight: 700 }}>
                                    {iniciais(user.name)}
                                </Avatar>
                                <Box sx={{ minWidth: 0 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap' }}>
                                        <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                            {user.name}
                                        </Typography>
                                        {user.is_admin && <Chip label="Admin" size="small" color="primary" />}
                                    </Box>
                                    <Typography variant="caption" color="text.secondary" display="block">
                                        {[user.cargo, user.email, user.telefone].filter(Boolean).join(' · ')}
                                    </Typography>
                                </Box>
                            </Box>
                        </motion.div>
                    ))}
                </Stack>
            )}
        </Card>
    );
}

function CardDocumentos({ titulo, docs, rota, campoData }) {
    return (
        <Card variant="outlined" sx={{ borderRadius: 3, p: 2, height: '100%' }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
                {titulo}
            </Typography>
            {docs.length === 0 ? (
                <Typography variant="body2" color="text.secondary">
                    Nenhum documento.
                </Typography>
            ) : (
                <Stack divider={<Divider flexItem />} spacing={1}>
                    {docs.map((doc, i) => (
                        <motion.div
                            key={doc.id}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, delay: i * 0.04 }}
                        >
                            <Box sx={{ pt: i === 0 ? 0 : 1 }}>
                                <Typography
                                    component={Link}
                                    href={route(rota, doc.id)}
                                    variant="body2"
                                    sx={{ fontWeight: 600, color: 'primary.main', textDecoration: 'none', '&:hover': { textDecoration: 'underline' } }}
                                >
                                    {doc.crianca?.nome_completo ?? 'Criança não identificada'}
                                </Typography>
                                <Typography variant="caption" color="text.secondary" display="block">
                                    {fmtData(doc[campoData])}
                                    {doc.criador?.name ? ` por ${doc.criador.name}` : ''}
                                </Typography>
                            </Box>
                        </motion.div>
                    ))}
                </Stack>
            )}
        </Card>
    );
}

export default function Show({ setor, subtopicos }) {
    const isAdmin = Boolean(usePage().props.auth?.user?.is_admin);
    const [confirmarExclusao, setConfirmarExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const excluir = () => {
        setExcluindo(true);
        router.delete(route('setores.destroy', setor.id), {
            onFinish: () => setExcluindo(false),
        });
    };

    return (
        <AppLayout>
            <Head title={setor.nome} />
            <PageHeader titulo={setor.nome} subtitulo={setor.descricao} />

            <Stack spacing={2}>
                {isAdmin && (
                    <Accordion variant="outlined" sx={{ borderRadius: 3, '&:before': { display: 'none' } }}>
                        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
                                Editar setor
                            </Typography>
                        </AccordionSummary>
                        <AccordionDetails>
                            <Stack spacing={2}>
                                <FormEditarSetor setor={setor} />
                                <Divider />
                                <Box>
                                    <Button
                                        color="error"
                                        variant="outlined"
                                        size="small"
                                        startIcon={<DeleteIcon />}
                                        onClick={() => setConfirmarExclusao(true)}
                                    >
                                        Excluir setor
                                    </Button>
                                </Box>
                            </Stack>
                        </AccordionDetails>
                    </Accordion>
                )}

                <CardMembros users={setor.users ?? []} />

                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' },
                        gap: 2,
                    }}
                >
                    {secoesDocs.map((secao) => (
                        <CardDocumentos
                            key={secao.chave}
                            titulo={secao.titulo}
                            docs={subtopicos?.[secao.chave] ?? []}
                            rota={secao.rota}
                            campoData={secao.campoData}
                        />
                    ))}
                </Box>
            </Stack>

            <ConfirmDialog
                aberto={confirmarExclusao}
                titulo="Excluir setor"
                mensagem={`Tem certeza que deseja excluir o setor "${setor.nome}"? Os membros ficarão sem setor.`}
                aoCancelar={() => setConfirmarExclusao(false)}
                aoConfirmar={excluir}
                processando={excluindo}
            />
        </AppLayout>
    );
}

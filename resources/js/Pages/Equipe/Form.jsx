import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Box, Button, Card, Divider, MenuItem, TextField, Typography,
} from '@mui/material';
import { Delete as DeleteIcon, Save as SaveIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import ConfirmDialog from '@/Components/ConfirmDialog';

export default function Form({ usuario, setores }) {
    const editando = Boolean(usuario);
    const isAdmin = Boolean(usePage().props.auth?.user?.is_admin);
    const [confirmarExclusao, setConfirmarExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);

    const form = useForm({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        password: '',
        password_confirmation: '',
        setor_id: usuario?.setor_id ?? '',
        role: usuario?.role ?? 'servidor',
        cargo: usuario?.cargo ?? '',
        telefone: usuario?.telefone ?? '',
    });

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.put(route('equipe.update', usuario.id));
        } else {
            form.post(route('equipe.store'));
        }
    };

    const excluir = () => {
        setExcluindo(true);
        router.delete(route('equipe.destroy', usuario.id), {
            onFinish: () => setExcluindo(false),
        });
    };

    return (
        <AppLayout>
            <Head title={editando ? 'Editar usuário' : 'Novo usuário'} />
            <PageHeader titulo={editando ? 'Editar usuário' : 'Novo usuário'} />

            <Card variant="outlined" sx={{ borderRadius: 3, p: { xs: 2, sm: 3 } }}>
                <Box
                    component="form"
                    onSubmit={enviar}
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' },
                        gap: 2,
                    }}
                >
                    <TextField
                        label="Nome *"
                        fullWidth
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        error={Boolean(form.errors.name)}
                        helperText={form.errors.name}
                    />
                    <TextField
                        label="E-mail *"
                        type="email"
                        fullWidth
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={Boolean(form.errors.email)}
                        helperText={form.errors.email}
                    />
                    <TextField
                        label={editando ? 'Nova senha (deixe em branco para manter)' : 'Senha *'}
                        type="password"
                        autoComplete="new-password"
                        fullWidth
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        error={Boolean(form.errors.password)}
                        helperText={form.errors.password}
                    />
                    <TextField
                        label="Confirmar senha"
                        type="password"
                        autoComplete="new-password"
                        fullWidth
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                        error={Boolean(form.errors.password_confirmation)}
                        helperText={form.errors.password_confirmation}
                    />
                    <TextField
                        label="Setor"
                        select
                        fullWidth
                        value={form.data.setor_id}
                        onChange={(e) => form.setData('setor_id', e.target.value)}
                        error={Boolean(form.errors.setor_id)}
                        helperText={form.errors.setor_id}
                    >
                        <MenuItem value="">Sem setor</MenuItem>
                        {setores.map((setor) => (
                            <MenuItem key={setor.id} value={setor.id}>
                                {setor.nome}
                            </MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        label="Perfil *"
                        select
                        fullWidth
                        value={form.data.role}
                        onChange={(e) => form.setData('role', e.target.value)}
                        error={Boolean(form.errors.role)}
                        helperText={form.errors.role}
                    >
                        <MenuItem value="servidor">Servidor</MenuItem>
                        <MenuItem value="admin">Administrador</MenuItem>
                    </TextField>
                    <TextField
                        label="Cargo"
                        fullWidth
                        value={form.data.cargo}
                        onChange={(e) => form.setData('cargo', e.target.value)}
                        error={Boolean(form.errors.cargo)}
                        helperText={form.errors.cargo}
                    />
                    <TextField
                        label="Telefone"
                        fullWidth
                        value={form.data.telefone}
                        onChange={(e) => form.setData('telefone', e.target.value)}
                        error={Boolean(form.errors.telefone)}
                        helperText={form.errors.telefone}
                    />

                    <Box sx={{ gridColumn: '1 / -1', display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                        <Button
                            type="submit"
                            variant="contained"
                            startIcon={<SaveIcon />}
                            disabled={form.processing}
                        >
                            {editando ? 'Salvar alterações' : 'Criar usuário'}
                        </Button>
                    </Box>
                </Box>

                {editando && isAdmin && (
                    <>
                        <Divider sx={{ my: 3 }} />
                        <Box>
                            <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
                                Zona de risco
                            </Typography>
                            <Button
                                color="error"
                                variant="outlined"
                                startIcon={<DeleteIcon />}
                                onClick={() => setConfirmarExclusao(true)}
                            >
                                Remover usuário
                            </Button>
                        </Box>
                    </>
                )}
            </Card>

            {editando && (
                <ConfirmDialog
                    aberto={confirmarExclusao}
                    titulo="Remover usuário"
                    mensagem={`Tem certeza que deseja remover "${usuario.name}"? Esta ação não pode ser desfeita.`}
                    aoCancelar={() => setConfirmarExclusao(false)}
                    aoConfirmar={excluir}
                    processando={excluindo}
                />
            )}
        </AppLayout>
    );
}

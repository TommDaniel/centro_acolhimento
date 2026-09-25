import { Head, useForm } from '@inertiajs/react';
import {
    Box, Button, Card, MenuItem, TextField,
} from '@mui/material';
import { Save as SaveIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';

export default function Form({ usuario, setores }) {
    const editando = Boolean(usuario);
    const possuiMfaAtivo = Boolean(usuario?.has_active_mfa);
    const situacaoEfetiva = usuario?.effective_status ?? usuario?.status ?? 'pendente_mfa';
    const form = useForm({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        password: '',
        password_confirmation: '',
        setor_id: usuario?.setor_id ?? '',
        role: usuario?.role ?? 'equipe_tecnica',
        status: situacaoEfetiva,
        cargo: usuario?.cargo ?? '',
        telefone: usuario?.telefone ?? '',
    });
    const situacoes = possuiMfaAtivo
        ? [
            { value: 'ativa', label: 'Ativa' },
            { value: 'inativa', label: 'Inativa' },
        ]
        : [
            { value: 'pendente_mfa', label: 'Pendente de MFA' },
            { value: 'inativa', label: 'Inativa' },
        ];
    const ajudaSituacao = possuiMfaAtivo
        ? 'A conta pode ser inativada; a reativação mantém o segundo fator já vinculado.'
        : 'Pendente de MFA significa que a pessoa ainda precisa entrar e vincular o aplicativo autenticador antes de acessar dados assistenciais.';

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.put(route('equipe.update', usuario.id));
        } else {
            form.post(route('equipe.store'));
        }
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
                        <MenuItem value="equipe_tecnica">Equipe técnica</MenuItem>
                        <MenuItem value="administradora">Administradora</MenuItem>
                    </TextField>
                    {editando ? (
                        <TextField
                            label="Situação da conta *"
                            select
                            fullWidth
                            value={form.data.status}
                            onChange={(e) => form.setData('status', e.target.value)}
                            error={Boolean(form.errors.status)}
                            helperText={form.errors.status ?? ajudaSituacao}
                        >
                            {situacoes.map((situacao) => (
                                <MenuItem key={situacao.value} value={situacao.value}>
                                    {situacao.label}
                                </MenuItem>
                            ))}
                        </TextField>
                    ) : (
                        <TextField
                            label="Situação inicial"
                            fullWidth
                            value="Pendente de ativação do MFA"
                            disabled
                            helperText="A conta será ativada depois que a pessoa vincular o aplicativo autenticador."
                        />
                    )}
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

            </Card>
        </AppLayout>
    );
}

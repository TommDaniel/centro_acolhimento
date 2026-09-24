import { Head, router } from '@inertiajs/react';
import {
    Box, Card, Chip, MenuItem, Stack, TextField, Typography,
} from '@mui/material';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Paginacao from '@/Components/Paginacao';
import { fmtDataHora } from '@/utils/format';

const rotulos = {
    'auth.login_succeeded': 'Login realizado',
    'auth.login_failed': 'Tentativa de login negada',
    'access.denied': 'Acesso negado',
    'crianca.created': 'Cadastro criado',
    'crianca.updated': 'Cadastro atualizado',
    'crianca.viewed': 'Ficha consultada',
    'user.created': 'Conta criada',
    'user.updated': 'Conta atualizada',
};

export default function Index({ events, filters, actions, actors }) {
    const filtrar = (campo, valor) => {
        router.get(route('auditoria.index'), { ...filters, [campo]: valor || undefined }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Auditoria" />
            <PageHeader
                titulo="Auditoria funcional"
                subtitulo="Consulta somente leitura de autoria, ação e horário. Conteúdos sensíveis não são copiados para esta trilha."
            />

            <Card variant="outlined" sx={{ p: 2, mb: 2, borderRadius: 3 }}>
                <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
                    <TextField
                        select
                        fullWidth
                        label="Ação"
                        value={filters.action ?? ''}
                        onChange={(event) => filtrar('action', event.target.value)}
                    >
                        <MenuItem value="">Todas</MenuItem>
                        {actions.map((action) => (
                            <MenuItem key={action} value={action}>{rotulos[action] ?? action}</MenuItem>
                        ))}
                    </TextField>
                    <TextField
                        select
                        fullWidth
                        label="Resultado"
                        value={filters.result ?? ''}
                        onChange={(event) => filtrar('result', event.target.value)}
                    >
                        <MenuItem value="">Todos</MenuItem>
                        <MenuItem value="success">Concluído</MenuItem>
                        <MenuItem value="denied">Negado</MenuItem>
                    </TextField>
                    <TextField
                        select
                        fullWidth
                        label="Usuária"
                        value={filters.actor_id ?? ''}
                        onChange={(event) => filtrar('actor_id', event.target.value)}
                    >
                        <MenuItem value="">Todas</MenuItem>
                        {actors.map((actor) => (
                            <MenuItem key={actor.id} value={actor.id}>{actor.name}</MenuItem>
                        ))}
                    </TextField>
                </Stack>
            </Card>

            {events.data.length === 0 ? (
                <EmptyState titulo="Nenhum evento encontrado." />
            ) : (
                <Stack spacing={1.5}>
                    {events.data.map((event) => (
                        <Card key={event.id} variant="outlined" sx={{ p: 2, borderRadius: 3 }}>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1} alignItems={{ sm: 'center' }}>
                                <Box sx={{ flex: 1, minWidth: 0 }}>
                                    <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                        {rotulos[event.action] ?? event.action}
                                    </Typography>
                                    <Typography variant="caption" color="text.secondary">
                                        {event.actor?.name ?? 'Ator não identificado'} · {fmtDataHora(event.occurred_at)}
                                    </Typography>
                                    {event.subject_type && (
                                        <Typography variant="caption" display="block" color="text.secondary">
                                            Alvo: {event.subject_type}{event.subject_id ? ` #${event.subject_id}` : ''}
                                        </Typography>
                                    )}
                                    {event.changed_fields.length > 0 && (
                                        <Typography variant="caption" display="block" color="text.secondary">
                                            Campos alterados: {event.changed_fields.join(', ')}
                                        </Typography>
                                    )}
                                </Box>
                                <Chip
                                    size="small"
                                    color={event.result === 'success' ? 'success' : 'error'}
                                    label={event.result === 'success' ? 'Concluído' : 'Negado'}
                                />
                            </Stack>
                        </Card>
                    ))}
                    <Paginacao links={events.links} />
                </Stack>
            )}
        </AppLayout>
    );
}

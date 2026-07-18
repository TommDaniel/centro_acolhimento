import { Head, useForm } from '@inertiajs/react';
import { Box, Button, Card, MenuItem, Stack, TextField, Typography } from '@mui/material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaSelect from '@/Components/CriancaSelect';

const tipos = [
    'Visita da família (recebida)',
    'Visita ao núcleo familiar',
    'Conselho Tutelar',
    'Judiciário / Ministério Público',
    'Outra',
];

export default function Form({ visita, criancas, criancaId, hoje }) {
    const editando = Boolean(visita);

    const form = useForm({
        crianca_id: visita?.crianca_id ?? criancaId ?? '',
        numero_oficio: visita?.numero_oficio ?? '',
        data_visita: visita?.data_visita?.slice(0, 10) ?? hoje ?? '',
        hora_visita: visita?.hora_visita?.slice(0, 5) ?? '',
        tipo: visita?.tipo ?? '',
        visitante: visita?.visitante ?? '',
        local: visita?.local ?? '',
        motivo: visita?.motivo ?? '',
        relato: visita?.relato ?? '',
        encaminhamentos: visita?.encaminhamentos ?? '',
    });

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.put(route('visitas-tecnicas.update', visita.id));
        } else {
            form.post(route('visitas-tecnicas.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={editando ? 'Editar visita técnica' : 'Nova visita técnica'} />
            <PageHeader
                titulo={editando ? 'Editar visita técnica' : 'Nova visita técnica'}
                subtitulo="Registro de visita da família, ao núcleo familiar ou de órgãos externos"
            />

            <Box component="form" onSubmit={enviar}>
                <Stack spacing={2.5}>
                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Detalhes da visita
                        </Typography>
                        <Stack spacing={2}>
                            <CriancaSelect
                                criancas={criancas}
                                value={form.data.crianca_id}
                                onChange={(id) => form.setData('crianca_id', id)}
                                error={form.errors.crianca_id}
                            />
                            <TextField
                                label="Nº do ofício (opcional — deixe em branco para gerar automaticamente)"
                                fullWidth
                                value={form.data.numero_oficio}
                                onChange={(e) => form.setData('numero_oficio', e.target.value)}
                                error={Boolean(form.errors.numero_oficio)}
                                helperText={form.errors.numero_oficio}
                            />
                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr 1fr' },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    type="date"
                                    label="Data da visita *"
                                    fullWidth
                                    value={form.data.data_visita}
                                    onChange={(e) => form.setData('data_visita', e.target.value)}
                                    error={Boolean(form.errors.data_visita)}
                                    helperText={form.errors.data_visita}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <TextField
                                    type="time"
                                    label="Hora"
                                    fullWidth
                                    value={form.data.hora_visita}
                                    onChange={(e) => form.setData('hora_visita', e.target.value)}
                                    error={Boolean(form.errors.hora_visita)}
                                    helperText={form.errors.hora_visita}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <TextField
                                    select
                                    label="Tipo de visita"
                                    fullWidth
                                    value={form.data.tipo}
                                    onChange={(e) => form.setData('tipo', e.target.value)}
                                    error={Boolean(form.errors.tipo)}
                                    helperText={form.errors.tipo}
                                >
                                    {tipos.map((tipo) => (
                                        <MenuItem key={tipo} value={tipo}>
                                            {tipo}
                                        </MenuItem>
                                    ))}
                                </TextField>
                            </Box>
                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    label="Visitante / responsável"
                                    fullWidth
                                    value={form.data.visitante}
                                    onChange={(e) => form.setData('visitante', e.target.value)}
                                    error={Boolean(form.errors.visitante)}
                                    helperText={form.errors.visitante}
                                />
                                <TextField
                                    label="Local"
                                    fullWidth
                                    value={form.data.local}
                                    onChange={(e) => form.setData('local', e.target.value)}
                                    error={Boolean(form.errors.local)}
                                    helperText={form.errors.local}
                                />
                            </Box>
                            <TextField
                                label="Motivo"
                                fullWidth
                                multiline
                                rows={2}
                                value={form.data.motivo}
                                onChange={(e) => form.setData('motivo', e.target.value)}
                                error={Boolean(form.errors.motivo)}
                                helperText={form.errors.motivo}
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Relato da visita
                        </Typography>
                        <Stack spacing={2}>
                            <TextField
                                label="Relato *"
                                fullWidth
                                multiline
                                rows={6}
                                value={form.data.relato}
                                onChange={(e) => form.setData('relato', e.target.value)}
                                error={Boolean(form.errors.relato)}
                                helperText={form.errors.relato}
                            />
                            <TextField
                                label="Encaminhamentos"
                                fullWidth
                                multiline
                                rows={3}
                                value={form.data.encaminhamentos}
                                onChange={(e) => form.setData('encaminhamentos', e.target.value)}
                                error={Boolean(form.errors.encaminhamentos)}
                                helperText={form.errors.encaminhamentos}
                            />
                        </Stack>
                    </Card>

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {editando ? 'Salvar alterações' : 'Registrar visita'}
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </AppLayout>
    );
}

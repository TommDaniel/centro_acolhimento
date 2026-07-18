import { Head, useForm } from '@inertiajs/react';
import { Box, Button, Card, Stack, TextField, Typography } from '@mui/material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaSelect from '@/Components/CriancaSelect';

export default function Form({ report, criancas, criancaId }) {
    const editando = Boolean(report);

    const form = useForm({
        crianca_id: report?.crianca_id ?? criancaId ?? '',
        numero_oficio: report?.numero_oficio ?? '',
        titulo: report?.titulo ?? '',
        introducao: report?.introducao ?? '',
        desenvolvimento: report?.desenvolvimento ?? '',
        consideracoes: report?.consideracoes ?? '',
    });

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.put(route('reports.update', report.id));
        } else {
            form.post(route('reports.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={editando ? 'Editar ocorrência' : 'Nova ocorrência'} />
            <PageHeader
                titulo={editando ? 'Editar ocorrência' : 'Nova ocorrência'}
                subtitulo="Relatório de ocorrência ou situação relevante"
            />

            <Box component="form" onSubmit={enviar}>
                <Stack spacing={2.5}>
                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Identificação
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
                            <TextField
                                label="Título"
                                fullWidth
                                value={form.data.titulo}
                                onChange={(e) => form.setData('titulo', e.target.value)}
                                error={Boolean(form.errors.titulo)}
                                helperText={form.errors.titulo}
                            />
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Conteúdo
                        </Typography>
                        <Stack spacing={2}>
                            <TextField
                                label="Introdução ao ocorrido *"
                                fullWidth
                                multiline
                                rows={4}
                                value={form.data.introducao}
                                onChange={(e) => form.setData('introducao', e.target.value)}
                                error={Boolean(form.errors.introducao)}
                                helperText={form.errors.introducao}
                            />
                            <TextField
                                label="Desenvolvimento *"
                                fullWidth
                                multiline
                                rows={6}
                                value={form.data.desenvolvimento}
                                onChange={(e) => form.setData('desenvolvimento', e.target.value)}
                                error={Boolean(form.errors.desenvolvimento)}
                                helperText={form.errors.desenvolvimento}
                            />
                            <TextField
                                label="Considerações / o que está sendo solicitado"
                                fullWidth
                                multiline
                                rows={4}
                                value={form.data.consideracoes}
                                onChange={(e) => form.setData('consideracoes', e.target.value)}
                                error={Boolean(form.errors.consideracoes)}
                                helperText={form.errors.consideracoes}
                            />
                        </Stack>
                    </Card>

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {editando ? 'Salvar alterações' : 'Registrar ocorrência'}
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </AppLayout>
    );
}

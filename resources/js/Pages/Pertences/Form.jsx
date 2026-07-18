import { Head, useForm } from '@inertiajs/react';
import {
    Box, Button, Card, FormControlLabel, FormHelperText, IconButton,
    Stack, Switch, TextField, Typography,
} from '@mui/material';
import { Add as AddIcon, Delete as DeleteIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaSelect from '@/Components/CriancaSelect';

export default function Form({ pertence, criancas, criancaId, hoje }) {
    const editando = Boolean(pertence);

    // Normaliza: o backend grava quantidade null quando a qtd. fica vazia,
    // e o TextField controlado precisa sempre de string.
    const itensIniciais = pertence?.itens?.length
        ? pertence.itens.map((item) => ({
              descricao: item.descricao ?? '',
              quantidade: item.quantidade ?? '',
          }))
        : [{ descricao: '', quantidade: '' }];

    const form = useForm({
        crianca_id: pertence?.crianca_id ?? criancaId ?? '',
        numero_oficio: pertence?.numero_oficio ?? '',
        data_entrega: pertence?.data_entrega?.slice(0, 10) ?? hoje ?? '',
        assinatura_entrega: pertence?.assinatura_entrega ?? '',
        itens: itensIniciais,
        devolvido: !!pertence?.devolvido,
        data_devolucao: pertence?.data_devolucao?.slice(0, 10) ?? '',
        assinatura_devolucao: pertence?.assinatura_devolucao ?? '',
        observacao_devolucao: pertence?.observacao_devolucao ?? '',
    });

    const erroItens =
        Boolean(form.errors.itens) || Object.keys(form.errors).some((k) => k.startsWith('itens.'));

    const atualizarItem = (i, campo, valor) => {
        form.setData(
            'itens',
            form.data.itens.map((item, idx) => (idx === i ? { ...item, [campo]: valor } : item))
        );
    };

    const adicionarItem = () => {
        form.setData('itens', [...form.data.itens, { descricao: '', quantidade: '' }]);
    };

    const removerItem = (i) => {
        if (form.data.itens.length <= 1) return;
        form.setData('itens', form.data.itens.filter((_, idx) => idx !== i));
    };

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.put(route('pertences.update', pertence.id));
        } else {
            form.post(route('pertences.store'));
        }
    };

    return (
        <AppLayout>
            <Head title={editando ? 'Editar termo de pertences' : 'Novo termo de pertences'} />
            <PageHeader
                titulo={editando ? 'Editar termo de pertences' : 'Novo termo de pertences'}
                subtitulo="Registro de entrega e devolução de pertences pessoais"
            />

            <Box component="form" onSubmit={enviar}>
                <Stack spacing={2.5}>
                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Entrega
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
                                    gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    type="date"
                                    label="Data de entrega *"
                                    fullWidth
                                    value={form.data.data_entrega}
                                    onChange={(e) => form.setData('data_entrega', e.target.value)}
                                    error={Boolean(form.errors.data_entrega)}
                                    helperText={form.errors.data_entrega}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <TextField
                                    label="Assinatura do menor (nome digitado) — entrega"
                                    fullWidth
                                    value={form.data.assinatura_entrega}
                                    onChange={(e) => form.setData('assinatura_entrega', e.target.value)}
                                    error={Boolean(form.errors.assinatura_entrega)}
                                    helperText={form.errors.assinatura_entrega}
                                />
                            </Box>
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Itens recebidos
                        </Typography>
                        <Stack spacing={1.5}>
                            {form.data.itens.map((item, i) => (
                                <Box key={i} sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                                    <TextField
                                        label="Descrição do item"
                                        size="small"
                                        sx={{ flex: 1 }}
                                        value={item.descricao}
                                        onChange={(e) => atualizarItem(i, 'descricao', e.target.value)}
                                    />
                                    <TextField
                                        label="Qtd."
                                        size="small"
                                        sx={{ width: 100 }}
                                        value={item.quantidade}
                                        onChange={(e) => atualizarItem(i, 'quantidade', e.target.value)}
                                    />
                                    <IconButton
                                        color="error"
                                        onClick={() => removerItem(i)}
                                        disabled={form.data.itens.length <= 1}
                                        aria-label="Remover item"
                                    >
                                        <DeleteIcon fontSize="small" />
                                    </IconButton>
                                </Box>
                            ))}
                            {erroItens && (
                                <FormHelperText error>
                                    Informe ao menos um item com descrição.
                                </FormHelperText>
                            )}
                            <Box>
                                <Button
                                    size="small"
                                    variant="outlined"
                                    startIcon={<AddIcon />}
                                    onClick={adicionarItem}
                                >
                                    Adicionar item
                                </Button>
                            </Box>
                        </Stack>
                    </Card>

                    <Card variant="outlined" sx={{ p: { xs: 2, sm: 3 }, borderRadius: 3 }}>
                        <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 2 }}>
                            Devolução
                        </Typography>
                        <Stack spacing={2}>
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={form.data.devolvido}
                                        onChange={(e) => form.setData('devolvido', e.target.checked)}
                                    />
                                }
                                label="Pertences devolvidos ao menor"
                            />
                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                    gap: 2,
                                }}
                            >
                                <TextField
                                    type="date"
                                    label="Data da devolução"
                                    fullWidth
                                    value={form.data.data_devolucao}
                                    onChange={(e) => form.setData('data_devolucao', e.target.value)}
                                    error={Boolean(form.errors.data_devolucao)}
                                    helperText={form.errors.data_devolucao}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                />
                                <TextField
                                    label="Assinatura do menor (nome digitado) — devolução"
                                    fullWidth
                                    value={form.data.assinatura_devolucao}
                                    onChange={(e) => form.setData('assinatura_devolucao', e.target.value)}
                                    error={Boolean(form.errors.assinatura_devolucao)}
                                    helperText={form.errors.assinatura_devolucao}
                                />
                            </Box>
                            <TextField
                                label="Observação da devolução"
                                fullWidth
                                multiline
                                rows={3}
                                value={form.data.observacao_devolucao}
                                onChange={(e) => form.setData('observacao_devolucao', e.target.value)}
                                error={Boolean(form.errors.observacao_devolucao)}
                                helperText={form.errors.observacao_devolucao}
                            />
                            <FormHelperText>
                                Se algo não foi devolvido (ex.: perda), deixe desmarcado e descreva o ocorrido na
                                observação.
                            </FormHelperText>
                        </Stack>
                    </Card>

                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {editando ? 'Salvar alterações' : 'Registrar termo'}
                        </Button>
                    </Box>
                </Stack>
            </Box>
        </AppLayout>
    );
}

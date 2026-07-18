import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ptBr from '@fullcalendar/core/locales/pt-br';
import { motion } from 'framer-motion';
import {
    Box, Button, Card, CardContent, Chip, Dialog, DialogActions, DialogContent,
    DialogTitle, FormControlLabel, MenuItem, Stack, Switch, TextField, Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    CheckCircle as ConcluirIcon,
    Delete as DeleteIcon,
    Edit as EditIcon,
    Undo as ReabrirIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import CriancaSelect from '@/Components/CriancaSelect';
import ConfirmDialog from '@/Components/ConfirmDialog';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';

const FUSO = 'America/Sao_Paulo';

const coresTipo = {
    visita: '#059669',
    audiencia: '#e11d48',
    atendimento: '#4f46e5',
    tarefa: '#d97706',
    outro: '#64748b',
};

/*
 * O backend interpreta horários no fuso America/Sao_Paulo e serializa em UTC (ISO ...Z).
 * Os helpers abaixo convertem de volta para o fuso da unidade — usar slice() no ISO
 * mostraria o horário em UTC (3h de diferença).
 */

/** ISO (UTC) → "yyyy-mm-dd" no fuso da unidade (inputs date / eventos de dia inteiro). */
const dataIsoLocal = (iso) => (iso ? new Date(iso).toLocaleDateString('en-CA', { timeZone: FUSO }) : '');

/** ISO (UTC) → "dd/mm/aaaa" no fuso da unidade. */
const dataLocal = (iso) => (iso
    ? new Date(iso).toLocaleDateString('pt-BR', { timeZone: FUSO, day: '2-digit', month: '2-digit', year: 'numeric' })
    : null);

/** ISO (UTC) → "HH:MM" no fuso da unidade. */
const horaLocal = (iso) => (iso
    ? new Date(iso).toLocaleTimeString('pt-BR', { timeZone: FUSO, hour: '2-digit', minute: '2-digit', hour12: false })
    : null);

/** Resumo "17/07/2026 · 09:00" (ou "Dia inteiro") usado nas listas e no detalhe. */
function resumoDataHora(ev) {
    const data = dataLocal(ev.inicio);
    if (ev.dia_inteiro) return `${data} · Dia inteiro`;
    const fim = ev.fim ? ` – ${horaLocal(ev.fim)}` : '';
    return `${data} · ${horaLocal(ev.inicio)}${fim}`;
}

export default function Index({ eventos, criancas, tipos }) {
    const usuario = usePage().props.auth?.user;

    // Admin altera tudo; servidor só altera eventos do próprio setor (mas vê tudo).
    const podeAlterar = (ev) => Boolean(usuario?.is_admin) || ev.setor_id === usuario?.setor_id;

    const [dialogForm, setDialogForm] = useState(false);
    const [eventoEdicao, setEventoEdicao] = useState(null);
    const [detalhes, setDetalhes] = useState(null);
    const [excluirEv, setExcluirEv] = useState(null);
    const [excluindo, setExcluindo] = useState(false);

    const form = useForm({
        titulo: '',
        tipo: 'visita',
        crianca_id: '',
        data: '',
        hora_inicio: '09:00',
        hora_fim: '',
        dia_inteiro: false,
        descricao: '',
    });

    const campo = (nome, chaveErro) => ({
        value: form.data[nome],
        onChange: (e) => form.setData(nome, e.target.value),
        error: Boolean(form.errors[chaveErro ?? nome]),
        helperText: form.errors[chaveErro ?? nome],
    });

    const abrirCriar = (dateStr) => {
        const temHora = dateStr?.includes('T');
        setEventoEdicao(null);
        form.clearErrors();
        form.setData({
            titulo: '',
            tipo: 'visita',
            crianca_id: '',
            data: temHora ? dateStr.slice(0, 10) : (dateStr ?? dataIsoLocal(new Date())),
            hora_inicio: temHora ? dateStr.slice(11, 16) : '09:00',
            hora_fim: '',
            dia_inteiro: false,
            descricao: '',
        });
        setDialogForm(true);
    };

    const abrirEdicao = (ev) => {
        setDetalhes(null);
        setEventoEdicao(ev);
        form.clearErrors();
        form.setData({
            titulo: ev.titulo ?? '',
            tipo: ev.tipo ?? 'visita',
            crianca_id: ev.crianca?.id ?? '',
            data: dataIsoLocal(ev.inicio),
            hora_inicio: ev.dia_inteiro ? '09:00' : (horaLocal(ev.inicio) ?? '09:00'),
            hora_fim: ev.fim ? (horaLocal(ev.fim) ?? '') : '',
            dia_inteiro: Boolean(ev.dia_inteiro),
            descricao: ev.descricao ?? '',
        });
        setDialogForm(true);
    };

    const enviar = (e) => {
        e.preventDefault();
        const d = form.data;
        const inicio = d.dia_inteiro
            ? `${d.data}T00:00:00`
            : `${d.data}T${d.hora_inicio || '00:00'}:00`;
        const fim = !d.dia_inteiro && d.hora_fim ? `${d.data}T${d.hora_fim}:00` : null;

        // No Inertia v2 transform() retorna void — configurar antes e chamar post() depois.
        form.transform((dados) => ({
            titulo: dados.titulo,
            tipo: dados.tipo,
            crianca_id: dados.crianca_id || null,
            inicio,
            fim,
            dia_inteiro: dados.dia_inteiro,
            descricao: dados.descricao,
            ...(eventoEdicao ? { _method: 'put' } : {}),
        }));

        form.post(eventoEdicao ? route('agenda.update', eventoEdicao.id) : route('agenda.store'), {
            preserveScroll: true,
            onSuccess: () => setDialogForm(false),
        });
    };

    const alternarConcluido = (ev) => {
        router.patch(route('agenda.concluido', ev.id), {}, {
            preserveScroll: true,
            onSuccess: () => setDetalhes(null),
        });
    };

    const excluirEvento = () => {
        router.delete(route('agenda.destroy', excluirEv.id), {
            preserveScroll: true,
            onStart: () => setExcluindo(true),
            onFinish: () => {
                setExcluindo(false);
                setExcluirEv(null);
            },
        });
    };

    const eventosCalendario = eventos.map((ev) => {
        const cor = coresTipo[ev.tipo] ?? coresTipo.outro;
        return {
            id: String(ev.id),
            title: ev.titulo,
            // Dia inteiro: data pura evita deslocamento de fuso no FullCalendar.
            start: ev.dia_inteiro ? dataIsoLocal(ev.inicio) : ev.inicio,
            end: ev.fim ?? undefined,
            allDay: ev.dia_inteiro,
            backgroundColor: cor,
            borderColor: cor,
            extendedProps: { evento: ev },
        };
    });

    const inicioHoje = new Date(new Date().toDateString());
    const proximos = eventos
        .filter((ev) => !ev.concluido && new Date(ev.inicio) >= inicioHoje)
        .sort((a, b) => new Date(a.inicio) - new Date(b.inicio))
        .slice(0, 10);

    return (
        <AppLayout>
            <Head title="Agenda" />

            <PageHeader
                titulo="Agenda"
                subtitulo="Compromissos e tarefas da equipe"
                acoes={
                    <Button variant="contained" startIcon={<AddIcon />} onClick={() => abrirCriar()}>
                        Novo compromisso
                    </Button>
                }
            />

            <Box
                sx={{
                    display: 'grid',
                    gridTemplateColumns: { xs: '1fr', lg: '1fr 320px' },
                    gap: 2.5,
                    alignItems: 'start',
                }}
            >
                <Card>
                    <CardContent sx={{ p: { xs: 1, sm: 2 } }}>
                        <FullCalendar
                            plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
                            locales={[ptBr]}
                            locale="pt-br"
                            initialView="dayGridMonth"
                            height="auto"
                            dayMaxEventRows={3}
                            headerToolbar={{
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,timeGridWeek,listWeek',
                            }}
                            buttonText={{ today: 'Hoje', month: 'Mês', week: 'Semana', list: 'Lista' }}
                            nowIndicator
                            weekends
                            events={eventosCalendario}
                            eventClassNames={({ event }) => (
                                event.extendedProps.evento.concluido ? ['evento-concluido'] : []
                            )}
                            dateClick={(info) => abrirCriar(info.dateStr)}
                            eventClick={({ event }) => setDetalhes(event.extendedProps.evento)}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                        <Typography variant="h6" sx={{ mb: 1.5 }}>
                            Próximos compromissos
                        </Typography>
                        {proximos.length === 0 ? (
                            <EmptyState titulo="Nenhum compromisso futuro." />
                        ) : (
                            <Stack spacing={0.5}>
                                {proximos.map((ev, i) => (
                                    <motion.div
                                        key={ev.id}
                                        initial={{ opacity: 0, y: 8 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{ duration: 0.2, delay: i * 0.04 }}
                                    >
                                        <Box
                                            component="button"
                                            onClick={() => setDetalhes(ev)}
                                            sx={{
                                                display: 'flex', alignItems: 'center', gap: 1.5,
                                                width: '100%', py: 1, px: 1, mx: -1,
                                                border: 0, bgcolor: 'transparent', cursor: 'pointer',
                                                textAlign: 'left', borderRadius: 2,
                                                '&:hover': { bgcolor: '#f1f5f9' },
                                            }}
                                        >
                                            <Box
                                                sx={{
                                                    width: 10, height: 10, borderRadius: '50%', flexShrink: 0,
                                                    bgcolor: coresTipo[ev.tipo] ?? coresTipo.outro,
                                                }}
                                            />
                                            <Box sx={{ flex: 1, minWidth: 0 }}>
                                                <Typography variant="body2" noWrap sx={{ fontWeight: 600 }}>
                                                    {ev.titulo}
                                                </Typography>
                                                <Typography
                                                    variant="caption"
                                                    color="text.secondary"
                                                    noWrap
                                                    sx={{ display: 'block' }}
                                                >
                                                    {resumoDataHora(ev)}
                                                    {ev.crianca ? ` · ${ev.crianca.nome_completo}` : ''}
                                                </Typography>
                                            </Box>
                                            <Chip
                                                size="small"
                                                variant="outlined"
                                                label={tipos[ev.tipo] ?? ev.tipo}
                                                sx={{ flexShrink: 0 }}
                                            />
                                        </Box>
                                    </motion.div>
                                ))}
                            </Stack>
                        )}
                    </CardContent>
                </Card>
            </Box>

            <Dialog open={dialogForm} onClose={() => setDialogForm(false)} maxWidth="sm" fullWidth>
                <Box component="form" onSubmit={enviar}>
                    <DialogTitle>{eventoEdicao ? 'Editar compromisso' : 'Novo compromisso'}</DialogTitle>
                    <DialogContent>
                        <Stack spacing={2} sx={{ pt: 0.5 }}>
                            <TextField label="Título *" autoFocus {...campo('titulo')} />
                            <TextField select label="Tipo *" {...campo('tipo')}>
                                {Object.entries(tipos).map(([valor, rotulo]) => (
                                    <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                                ))}
                            </TextField>
                            <CriancaSelect
                                criancas={criancas}
                                value={form.data.crianca_id}
                                onChange={(id) => form.setData('crianca_id', id)}
                                error={form.errors.crianca_id}
                                label="Criança/adolescente (opcional)"
                            />
                            <TextField
                                label="Data *"
                                type="date"
                                slotProps={{ inputLabel: { shrink: true } }}
                                {...campo('data', 'inicio')}
                            />
                            <FormControlLabel
                                control={
                                    <Switch
                                        checked={form.data.dia_inteiro}
                                        onChange={(e) => form.setData('dia_inteiro', e.target.checked)}
                                    />
                                }
                                label="Dia inteiro"
                            />
                            <Stack direction="row" spacing={2}>
                                <TextField
                                    fullWidth
                                    label="Início"
                                    type="time"
                                    disabled={form.data.dia_inteiro}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                    {...campo('hora_inicio')}
                                />
                                <TextField
                                    fullWidth
                                    label="Fim (opcional)"
                                    type="time"
                                    disabled={form.data.dia_inteiro}
                                    slotProps={{ inputLabel: { shrink: true } }}
                                    {...campo('hora_fim', 'fim')}
                                />
                            </Stack>
                            <TextField
                                label="Descrição"
                                multiline
                                rows={3}
                                {...campo('descricao')}
                            />
                        </Stack>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2 }}>
                        <Button onClick={() => setDialogForm(false)} color="inherit">
                            Cancelar
                        </Button>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {eventoEdicao ? 'Salvar alterações' : 'Agendar'}
                        </Button>
                    </DialogActions>
                </Box>
            </Dialog>

            <Dialog open={Boolean(detalhes)} onClose={() => setDetalhes(null)} maxWidth="sm" fullWidth>
                {detalhes && (
                    <>
                        <DialogTitle sx={{ pb: 1 }}>
                            <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                                <Chip
                                    size="small"
                                    label={tipos[detalhes.tipo] ?? detalhes.tipo}
                                    sx={{
                                        bgcolor: coresTipo[detalhes.tipo] ?? coresTipo.outro,
                                        color: '#fff',
                                        fontWeight: 700,
                                    }}
                                />
                                {detalhes.concluido && (
                                    <Chip size="small" color="success" label="Concluído" />
                                )}
                            </Stack>
                        </DialogTitle>
                        <DialogContent sx={{ pt: 0 }}>
                            <Typography variant="h6" sx={{ mb: 0.5 }}>
                                {detalhes.titulo}
                            </Typography>
                            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                                {resumoDataHora(detalhes)}
                            </Typography>
                            {detalhes.crianca && (
                                <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'baseline', mb: 1 }}>
                                    <Typography variant="body2" color="text.secondary">
                                        Criança:
                                    </Typography>
                                    <Typography
                                        component={Link}
                                        href={route('criancas.show', detalhes.crianca.id)}
                                        variant="body2"
                                        sx={{
                                            color: 'primary.main', fontWeight: 600, textDecoration: 'none',
                                            '&:hover': { textDecoration: 'underline' },
                                        }}
                                    >
                                        {detalhes.crianca.nome_completo}
                                    </Typography>
                                </Box>
                            )}
                            {detalhes.descricao && (
                                <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7, mb: 1 }}>
                                    {detalhes.descricao}
                                </Typography>
                            )}
                            <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 1.5 }}>
                                Criado por {detalhes.criador?.name ?? '—'} · Setor: {detalhes.setor?.nome ?? '—'}
                            </Typography>
                        </DialogContent>
                        {podeAlterar(detalhes) && (
                            <DialogActions sx={{ px: 3, pb: 2, flexWrap: 'wrap', gap: 1 }}>
                                <Button
                                    startIcon={detalhes.concluido ? <ReabrirIcon /> : <ConcluirIcon />}
                                    onClick={() => alternarConcluido(detalhes)}
                                >
                                    {detalhes.concluido ? 'Reabrir' : 'Concluir'}
                                </Button>
                                <Button startIcon={<EditIcon />} onClick={() => abrirEdicao(detalhes)}>
                                    Editar
                                </Button>
                                <Box sx={{ flex: 1 }} />
                                <Button
                                    color="error"
                                    startIcon={<DeleteIcon />}
                                    onClick={() => {
                                        setExcluirEv(detalhes);
                                        setDetalhes(null);
                                    }}
                                >
                                    Excluir
                                </Button>
                            </DialogActions>
                        )}
                    </>
                )}
            </Dialog>

            <ConfirmDialog
                aberto={excluirEv !== null}
                titulo="Excluir compromisso"
                mensagem={`Tem certeza que deseja excluir "${excluirEv?.titulo}"? Esta ação não pode ser desfeita.`}
                aoCancelar={() => setExcluirEv(null)}
                aoConfirmar={excluirEvento}
                processando={excluindo}
            />
        </AppLayout>
    );
}

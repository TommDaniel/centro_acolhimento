import { Head, Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Card, CardContent, Chip, Stack, Typography } from '@mui/material';
import {
    Add as AddIcon,
    ChildCare as CriancasIcon,
    Description as PiaIcon,
    DirectionsWalk as VisitasIcon,
    Inventory2 as PertencesIcon,
    ReportProblem as ReportsIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import EmptyState from '@/Components/EmptyState';
import { fmtDataHora } from '@/utils/format';

const coresTipo = {
    indigo: { bg: '#e0e7ff', fg: '#3730a3' },
    rose: { bg: '#ffe4e6', fg: '#9f1239' },
    emerald: { bg: '#d1fae5', fg: '#065f46' },
    amber: { bg: '#fef3c7', fg: '#92400e' },
};

// Mesma paleta de tipos usada na Agenda.
const coresEvento = {
    visita: '#059669',
    audiencia: '#e11d48',
    atendimento: '#4f46e5',
    tarefa: '#d97706',
    outro: '#64748b',
};

export default function Dashboard({ totais, recentes, proximosEventos }) {
    const { auth } = usePage().props;
    const primeiroNome = auth?.user?.name?.trim().split(/\s+/)[0] ?? '';

    const cartoes = [
        { rotulo: 'Crianças', total: totais.criancas, href: '/criancas', icone: CriancasIcon },
        { rotulo: 'PIA', total: totais.pias, href: '/pias', icone: PiaIcon },
        { rotulo: 'Ocorrências', total: totais.reports, href: '/reports', icone: ReportsIcon },
        { rotulo: 'Visitas', total: totais.visitas, href: '/visitas-tecnicas', icone: VisitasIcon },
        { rotulo: 'Pertences', total: totais.pertences, href: '/pertences', icone: PertencesIcon },
    ];

    const acoesRapidas = [
        { rotulo: 'Nova criança', href: route('criancas.create') },
        { rotulo: 'Novo PIA', href: route('pias.create') },
        { rotulo: 'Nova ocorrência', href: route('reports.create') },
        { rotulo: 'Nova visita', href: route('visitas-tecnicas.create') },
        { rotulo: 'Novo termo de pertences', href: route('pertences.create') },
    ];

    return (
        <AppLayout>
            <Head title="Painel" />

            <Box sx={{ mb: { xs: 2, sm: 3 } }}>
                <Typography variant="h5" component="h1">
                    Olá, {primeiroNome}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                    Resumo da unidade
                </Typography>
            </Box>

            <Box
                sx={{
                    display: 'grid',
                    gridTemplateColumns: { xs: '1fr 1fr', sm: 'repeat(3, 1fr)', md: 'repeat(5, 1fr)' },
                    gap: { xs: 1.5, sm: 2 },
                    mb: { xs: 3, sm: 4 },
                }}
            >
                {cartoes.map((cartao, i) => {
                    const Icone = cartao.icone;
                    return (
                        <motion.div
                            key={cartao.rotulo}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, delay: i * 0.04 }}
                        >
                            <Box
                                component={Link}
                                href={cartao.href}
                                sx={{
                                    display: 'flex', alignItems: 'center', gap: 1.5, p: 2, height: '100%',
                                    bgcolor: 'background.paper', borderRadius: 3, border: '1px solid #e2e8f0',
                                    textDecoration: 'none', color: 'inherit',
                                    transition: 'box-shadow .2s, transform .2s',
                                    '&:hover': { boxShadow: 6, transform: 'translateY(-2px)' },
                                }}
                            >
                                <Box
                                    sx={{
                                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                                        width: 44, height: 44, borderRadius: 2.5, flexShrink: 0,
                                        bgcolor: '#f0fdfa', color: 'primary.main',
                                    }}
                                >
                                    <Icone />
                                </Box>
                                <Box sx={{ minWidth: 0 }}>
                                    <Typography variant="h5" component="p" sx={{ lineHeight: 1.1 }}>
                                        {cartao.total}
                                    </Typography>
                                    <Typography variant="caption" color="text.secondary" noWrap>
                                        {cartao.rotulo}
                                    </Typography>
                                </Box>
                            </Box>
                        </motion.div>
                    );
                })}
            </Box>

            <Typography variant="h6" sx={{ mb: 1.5 }}>
                Ações rápidas
            </Typography>
            <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap sx={{ mb: { xs: 3, sm: 4 } }}>
                {acoesRapidas.map((acao) => (
                    <Button
                        key={acao.rotulo}
                        component={Link}
                        href={acao.href}
                        variant="outlined"
                        startIcon={<AddIcon />}
                    >
                        {acao.rotulo}
                    </Button>
                ))}
            </Stack>

            <Card sx={{ mb: { xs: 3, sm: 4 } }}>
                <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                        <Typography variant="h6" sx={{ flex: 1 }}>
                            Próximos compromissos
                        </Typography>
                        <Button
                            component={Link}
                            href={route('agenda.index')}
                            size="small"
                            variant="outlined"
                        >
                            Ver agenda
                        </Button>
                    </Box>
                    {proximosEventos.length === 0 ? (
                        <EmptyState titulo="Nenhum compromisso futuro." />
                    ) : (
                        <Stack spacing={0.5}>
                            {proximosEventos.map((ev, i) => (
                                <motion.div
                                    key={ev.id}
                                    initial={{ opacity: 0, y: 8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.2, delay: i * 0.04 }}
                                >
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 0.75 }}>
                                        <Box
                                            sx={{
                                                width: 10, height: 10, borderRadius: '50%', flexShrink: 0,
                                                bgcolor: coresEvento[ev.tipo] ?? coresEvento.outro,
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
                                                {fmtDataHora(ev.inicio)}
                                                {ev.crianca ? ` · ${ev.crianca.nome_completo}` : ''}
                                            </Typography>
                                        </Box>
                                    </Box>
                                </motion.div>
                            ))}
                        </Stack>
                    )}
                </CardContent>
            </Card>

            <Typography variant="h6" sx={{ mb: 1.5 }}>
                Atividade recente
            </Typography>
            {recentes.length === 0 ? (
                <EmptyState
                    titulo="Nenhuma atividade recente"
                    mensagem="Os documentos registrados aparecerão aqui."
                />
            ) : (
                <Stack spacing={1.5}>
                    {recentes.map((item, i) => {
                        const cor = coresTipo[item.cor] ?? coresTipo.indigo;
                        return (
                            <motion.div
                                key={`${item.tipo}-${item.doc.id}`}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.2, delay: i * 0.04 }}
                            >
                                <Box
                                    sx={{
                                        display: 'flex', alignItems: 'center', gap: 1.5, p: 1.5,
                                        bgcolor: 'background.paper', borderRadius: 3,
                                        border: '1px solid #e2e8f0', flexWrap: { xs: 'wrap', sm: 'nowrap' },
                                    }}
                                >
                                    <Chip
                                        size="small"
                                        label={item.tipo}
                                        sx={{ bgcolor: cor.bg, color: cor.fg, fontWeight: 700 }}
                                    />
                                    <Box sx={{ flex: 1, minWidth: 0 }}>
                                        {item.doc.crianca ? (
                                            <Typography
                                                variant="body2"
                                                component={Link}
                                                href={route('criancas.show', item.doc.crianca.id)}
                                                noWrap
                                                sx={{
                                                    display: 'block', fontWeight: 600, color: 'text.primary',
                                                    textDecoration: 'none', '&:hover': { color: 'primary.main' },
                                                }}
                                            >
                                                {item.doc.crianca.nome_completo}
                                            </Typography>
                                        ) : (
                                            <Typography variant="body2" noWrap sx={{ fontWeight: 600 }}>
                                                Criança removida
                                            </Typography>
                                        )}
                                        <Typography variant="caption" color="text.secondary">
                                            por {item.doc.criador?.name ?? '—'} em {fmtDataHora(item.doc.created_at)}
                                        </Typography>
                                    </Box>
                                    <Button size="small" component={Link} href={item.rota}>
                                        Abrir
                                    </Button>
                                </Box>
                            </motion.div>
                        );
                    })}
                </Stack>
            )}
        </AppLayout>
    );
}

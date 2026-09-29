import { useEffect, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Accordion, AccordionDetails, AccordionSummary, Alert, Box, Button, Card, CardContent,
    Chip, Dialog, DialogActions, DialogContent, DialogTitle, Divider, IconButton,
    MenuItem, Stack, TextField, Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    Edit as EditIcon,
    ExpandMore as ExpandMoreIcon,
    School as SchoolIcon,
    PictureAsPdf as PdfIcon,
    Visibility as VerIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import AcolhimentoPanel from '@/Components/AcolhimentoPanel';
import CriancaAvatar from '@/Components/CriancaAvatar';
import DocMeta from '@/Components/DocMeta';
import EmptyState from '@/Components/EmptyState';
import InformacaoEscolarFields, {
    informacaoEscolarVazia,
    novaChaveIdempotencia,
} from '@/Components/InformacaoEscolarFields';
import KvList from '@/Components/KvList';
import StatusChip from '@/Components/StatusChip';
import { fmtData, fmtDataHora } from '@/utils/format';

const tiposFamiliar = {
    genitora: 'Genitora',
    genitor: 'Genitor',
    responsavel: 'Responsável',
    familiar: 'Familiar',
};

/** Card de uma coleção de documentos da criança (PIAs, visitas, ocorrências, pertences). */
function CartaoDocumentos({ titulo, itens, hrefNovo, dataDe, chipDe, rotaShow, rotaEdit, rotaPdf, podeAlterar }) {
    return (
        <Card>
            <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                    <Typography variant="h6" sx={{ flex: 1 }}>
                        {titulo}
                    </Typography>
                    <Button
                        component={Link}
                        href={hrefNovo}
                        size="small"
                        variant="outlined"
                        startIcon={<AddIcon />}
                    >
                        Novo
                    </Button>
                </Box>

                {itens.length === 0 ? (
                    <EmptyState titulo="Nenhum registro." />
                ) : (
                    <Stack divider={<Divider />} spacing={0}>
                        {itens.map((item, i) => (
                            <motion.div
                                key={item.id}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.2, delay: i * 0.04 }}
                            >
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 1.25 }}>
                                    <Box sx={{ flex: 1, minWidth: 0 }}>
                                        <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap" useFlexGap>
                                            <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                                {dataDe(item)}
                                            </Typography>
                                            {chipDe?.(item)}
                                        </Stack>
                                        <Typography variant="caption" color="text.secondary">
                                            por {item.criador?.name ?? '—'}
                                        </Typography>
                                    </Box>
                                    {podeAlterar(item) && (
                                        <IconButton
                                            component={Link}
                                            href={route(rotaEdit, item.id)}
                                            size="small"
                                            aria-label={`Editar ${titulo}`}
                                        >
                                            <EditIcon fontSize="small" />
                                        </IconButton>
                                    )}
                                    <IconButton
                                        component={Link}
                                        href={route(rotaShow, item.id)}
                                        size="small"
                                        aria-label={`Ver ${titulo}`}
                                    >
                                        <VerIcon fontSize="small" />
                                    </IconButton>
                                    <IconButton
                                        component="a"
                                        href={route(rotaPdf, item.id)}
                                        target="_blank"
                                        size="small"
                                        aria-label={`PDF de ${titulo}`}
                                    >
                                        <PdfIcon fontSize="small" />
                                    </IconButton>
                                </Box>
                            </motion.div>
                        ))}
                    </Stack>
                )}
            </CardContent>
        </Card>
    );
}

function dadosInformacaoEscolar(informacao, mostrarAusentes = false) {
    if (!informacao) return {};

    const dados = {
        'Situação': informacao.situacao_complemento
            ? `${informacao.situacao}: ${informacao.situacao_complemento}`
            : informacao.situacao,
        'Escola': informacao.escola_nome,
        'Rede': informacao.rede_complemento
            ? `${informacao.rede}: ${informacao.rede_complemento}`
            : informacao.rede,
        'Matrícula': informacao.matricula,
        'Ano / série': informacao.ano_serie,
        'Turma': informacao.turma,
        'Turno': informacao.turno_complemento
            ? `${informacao.turno}: ${informacao.turno_complemento}`
            : informacao.turno,
        'Vigente em': informacao.vigente_em ? fmtData(informacao.vigente_em) : null,
        'Fonte': informacao.fonte_complemento
            ? `${informacao.fonte}: ${informacao.fonte_complemento}`
            : informacao.fonte,
    };

    if (!mostrarAusentes) return dados;

    return Object.fromEntries(
        Object.entries(dados).map(([rotulo, valor]) => [rotulo, valor || 'Não informado']),
    );
}

function InformacaoEscolarPanel({ criancaId, atual, historico, opcoes }) {
    const [dialogAberto, setDialogAberto] = useState(false);
    const [versoes, setVersoes] = useState(historico.data);
    const [temMais, setTemMais] = useState(historico.tem_mais);
    const [proximoAntesDe, setProximoAntesDe] = useState(historico.proximo_antes_de);
    const [carregandoHistorico, setCarregandoHistorico] = useState(false);
    const [erroHistorico, setErroHistorico] = useState(false);
    const form = useForm(informacaoEscolarVazia());

    useEffect(() => {
        setVersoes(historico.data);
        setTemMais(historico.tem_mais);
        setProximoAntesDe(historico.proximo_antes_de);
        setErroHistorico(false);
    }, [historico]);

    const abrirAtualizacao = () => {
        const novosDados = informacaoEscolarVazia();
        if (atual) {
            Object.keys(novosDados).forEach((campo) => {
                if (campo !== 'idempotency_key' && atual[campo] !== null && atual[campo] !== undefined) {
                    novosDados[campo] = atual[campo];
                }
            });
        }
        novosDados.idempotency_key = novaChaveIdempotencia();
        form.setData(novosDados);
        form.clearErrors();
        setDialogAberto(true);
    };

    const fechar = () => {
        if (!form.processing) {
            setDialogAberto(false);
            form.clearErrors();
        }
    };

    const enviar = (event) => {
        event.preventDefault();
        form.post(route('criancas.informacoes-escolares.store', criancaId), {
            preserveScroll: true,
            onSuccess: () => {
                setDialogAberto(false);
                form.reset();
            },
        });
    };

    const carregarMais = async () => {
        if (carregandoHistorico || !temMais || !proximoAntesDe) return;

        setCarregandoHistorico(true);
        setErroHistorico(false);

        try {
            const response = await window.fetch(route('criancas.informacoes-escolares.index', {
                crianca: criancaId,
                before_id: proximoAntesDe,
            }), {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) throw new Error('Falha ao carregar o histórico escolar.');

            const page = await response.json();
            setVersoes((anteriores) => [...anteriores, ...page.data]);
            setTemMais(page.tem_mais);
            setProximoAntesDe(page.proximo_antes_de);
        } catch {
            setErroHistorico(true);
        } finally {
            setCarregandoHistorico(false);
        }
    };

    return (
        <Card>
            <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                    <SchoolIcon color="primary" />
                    <Typography variant="h6" sx={{ flex: 1 }}>Educação</Typography>
                    <Button size="small" variant="outlined" onClick={abrirAtualizacao} startIcon={<AddIcon />}>
                        {atual ? 'Registrar atualização' : 'Registrar informação'}
                    </Button>
                </Box>

                {atual === null ? (
                    <EmptyState titulo="Nenhuma informação escolar registrada." />
                ) : (
                    <Stack spacing={1.5}>
                        <Alert severity={atual.situacao_codigo === 'nao_informada' ? 'warning' : 'info'}>
                            Estado atual: <strong>{atual.situacao}</strong>
                        </Alert>
                        <KvList dados={dadosInformacaoEscolar(atual)} />
                        <Typography variant="caption" color="text.secondary">
                            Registrado por {atual.registrado_por ?? '—'} em {fmtDataHora(atual.registrado_em)}.
                        </Typography>
                    </Stack>
                )}

                <Divider sx={{ my: 2 }} />
                <Typography variant="subtitle2" sx={{ mb: 1 }}>Histórico de versões</Typography>
                {versoes.length === 0 ? (
                    <Typography variant="body2" color="text.secondary">
                        Nenhuma versão anterior.
                    </Typography>
                ) : (
                    <Stack spacing={1}>
                        {versoes.map((versao) => (
                            <Accordion key={versao.id} disableGutters variant="outlined">
                                <AccordionSummary
                                    expandIcon={<ExpandMoreIcon />}
                                    aria-controls={`school-history-${versao.id}-content`}
                                    id={`school-history-${versao.id}-header`}
                                >
                                    <Box sx={{ width: '100%', pr: 1 }}>
                                        <Stack direction={{ xs: 'column', sm: 'row' }} gap={0.5} justifyContent="space-between">
                                            <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                                {versao.situacao_complemento
                                                    ? `${versao.situacao}: ${versao.situacao_complemento}`
                                                    : versao.situacao}
                                            </Typography>
                                            <Typography variant="caption" color="text.secondary">
                                                {fmtDataHora(versao.registrado_em)} · {versao.registrado_por ?? '—'}
                                            </Typography>
                                        </Stack>
                                        <Typography variant="caption" color="text.secondary">
                                            Ver todos os campos desta versão
                                        </Typography>
                                    </Box>
                                </AccordionSummary>
                                <AccordionDetails id={`school-history-${versao.id}-content`}>
                                    <KvList dados={dadosInformacaoEscolar(versao, true)} />
                                    <Typography variant="caption" color="text.secondary" display="block" sx={{ mt: 1.5 }}>
                                        Registrado por {versao.registrado_por ?? '—'} em {fmtDataHora(versao.registrado_em)}.
                                    </Typography>
                                </AccordionDetails>
                            </Accordion>
                        ))}
                    </Stack>
                )}
                {erroHistorico && (
                    <Alert severity="error" sx={{ mt: 2 }}>
                        Não foi possível carregar versões anteriores. Tente novamente.
                    </Alert>
                )}
                {temMais && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
                        <Button variant="outlined" onClick={carregarMais} disabled={carregandoHistorico}>
                            {carregandoHistorico ? 'Carregando…' : 'Carregar versões anteriores'}
                        </Button>
                    </Box>
                )}
            </CardContent>

            <Dialog open={dialogAberto} onClose={fechar} maxWidth="md" fullWidth>
                <Box component="form" onSubmit={enviar}>
                    <DialogTitle>Registrar nova versão da informação escolar</DialogTitle>
                    <DialogContent>
                        <Box
                            sx={{
                                display: 'grid',
                                gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                gap: 2,
                                pt: 1,
                            }}
                        >
                            <InformacaoEscolarFields
                                data={form.data}
                                setData={form.setData}
                                errors={form.errors}
                                opcoes={opcoes}
                            />
                        </Box>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2 }}>
                        <Button onClick={fechar} color="inherit" disabled={form.processing}>Cancelar</Button>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {form.processing ? 'Salvando…' : 'Salvar nova versão'}
                        </Button>
                    </DialogActions>
                </Box>
            </Dialog>
        </Card>
    );
}

export default function Show({
    crianca,
    identificacao,
    ultimaAtualizacao,
    acolhimento,
    linhaDoTempoAcolhimento,
    legadoAConferir,
    opcoesAcolhimento,
    informacaoEscolarAtual,
    historicoInformacaoEscolar,
    opcoesInformacaoEscolar,
}) {
    const podeAlterar = () => true;

    const [dialogFamiliar, setDialogFamiliar] = useState(false);

    const formFamiliar = useForm({
        tipo: '',
        nome: '',
        parentesco: '',
        data_nascimento: '',
        rg: '',
        cpf: '',
        telefone: '',
        endereco: '',
        ocupacao: '',
        observacoes: '',
    });

    const campoFamiliar = (nome) => ({
        value: formFamiliar.data[nome] ?? '',
        onChange: (e) => formFamiliar.setData(nome, e.target.value),
        error: Boolean(formFamiliar.errors[nome]),
        helperText: formFamiliar.errors[nome],
    });

    const enviarFamiliar = (e) => {
        e.preventDefault();
        formFamiliar.post(route('criancas.familiares.store', crianca.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDialogFamiliar(false);
                formFamiliar.reset();
            },
        });
    };

    const resumoFamiliar = (f) => [
        f.parentesco,
        f.data_nascimento
            ? `Nasc. ${fmtData(f.data_nascimento)}${f.idade !== null && f.idade !== undefined ? ` · ${f.idade} anos` : ''}`
            : null,
        f.rg ? `RG ${f.rg}` : null,
        f.cpf ? `CPF ${f.cpf}` : null,
        f.telefone,
        f.ocupacao,
    ].filter(Boolean).join(' · ');

    const secoesDocs = [
        {
            titulo: 'PIAs',
            itens: crianca.pias ?? [],
            hrefNovo: `${route('pias.create')}?crianca_id=${crianca.id}`,
            dataDe: (d) => fmtData(d.created_at),
            rotaShow: 'pias.show',
            rotaEdit: 'pias.edit',
            rotaPdf: 'pias.pdf',
        },
        {
            titulo: 'Visitas técnicas',
            itens: crianca.visitas_tecnicas ?? [],
            hrefNovo: `${route('visitas-tecnicas.create')}?crianca_id=${crianca.id}`,
            dataDe: (d) => fmtData(d.data_visita),
            rotaShow: 'visitas-tecnicas.show',
            rotaEdit: 'visitas-tecnicas.edit',
            rotaPdf: 'visitas-tecnicas.pdf',
        },
        {
            titulo: 'Ocorrências',
            itens: crianca.reports ?? [],
            hrefNovo: `${route('reports.create')}?crianca_id=${crianca.id}`,
            dataDe: (d) => fmtData(d.created_at),
            rotaShow: 'reports.show',
            rotaEdit: 'reports.edit',
            rotaPdf: 'reports.pdf',
        },
        {
            titulo: 'Pertences',
            itens: crianca.pertences ?? [],
            hrefNovo: `${route('pertences.create')}?crianca_id=${crianca.id}`,
            dataDe: (d) => fmtData(d.created_at),
            chipDe: (d) => (
                <Chip
                    size="small"
                    label={d.devolvido ? 'Devolvido' : 'Pendente'}
                    color={d.devolvido ? 'success' : 'warning'}
                    variant={d.devolvido ? 'filled' : 'outlined'}
                />
            ),
            rotaShow: 'pertences.show',
            rotaEdit: 'pertences.edit',
            rotaPdf: 'pertences.pdf',
        },
    ];

    return (
        <AppLayout>
            <Head title={crianca.nome_completo} />

            <Stack spacing={{ xs: 2, sm: 3 }}>
                <Card>
                    <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                        <Stack
                            direction={{ xs: 'column', sm: 'row' }}
                            spacing={2}
                            alignItems={{ xs: 'flex-start', sm: 'center' }}
                        >
                            <CriancaAvatar crianca={crianca} tamanho={72} />
                            <Box sx={{ flex: 1, minWidth: 0 }}>
                                <Typography variant="h5" component="h1">
                                    {crianca.nome_completo}
                                </Typography>
                                {crianca.nome_social && (
                                    <Typography variant="body2" color="text.secondary">
                                        Nome social: {crianca.nome_social}
                                    </Typography>
                                )}
                                <Stack direction="row" spacing={1} alignItems="center" sx={{ mt: 0.5 }} flexWrap="wrap" useFlexGap>
                                    <StatusChip
                                        situacao={acolhimento?.situacao}
                                        fonte={acolhimento ? 'episodio' : (legadoAConferir ? 'legado' : 'nenhum')}
                                    />
                                    {crianca.idade !== null && crianca.idade !== undefined && (
                                        <Typography variant="body2" color="text.secondary">
                                            Idade: {crianca.idade} anos
                                        </Typography>
                                    )}
                                </Stack>
                            </Box>
                            <Stack direction="row" spacing={1}>
                                <Button
                                    component={Link}
                                    href={route('criancas.edit', crianca.id)}
                                    variant="outlined"
                                    startIcon={<EditIcon />}
                                >
                                    Editar
                                </Button>
                            </Stack>
                        </Stack>
                        <Box sx={{ mt: 1.5 }}>
                            <DocMeta doc={{ criador: crianca.criador, created_at: crianca.created_at }} />
                            {ultimaAtualizacao && (
                                <Typography variant="caption" color="text.secondary" display="block">
                                    Atualizado por {ultimaAtualizacao.author} em {fmtDataHora(ultimaAtualizacao.at)}
                                </Typography>
                            )}
                        </Box>
                    </CardContent>
                </Card>

                <AcolhimentoPanel
                    criancaId={crianca.id}
                    acolhimento={acolhimento}
                    linhaDoTempo={linhaDoTempoAcolhimento}
                    legadoAConferir={legadoAConferir}
                    opcoes={opcoesAcolhimento}
                />

                <InformacaoEscolarPanel
                    criancaId={crianca.id}
                    atual={informacaoEscolarAtual}
                    historico={historicoInformacaoEscolar}
                    opcoes={opcoesInformacaoEscolar}
                />

                <Card>
                    <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Dados de identificação
                        </Typography>
                        <KvList dados={identificacao} />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                            <Typography variant="h6" sx={{ flex: 1 }}>
                                Família / Filiação
                            </Typography>
                            <Button
                                size="small"
                                variant="outlined"
                                startIcon={<AddIcon />}
                                onClick={() => setDialogFamiliar(true)}
                            >
                                Adicionar familiar
                            </Button>
                        </Box>

                        {(crianca.familiares ?? []).length === 0 ? (
                            <EmptyState titulo="Nenhum familiar cadastrado." />
                        ) : (
                            <Stack divider={<Divider />} spacing={0}>
                                {crianca.familiares.map((f, i) => (
                                    <motion.div
                                        key={f.id}
                                        initial={{ opacity: 0, y: 8 }}
                                        animate={{ opacity: 1, y: 0 }}
                                        transition={{ duration: 0.2, delay: i * 0.04 }}
                                    >
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 1.25 }}>
                                            <Chip
                                                size="small"
                                                variant="outlined"
                                                color="primary"
                                                label={tiposFamiliar[f.tipo] ?? f.tipo}
                                            />
                                            <Box sx={{ flex: 1, minWidth: 0 }}>
                                                <Typography variant="body2" sx={{ fontWeight: 700 }}>
                                                    {f.nome}
                                                </Typography>
                                                {resumoFamiliar(f) !== '' && (
                                                    <Typography variant="caption" color="text.secondary">
                                                        {resumoFamiliar(f)}
                                                    </Typography>
                                                )}
                                            </Box>
                                        </Box>
                                    </motion.div>
                                ))}
                            </Stack>
                        )}
                    </CardContent>
                </Card>

                {crianca.observacoes && (
                    <Card>
                        <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                            <Typography variant="h6" sx={{ mb: 1 }}>
                                Observações
                            </Typography>
                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                                {crianca.observacoes}
                            </Typography>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                        <Typography variant="h6" sx={{ mb: 2 }}>
                            Anexos
                        </Typography>
                        <Alert severity="info">
                            O envio e o download de anexos estão temporariamente desativados até a adoção de
                            armazenamento privado durável e varredura antimalware.
                        </Alert>
                    </CardContent>
                </Card>

                {secoesDocs.map((secao) => (
                    <CartaoDocumentos key={secao.titulo} podeAlterar={podeAlterar} {...secao} />
                ))}
            </Stack>

            <Dialog
                open={dialogFamiliar}
                onClose={() => setDialogFamiliar(false)}
                maxWidth="sm"
                fullWidth
            >
                <Box component="form" onSubmit={enviarFamiliar}>
                    <DialogTitle>Adicionar familiar</DialogTitle>
                    <DialogContent>
                        <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 2 }}>
                            Genitores e responsáveis alimentam a seção Filiação do PIA; todos entram na Composição familiar.
                        </Typography>
                        <Stack spacing={2}>
                            <TextField select label="Tipo *" {...campoFamiliar('tipo')}>
                                {Object.entries(tiposFamiliar).map(([valor, rotulo]) => (
                                    <MenuItem key={valor} value={valor}>{rotulo}</MenuItem>
                                ))}
                            </TextField>
                            <TextField label="Nome *" {...campoFamiliar('nome')} />
                            <TextField label="Parentesco" {...campoFamiliar('parentesco')} />
                            <TextField
                                label="Data de nascimento"
                                type="date"
                                slotProps={{ inputLabel: { shrink: true } }}
                                {...campoFamiliar('data_nascimento')}
                            />
                            <TextField label="RG" {...campoFamiliar('rg')} />
                            <TextField label="CPF" {...campoFamiliar('cpf')} />
                            <TextField label="Telefone" {...campoFamiliar('telefone')} />
                            <TextField label="Endereço" {...campoFamiliar('endereco')} />
                            <TextField label="Ocupação" {...campoFamiliar('ocupacao')} />
                            <TextField
                                label="Observações"
                                multiline
                                rows={2}
                                {...campoFamiliar('observacoes')}
                            />
                        </Stack>
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2 }}>
                        <Button onClick={() => setDialogFamiliar(false)} color="inherit">
                            Cancelar
                        </Button>
                        <Button type="submit" variant="contained" disabled={formFamiliar.processing}>
                            Salvar
                        </Button>
                    </DialogActions>
                </Box>
            </Dialog>

        </AppLayout>
    );
}

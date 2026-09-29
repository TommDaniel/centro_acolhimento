import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Alert, Box, Button, Card, CardContent, Chip, Dialog, DialogActions, DialogContent,
    DialogTitle, Divider, IconButton, MenuItem, Stack, TextField, Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    Edit as EditIcon,
    PictureAsPdf as PdfIcon,
    Visibility as VerIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import AcolhimentoPanel from '@/Components/AcolhimentoPanel';
import CriancaAvatar from '@/Components/CriancaAvatar';
import DocMeta from '@/Components/DocMeta';
import EmptyState from '@/Components/EmptyState';
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

export default function Show({
    crianca,
    identificacao,
    ultimaAtualizacao,
    acolhimento,
    linhaDoTempoAcolhimento,
    legadoAConferir,
    opcoesAcolhimento,
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

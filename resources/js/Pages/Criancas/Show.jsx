import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Box, Button, Card, CardContent, Chip, Dialog, DialogActions, DialogContent,
    DialogTitle, Divider, IconButton, MenuItem, Stack, TextField, Typography,
} from '@mui/material';
import {
    Add as AddIcon,
    Delete as DeleteIcon,
    Download as DownloadIcon,
    Edit as EditIcon,
    PictureAsPdf as PdfIcon,
    UploadFile as UploadIcon,
    Visibility as VerIcon,
} from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import ConfirmDialog from '@/Components/ConfirmDialog';
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

export default function Show({ crianca, identificacao }) {
    const usuario = usePage().props.auth?.user;

    // Admin altera tudo; servidor só altera documentos do próprio setor (mas vê tudo).
    const podeAlterar = (doc) => Boolean(usuario?.is_admin || doc.setor_id === usuario?.setor_id);

    const [confirmarExclusao, setConfirmarExclusao] = useState(false);
    const [excluindo, setExcluindo] = useState(false);
    const [docParaExcluir, setDocParaExcluir] = useState(null);
    const [excluindoDoc, setExcluindoDoc] = useState(false);
    const [dialogFamiliar, setDialogFamiliar] = useState(false);
    const [familiarParaExcluir, setFamiliarParaExcluir] = useState(null);
    const [excluindoFamiliar, setExcluindoFamiliar] = useState(false);

    const upload = useForm({ anexos: [] });

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

    const erroAnexos = Object.keys(upload.errors)
        .filter((k) => k.startsWith('anexos'))
        .map((k) => upload.errors[k])[0];

    const enviarAnexos = (e) => {
        e.preventDefault();
        upload.post(route('criancas.documentos.store', crianca.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => upload.reset(),
        });
    };

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

    const excluirCrianca = () => {
        router.delete(route('criancas.destroy', crianca.id), {
            onStart: () => setExcluindo(true),
            onFinish: () => setExcluindo(false),
        });
    };

    const excluirDocumento = () => {
        router.delete(route('documentos.destroy', docParaExcluir.id), {
            preserveScroll: true,
            onStart: () => setExcluindoDoc(true),
            onFinish: () => {
                setExcluindoDoc(false);
                setDocParaExcluir(null);
            },
        });
    };

    const excluirFamiliar = () => {
        router.delete(route('familiares.destroy', familiarParaExcluir.id), {
            preserveScroll: true,
            onStart: () => setExcluindoFamiliar(true),
            onFinish: () => {
                setExcluindoFamiliar(false);
                setFamiliarParaExcluir(null);
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
                                    <StatusChip status={crianca.status} />
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
                                {usuario?.is_admin && (
                                    <Button
                                        variant="outlined"
                                        color="error"
                                        startIcon={<DeleteIcon />}
                                        onClick={() => setConfirmarExclusao(true)}
                                    >
                                        Excluir
                                    </Button>
                                )}
                            </Stack>
                        </Stack>
                        <Box sx={{ mt: 1.5 }}>
                            <DocMeta doc={{ criador: crianca.criador, created_at: crianca.created_at }} />
                        </Box>
                    </CardContent>
                </Card>

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
                                            <IconButton
                                                size="small"
                                                color="error"
                                                aria-label={`Excluir ${f.nome}`}
                                                onClick={() => setFamiliarParaExcluir(f)}
                                            >
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        </Box>
                                    </motion.div>
                                ))}
                            </Stack>
                        )}
                    </CardContent>
                </Card>

                {crianca.motivo_acolhimento && (
                    <Card>
                        <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                            <Typography variant="h6" sx={{ mb: 1 }}>
                                Motivo do acolhimento
                            </Typography>
                            <Typography variant="body2" sx={{ whiteSpace: 'pre-line', lineHeight: 1.7 }}>
                                {crianca.motivo_acolhimento}
                            </Typography>
                        </CardContent>
                    </Card>
                )}

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
                            Documentos anexados
                        </Typography>

                        <Box component="form" onSubmit={enviarAnexos} sx={{ mb: 2 }}>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ sm: 'center' }}>
                                <Button component="label" variant="outlined" startIcon={<UploadIcon />}>
                                    Selecionar arquivos
                                    <input
                                        hidden
                                        type="file"
                                        multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                        onChange={(e) => {
                                            upload.setData('anexos', Array.from(e.target.files ?? []));
                                            e.target.value = '';
                                        }}
                                    />
                                </Button>
                                <Button
                                    type="submit"
                                    variant="contained"
                                    disabled={upload.processing || upload.data.anexos.length === 0}
                                >
                                    Enviar
                                </Button>
                            </Stack>
                            {upload.data.anexos.length > 0 && (
                                <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap sx={{ mt: 1 }}>
                                    {upload.data.anexos.map((arquivo) => (
                                        <Chip key={arquivo.name} size="small" variant="outlined" label={arquivo.name} />
                                    ))}
                                </Stack>
                            )}
                            {erroAnexos && (
                                <Typography variant="caption" color="error" sx={{ display: 'block', mt: 0.5 }}>
                                    {erroAnexos}
                                </Typography>
                            )}
                        </Box>

                        {(crianca.documentos ?? []).length === 0 ? (
                            <EmptyState titulo="Nenhum anexo." />
                        ) : (
                            <Stack divider={<Divider />} spacing={0}>
                                {crianca.documentos.map((doc) => (
                                    <Box
                                        key={doc.id}
                                        sx={{ display: 'flex', alignItems: 'center', gap: 1.5, py: 1.25 }}
                                    >
                                        <Box sx={{ flex: 1, minWidth: 0 }}>
                                            <Typography variant="body2" noWrap sx={{ fontWeight: 600 }}>
                                                {doc.nome_original}
                                            </Typography>
                                            <Typography variant="caption" color="text.secondary">
                                                por {doc.uploader?.name ?? '—'} em {fmtDataHora(doc.created_at)}
                                            </Typography>
                                        </Box>
                                        <IconButton
                                            component="a"
                                            href={doc.url}
                                            target="_blank"
                                            size="small"
                                            aria-label={`Baixar ${doc.nome_original}`}
                                        >
                                            <DownloadIcon fontSize="small" />
                                        </IconButton>
                                        {(usuario?.is_admin || doc.uploaded_by === usuario?.id) && (
                                            <IconButton
                                                size="small"
                                                color="error"
                                                aria-label={`Excluir ${doc.nome_original}`}
                                                onClick={() => setDocParaExcluir(doc)}
                                            >
                                                <DeleteIcon fontSize="small" />
                                            </IconButton>
                                        )}
                                    </Box>
                                ))}
                            </Stack>
                        )}
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

            <ConfirmDialog
                aberto={confirmarExclusao}
                titulo="Excluir cadastro"
                mensagem={`Tem certeza que deseja excluir ${crianca.nome_completo}? Todos os documentos vinculados (PIAs, visitas, ocorrências, pertences e anexos) também serão removidos. Esta ação não pode ser desfeita.`}
                aoCancelar={() => setConfirmarExclusao(false)}
                aoConfirmar={excluirCrianca}
                processando={excluindo}
            />

            <ConfirmDialog
                aberto={docParaExcluir !== null}
                titulo="Excluir anexo"
                mensagem={`Tem certeza que deseja excluir o anexo "${docParaExcluir?.nome_original}"? Esta ação não pode ser desfeita.`}
                aoCancelar={() => setDocParaExcluir(null)}
                aoConfirmar={excluirDocumento}
                processando={excluindoDoc}
            />

            <ConfirmDialog
                aberto={familiarParaExcluir !== null}
                titulo="Excluir familiar"
                mensagem={`Tem certeza que deseja excluir ${familiarParaExcluir?.nome} da composição familiar? Esta ação não pode ser desfeita.`}
                aoCancelar={() => setFamiliarParaExcluir(null)}
                aoConfirmar={excluirFamiliar}
                processando={excluindoFamiliar}
            />
        </AppLayout>
    );
}

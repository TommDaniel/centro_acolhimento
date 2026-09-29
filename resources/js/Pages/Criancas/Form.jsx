import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Avatar, Box, Button, Card, CardContent, FormControlLabel, MenuItem, Stack, Switch, TextField, Typography,
} from '@mui/material';
import { PhotoCamera as FotoIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import InformacaoEscolarFields, { informacaoEscolarVazia } from '@/Components/InformacaoEscolarFields';
import { iniciais } from '@/utils/format';

function Secao({ titulo, children }) {
    return (
        <Card>
            <CardContent sx={{ p: { xs: 2, sm: 3 } }}>
                <Typography variant="h6" sx={{ mb: 2 }}>
                    {titulo}
                </Typography>
                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                        gap: 2,
                    }}
                >
                    {children}
                </Box>
            </CardContent>
        </Card>
    );
}

export default function Form({ crianca, opcoesInformacaoEscolar }) {
    const editando = Boolean(crianca);

    const form = useForm({
        nome_completo: crianca?.nome_completo ?? '',
        nome_social: crianca?.nome_social ?? '',
        data_nascimento: crianca?.data_nascimento?.slice(0, 10) ?? '',
        sexo: crianca?.sexo ?? '',
        identidade_genero: crianca?.identidade_genero ?? '',
        cor_raca: crianca?.cor_raca ?? '',
        naturalidade: crianca?.naturalidade ?? '',
        nacionalidade: crianca?.nacionalidade ?? '',
        rg: crianca?.rg ?? '',
        cpf: crianca?.cpf ?? '',
        certidao_nascimento: crianca?.certidao_nascimento ?? '',
        rn: crianca?.rn ?? '',
        cartao_sus: crianca?.cartao_sus ?? '',
        nis: crianca?.nis ?? '',
        titulo_eleitor: crianca?.titulo_eleitor ?? '',
        nome_mae: crianca?.nome_mae ?? '',
        nome_pai: crianca?.nome_pai ?? '',
        responsavel_legal: crianca?.responsavel_legal ?? '',
        contato_responsavel: crianca?.contato_responsavel ?? '',
        endereco_familia: crianca?.endereco_familia ?? '',
        processo_numero: crianca?.processo_numero ?? '',
        vara: crianca?.vara ?? '',
        comarca: crianca?.comarca ?? '',
        foto: null,
        observacoes: crianca?.observacoes ?? '',
        informacao_escolar: null,
    });

    const [preview, setPreview] = useState(null);
    const [registrarInformacaoEscolar, setRegistrarInformacaoEscolar] = useState(false);

    const campo = (nome) => ({
        value: form.data[nome] ?? '',
        onChange: (e) => form.setData(nome, e.target.value),
        error: Boolean(form.errors[nome]),
        helperText: form.errors[nome],
    });

    const aoSelecionarFoto = (e) => {
        const arquivo = e.target.files?.[0] ?? null;
        form.setData('foto', arquivo);
        if (preview) URL.revokeObjectURL(preview);
        setPreview(arquivo ? URL.createObjectURL(arquivo) : null);
    };

    const alternarInformacaoEscolar = (event) => {
        const ativo = event.target.checked;
        setRegistrarInformacaoEscolar(ativo);
        form.setData('informacao_escolar', ativo ? informacaoEscolarVazia() : null);
        if (!ativo) {
            form.clearErrors('informacao_escolar');
        }
    };

    const alterarInformacaoEscolar = (nomeOuDados, valor) => {
        form.setData('informacao_escolar', typeof nomeOuDados === 'object'
            ? nomeOuDados
            : {
                ...form.data.informacao_escolar,
                [nomeOuDados]: valor,
            });
    };

    const enviar = (e) => {
        e.preventDefault();
        if (editando) {
            form.transform((dados) => ({ ...dados, _method: 'put' }));
            form.post(route('criancas.update', crianca.id), { forceFormData: true });
        } else {
            form.post(route('criancas.store'), { forceFormData: true });
        }
    };

    const titulo = editando ? 'Editar cadastro' : 'Nova criança';
    const fotoAtual = preview ?? crianca?.foto_url ?? undefined;

    return (
        <AppLayout>
            <Head title={titulo} />

            <PageHeader
                titulo={titulo}
                subtitulo={editando
                    ? crianca.nome_completo
                    : 'Cadastre a pessoa primeiro. O ingresso é registrado em seguida, na ficha.'}
            />

            <Box component="form" onSubmit={enviar}>
                <Stack spacing={{ xs: 2, sm: 3 }}>
                    <Secao titulo="Identificação">
                        <TextField label="Nome completo *" {...campo('nome_completo')} />
                        <TextField label="Nome social" {...campo('nome_social')} />
                        <TextField
                            label="Data de nascimento"
                            type="date"
                            slotProps={{ inputLabel: { shrink: true } }}
                            {...campo('data_nascimento')}
                        />
                        <TextField select label="Sexo" {...campo('sexo')}>
                            <MenuItem value="">Não informado</MenuItem>
                            <MenuItem value="Feminino">Feminino</MenuItem>
                            <MenuItem value="Masculino">Masculino</MenuItem>
                            <MenuItem value="Outro">Outro</MenuItem>
                        </TextField>
                        <TextField label="Identidade de gênero" {...campo('identidade_genero')} />
                        <TextField label="Cor/Raça" {...campo('cor_raca')} />
                        <TextField label="Naturalidade" {...campo('naturalidade')} />
                        <TextField label="Nacionalidade" {...campo('nacionalidade')} />
                        <TextField label="RG" {...campo('rg')} />
                        <TextField label="CPF" {...campo('cpf')} />
                        <TextField label="Certidão de nascimento" {...campo('certidao_nascimento')} />
                        <TextField label="RN (Registro de Nascimento)" {...campo('rn')} />
                        <TextField label="Cartão do SUS" {...campo('cartao_sus')} />
                        <TextField label="NIS" {...campo('nis')} />
                        <TextField label="Título de eleitor" {...campo('titulo_eleitor')} />
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                            <Avatar
                                src={fotoAtual}
                                alt={form.data.nome_completo}
                                sx={{ width: 96, height: 96, bgcolor: 'primary.main', fontWeight: 700, fontSize: 34 }}
                            >
                                {iniciais(form.data.nome_completo)}
                            </Avatar>
                            <Box>
                                <Button component="label" variant="outlined" startIcon={<FotoIcon />}>
                                    {fotoAtual ? 'Trocar foto' : 'Escolher foto'}
                                    <input hidden type="file" accept="image/jpeg,image/png" onChange={aoSelecionarFoto} />
                                </Button>
                                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mt: 0.5 }}>
                                    Opcional. JPEG ou PNG, até 4 MB.
                                </Typography>
                                {form.errors.foto && (
                                    <Typography variant="caption" color="error" sx={{ display: 'block', mt: 0.5 }}>
                                        {form.errors.foto}
                                    </Typography>
                                )}
                            </Box>
                        </Box>
                    </Secao>

                    <Secao titulo="Família">
                        <TextField label="Nome da mãe" {...campo('nome_mae')} />
                        <TextField label="Nome do pai" {...campo('nome_pai')} />
                        <TextField label="Responsável legal" {...campo('responsavel_legal')} />
                        <TextField label="Contato do responsável" {...campo('contato_responsavel')} />
                        <TextField
                            label="Endereço da família"
                            sx={{ gridColumn: { sm: '1 / -1' } }}
                            {...campo('endereco_familia')}
                        />
                    </Secao>

                    <Secao titulo="Processo">
                        <TextField label="Nº do processo" {...campo('processo_numero')} />
                        <TextField label="Vara" {...campo('vara')} />
                        <TextField label="Comarca" {...campo('comarca')} />
                    </Secao>

                    {!editando && (
                        <Secao titulo="Educação — informação inicial opcional">
                            <FormControlLabel
                                sx={{ gridColumn: '1 / -1' }}
                                control={(
                                    <Switch
                                        checked={registrarInformacaoEscolar}
                                        onChange={alternarInformacaoEscolar}
                                    />
                                )}
                                label="Registrar a informação escolar conhecida agora"
                            />
                            {registrarInformacaoEscolar && (
                                <InformacaoEscolarFields
                                    data={form.data.informacao_escolar}
                                    setData={alterarInformacaoEscolar}
                                    errors={form.errors}
                                    errorPrefix="informacao_escolar."
                                    opcoes={opcoesInformacaoEscolar}
                                />
                            )}
                        </Secao>
                    )}

                    <Secao titulo="Outros">
                        <TextField
                            label="Observações"
                            multiline
                            rows={3}
                            sx={{ gridColumn: { sm: '1 / -1' } }}
                            {...campo('observacoes')}
                        />
                    </Secao>

                    <Stack direction="row" spacing={1.5} justifyContent="flex-end">
                        <Button
                            component={Link}
                            href={editando ? route('criancas.show', crianca.id) : route('criancas.index')}
                            color="inherit"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" variant="contained" disabled={form.processing}>
                            {editando ? 'Salvar alterações' : 'Cadastrar'}
                        </Button>
                    </Stack>
                </Stack>
            </Box>
        </AppLayout>
    );
}

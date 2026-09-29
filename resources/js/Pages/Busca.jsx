import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, Chip, InputAdornment, Stack, TextField, Typography } from '@mui/material';
import { Search as SearchIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import CriancaCard from '@/Components/CriancaCard';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import SituacaoFilter from '@/Components/SituacaoFilter';

export default function Busca({ criancas, situacao, filtrosSituacao }) {
    const [valor, setValor] = useState('');
    const [filtroCarregando, setFiltroCarregando] = useState(false);
    const [filtroErro, setFiltroErro] = useState(false);

    const enviar = (e) => {
        e.preventDefault();
        const termo = valor.trim();

        if (termo === '') {
            router.get(route('busca'), {}, { replace: true });
            return;
        }

        router.post(
            route('busca.search'),
            { q: termo, situacao },
            { preserveState: true },
        );
    };

    const extras = (crianca) => (
        <Box sx={{ mt: 1 }}>
            {crianca.processo_numero && (
                <Typography
                    variant="caption"
                    color="primary"
                    sx={{ display: 'block', fontWeight: 700, mb: 0.5 }}
                >
                    Processo: {crianca.processo_numero}
                </Typography>
            )}
            <Stack direction="row" spacing={0.5} flexWrap="wrap" useFlexGap>
                <Chip size="small" variant="outlined" label={`PIA: ${crianca.pias_count ?? 0}`} />
                <Chip size="small" variant="outlined" label={`Ocorrências: ${crianca.reports_count ?? 0}`} />
                <Chip size="small" variant="outlined" label={`Visitas: ${crianca.visitas_tecnicas_count ?? 0}`} />
                <Chip size="small" variant="outlined" label={`Pertences: ${crianca.pertences_count ?? 0}`} />
            </Stack>
        </Box>
    );

    return (
        <AppLayout>
            <Head title="Busca" />

            <Box
                component="form"
                onSubmit={enviar}
                sx={{
                    display: 'flex', gap: 1.5, alignItems: 'flex-start',
                    flexDirection: { xs: 'column', sm: 'row' },
                    p: { xs: 2, sm: 3 }, mb: { xs: 2, sm: 3 },
                    bgcolor: 'background.paper', borderRadius: 3, border: '1px solid #e2e8f0',
                }}
            >
                <TextField
                    fullWidth
                    autoFocus
                    value={valor}
                    onChange={(e) => setValor(e.target.value)}
                    placeholder="Nome, nº do processo, RG, CPF ou nome dos pais..."
                    slotProps={{
                        input: {
                            startAdornment: (
                                <InputAdornment position="start">
                                    <SearchIcon color="action" />
                                </InputAdornment>
                            ),
                        },
                    }}
                />
                <Button
                    type="submit"
                    variant="contained"
                    size="large"
                    sx={{ flexShrink: 0, width: { xs: '100%', sm: 'auto' } }}
                >
                    Buscar
                </Button>
            </Box>

            {filtrosSituacao.length > 0 && (
                <SituacaoFilter
                    options={filtrosSituacao}
                    active={situacao}
                    loading={filtroCarregando}
                    error={filtroErro}
                    onStart={() => {
                        setFiltroErro(false);
                        setFiltroCarregando(true);
                    }}
                    onFinish={() => setFiltroCarregando(false)}
                    onError={() => setFiltroErro(true)}
                />
            )}

            {criancas === null && (
                <EmptyState
                    icone={SearchIcon}
                    titulo="Busca rápida"
                    mensagem="Encontre uma criança pelo nome, nº do processo, RG, CPF ou nome dos pais."
                />
            )}

            {criancas !== null && criancas.data.length === 0 && (
                <EmptyState
                    titulo="Nenhum cadastro nesta situação"
                    mensagem="Tente outro termo ou escolha outro filtro."
                />
            )}

            {criancas !== null && criancas.data.length > 0 && (
                <>
                    <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                        {criancas.total} resultado(s) encontrado(s)
                    </Typography>
                    <Stack spacing={1.5}>
                        {criancas.data.map((crianca, i) => (
                            <motion.div
                                key={crianca.id}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.2, delay: i * 0.04 }}
                            >
                                <CriancaCard crianca={crianca} detalhesExtras={extras(crianca)} />
                            </motion.div>
                        ))}
                    </Stack>
                    <Paginacao links={criancas.links} />
                </>
            )}
        </AppLayout>
    );
}

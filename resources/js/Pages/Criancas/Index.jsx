import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Box, Button, InputAdornment, TextField } from '@mui/material';
import { Add as AddIcon, Search as SearchIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaCard from '@/Components/CriancaCard';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';
import SituacaoFilter from '@/Components/SituacaoFilter';

export default function Index({ criancas, situacao, filtrosSituacao }) {
    const [busca, setBusca] = useState('');
    const [filtroCarregando, setFiltroCarregando] = useState(false);
    const [filtroErro, setFiltroErro] = useState(false);

    const enviarBusca = (event) => {
        event.preventDefault();
        const termo = busca.trim();

        if (termo !== '') {
            router.post(route('busca.search'), { q: termo, situacao });
        }
    };

    return (
        <AppLayout>
            <Head title="Crianças" />

            <PageHeader
                titulo="Crianças e adolescentes"
                acoes={
                    <Button
                        component={Link}
                        href={route('criancas.create')}
                        variant="contained"
                        startIcon={<AddIcon />}
                    >
                        Nova criança
                    </Button>
                }
            />

            <Box
                component="form"
                onSubmit={enviarBusca}
                sx={{
                    display: 'flex', gap: 1.5, mb: { xs: 2, sm: 3 }, p: { xs: 2, sm: 3 },
                    flexDirection: { xs: 'column', sm: 'row' },
                    bgcolor: 'background.paper', borderRadius: 3, border: '1px solid #e2e8f0',
                }}
            >
                <TextField
                    label="Buscar criança ou adolescente"
                    fullWidth
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    placeholder="Nome, processo ou RG..."
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
                    disabled={busca.trim() === ''}
                    sx={{ flexShrink: 0, width: { xs: '100%', sm: 'auto' } }}
                >
                    Buscar
                </Button>
            </Box>

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

            {criancas.data.length === 0 ? (
                <EmptyState
                    titulo="Nenhum cadastro nesta situação"
                    mensagem="Escolha outro filtro ou registre uma nova pessoa."
                />
            ) : (
                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr', lg: 'repeat(3, 1fr)' },
                        gap: { xs: 1.5, sm: 2 },
                    }}
                >
                    {criancas.data.map((crianca) => (
                        <Box key={crianca.id}>
                            <CriancaCard crianca={crianca} />
                        </Box>
                    ))}
                </Box>
            )}

            <Paginacao links={criancas.links} />
        </AppLayout>
    );
}

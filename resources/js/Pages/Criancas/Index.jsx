import { useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Box, Button, InputAdornment, MenuItem, TextField } from '@mui/material';
import { Add as AddIcon, Search as SearchIcon } from '@mui/icons-material';
import AppLayout from '@/Layouts/AppLayout';
import PageHeader from '@/Components/PageHeader';
import CriancaCard from '@/Components/CriancaCard';
import EmptyState from '@/Components/EmptyState';
import Paginacao from '@/Components/Paginacao';

export default function Index({ criancas, q, status }) {
    const [busca, setBusca] = useState(q ?? '');
    const primeiraVez = useRef(true);

    // Busca com debounce de 400ms ao digitar.
    useEffect(() => {
        if (primeiraVez.current) {
            primeiraVez.current = false;
            return;
        }
        const timer = setTimeout(() => {
            router.get(
                route('criancas.index'),
                { q: busca, status },
                { preserveState: true, replace: true },
            );
        }, 400);
        return () => clearTimeout(timer);
    }, [busca]);

    const aoMudarStatus = (e) => {
        router.get(
            route('criancas.index'),
            { q: busca, status: e.target.value },
            { preserveState: true, replace: true },
        );
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
                sx={{
                    display: 'flex', gap: 1.5, mb: { xs: 2, sm: 3 }, p: { xs: 2, sm: 3 },
                    flexDirection: { xs: 'column', sm: 'row' },
                    bgcolor: 'background.paper', borderRadius: 3, border: '1px solid #e2e8f0',
                }}
            >
                <TextField
                    fullWidth
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    placeholder="Buscar por nome, processo ou RG..."
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
                <TextField
                    select
                    label="Status"
                    value={status}
                    onChange={aoMudarStatus}
                    sx={{ minWidth: { xs: '100%', sm: 180 }, flexShrink: 0 }}
                >
                    <MenuItem value="acolhida">Acolhidas</MenuItem>
                    <MenuItem value="desligada">Desligadas</MenuItem>
                    <MenuItem value="todas">Todas</MenuItem>
                </TextField>
            </Box>

            {criancas.data.length === 0 ? (
                <EmptyState
                    titulo="Nenhuma criança encontrada"
                    mensagem="Ajuste a busca ou o filtro de status."
                />
            ) : (
                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr', lg: 'repeat(3, 1fr)' },
                        gap: { xs: 1.5, sm: 2 },
                    }}
                >
                    {criancas.data.map((crianca, i) => (
                        <motion.div
                            key={crianca.id}
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, delay: i * 0.04 }}
                        >
                            <CriancaCard crianca={crianca} />
                        </motion.div>
                    ))}
                </Box>
            )}

            <Paginacao links={criancas.links} />
        </AppLayout>
    );
}

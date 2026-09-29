import { useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { Alert, Box, Button, CircularProgress, Typography } from '@mui/material';

export default function SituacaoFilter({ options, active, loading, error, onStart, onFinish, onError }) {
    const [technicalError, setTechnicalError] = useState(false);

    useEffect(() => router.on('exception', () => {
        setTechnicalError(true);
    }), []);

    const handleStart = (...args) => {
        setTechnicalError(false);
        onStart?.(...args);
    };

    return (
        <Box
            component="section"
            aria-labelledby="situacao-filter-title"
            sx={{ mb: { xs: 2, sm: 3 } }}
        >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                <Typography id="situacao-filter-title" variant="subtitle2" sx={{ fontWeight: 700 }}>
                    Filtrar por situação
                </Typography>
                {loading && <CircularProgress size={16} aria-label="Carregando filtro" />}
            </Box>

            {(error || technicalError) && (
                <Alert severity="error" sx={{ mb: 1 }} role="alert">
                    Não foi possível aplicar o filtro. Tente novamente.
                </Alert>
            )}

            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {options.map((option) => {
                    const isActive = option.value === active;

                    return (
                        <Button
                            key={option.value}
                            component={Link}
                            href={option.href}
                            preserveScroll
                            size="small"
                            variant={isActive ? 'contained' : 'outlined'}
                            aria-current={isActive ? 'page' : undefined}
                            aria-label={`${option.label}: ${option.count}`}
                            onStart={handleStart}
                            onFinish={onFinish}
                            onError={onError}
                            sx={{ minHeight: 40 }}
                        >
                            {option.label} ({option.count})
                        </Button>
                    );
                })}
            </Box>
        </Box>
    );
}

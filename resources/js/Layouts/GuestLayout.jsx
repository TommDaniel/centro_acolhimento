import { Link } from '@inertiajs/react';
import { Box, Chip, Typography } from '@mui/material';
import { VolunteerActivism as LogoIcon } from '@mui/icons-material';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-50 px-4 pt-10 sm:justify-center sm:pt-0">
            <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 1, mb: 2 }}>
                <LogoIcon sx={{ fontSize: 48, color: 'primary.main' }} />
                <Typography variant="h6" sx={{ fontWeight: 800, color: 'text.primary' }}>
                    Centro de Acolhimento
                </Typography>
                <Chip size="small" color="warning" variant="outlined" label="POC — apenas dados fictícios" />
            </Box>

            <div className="w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:max-w-md sm:rounded-2xl">
                {children}
            </div>
        </div>
    );
}

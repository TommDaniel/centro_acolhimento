import { createTheme } from '@mui/material/styles';

const theme = createTheme({
    palette: {
        primary: { main: '#0d9488', '50': '#f0fdfa', '100': '#ccfbf1' }, // teal-600
        secondary: { main: '#334155' }, // slate-700
        background: { default: '#f8fafc' }, // slate-50
    },
    shape: { borderRadius: 12 },
    typography: {
        fontFamily: 'Figtree, ui-sans-serif, system-ui, sans-serif',
        h5: { fontWeight: 700 },
        h6: { fontWeight: 700 },
    },
    components: {
        MuiButton: {
            styleOverrides: {
                root: { textTransform: 'none', fontWeight: 600 },
            },
        },
        MuiCard: {
            styleOverrides: {
                root: {
                    boxShadow:
                        '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.05)',
                },
            },
        },
        MuiDrawer: {
            styleOverrides: {
                paper: { borderRight: '1px solid #e2e8f0' },
            },
        },
    },
});

export default theme;

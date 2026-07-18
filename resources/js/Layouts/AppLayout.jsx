import { useEffect, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    AppBar, Avatar, Box, Chip, Divider, Drawer, IconButton, InputAdornment,
    List, ListItemButton, ListItemIcon, ListItemText, ListSubheader, Menu,
    MenuItem, Snackbar, Alert, TextField, Toolbar, Typography,
} from '@mui/material';
import {
    Apartment as SetoresIcon,
    CalendarMonth as AgendaIcon,
    ChildCare as CriancasIcon,
    Dashboard as PainelIcon,
    Description as PiaIcon,
    DirectionsWalk as VisitasIcon,
    Groups as EquipeIcon,
    Inventory2 as PertencesIcon,
    Logout as LogoutIcon,
    Menu as MenuIcon,
    Person as PersonIcon,
    ReportProblem as ReportsIcon,
    Search as SearchIcon,
} from '@mui/icons-material';

const drawerWidth = 264;

/**
 * Mapa de navegação — fonte única do menu lateral.
 *
 * No menu: navegação principal (Painel, Crianças), os quatro tipos de
 * documento e a organização (Setores, Equipe).
 * Fora do menu, de propósito: busca (fica no header, é ação global),
 * perfil/sair (menu do usuário no header), criação de registros
 * (contextual — botões "Novo" nas listas e na ficha da criança) e
 * ações de documento (PDF/excluir ficam na tela de visualização).
 */
const secoes = [
    {
        titulo: 'Principal',
        itens: [
            { rotulo: 'Painel', href: '/dashboard', icone: PainelIcon },
            { rotulo: 'Agenda', href: '/agenda', icone: AgendaIcon },
            { rotulo: 'Crianças', href: '/criancas', icone: CriancasIcon },
        ],
    },
    {
        titulo: 'Documentos',
        itens: [
            { rotulo: 'PIA', href: '/pias', icone: PiaIcon },
            { rotulo: 'Visitas técnicas', href: '/visitas-tecnicas', icone: VisitasIcon },
            { rotulo: 'Ocorrências', href: '/reports', icone: ReportsIcon },
            { rotulo: 'Pertences', href: '/pertences', icone: PertencesIcon },
        ],
    },
    {
        titulo: 'Organização',
        itens: [
            { rotulo: 'Setores', href: '/setores', icone: SetoresIcon },
            { rotulo: 'Equipe', href: '/equipe', icone: EquipeIcon },
        ],
    },
];

function itemAtivo(url, href) {
    if (href === '/dashboard') return url === '/dashboard' || url === '/';
    return url.startsWith(href);
}

function ConteudoDrawer({ url, aoNavegar }) {
    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
            <Box sx={{ flex: 1, overflowY: 'auto', py: 1 }}>
                {secoes.map((secao) => (
                    <List
                        key={secao.titulo}
                        subheader={
                            <ListSubheader
                                component="div"
                                sx={{ bgcolor: 'transparent', fontWeight: 700, fontSize: 11, letterSpacing: 1 }}
                            >
                                {secao.titulo.toUpperCase()}
                            </ListSubheader>
                        }
                    >
                        {secao.itens.map((item) => {
                            const Icone = item.icone;
                            const ativo = itemAtivo(url, item.href);
                            return (
                                <ListItemButton
                                    key={item.href}
                                    component={Link}
                                    href={item.href}
                                    onClick={aoNavegar}
                                    selected={ativo}
                                    sx={{
                                        mx: 1,
                                        borderRadius: 2,
                                        '&.Mui-selected': {
                                            bgcolor: 'primary.50',
                                            color: 'primary.dark',
                                            '& .MuiListItemIcon-root': { color: 'primary.main' },
                                        },
                                    }}
                                >
                                    <ListItemIcon sx={{ minWidth: 40 }}>
                                        <Icone fontSize="small" />
                                    </ListItemIcon>
                                    <ListItemText
                                        primary={item.rotulo}
                                        primaryTypographyProps={{ fontWeight: ativo ? 700 : 500, fontSize: 14 }}
                                    />
                                </ListItemButton>
                            );
                        })}
                    </List>
                ))}
            </Box>
            <Divider />
            <Box sx={{ p: 2 }}>
                <Chip
                    size="small"
                    color="warning"
                    variant="outlined"
                    label="POC — apenas dados fictícios"
                    sx={{ width: '100%' }}
                />
            </Box>
        </Box>
    );
}

export default function AppLayout({ titulo, children }) {
    const { url, props } = usePage();
    const usuario = props.auth?.user;
    const [menuAberto, setMenuAberto] = useState(false);
    const [anchorUsuario, setAnchorUsuario] = useState(null);
    const [busca, setBusca] = useState(props.q ?? '');
    const [flashAberto, setFlashAberto] = useState(false);

    useEffect(() => {
        if (props.flash?.sucesso) setFlashAberto(true);
    }, [props.flash?.sucesso]);

    const enviarBusca = (e) => {
        e.preventDefault();
        if (busca.trim()) router.get(route('busca'), { q: busca.trim() });
    };

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh', bgcolor: 'background.default' }}>
            <AppBar
                position="fixed"
                color="inherit"
                elevation={0}
                sx={{
                    borderBottom: '1px solid #e2e8f0',
                    width: { md: `calc(100% - ${drawerWidth}px)` },
                    ml: { md: `${drawerWidth}px` },
                }}
            >
                <Toolbar sx={{ gap: 2 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', flex: 1, minWidth: 0 }}>
                        <IconButton
                            edge="start"
                            onClick={() => setMenuAberto(true)}
                            sx={{ display: { md: 'none' } }}
                            aria-label="Abrir menu"
                        >
                            <MenuIcon />
                        </IconButton>

                        <Box
                            component={Link}
                            href="/dashboard"
                            sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'primary.main', textDecoration: 'none' }}
                        >
                            <Box
                                component="img"
                                src="/images/logo-icon.jpg"
                                alt="Centro de Acolhimento Martinho Lutero"
                                sx={{ height: 42, width: 'auto', display: 'block' }}
                            />
                            <Typography
                                variant="subtitle1"
                                sx={{ fontWeight: 800, color: 'text.primary', display: { xs: 'none', sm: 'block' } }}
                                noWrap
                            >
                                Centro de Acolhimento
                            </Typography>
                        </Box>
                    </Box>

                    <Box
                        component="form"
                        onSubmit={enviarBusca}
                        sx={{ width: { xs: 200, sm: 380, md: 480 }, flexShrink: 1 }}
                    >
                        <TextField
                            size="small"
                            fullWidth
                            value={busca}
                            onChange={(e) => setBusca(e.target.value)}
                            placeholder="Buscar por nome, processo, RG, CPF..."
                            InputProps={{
                                startAdornment: (
                                    <InputAdornment position="start">
                                        <SearchIcon fontSize="small" color="action" />
                                    </InputAdornment>
                                ),
                                sx: { borderRadius: 99, bgcolor: '#f1f5f9' },
                            }}
                        />
                    </Box>

                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', flex: 1, minWidth: 0 }}>
                        <Box
                            component="button"
                            onClick={(e) => setAnchorUsuario(e.currentTarget)}
                            sx={{
                                display: 'flex', alignItems: 'center', gap: 1, cursor: 'pointer',
                                border: 0, bgcolor: 'transparent', p: 0.5, borderRadius: 2,
                                '&:hover': { bgcolor: '#f1f5f9' },
                            }}
                        >
                            <Avatar sx={{ width: 34, height: 34, bgcolor: 'primary.main', fontSize: 14, fontWeight: 700 }}>
                                {(usuario?.name ?? '?').trim().split(/\s+/).map((p, i, a) => (i === 0 || i === a.length - 1) ? p[0] : '').join('').toUpperCase()}
                            </Avatar>
                            <Typography
                                variant="body2"
                                sx={{ fontWeight: 600, color: 'text.primary', display: { xs: 'none', sm: 'block' }, maxWidth: 180 }}
                                noWrap
                            >
                                {usuario?.name}
                            </Typography>
                        </Box>
                    </Box>
                    <Menu
                        anchorEl={anchorUsuario}
                        open={Boolean(anchorUsuario)}
                        onClose={() => setAnchorUsuario(null)}
                        transformOrigin={{ horizontal: 'right', vertical: 'top' }}
                        anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
                    >
                        <MenuItem component={Link} href={route('profile.edit')} onClick={() => setAnchorUsuario(null)}>
                            <ListItemIcon><PersonIcon fontSize="small" /></ListItemIcon>
                            Meu perfil
                        </MenuItem>
                        <MenuItem onClick={() => router.post(route('logout'))}>
                            <ListItemIcon><LogoutIcon fontSize="small" /></ListItemIcon>
                            Sair
                        </MenuItem>
                    </Menu>
                </Toolbar>
            </AppBar>

            <Box component="nav" sx={{ width: { md: drawerWidth }, flexShrink: { md: 0 } }}>
                <Drawer
                    variant="temporary"
                    open={menuAberto}
                    onClose={() => setMenuAberto(false)}
                    ModalProps={{ keepMounted: true }}
                    sx={{ display: { xs: 'block', md: 'none' }, '& .MuiDrawer-paper': { width: drawerWidth } }}
                >
                    <ConteudoDrawer url={url} aoNavegar={() => setMenuAberto(false)} />
                </Drawer>
                <Drawer
                    variant="permanent"
                    open
                    sx={{ display: { xs: 'none', md: 'block' }, '& .MuiDrawer-paper': { width: drawerWidth } }}
                >
                    <ConteudoDrawer url={url} />
                </Drawer>
            </Box>

            <Box component="main" sx={{ flexGrow: 1, width: { md: `calc(100% - ${drawerWidth}px)` }, minWidth: 0 }}>
                <Toolbar />
                <motion.div
                    key={url}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.22, ease: 'easeOut' }}
                >
                    <Box sx={{ p: { xs: 2, sm: 3 }, maxWidth: 1200, mx: 'auto' }}>{children}</Box>
                </motion.div>
            </Box>

            <Snackbar
                open={flashAberto}
                autoHideDuration={4500}
                onClose={() => setFlashAberto(false)}
                anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
            >
                <Alert onClose={() => setFlashAberto(false)} severity="success" variant="filled" sx={{ borderRadius: 2 }}>
                    {props.flash?.sucesso}
                </Alert>
            </Snackbar>
        </Box>
    );
}

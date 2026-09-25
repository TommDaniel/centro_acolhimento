import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider, CssBaseline } from '@mui/material';
import theme from './theme';
import { removePasswordResetFragmentFromAddressBar } from './passwordResetFragment';
import {
    installBackForwardCacheProtection,
    protectAuthenticatedHistory,
} from './secureHistory';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

removePasswordResetFragmentFromAddressBar();
installBackForwardCacheProtection();

router.on('navigate', (event) => {
    protectAuthenticatedHistory(Boolean(event.detail.page.props.auth?.user));
});

router.on('invalid', (event) => {
    const response = event.detail.response;
    const sessionExpired = response.headers?.get?.('x-mfa-session-expired')
        ?? response.headers?.['x-mfa-session-expired'];

    if (response.status === 429 && sessionExpired === '1') {
        event.preventDefault();
        document.getElementById('app')?.replaceChildren();
        window.location.replace(route('login', undefined, false));
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        protectAuthenticatedHistory(Boolean(props.initialPage.props.auth?.user));

        root.render(
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <App {...props} />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#0d9488',
    },
});

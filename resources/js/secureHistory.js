import { router } from '@inertiajs/react';
import axios from 'axios';

const LOGOUT_MARKER_KEY = 'centro-acolhimento:secure-logout';
const LOGOUT_AUTO_ATTEMPT_KEY = 'centro-acolhimento:secure-logout-auto-attempted';
const LOGOUT_HISTORY_MARKER_KEY = 'secureLogoutPending';
const LOGOUT_HISTORY_AUTO_ATTEMPT_KEY = 'secureLogoutAutomaticAttempted';
const SHIELD_ID = 'secure-session-shield';

let protectedPage = false;
let logoutMarkerFallback = false;
let logoutAutomaticAttemptFallback = false;
let logoutRequestInFlight = false;
let loginUrl = '/login';
let logoutUrl = '/logout';
let protectionInstalled = false;
let applicationObserver = null;
let logoutAttemptSequence = 0;
let activeLogoutAttempt = null;

function hasLogoutMarker() {
    if (logoutMarkerFallback) {
        return true;
    }

    try {
        if (window.sessionStorage.getItem(LOGOUT_MARKER_KEY) === '1') {
            return true;
        }
    } catch {
        // O histórico da aba mantém o fail-closed quando o storage está indisponível.
    }

    try {
        return window.history.state?.[LOGOUT_HISTORY_MARKER_KEY] === true;
    } catch {
        return false;
    }
}

function markLogoutStarted() {
    logoutMarkerFallback = true;

    try {
        window.sessionStorage.setItem(LOGOUT_MARKER_KEY, '1');
    } catch {
        // O fallback em memória protege a aba quando o storage está indisponível.
    }

    try {
        const currentState = window.history.state && typeof window.history.state === 'object'
            ? window.history.state
            : {};
        window.history.replaceState(
            { ...currentState, [LOGOUT_HISTORY_MARKER_KEY]: true },
            document.title,
            window.location.href,
        );
    } catch {
        // O shield em memória continua protegendo o documento atual.
    }
}

function hasAutomaticLogoutAttempt() {
    if (logoutAutomaticAttemptFallback) {
        return true;
    }

    try {
        if (window.sessionStorage.getItem(LOGOUT_AUTO_ATTEMPT_KEY) === '1') {
            return true;
        }
    } catch {
        // O estado no histórico limita a recuperação mesmo sem storage.
    }

    try {
        return window.history.state?.[LOGOUT_HISTORY_AUTO_ATTEMPT_KEY] === true;
    } catch {
        return false;
    }
}

function markAutomaticLogoutAttempt() {
    logoutAutomaticAttemptFallback = true;

    try {
        window.sessionStorage.setItem(LOGOUT_AUTO_ATTEMPT_KEY, '1');
    } catch {
        // O histórico da aba preserva o limite de tentativas.
    }

    try {
        const currentState = window.history.state && typeof window.history.state === 'object'
            ? window.history.state
            : {};
        window.history.replaceState(
            { ...currentState, [LOGOUT_HISTORY_AUTO_ATTEMPT_KEY]: true },
            document.title,
            window.location.href,
        );
    } catch {
        // O fallback em memória ainda limita a página atual.
    }
}

function clearAutomaticLogoutAttempt() {
    logoutAutomaticAttemptFallback = false;

    try {
        window.sessionStorage.removeItem(LOGOUT_AUTO_ATTEMPT_KEY);
    } catch {
        // O fallback em memória já foi limpo.
    }

    try {
        if (window.history.state && typeof window.history.state === 'object') {
            const nextState = { ...window.history.state };
            delete nextState[LOGOUT_HISTORY_AUTO_ATTEMPT_KEY];
            window.history.replaceState(nextState, document.title, window.location.href);
        }
    } catch {
        // A entrada atual continua coberta pelo marker principal.
    }
}

export function clearSecureLogoutMarker() {
    logoutMarkerFallback = false;
    clearAutomaticLogoutAttempt();

    try {
        window.sessionStorage.removeItem(LOGOUT_MARKER_KEY);
    } catch {
        // O fallback em memória já permite o novo login explícito.
    }

    try {
        if (window.history.state && typeof window.history.state === 'object') {
            const nextState = { ...window.history.state };
            delete nextState[LOGOUT_HISTORY_MARKER_KEY];
            delete nextState[LOGOUT_HISTORY_AUTO_ATTEMPT_KEY];
            window.history.replaceState(nextState, document.title, window.location.href);
        }
    } catch {
        // A navegação rígida subsequente substitui a entrada atual.
    }
}

function clearInertiaHistorySafely() {
    try {
        router.clearHistory();
    } catch {
        // Storage indisponível já impede a descriptografia; o shield segue ativo.
    }
}

function clearApplication() {
    const application = document.getElementById('app');

    if (!application) {
        return;
    }

    application.removeAttribute('data-page');

    if (application.hasChildNodes()) {
        application.replaceChildren();
    }

    application.setAttribute('aria-hidden', 'true');
    application.setAttribute('inert', '');
}

function installApplicationObserver() {
    const application = document.getElementById('app');

    if (!application || applicationObserver) {
        return;
    }

    applicationObserver = new MutationObserver(() => {
        if (document.documentElement.hasAttribute('data-secure-session-shielded')
            && (application.hasChildNodes() || application.hasAttribute('data-page'))) {
            clearApplication();
        }
    });
    applicationObserver.observe(application, {
        childList: true,
        subtree: true,
    });
}

function shieldApplication(message, failure = false, retry = null) {
    document.documentElement.setAttribute('data-secure-session-shielded', '');
    installApplicationObserver();
    clearApplication();

    document.getElementById(SHIELD_ID)?.remove();

    const shield = document.createElement('main');
    shield.id = SHIELD_ID;
    shield.setAttribute('role', failure ? 'alert' : 'status');
    shield.style.cssText = 'position:fixed;inset:0;z-index:2147483647;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;background:#f8fafc;color:#334155;font-family:Figtree,sans-serif;';

    const text = document.createElement('p');
    text.textContent = message;
    shield.append(text);

    if (retry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Tentar sair novamente';
        button.style.cssText = 'margin-top:1rem;border:0;border-radius:.5rem;padding:.75rem 1rem;background:#0d9488;color:#fff;font:inherit;cursor:pointer;';
        button.addEventListener('click', retry);
        shield.append(button);
        document.body.append(shield);
        button.focus();

        return;
    }

    document.body.append(shield);
}

function showLogoutFailure(logoutUrl, attemptId) {
    if (attemptId !== undefined && activeLogoutAttempt?.id !== attemptId) {
        return;
    }

    logoutRequestInFlight = false;
    activeLogoutAttempt = null;
    shieldApplication(
        'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        true,
        () => retrySecureLogout(logoutUrl),
    );
}

async function sessionIsConfirmedLoggedOut() {
    try {
        const response = await window.fetch(loginUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html',
                'Cache-Control': 'no-cache',
            },
            redirect: 'manual',
        });
        const loginPath = new URL(loginUrl, window.location.origin).pathname;
        const responsePath = response.url
            ? new URL(response.url, window.location.origin).pathname
            : null;

        return response.ok && response.type !== 'opaqueredirect' && responsePath === loginPath;
    } catch {
        return false;
    }
}

function finishConfirmedLogout() {
    clearInertiaHistorySafely();
    clearSecureLogoutMarker();
    window.location.replace(loginUrl);
}

async function retrySecureLogout(logoutUrl) {
    if (logoutRequestInFlight) {
        return;
    }

    logoutRequestInFlight = true;
    markLogoutStarted();
    shieldApplication('Saindo com segurança…');
    clearInertiaHistorySafely();

    try {
        await axios.post(logoutUrl);
        finishConfirmedLogout();
    } catch {
        if (await sessionIsConfirmedLoggedOut()) {
            finishConfirmedLogout();

            return;
        }

        logoutRequestInFlight = false;
        showLogoutFailure(logoutUrl);
    }
}

function resumePendingLogout() {
    shieldApplication('Saindo com segurança…');

    if (logoutRequestInFlight) {
        return;
    }

    if (hasAutomaticLogoutAttempt()) {
        showLogoutFailure(logoutUrl);

        return;
    }

    markAutomaticLogoutAttempt();
    void retrySecureLogout(logoutUrl);
}

function revalidateProtectedHistory() {
    shieldApplication('Validando a sessão…');

    if (hasLogoutMarker()) {
        resumePendingLogout();
        return;
    }

    window.location.reload();
}

export function protectAuthenticatedHistory(isAuthenticated) {
    protectedPage = Boolean(isAuthenticated);

    if (hasLogoutMarker()) {
        resumePendingLogout();

        return false;
    }

    return true;
}

export function logoutSecurely(logoutUrl) {
    if (logoutRequestInFlight) {
        return;
    }

    logoutRequestInFlight = true;
    const attemptId = ++logoutAttemptSequence;
    activeLogoutAttempt = { id: attemptId, url: logoutUrl };
    clearAutomaticLogoutAttempt();
    markLogoutStarted();
    shieldApplication('Saindo com segurança…');
    clearInertiaHistorySafely();

    let succeeded = false;
    try {
        router.post(logoutUrl, {}, {
            preserveState: false,
            onSuccess: () => {
                if (activeLogoutAttempt?.id !== attemptId) {
                    return;
                }

                succeeded = true;
                activeLogoutAttempt = null;
                finishConfirmedLogout();
            },
            onError: () => showLogoutFailure(logoutUrl, attemptId),
            onCancel: () => showLogoutFailure(logoutUrl, attemptId),
            onFinish: () => {
                if (!succeeded && activeLogoutAttempt?.id === attemptId) {
                    showLogoutFailure(logoutUrl, attemptId);
                }
            },
        });
    } catch {
        showLogoutFailure(logoutUrl, attemptId);
    }
}

export function installBackForwardCacheProtection(options = {}) {
    if (protectionInstalled) {
        return;
    }

    protectionInstalled = true;
    loginUrl = options.loginUrl ?? loginUrl;
    logoutUrl = options.logoutUrl ?? logoutUrl;

    if (hasLogoutMarker()) {
        resumePendingLogout();
    }

    window.addEventListener('pagehide', () => {
        if (protectedPage) {
            shieldApplication('Protegendo a sessão…');
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted && protectedPage) {
            revalidateProtectedHistory();
        }
    });

    window.addEventListener('popstate', () => {
        if (protectedPage || hasLogoutMarker()) {
            revalidateProtectedHistory();
        }
    });

    router.on('exception', (event) => {
        if (logoutRequestInFlight && activeLogoutAttempt) {
            event.preventDefault();
            showLogoutFailure(activeLogoutAttempt.url, activeLogoutAttempt.id);
        }
    });

    router.on('invalid', (event) => {
        if (logoutRequestInFlight && activeLogoutAttempt) {
            event.preventDefault();
            showLogoutFailure(activeLogoutAttempt.url, activeLogoutAttempt.id);
        }
    });
}

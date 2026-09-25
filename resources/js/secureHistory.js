let protectedPage = false;

function blankApplication() {
    const application = document.getElementById('app');

    if (application) {
        application.replaceChildren();
        application.setAttribute('aria-busy', 'true');
    }
}

export function protectAuthenticatedHistory(isAuthenticated) {
    protectedPage = Boolean(isAuthenticated);
}

export function installBackForwardCacheProtection() {
    window.addEventListener('pagehide', (event) => {
        if (event.persisted && protectedPage) {
            blankApplication();
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted && protectedPage) {
            blankApplication();
            window.location.reload();
        }
    });
}

let capturedPasswordResetFragment = null;

export function removePasswordResetFragmentFromAddressBar() {
    if (window.location.hash.length <= 1) {
        return;
    }

    const fragment = window.location.hash.slice(1);
    const parameters = new URLSearchParams(fragment);
    const parameterNames = Array.from(parameters.keys(), (name) => name.toLowerCase());
    const hasResetBearer = parameterNames.includes('token');
    const hasResetEmail = parameterNames.includes('email');

    if (!hasResetBearer && !hasResetEmail) {
        return;
    }

    const isPasswordResetLanding = /\/reset-password\/?$/.test(window.location.pathname);
    const token = parameters.get('token');
    const email = parameters.get('email');

    if (isPasswordResetLanding && token && email) {
        capturedPasswordResetFragment = fragment;
    }

    const safeLocation = isPasswordResetLanding
        ? window.location.pathname
        : `${window.location.pathname}${window.location.search}`;
    window.history.replaceState(null, '', safeLocation);
}

export function takePasswordResetFragment() {
    const fragment = capturedPasswordResetFragment;
    capturedPasswordResetFragment = null;

    return fragment;
}

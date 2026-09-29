import { expect, request as playwrightRequest, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const e2eFixtures = {
    'chromium-desktop': {
        suffix: 'desktop',
        resetToken: 'token-reset-sintetico-desktop',
        secrets: {
            admin: 'JBSWY3DPEHPK3PXP',
            technical: 'KRSXG5DSNFXGOIDB',
            general: 'MFRGGZDFMZTWQ2LK',
            protectedLogout: 'MZXW6YTBOJQW443E',
            protectedTimeout: 'ON2XEZJOORUGS4ZJ',
            protectedInactive: 'GEZDGNBVGY3TQOJQ',
            adminSecurity: 'KRSXG5DSNFXGOIDB',
            adminUi: 'MFRGGZDFMZTWQ2LK',
        },
    },
    'chromium-mobile': {
        suffix: 'mobile',
        resetToken: 'token-reset-sintetico-mobile',
        secrets: {
            admin: 'ON2XEZJOORUGS4ZJ',
            technical: 'GEZDGNBVGY3TQOJQ',
            general: 'MZXW6YTBOJQW443E',
            protectedLogout: 'JBSWY3DPEHPK3PXP',
            protectedTimeout: 'KRSXG5DSNFXGOIDB',
            protectedInactive: 'MFRGGZDFMZTWQ2LK',
            adminSecurity: 'MZXW6YTBOJQW443E',
            adminUi: 'GEZDGNBVGY3TQOJQ',
        },
    },
};

function decodeBase32(value) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const character of value.replace(/=+$/, '')) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const bytes = [];
    for (let offset = 0; offset + 8 <= bits.length; offset += 8) {
        bytes.push(Number.parseInt(bits.slice(offset, offset + 8), 2));
    }

    return Buffer.from(bytes);
}

function totpForCounter(secret, counter) {
    const message = Buffer.alloc(8);
    message.writeBigUInt64BE(BigInt(counter));
    const digest = createHmac('sha1', decodeBase32(secret)).update(message).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return binary.toString().padStart(6, '0');
}

function currentTotp(secret) {
    return totpForCounter(secret, Math.floor(Date.now() / 30_000));
}

function invalidCurrentTotp(secret) {
    const counter = Math.floor(Date.now() / 30_000);
    const acceptedWindow = new Set([
        totpForCounter(secret, counter - 1),
        totpForCounter(secret, counter),
        totpForCounter(secret, counter + 1),
    ]);

    for (let candidate = 0; candidate <= 999_999; candidate++) {
        const code = candidate.toString().padStart(6, '0');
        if (!acceptedWindow.has(code)) {
            return code;
        }
    }

    throw new Error('Não foi possível gerar um código TOTP sintético inválido.');
}

async function loginWithMfa(page, email, secret) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL(/\/mfa\/challenge$/),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);
    await page.getByLabel('Código de segurança').fill(currentTotp(secret));
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('button', { name: 'Entrar' }).click(),
    ]);
}

async function openPendingEnrollment(page, email) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill('password');
    const [enrollmentResponse] = await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'GET'
            && new URL(response.url()).pathname === '/mfa/enroll'
        )),
        page.waitForURL(/\/mfa\/enroll$/),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);

    const enrollmentPage = await enrollmentResponse.json();

    return {
        enrollmentId: enrollmentPage.props.enrollmentId,
        setupKey: (await page.getByLabel('Chave de configuração do autenticador').textContent()).trim(),
    };
}

async function expectEnrollmentHistoryPurged(page, setupKey) {
    await page.goBack();
    await expect(page.getByLabel('QR Code para vincular o aplicativo autenticador')).toHaveCount(0);
    await expect(page.getByLabel('Chave de configuração do autenticador')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText(setupKey);
}

async function logoutFromApplication(page) {
    const userMenu = page.getByRole('button', { name: /Abrir menu de/ });
    await userMenu.focus();
    await expect(userMenu).toBeFocused();
    await page.keyboard.press('Enter');
    await Promise.all([
        page.waitForURL(/\/login$/),
        page.getByRole('menuitem', { name: 'Sair' }).click(),
    ]);
}

async function openAccountEdit(page, email) {
    const accountCard = page.locator('.MuiCard-root').filter({ hasText: email });
    await expect(accountCard).toBeVisible();
    await accountCard.getByRole('link', { name: /^Editar / }).click();
}

async function expectAuthenticatedHistoryPurged(page, sensitiveText) {
    await page.goBack();
    await expect(page.locator('body')).not.toContainText(sensitiveText);
    expect(await page.evaluate((text) => (
        !JSON.stringify(window.history.state).includes(text)
    ), sensitiveText)).toBe(true);
}

async function expectLoggedOutHistoryBlockedWithoutProtectedRequest(page, sensitiveText) {
    let protectedResponses = 0;
    const blockProtectedRequest = async (route) => {
        await route.abort();
    };
    const countProtectedResponse = (response) => {
        if (response.request().method() === 'GET' && new URL(response.url()).pathname === '/criancas') {
            protectedResponses += 1;
        }
    };

    await page.route('**/criancas', blockProtectedRequest);
    page.on('response', countProtectedResponse);
    await page.evaluate((text) => {
        const recordSensitiveVisibility = () => {
            window.sessionStorage.setItem(
                'e2e:secure-history-contained-sensitive-text',
                document.body.textContent.includes(text) ? '1' : '0',
            );
        };

        window.addEventListener('pagehide', recordSensitiveVisibility, { once: true });
        window.addEventListener('popstate', recordSensitiveVisibility, { once: true });
    }, sensitiveText);
    await page.evaluate(() => window.history.back());
    await expect(page).toHaveURL(/\/login$/);
    expect(await page.evaluate(() => (
        window.sessionStorage.getItem('e2e:secure-history-contained-sensitive-text')
    ))).toBe('0');
    await expect(page.locator('body')).not.toContainText(sensitiveText);
    expect(protectedResponses).toBe(0);
    expect(await page.evaluate((text) => (
        !JSON.stringify(window.history.state).includes(text)
    ), sensitiveText)).toBe(true);
    page.off('response', countProtectedResponse);
    await page.unroute('**/criancas', blockProtectedRequest);
}

function makeLogoutMarkerStorageUnavailable(markerKey) {
    for (const method of ['getItem', 'setItem', 'removeItem']) {
        const original = Storage.prototype[method];

        Object.defineProperty(Storage.prototype, method, {
            configurable: true,
            value(...args) {
                if (args[0] === markerKey) {
                    throw new DOMException('Storage indisponível no cenário sintético.', 'SecurityError');
                }

                return original.apply(this, args);
            },
        });
    }
}

async function fetchWithOriginalSession(isolatedRequest, originalRequest) {
    const originalHeaders = await originalRequest.allHeaders();
    const forwardedHeaders = {};
    for (const headerName of [
        'accept',
        'content-type',
        'cookie',
        'origin',
        'referer',
        'x-csrf-token',
        'x-inertia',
        'x-inertia-version',
        'x-requested-with',
        'x-xsrf-token',
    ]) {
        if (originalHeaders[headerName]) {
            forwardedHeaders[headerName] = originalHeaders[headerName];
        }
    }

    return isolatedRequest.fetch(originalRequest.url(), {
        data: originalRequest.postDataBuffer() ?? undefined,
        failOnStatusCode: false,
        headers: forwardedHeaders,
        maxRedirects: 0,
        method: originalRequest.method(),
    });
}

function cookieNamesWithChangedValues(before, after) {
    const cookiesAfterByName = new Map(after.map((cookie) => [cookie.name, cookie.value]));

    return before
        .filter((cookie) => cookiesAfterByName.get(cookie.name) !== cookie.value)
        .map((cookie) => cookie.name);
}

async function startLogoutWithoutWaitingForNavigation(page) {
    const userMenu = page.getByRole('button', { name: /Abrir menu de/ });
    await userMenu.focus();
    await page.keyboard.press('Enter');
    await page.getByRole('menuitem', { name: 'Sair' }).click();
}

test.describe('autenticação', () => {
    test('cadastro público permanece indisponível', async ({ request }) => {
        const getResponse = await request.get('/register');
        const postResponse = await request.post('/register', {
            form: {
                name: 'Usuário E2E Fictício',
                email: 'registro-e2e@poc.local',
                password: 'Senha-E2E-Ficticia-123!',
                password_confirmation: 'Senha-E2E-Ficticia-123!',
            },
        });

        expect(getResponse.status()).toBe(404);
        expect(postResponse.status()).toBe(404);
    });

    test('visitante é direcionado ao login ao abrir área protegida', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('heading', { name: 'Centro de Acolhimento' })).toBeVisible();
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByText('Remember me')).toHaveCount(0);
    });

    test('recuperação remove o token da URL, das props e do histórico', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const email = `reset.${fixture.suffix}@poc.local`;
        const requestedUrls = [];
        const requestReferrers = [];
        let addressBarAtCapture = null;
        page.on('request', (request) => {
            requestedUrls.push(request.url());
            requestReferrers.push(request.headers().referer ?? '');

            if (request.method() === 'POST'
                && new URL(request.url()).pathname === '/reset-password/capture') {
                addressBarAtCapture = page.url();
            }
        });
        await page.goto(`/reset-password#token=${encodeURIComponent(fixture.resetToken)}&email=${encodeURIComponent(email)}`);

        await expect(page).toHaveURL(/\/reset-password$/);
        expect(addressBarAtCapture).not.toContain('#');
        expect(requestedUrls.every((url) => !url.includes(fixture.resetToken))).toBe(true);
        expect(requestedUrls.every((url) => !url.includes(encodeURIComponent(email)))).toBe(true);
        expect(requestReferrers.every((referrer) => !referrer.includes(fixture.resetToken))).toBe(true);
        expect(requestReferrers.every((referrer) => !referrer.includes(encodeURIComponent(email)))).toBe(true);
        expect(page.url()).not.toContain(fixture.resetToken);
        await expect(page.locator('body')).not.toContainText(fixture.resetToken);
        await expect(page.getByLabel('Email')).toHaveValue(email, { timeout: 20_000 });

        await page.goto('/login');
        await page.goBack();
        await expect(page).toHaveURL(/\/reset-password$/);
        expect(page.url()).not.toContain(fixture.resetToken);
        await expect(page.locator('body')).not.toContainText(fixture.resetToken);
        await expect(page.getByLabel('Email')).toHaveValue(email);

        await page.getByLabel('Password', { exact: true }).fill('senha-e2e-nova-ficticia');
        await page.getByLabel('Confirm Password').fill('senha-e2e-nova-ficticia');
        await Promise.all([
            page.waitForURL(/\/login$/),
            page.getByRole('button', { name: 'Reset Password' }).click(),
        ]);

        await page.goBack();
        expect(page.url()).not.toContain(fixture.resetToken);
        await expect(page.locator('body')).not.toContainText(fixture.resetToken);
        await expect(page.getByLabel('Password')).toHaveCount(0);
    });

    test('sessão autenticada não preserva fragmento de recuperação após redirecionamento', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const email = `reset.${fixture.suffix}@poc.local`;
        await loginWithMfa(page, `general.${fixture.suffix}@poc.local`, fixture.secrets.general);

        await page.goto(`/reset-password#token=${encodeURIComponent(fixture.resetToken)}&email=${encodeURIComponent(email)}`);

        await expect(page).toHaveURL(/\/dashboard/);
        expect(page.url()).not.toContain(fixture.resetToken);
        expect(page.url()).not.toContain(encodeURIComponent(email));

        await page.goBack();
        expect(page.url()).not.toContain(fixture.resetToken);
        expect(page.url()).not.toContain(encodeURIComponent(email));
    });

    test('primeiro enrolamento remove o QR do histórico após confirmação', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];

        await page.goto('/login');
        expect(await page.evaluate(() => window.isSecureContext && window.crypto?.subtle !== undefined)).toBe(true);
        await page.getByLabel('Email').fill(`enrollment.${fixture.suffix}@poc.local`);
        await page.getByLabel('Password').fill('password');
        await Promise.all([
            page.waitForURL(/\/mfa\/enroll$/),
            page.getByRole('button', { name: 'Log in' }).click(),
        ]);

        const setupKey = (await page.getByLabel('Chave de configuração do autenticador').textContent()).trim();
        await page.getByLabel('Código de segurança').fill(currentTotp(setupKey));
        await Promise.all([
            page.waitForURL(/\/dashboard$/),
            page.getByRole('button', { name: 'Ativar proteção' }).click(),
        ]);

        await expectEnrollmentHistoryPurged(page, setupKey);
    });

    test('logout remove QR e chave do histórico', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const { setupKey } = await openPendingEnrollment(
            page,
            `enrollment-logout.${fixture.suffix}@poc.local`,
        );

        await Promise.all([
            page.waitForURL(/\/login$/),
            page.getByRole('button', { name: 'Sair com segurança' }).click(),
        ]);
        await expectEnrollmentHistoryPurged(page, setupKey);
    });

    test('pageshow inicial não revalida e restauração do bfcache revalida uma vez', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        await loginWithMfa(page, `general.${fixture.suffix}@poc.local`, fixture.secrets.general);

        let dashboardNavigations = 0;
        const countDashboardNavigation = (request) => {
            if (request.isNavigationRequest() && new URL(request.url()).pathname === '/dashboard') {
                dashboardNavigations += 1;
            }
        };
        page.on('request', countDashboardNavigation);

        const pathAfterInitialPageShow = await page.evaluate(async () => {
            window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: false }));
            await new Promise((resolve) => {
                window.requestAnimationFrame(() => window.requestAnimationFrame(resolve));
            });

            return window.location.pathname;
        });
        expect(pathAfterInitialPageShow).toBe('/dashboard');
        expect(dashboardNavigations).toBe(0);

        const navigation = page.waitForEvent('framenavigated', (frame) => frame === page.mainFrame());
        await page.evaluate(() => {
            window.setTimeout(() => {
                window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
            }, 0);
        });
        await navigation;
        await page.waitForLoadState('domcontentloaded');
        await page.evaluate(async () => {
            await new Promise((resolve) => {
                window.requestAnimationFrame(() => window.requestAnimationFrame(resolve));
            });
        });

        await expect(page).toHaveURL(/\/dashboard$/);
        expect(dashboardNavigations).toBe(1);
        page.off('request', countDashboardNavigation);
    });

    test('logout remove dados assistenciais do histórico e do bfcache', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        await loginWithMfa(
            page,
            `protected-logout.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedLogout,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();

        const logoutMarkerKey = 'centro-acolhimento:secure-logout';
        await page.addInitScript(makeLogoutMarkerStorageUnavailable, logoutMarkerKey);
        await page.evaluate(makeLogoutMarkerStorageUnavailable, logoutMarkerKey);

        let logoutPostRequests = 0;
        const failInterruptedLogouts = async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();

                return;
            }

            logoutPostRequests += 1;
            if (logoutPostRequests <= 2) {
                await route.abort('failed');

                return;
            }

            if (logoutPostRequests === 3) {
                await route.fulfill({
                    body: 'Sessão sintética não confirmada.',
                    contentType: 'text/plain',
                    status: 419,
                });

                return;
            }

            await route.continue();
        };
        await page.route('**/logout', failInterruptedLogouts);
        const userMenu = page.getByRole('button', { name: /Abrir menu de/ });
        await userMenu.focus();
        await page.keyboard.press('Enter');
        await page.getByRole('menuitem', { name: 'Sair' }).click();
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/criancas$/);
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBe(2);

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/criancas$/);
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBe(2);

        await page.getByRole('button', { name: 'Tentar sair novamente' }).click();
        await expect(page).toHaveURL(/\/criancas$/);
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBe(3);

        await Promise.all([
            page.waitForURL(/\/login$/),
            page.getByRole('button', { name: 'Tentar sair novamente' }).click(),
        ]);
        await page.unroute('**/logout', failInterruptedLogouts);

        await expect(page.locator('body')).not.toContainText(sensitiveText);
        await expectLoggedOutHistoryBlockedWithoutProtectedRequest(page, sensitiveText);

        await loginWithMfa(
            page,
            `admin-ui.${fixture.suffix}@poc.local`,
            fixture.secrets.adminUi,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();
        await page.goto('/dashboard');

        let historyRevalidationRequests = 0;
        const countHistoryRevalidation = (request) => {
            if (request.method() === 'GET' && new URL(request.url()).pathname === '/criancas') {
                historyRevalidationRequests += 1;
            }
        };
        page.on('request', countHistoryRevalidation);
        await page.goBack();
        await expect(page).toHaveURL(/\/criancas$/);
        await expect(page.getByText(sensitiveText).first()).toBeVisible();
        expect(historyRevalidationRequests).toBeGreaterThan(0);
        await page.goForward();
        await expect(page).toHaveURL(/\/dashboard$/);
        page.off('request', countHistoryRevalidation);
    });

    test('retry real após resposta perdida preserva cookies antigos e segue redirect guest', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        await loginWithMfa(
            page,
            `protected-logout.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedLogout,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();
        await page.waitForLoadState('networkidle');

        const browserCookiesBeforeLogout = await page.context().cookies();
        const isolatedRequest = await playwrightRequest.newContext();
        let logoutPostRequests = 0;
        let serverLogoutStatus = null;
        const discardFirstLogoutResponse = async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();

                return;
            }

            logoutPostRequests += 1;
            if (logoutPostRequests === 1) {
                const originalRequest = route.request();
                const response = await fetchWithOriginalSession(isolatedRequest, originalRequest);
                serverLogoutStatus = response.status();
                await route.abort('failed');

                return;
            }

            await route.continue();
        };
        await page.route('**/logout', discardFirstLogoutResponse);

        await startLogoutWithoutWaitingForNavigation(page);
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(serverLogoutStatus).toBeGreaterThanOrEqual(300);
        expect(serverLogoutStatus).toBeLessThan(400);

        const browserCookiesAfterLostResponse = await page.context().cookies();
        expect(cookieNamesWithChangedValues(
            browserCookiesBeforeLogout,
            browserCookiesAfterLostResponse,
        )).toEqual([]);

        let dashboardGets = 0;
        let loginRedirectGets = 0;
        let loginProbeCount = 0;
        const countRecoveryRequests = (request) => {
            if (request.method() !== 'GET') {
                return;
            }

            const path = new URL(request.url()).pathname;
            if (path === '/dashboard') {
                dashboardGets += 1;
            }

            if (path === '/login' && !request.isNavigationRequest()) {
                if (request.redirectedFrom() !== null) {
                    loginRedirectGets += 1;
                } else {
                    loginProbeCount += 1;
                }
            }
        };
        page.on('request', countRecoveryRequests);

        const retryResponsePromise = page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/logout'
        ));
        await Promise.all([
            page.waitForURL(/\/login$/),
            page.getByRole('button', { name: 'Tentar sair novamente' }).click(),
        ]);
        const retryResponse = await retryResponsePromise;
        expect(retryResponse.status()).toBe(302);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBe(2);
        expect(loginRedirectGets).toBe(1);
        expect(loginProbeCount).toBe(0);
        expect(dashboardGets).toBe(0);
        expect(await page.evaluate(() => (
            window.sessionStorage.getItem('centro-acolhimento:secure-logout') === null
            && window.history.state?.secureLogoutPending !== true
        ))).toBe(true);
        page.off('request', countRecoveryRequests);
        await page.unroute('**/logout', discardFirstLogoutResponse);
        await isolatedRequest.dispose();

        await expectLoggedOutHistoryBlockedWithoutProtectedRequest(page, sensitiveText);
    });

    test('419 sintético após logout efetivado usa probe antes de limpar o marker', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        await loginWithMfa(
            page,
            `protected-logout.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedLogout,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();
        await page.waitForLoadState('networkidle');

        const browserCookiesBeforeLogout = await page.context().cookies();
        const isolatedRequest = await playwrightRequest.newContext();
        let logoutPostRequests = 0;
        let serverLogoutStatus = null;
        const discardResponseThenReturn419 = async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();

                return;
            }

            logoutPostRequests += 1;
            if (logoutPostRequests === 1) {
                const response = await fetchWithOriginalSession(isolatedRequest, route.request());
                serverLogoutStatus = response.status();
                await route.abort('failed');

                return;
            }

            if (logoutPostRequests === 2) {
                await route.fulfill({
                    body: 'Sessão sintética expirada.',
                    contentType: 'text/plain',
                    status: 419,
                });

                return;
            }

            await route.continue();
        };
        await page.route('**/logout', discardResponseThenReturn419);

        await startLogoutWithoutWaitingForNavigation(page);
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(serverLogoutStatus).toBeGreaterThanOrEqual(300);
        expect(serverLogoutStatus).toBeLessThan(400);
        expect(cookieNamesWithChangedValues(
            browserCookiesBeforeLogout,
            await page.context().cookies(),
        )).toEqual([]);

        let dashboardGets = 0;
        let loginProbeCount = 0;
        let loginProbeCacheControl = null;
        let releaseLoginProbe;
        let signalLoginProbe;
        const loginProbeReleased = new Promise((resolve) => {
            releaseLoginProbe = resolve;
        });
        const loginProbeObserved = new Promise((resolve) => {
            signalLoginProbe = resolve;
        });
        const countDashboardGets = (request) => {
            if (request.method() === 'GET' && new URL(request.url()).pathname === '/dashboard') {
                dashboardGets += 1;
            }
        };
        const holdLoginProbe = async (route) => {
            const request = route.request();
            if (request.method() === 'GET'
                && new URL(request.url()).pathname === '/login'
                && !request.isNavigationRequest()) {
                loginProbeCount += 1;
                loginProbeCacheControl = (await request.allHeaders())['cache-control'] ?? null;
                signalLoginProbe();
                await loginProbeReleased;
            }

            await route.continue();
        };
        page.on('request', countDashboardGets);
        await page.route('**/login', holdLoginProbe);

        const retryResponsePromise = page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/logout'
        ));
        await page.getByRole('button', { name: 'Tentar sair novamente' }).click();
        const retryResponse = await retryResponsePromise;
        expect(retryResponse.status()).toBe(419);
        await loginProbeObserved;

        await expect(page.getByRole('status')).toContainText('Saindo com segurança…');
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(await page.evaluate(() => (
            window.sessionStorage.getItem('centro-acolhimento:secure-logout') === '1'
            || window.history.state?.secureLogoutPending === true
        ))).toBe(true);
        expect(loginProbeCount).toBe(1);
        expect(loginProbeCacheControl).toContain('no-cache');
        expect(dashboardGets).toBe(0);

        releaseLoginProbe();
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBe(2);
        expect(loginProbeCount).toBe(1);
        expect(dashboardGets).toBe(0);
        expect(await page.evaluate(() => (
            window.sessionStorage.getItem('centro-acolhimento:secure-logout') === null
            && window.history.state?.secureLogoutPending !== true
        ))).toBe(true);
        page.off('request', countDashboardGets);
        await page.unroute('**/login', holdLoginProbe);
        await page.unroute('**/logout', discardResponseThenReturn419);
        await isolatedRequest.dispose();

        await expectLoggedOutHistoryBlockedWithoutProtectedRequest(page, sensitiveText);
    });

    test('logout interrompido recupera após a sessão expirar sem ciclo de redirecionamento', async ({ page }, testInfo) => {
        test.setTimeout(180_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        await loginWithMfa(
            page,
            `protected-timeout.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedTimeout,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();

        let logoutPostRequests = 0;
        const failFirstLogout = async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();

                return;
            }

            logoutPostRequests += 1;
            if (logoutPostRequests === 1) {
                await route.abort('failed');

                return;
            }

            await route.continue();
        };
        await page.route('**/logout', failFirstLogout);

        const userMenu = page.getByRole('button', { name: /Abrir menu de/ });
        await userMenu.focus();
        await page.keyboard.press('Enter');
        await page.getByRole('menuitem', { name: 'Sair' }).click();
        await expect(page.getByRole('alert')).toContainText(
            'Não foi possível confirmar a saída. Nenhum dado assistencial está sendo exibido.',
        );
        await expect(page.locator('body')).not.toContainText(sensitiveText);

        const idleDeadline = Date.now() + 31_000;
        await expect.poll(
            async () => {
                const health = await page.request.get('/up');
                const serverDate = health.headers().date;

                return serverDate ? new Date(serverDate).getTime() : Date.now();
            },
            {
                message: 'aguardar o TTL idle após a tentativa de logout interrompida',
                timeout: 45_000,
                intervals: [5_000, 5_000, 5_000, 5_000, 5_000, 5_000, 5_000, 5_000],
            },
        ).toBeGreaterThan(idleDeadline);

        let dashboardNavigations = 0;
        const countDashboardNavigation = (request) => {
            if (request.isNavigationRequest() && new URL(request.url()).pathname === '/dashboard') {
                dashboardNavigations += 1;
            }
        };
        page.on('request', countDashboardNavigation);
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByLabel('Email')).toBeVisible({ timeout: 20_000 });
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        expect(logoutPostRequests).toBeGreaterThanOrEqual(2);
        expect(dashboardNavigations).toBe(0);
        page.off('request', countDashboardNavigation);
        await page.unroute('**/logout', failFirstLogout);

        await expectLoggedOutHistoryBlockedWithoutProtectedRequest(page, sensitiveText);
    });

    test('expiração verificada remove dados assistenciais do histórico', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        await loginWithMfa(
            page,
            `protected-timeout.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedTimeout,
        );
        await page.goto('/criancas');
        await expect(page.getByText(sensitiveText).first()).toBeVisible();

        const idleDeadline = Date.now() + 31_000;
        await expect.poll(
            async () => {
                const health = await page.request.get('/up');
                const serverDate = health.headers().date;

                return serverDate ? new Date(serverDate).getTime() : Date.now();
            },
            {
                message: 'aguardar o TTL idle sem tocar na sessão autenticada',
                timeout: 45_000,
                intervals: [5_000, 5_000, 5_000, 5_000, 5_000, 5_000, 5_000, 5_000],
            },
        ).toBeGreaterThan(idleDeadline);
        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.locator('body')).not.toContainText(sensitiveText);
        await expectAuthenticatedHistoryPurged(page, sensitiveText);
    });

    test('reset remoto da senha remove QR e chave do histórico restrito', async ({ browser }, testInfo) => {
        test.setTimeout(240_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const targetEmail = `enrollment-remote-reset.${fixture.suffix}@poc.local`;
        const restrictedContext = await browser.newContext();
        const restrictedPage = await restrictedContext.newPage();
        const { setupKey } = await openPendingEnrollment(restrictedPage, targetEmail);

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await loginWithMfa(
            adminPage,
            `admin-security.${fixture.suffix}@poc.local`,
            fixture.secrets.adminSecurity,
        );
        await adminPage.goto('/equipe');
        await openAccountEdit(adminPage, targetEmail);
        await adminPage.getByLabel('Nova senha (deixe em branco para manter)').fill('senha-remota-e2e-ficticia');
        await adminPage.getByLabel('Confirmar senha').fill('senha-remota-e2e-ficticia');
        await Promise.all([
            adminPage.waitForURL(/\/equipe$/),
            adminPage.getByRole('button', { name: 'Salvar alterações' }).click(),
        ]);

        await restrictedPage.reload({ waitUntil: 'domcontentloaded' });
        await expect(restrictedPage).toHaveURL(/\/login$/);
        await expectEnrollmentHistoryPurged(restrictedPage, setupKey);
        await adminContext.close();
        await restrictedContext.close();
    });

    test('administração mostra pendência de MFA e inativação limpa dados assistenciais', async ({ browser }, testInfo) => {
        test.setTimeout(240_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const sensitiveText = 'João Pedro da Silva Fictício';
        const inactiveTargetEmail = `protected-inactive.${fixture.suffix}@poc.local`;
        const pendingEmail = `pending-ui.${fixture.suffix}@poc.local`;
        const legacyEmail = `legacy-active-ui.${fixture.suffix}@poc.local`;
        const legacyName = `Conta legada aguardando MFA ${fixture.suffix} (Fictícia)`;

        const targetContext = await browser.newContext();
        const targetPage = await targetContext.newPage();
        await loginWithMfa(
            targetPage,
            `protected-inactive.${fixture.suffix}@poc.local`,
            fixture.secrets.protectedInactive,
        );
        await targetPage.goto('/criancas');
        await expect(targetPage.getByText(sensitiveText).first()).toBeVisible();

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await loginWithMfa(
            adminPage,
            `admin-ui.${fixture.suffix}@poc.local`,
            fixture.secrets.adminUi,
        );
        await adminPage.goto('/equipe');
        await expect(adminPage.getByText('Pendente de MFA').first()).toBeVisible();
        const legacyCard = adminPage.getByRole('article', { name: `Conta de ${legacyName}` });
        await expect(legacyCard).toContainText('Pendente de MFA');
        await expect(legacyCard.getByText('Ativa', { exact: true })).toHaveCount(0);

        await openAccountEdit(adminPage, legacyEmail);
        await expect(adminPage.getByLabel('Situação da conta *')).toHaveText(/Pendente de MFA/);
        await adminPage.getByLabel('Situação da conta *').click();
        await expect(adminPage.getByRole('option', { name: 'Ativa', exact: true })).toHaveCount(0);
        await expect(adminPage.getByRole('option', { name: 'Pendente de MFA' })).toBeVisible();
        await adminPage.keyboard.press('Escape');

        await adminPage.goto('/equipe');
        await openAccountEdit(adminPage, pendingEmail);
        await expect(adminPage.getByLabel('Situação da conta *')).toHaveText(/Pendente de MFA/);
        await adminPage.getByLabel('Situação da conta *').click();
        await expect(adminPage.getByRole('option', { name: 'Ativa', exact: true })).toHaveCount(0);
        await expect(adminPage.getByRole('option', { name: 'Pendente de MFA' })).toBeVisible();
        await adminPage.keyboard.press('Escape');

        await adminPage.goto('/equipe');
        await openAccountEdit(adminPage, inactiveTargetEmail);
        await adminPage.getByLabel('Situação da conta *').click();
        await adminPage.getByRole('option', { name: 'Inativa' }).click();
        await Promise.all([
            adminPage.waitForURL(/\/equipe$/),
            adminPage.getByRole('button', { name: 'Salvar alterações' }).click(),
        ]);

        const revokedResponse = await targetPage.reload({ waitUntil: 'domcontentloaded' });
        expect(revokedResponse.status()).toBe(403);
        await expect(targetPage.getByRole('heading', {
            name: 'Você não tem permissão para acessar esta área',
        })).toBeVisible();
        await expect(targetPage.locator('body')).not.toContainText(sensitiveText);
        await expectAuthenticatedHistoryPurged(targetPage, sensitiveText);
        await adminContext.close();
        await targetContext.close();
    });

    test('expiração da sessão restrita remove QR e chave do histórico', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const { setupKey } = await openPendingEnrollment(
            page,
            `enrollment-timeout.${fixture.suffix}@poc.local`,
        );

        await expect.poll(
            async () => {
                await page.reload({ waitUntil: 'domcontentloaded' });

                return new URL(page.url()).pathname;
            },
            {
                message: 'a sessão restrita deve expirar no servidor e redirecionar para o login',
                timeout: 75_000,
                intervals: [10_000, 10_000, 10_000, 10_000, 10_000, 10_000, 5_000],
            },
        ).toBe('/login');
        await expectEnrollmentHistoryPurged(page, setupKey);
    });

    test('limite de tentativas desmonta enrolamento e remove segredo do histórico', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const { enrollmentId, setupKey } = await openPendingEnrollment(
            page,
            `enrollment-rate-limit.${fixture.suffix}@poc.local`,
        );
        const invalidCode = invalidCurrentTotp(setupKey);
        const csrfCookie = (await page.context().cookies())
            .find((cookie) => cookie.name === 'XSRF-TOKEN');
        expect(csrfCookie).toBeDefined();

        for (let attempt = 0; attempt < 4; attempt++) {
            const response = await page.context().request.post(
                new URL('/mfa/enroll/confirm', page.url()).toString(),
                {
                    form: {
                        enrollment_id: enrollmentId,
                        code: invalidCode,
                    },
                    headers: {
                        'X-XSRF-TOKEN': decodeURIComponent(csrfCookie.value),
                    },
                    maxRedirects: 0,
                },
            );
            expect([302, 303]).toContain(response.status());
        }

        await page.getByLabel('Código de segurança').fill(invalidCode);
        const [limitedResponse] = await Promise.all([
            page.waitForResponse((candidate) => (
                candidate.request().method() === 'POST'
                && new URL(candidate.url()).pathname === '/mfa/enroll/confirm'
                && candidate.status() === 429
            )),
            page.waitForURL(/\/login$/),
            page.getByRole('button', { name: 'Ativar proteção' }).click(),
        ]);
        expect(limitedResponse.status()).toBe(429);
        await expect(page.getByLabel('QR Code para vincular o aplicativo autenticador')).toHaveCount(0);
        await expect(page.getByLabel('Chave de configuração do autenticador')).toHaveCount(0);
        await expect(page.locator('body')).not.toContainText(setupKey);
        await expect(page.getByText(/Muitas tentativas/)).toHaveCount(0);
        await expectEnrollmentHistoryPurged(page, setupKey);
    });

    test('usuário fictício acessa o painel e mantém horários da agenda', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        await loginWithMfa(page, `general.${fixture.suffix}@poc.local`, fixture.secrets.general);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: /Olá, Carla/ })).toBeVisible();
        await expect(page.getByText('Resumo da unidade')).toBeVisible();

        await page.goto('/agenda');
        await expect(page.getByRole('heading', { name: 'Agenda' })).toBeVisible();

        const localTomorrow = new Date(Date.now() + 86_400_000)
            .toLocaleDateString('en-CA', { timeZone: 'America/Sao_Paulo' });
        const localDayAfterTomorrow = new Date(Date.now() + 172_800_000)
            .toLocaleDateString('en-CA', { timeZone: 'America/Sao_Paulo' });
        const timedTitle = `Atendimento E2E fictício ${testInfo.project.name}`;
        const allDayTitle = `Audiência E2E fictícia ${testInfo.project.name}`;

        await page.getByRole('button', { name: 'Novo compromisso' }).click();
        await page.getByLabel('Título *').fill(timedTitle);
        await page.getByLabel('Data *').fill(localTomorrow);
        await page.getByLabel('Início').fill('09:15');

        const [createTimedResponse] = await Promise.all([
            page.waitForResponse((response) => (
                response.request().method() === 'POST'
                && new URL(response.url()).pathname === '/agenda'
            )),
            page.waitForResponse((response) => (
                response.request().method() === 'GET'
                && new URL(response.url()).pathname === '/agenda'
            )),
            page.getByRole('button', { name: 'Agendar' }).click(),
        ]);

        expect([302, 303]).toContain(createTimedResponse.status());
        await expect(page.getByRole('dialog', { name: 'Novo compromisso' })).toBeHidden();
        await page.getByRole('button').filter({ hasText: timedTitle }).filter({ hasText: '09:15' }).first().click();
        await expect(page.getByRole('dialog').filter({ hasText: timedTitle }).getByText(/09:15/)).toBeVisible();

        await page.getByRole('button', { name: 'Editar' }).click();
        await page.getByLabel('Início').fill('10:30');
        const [updateResponse] = await Promise.all([
            page.waitForResponse((response) => (
                response.request().method() === 'POST'
                && /\/agenda\/\d+$/.test(new URL(response.url()).pathname)
            )),
            page.waitForResponse((response) => (
                response.request().method() === 'GET'
                && new URL(response.url()).pathname === '/agenda'
            )),
            page.getByRole('button', { name: 'Salvar alterações' }).click(),
        ]);

        expect([302, 303]).toContain(updateResponse.status());
        await expect(page.getByRole('dialog', { name: 'Editar compromisso' })).toBeHidden();
        await page.getByRole('button').filter({ hasText: timedTitle }).filter({ hasText: '10:30' }).first().click();
        await expect(page.getByRole('dialog').filter({ hasText: timedTitle }).getByText(/10:30/)).toBeVisible();
        await page.getByRole('button', { name: 'Editar' }).click();
        await page.getByRole('button', { name: 'Cancelar' }).click();

        await page.getByRole('button', { name: 'Novo compromisso' }).click();
        await page.getByLabel('Título *').fill(allDayTitle);
        await page.getByLabel('Data *').fill(localDayAfterTomorrow);
        await page.getByRole('switch', { name: 'Dia inteiro' }).check();
        const [createAllDayResponse] = await Promise.all([
            page.waitForResponse((response) => (
                response.request().method() === 'POST'
                && new URL(response.url()).pathname === '/agenda'
            )),
            page.waitForResponse((response) => (
                response.request().method() === 'GET'
                && new URL(response.url()).pathname === '/agenda'
            )),
            page.getByRole('button', { name: 'Agendar' }).click(),
        ]);

        expect([302, 303]).toContain(createAllDayResponse.status());
        await page.getByRole('button').filter({ hasText: allDayTitle }).filter({ hasText: 'Dia inteiro' }).first().click();
        await expect(page.getByRole('dialog').filter({ hasText: allDayTitle }).getByText(/Dia inteiro/)).toBeVisible();
    });

    test('administradora consulta auditoria e técnica recebe acesso negado', async ({ browser }, testInfo) => {
        test.setTimeout(120_000);

        const fixture = e2eFixtures[testInfo.project.name];
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await loginWithMfa(adminPage, `admin.${fixture.suffix}@poc.local`, fixture.secrets.admin);
        await adminPage.goto('/auditoria');
        await expect(adminPage.getByRole('heading', { name: 'Auditoria funcional' })).toBeVisible();
        await adminContext.close();

        const technicalContext = await browser.newContext({
            viewport: { width: 390, height: 844 },
        });
        const technicalPage = await technicalContext.newPage();
        await loginWithMfa(technicalPage, `technical.${fixture.suffix}@poc.local`, fixture.secrets.technical);
        const deniedResponse = await technicalPage.goto('/auditoria');
        expect(deniedResponse.status()).toBe(403);
        const deniedHeading = technicalPage.getByRole('heading', {
            name: 'Você não tem permissão para acessar esta área',
        });
        await expect(deniedHeading).toBeVisible();
        await expect(deniedHeading).toBeFocused();
        await expect(technicalPage.getByText('/auditoria')).toHaveCount(0);
        await technicalPage.keyboard.press('Tab');
        await expect(technicalPage.getByRole('link', { name: 'Ir para o acesso ao sistema' })).toBeFocused();
        await technicalContext.close();
    });
});

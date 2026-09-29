import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const credentialsByProjectAndScenario = {
    'chromium-desktop': {
        normal: {
            email: 'bruno@poc.local',
            secret: 'JBSWY3DPEHPK3PXP',
        },
        concurrent: {
            email: 'carla@poc.local',
            secret: 'JBSWY3DPEHPK3PXP',
        },
    },
    'chromium-mobile': {
        normal: {
            email: 'diego@poc.local',
            secret: 'JBSWY3DPEHPK3PXP',
        },
        concurrent: {
            email: 'admin@poc.local',
            secret: 'JBSWY3DPEHPK3PXP',
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

function currentTotp(secret) {
    const message = Buffer.alloc(8);
    message.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000)));
    const digest = createHmac('sha1', decodeBase32(secret)).update(message).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return binary.toString().padStart(6, '0');
}

async function loginWithMfa(page, credentials) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(credentials.email);
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL(/\/mfa\/challenge$/),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);
    await page.getByLabel('Código de segurança').fill(currentTotp(credentials.secret));
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('button', { name: 'Entrar' }).click(),
    ]);
}

function expectSensitiveHeaders(response) {
    expect(response.headers()['cache-control']).toBe('no-store, private');
    expect(response.headers().pragma).toBe('no-cache');
    expect(response.headers()['referrer-policy']).toBe('no-referrer');
}

async function submitSearch(page, input, term) {
    const submissionPromise = page.waitForResponse((response) => (
        response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/busca'
    ));
    const resultsPromise = page.waitForResponse((response) => (
        response.request().method() === 'GET'
        && /^\/busca\/[a-zA-Z0-9]{64}$/.test(new URL(response.url()).pathname)
    ));

    await input.fill(term);
    await input.press('Enter');

    const [submissionResponse, resultsResponse] = await Promise.all([
        submissionPromise,
        resultsPromise,
    ]);

    expect(submissionResponse.status()).toBe(303);
    expect(submissionResponse.request().postDataJSON()).toMatchObject({ q: term });
    expectSensitiveHeaders(submissionResponse);
    expect(resultsResponse.status()).toBe(200);
    expectSensitiveHeaders(resultsResponse);

    const resultPayload = await resultsResponse.json();
    expect(resultPayload.props).not.toHaveProperty('q');
    expect(resultPayload.url).toMatch(/^\/busca\/[a-zA-Z0-9]{64}(?:\?situacao=[a-z_]+)?$/);

    const resultUrl = new URL(resultsResponse.url());
    expect([...resultUrl.searchParams.keys()].every((key) => key === 'situacao')).toBe(true);

    return { resultPayload, submissionResponse };
}

test.describe('privacidade da busca autorizada', () => {
    test('busca global e contextual usam corpo POST sem vazar o termo', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        const credentials = credentialsByProjectAndScenario[testInfo.project.name].normal;
        const observedRequests = [];
        page.on('request', (request) => {
            observedRequests.push({
                url: request.url(),
                referrer: request.headers().referer ?? '',
            });
        });

        await loginWithMfa(page, credentials);

        const csrfMarker = `CSRF-BUSCA-FICTICIO-${testInfo.project.name}`;
        const csrfResponse = await page.request.post('/busca', {
            form: { q: csrfMarker },
            maxRedirects: 0,
        });
        expect(csrfResponse.status()).toBe(419);
        expect(await csrfResponse.text()).not.toContain(csrfMarker);

        await page.goto('/dashboard');
        const globalSearch = page.getByRole('textbox');
        await expect(globalSearch).toHaveCount(1);

        const nameTerm = 'João Pedro da Silva Fictício';
        const { resultPayload: globalPayload, submissionResponse } = await submitSearch(
            page,
            globalSearch,
            nameTerm,
        );

        expect(globalPayload.props.criancas.total).toBe(1);
        expect(globalPayload.props.criancas.data[0].nome_completo).toBe(nameTerm);
        await expect(page.getByRole('link', { name: new RegExp(nameTerm) }).first()).toBeVisible();

        const searchLocation = submissionResponse.headers().location;
        expect(searchLocation).toMatch(/\/busca\/[a-zA-Z0-9]{64}(?:\?situacao=todos)?$/);
        await expect(page).toHaveURL(/\/busca\/[a-zA-Z0-9]{64}(?:\?situacao=todos)?$/);
        expect(page.url()).not.toContain(nameTerm);
        expect(page.url()).not.toContain(encodeURIComponent(nameTerm));

        await page.goto('/criancas');
        const indexSearch = page.getByLabel('Buscar criança ou adolescente');
        await expect(indexSearch).toBeVisible();
        const { resultPayload: indexPayload } = await submitSearch(page, indexSearch, nameTerm);
        expect(indexPayload.props.criancas.total).toBe(1);
        await expect(page).toHaveURL(/\/busca\/[a-zA-Z0-9]{64}(?:\?situacao=todos)?$/);
        expect(page.url()).not.toContain(nameTerm);
        expect(page.url()).not.toContain(encodeURIComponent(nameTerm));

        const browserState = await page.evaluate(() => ({
            history: JSON.stringify(window.history.state),
            localStorage: JSON.stringify({ ...window.localStorage }),
            sessionStorage: JSON.stringify({ ...window.sessionStorage }),
        }));
        for (const serializedState of Object.values(browserState)) {
            expect(serializedState).not.toContain(nameTerm);
            expect(serializedState).not.toContain(encodeURIComponent(nameTerm));
        }

        const referrerProbePromise = page.waitForRequest((request) => (
            request.method() === 'GET'
            && new URL(request.url()).pathname === '/up'
        ));
        await page.evaluate(() => fetch('/up', { cache: 'no-store' }));
        const referrerProbe = await referrerProbePromise;
        const probeReferrer = referrerProbe.headers().referer ?? '';
        expect(probeReferrer).toBe('');
        expect(probeReferrer).not.toContain(nameTerm);
        expect(probeReferrer).not.toContain(encodeURIComponent(nameTerm));

        await page.goto('/busca');
        const pageSearch = page.getByPlaceholder('Nome, nº do processo, RG, CPF ou nome dos pais...');
        await expect(pageSearch).toBeVisible();

        const processTerm = '1234567-89.2026.8.24.0000';
        const { resultPayload: contextualPayload } = await submitSearch(
            page,
            pageSearch,
            processTerm,
        );
        expect(contextualPayload.props.criancas.total).toBe(1);
        expect(contextualPayload.props.criancas.data[0].processo_numero).toBe(processTerm);
        await expect(page.getByText(`Processo: ${processTerm}`)).toBeVisible();

        await page.goto(`/busca/${'A'.repeat(64)}`);
        await expect(page).toHaveURL(/\/busca$/);
        await expect(page.getByText('Busca rápida')).toBeVisible();

        for (const term of [nameTerm, processTerm, csrfMarker]) {
            const encodedTerm = encodeURIComponent(term);
            for (const request of observedRequests) {
                expect(request.url).not.toContain(term);
                expect(request.url).not.toContain(encodedTerm);
                expect(request.referrer).not.toContain(term);
                expect(request.referrer).not.toContain(encodedTerm);
            }
        }
    });

    test('filtros de situação preservam busca opaca, teclado, histórico e estado vazio', async ({ page }, testInfo) => {
        test.setTimeout(180_000);
        const credentials = credentialsByProjectAndScenario[testInfo.project.name].normal;
        const childName = `Pessoa Filtro ${testInfo.project.name} Inteiramente Fictícia`;

        await loginWithMfa(page, credentials);
        await page.goto('/criancas/create');
        await page.getByLabel('Nome completo *').fill(childName);
        await Promise.all([
            page.waitForURL(/\/criancas\/\d+$/),
            page.getByRole('button', { name: 'Cadastrar' }).click(),
        ]);

        await page.goto('/criancas?situacao=sem_ingresso');
        const withoutAdmission = page.getByRole('link', { name: /Sem ingresso: \d+/ });
        await expect(withoutAdmission).toHaveAttribute('aria-current', 'page');
        await expect(page.getByRole('link', { name: new RegExp(childName) }).first()).toBeVisible();

        const legacy = page.getByRole('link', { name: /A conferir: \d+/ });
        await legacy.focus();
        await legacy.press('Enter');
        await expect(page).toHaveURL(/\/criancas\?situacao=a_conferir$/);
        await expect(page.getByText('João Pedro da Silva Fictício', { exact: true })).toBeVisible();
        await expect(page.getByText(childName, { exact: true })).toHaveCount(0);

        await page.goBack();
        await expect(page).toHaveURL(/\/criancas\?situacao=sem_ingresso$/);
        await expect(page.getByRole('link', { name: new RegExp(childName) }).first()).toBeVisible();

        await page.route(/\/criancas\?situacao=internados$/, async (route) => {
            await route.abort('failed');
        }, { times: 1 });
        await page.getByRole('link', { name: /Internados: \d+/ }).click();
        await expect(page.getByRole('alert')).toContainText('Não foi possível aplicar o filtro');
        await expect(page).toHaveURL(/\/criancas\?situacao=sem_ingresso$/);

        const indexSearch = page.getByLabel('Buscar criança ou adolescente');
        const { submissionResponse } = await submitSearch(page, indexSearch, childName);
        expect(submissionResponse.request().postDataJSON()).toMatchObject({
            q: childName,
            situacao: 'sem_ingresso',
        });
        await expect(page).toHaveURL(/\/busca\/[a-zA-Z0-9]{64}\?situacao=sem_ingresso$/);
        await expect(page.getByRole('link', { name: new RegExp(childName) }).first()).toBeVisible();

        await page.getByRole('link', { name: /Internados: 0/ }).click();
        await expect(page).toHaveURL(/\/busca\/[a-zA-Z0-9]{64}\?situacao=internados$/);
        await expect(page.getByText('Nenhum cadastro nesta situação')).toBeVisible();
        expect(page.url()).not.toContain(childName);
        expect(page.url()).not.toContain(encodeURIComponent(childName));
    });

    test('submissões paralelas preservam cada handle dentro do limite da sessão', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        await loginWithMfa(
            page,
            credentialsByProjectAndScenario[testInfo.project.name].concurrent,
        );

        const csrfCookie = (await page.context().cookies())
            .find((cookie) => cookie.name === 'XSRF-TOKEN');
        expect(csrfCookie).toBeDefined();

        const terms = Array.from(
            { length: 4 },
            (_, index) => `Busca concorrente inteiramente fictícia ${index + 1}`,
        );
        const responses = await Promise.all(terms.map((term) => page.request.post('/busca', {
            form: { q: term },
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrfCookie.value) },
            maxRedirects: 0,
        })));
        const locations = responses.map((response) => response.headers().location);

        for (const [index, response] of responses.entries()) {
            expect(response.status()).toBe(303);
            expectSensitiveHeaders(response);
            expect(locations[index]).toMatch(/\/busca\/[a-zA-Z0-9]{64}$/);
            expect(locations[index]).not.toContain(terms[index]);
            expect(locations[index]).not.toContain(encodeURIComponent(terms[index]));
        }
        expect(new Set(locations).size).toBe(terms.length);

        for (const location of locations) {
            const path = new URL(location, page.url()).pathname;
            const result = await page.request.get(path, { maxRedirects: 0 });
            expect(result.status()).toBe(200);
            expectSensitiveHeaders(result);

            const body = await result.text();
            for (const term of terms) {
                expect(body).not.toContain(term);
                expect(body).not.toContain(encodeURIComponent(term));
            }
        }
    });
});

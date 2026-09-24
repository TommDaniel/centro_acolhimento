import { expect, test } from '@playwright/test';

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

    test('usuário fictício acessa o painel e mantém horários da agenda', async ({ page }, testInfo) => {
        test.setTimeout(120_000);

        await page.goto('/login');
        await page.getByLabel('Email').fill('admin@poc.local');
        await page.getByLabel('Password').fill('password');

        const loginResponsePromise = page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/login'
        ));
        await Promise.all([
            page.waitForURL(/\/dashboard$/),
            page.getByRole('button', { name: 'Log in' }).click(),
        ]);
        const loginResponse = await loginResponsePromise;

        expect([302, 303]).toContain(loginResponse.status());
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: /Olá, Ana/ })).toBeVisible();
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

    test('administradora consulta auditoria e técnica recebe acesso negado', async ({ browser }) => {
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        await adminPage.goto('/login');
        await adminPage.getByLabel('Email').fill('admin@poc.local');
        await adminPage.getByLabel('Password').fill('password');
        await Promise.all([
            adminPage.waitForURL(/\/dashboard$/),
            adminPage.getByRole('button', { name: 'Log in' }).click(),
        ]);
        await adminPage.goto('/auditoria');
        await expect(adminPage.getByRole('heading', { name: 'Auditoria funcional' })).toBeVisible();
        await adminContext.close();

        const technicalContext = await browser.newContext({
            viewport: { width: 390, height: 844 },
        });
        const technicalPage = await technicalContext.newPage();
        await technicalPage.goto('/login');
        await technicalPage.getByLabel('Email').fill('bruno@poc.local');
        await technicalPage.getByLabel('Password').fill('password');
        await Promise.all([
            technicalPage.waitForURL(/\/dashboard$/),
            technicalPage.getByRole('button', { name: 'Log in' }).click(),
        ]);
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

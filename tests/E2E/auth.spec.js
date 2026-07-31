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
    });

    test('usuário fictício autenticado acessa o painel', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Email').fill('admin@poc.local');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Log in' }).click();

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: /Olá, Ana/ })).toBeVisible();
        await expect(page.getByText('Resumo da unidade')).toBeVisible();
    });
});

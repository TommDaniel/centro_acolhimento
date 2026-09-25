import { expect, test } from '@playwright/test';

test.describe('higiene do fragmento de recuperação', () => {
    test('preserva uma âncora comum sem parâmetros sensíveis', async ({ page }) => {
        await page.goto('/forgot-password#ajuda-recuperacao');

        await expect(page).toHaveURL(/\/forgot-password#ajuda-recuperacao$/);
    });

    test('fragmento parcial de recuperação falha fechado sem chegar à rede', async ({ page }, testInfo) => {
        const marker = `parcial.${testInfo.project.name}@poc.local`;
        const requestedUrls = [];
        page.on('request', (request) => requestedUrls.push(request.url()));

        await page.goto(`/reset-password#email=${encodeURIComponent(marker)}`);

        await expect(page).toHaveURL(/\/forgot-password$/);
        expect(page.url()).not.toContain(marker);
        expect(requestedUrls.every((url) => !url.includes(encodeURIComponent(marker)))).toBe(true);
    });
});

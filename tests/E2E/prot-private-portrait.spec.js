import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const accounts = {
    'chromium-desktop': {
        suffix: 'desktop',
        secret: 'KRSXG5DSNFXGOIDB',
    },
    'chromium-mobile': {
        suffix: 'mobile',
        secret: 'GEZDGNBVGY3TQOJQ',
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
    const counter = Math.floor(Date.now() / 30_000);
    const message = Buffer.alloc(8);
    message.writeBigUInt64BE(BigInt(counter));
    const digest = createHmac('sha1', decodeBase32(secret)).update(message).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return binary.toString().padStart(6, '0');
}

async function waitForFreshTotpStep() {
    const initialCounter = Math.floor(Date.now() / 30_000);

    await expect.poll(
        () => Math.floor(Date.now() / 30_000),
        {
            message: 'usar um passo TOTP ainda não consumido por outro cenário sintético',
            timeout: 35_000,
            intervals: [250, 500, 1_000],
        },
    ).toBeGreaterThan(initialCounter);
}

async function loginWithMfa(page, account) {
    await waitForFreshTotpStep();
    await page.goto('/login');
    await page.getByLabel('Email').fill(`technical.${account.suffix}@poc.local`);
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL(/\/mfa\/challenge$/),
        page.getByRole('button', { name: 'Log in' }).click(),
    ]);
    await page.getByLabel('Código de segurança').fill(currentTotp(account.secret));
    await Promise.all([
        page.waitForURL(/\/dashboard$/),
        page.getByRole('button', { name: 'Entrar' }).click(),
    ]);
}

async function syntheticPng(page) {
    const dataUrl = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 64;
        canvas.height = 48;
        const context = canvas.getContext('2d');
        context.fillStyle = '#2563eb';
        context.fillRect(0, 0, 64, 48);
        context.fillStyle = '#facc15';
        context.fillRect(8, 8, 20, 20);

        return canvas.toDataURL('image/png');
    });

    return Buffer.from(dataUrl.split(',', 2)[1], 'base64');
}

async function expectAttachmentControlsAbsent(page) {
    await expect(page.getByText(
        /O envio e o download de anexos estão temporariamente desativados/,
    )).toBeVisible();
    await expect(page.getByRole('button', { name: /anex|arquivo/i })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /anex|arquivo/i })).toHaveCount(0);
    await expect(page.getByLabel(/anex|arquivo/i)).toHaveCount(0);
}

test('retrato privado percorre UI, endpoint e PDF sem reativar anexos', async ({ page }, testInfo) => {
    test.setTimeout(180_000);

    const account = accounts[testInfo.project.name];
    const childName = `Acolhido Retrato E2E ${account.suffix} (Fictício)`;
    await loginWithMfa(page, account);

    await page.goto('/criancas/create');
    await page.getByLabel('Nome completo *').fill(childName);
    await page.getByLabel('Escolher foto').setInputFiles({
        name: `retrato-${account.suffix}-ficticio.png`,
        mimeType: 'image/png',
        buffer: await syntheticPng(page),
    });
    await expect(page.getByRole('img', { name: childName })).toBeVisible();

    const portraitResponsePromise = page.waitForResponse((response) => (
        response.status() === 200
        && /\/criancas\/\d+\/portrait$/.test(new URL(response.url()).pathname)
    ));
    await Promise.all([
        page.waitForURL(/\/criancas\/\d+$/),
        page.getByRole('button', { name: 'Cadastrar' }).click(),
    ]);
    const portraitResponse = await portraitResponsePromise;

    expect(portraitResponse.headers()['content-type']).toBe('image/png');
    expect(portraitResponse.headers()['cache-control']).toBe('no-store, private');
    expect(portraitResponse.headers().pragma).toBe('no-cache');
    expect(portraitResponse.headers()['referrer-policy']).toBe('no-referrer');
    expect(portraitResponse.headers()['x-content-type-options']).toBe('nosniff');
    expect((await portraitResponse.body()).subarray(0, 8)).toEqual(
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    );

    const portrait = page.getByRole('img', { name: childName });
    await expect(portrait).toBeVisible();
    await expect(portrait).toHaveAttribute('src', /\/criancas\/\d+\/portrait$/);
    expect(await portrait.getAttribute('src')).not.toContain('/storage/');
    await expect(page.locator('body')).not.toContainText('portraits/v1/');
    await expectAttachmentControlsAbsent(page);

    const childId = new URL(page.url()).pathname.split('/').at(-1);
    await page.goto(`/pias/create?crianca_id=${childId}`);
    await expectAttachmentControlsAbsent(page);
    await Promise.all([
        page.waitForURL(/\/pias\/\d+$/),
        page.getByRole('button', { name: 'Registrar PIA' }).click(),
    ]);

    await expect(page.getByRole('img', { name: childName })).toBeVisible();
    await expectAttachmentControlsAbsent(page);
    await expect(page.locator('body')).not.toContainText('portraits/v1/');

    const piaId = new URL(page.url()).pathname.split('/').at(-1);
    const pdfResponse = await page.request.get(`/pias/${piaId}/pdf`);
    expect(pdfResponse.status()).toBe(200);
    expect(pdfResponse.headers()['content-type']).toBe('application/pdf');
    expect(pdfResponse.headers()['cache-control']).toBe('no-store, private');
    expect(pdfResponse.headers()['x-content-type-options']).toBe('nosniff');
    const pdf = await pdfResponse.body();
    expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');
    expect(pdf.toString('latin1')).not.toContain('portraits/v1/');
});

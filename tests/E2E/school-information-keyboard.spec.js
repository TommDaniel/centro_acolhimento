import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

const accounts = {
    'chromium-desktop': { suffix: 'desktop', secret: 'KRSXG5DSNFXGOIDB' },
    'chromium-mobile': { suffix: 'mobile', secret: 'GEZDGNBVGY3TQOJQ' },
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
        { timeout: 35_000, intervals: [250, 500, 1_000] },
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

test('Nova criança permite navegar na seção escolar pelo teclado', async ({ page }, testInfo) => {
    test.setTimeout(90_000);
    const account = accounts[testInfo.project.name];

    await loginWithMfa(page, account);
    await page.goto('/criancas/create');

    const schoolSwitch = page.getByRole('switch', { name: 'Registrar a informação escolar conhecida agora' });
    await schoolSwitch.focus();
    await expect(schoolSwitch).toBeFocused();
    await page.keyboard.press('Space');
    await expect(schoolSwitch).toBeChecked();

    const schoolStatus = page.getByRole('combobox', { name: 'Situação escolar', exact: true });
    await expect(schoolStatus).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(schoolStatus).toBeFocused();
});

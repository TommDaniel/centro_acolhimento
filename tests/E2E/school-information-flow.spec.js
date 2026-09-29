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

async function escolherOpcao(page, label, option) {
    await page.getByRole('combobox', { name: label, exact: true }).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

test('informação escolar é opcional, responsiva e atualizada sem apagar histórico', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const account = accounts[testInfo.project.name];
    const childName = `Pessoa Educação E2E ${account.suffix} (Fictícia)`;
    const initialSchool = `Escola Inicial E2E ${account.suffix} Fictícia`;
    const initialEnrollment = `MATR-E2E-INICIAL-${account.suffix}`;
    const revisedEnrollment = `MATR-E2E-REVISADA-${account.suffix}`;

    await loginWithMfa(page, account);
    await page.goto('/criancas/create');
    await page.getByLabel('Nome completo *').fill(childName);
    await page.getByRole('switch', { name: 'Registrar a informação escolar conhecida agora' }).check();
    await escolherOpcao(page, 'Situação escolar', 'Matriculada');
    await page.getByRole('textbox', { name: 'Escola', exact: true }).fill(initialSchool);
    await escolherOpcao(page, 'Rede de ensino', 'Municipal');
    await page.getByLabel('Matrícula').fill(initialEnrollment);
    await page.getByLabel('Ano / série').fill('7º ano fictício');
    await page.getByLabel('Turma').fill('Turma E2E fictícia');
    await escolherOpcao(page, 'Turno', 'Matutino');
    await page.getByLabel('Informação vigente em').fill('2026-09-29');
    await escolherOpcao(page, 'Fonte da informação', 'Documento');
    await Promise.all([
        page.waitForURL(/\/criancas\/\d+$/),
        page.getByRole('button', { name: 'Cadastrar' }).click(),
    ]);

    await expect(page.getByRole('heading', { name: 'Educação', exact: true })).toBeVisible();
    await expect(page.getByText('Estado atual: Matriculada')).toBeVisible();
    await expect(page.getByText(initialSchool, { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Nenhuma versão anterior.')).toBeVisible();

    await page.getByRole('button', { name: 'Registrar atualização' }).click();
    const dialog = page.getByRole('dialog');
    await escolherOpcao(page, 'Rede de ensino', 'Outra');
    await dialog.getByLabel('Qual rede?').fill('Rede conveniada E2E fictícia');
    await dialog.getByLabel('Matrícula').fill(revisedEnrollment);
    await dialog.getByLabel('Informação vigente em').fill('2026-09-30');
    await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && /\/criancas\/\d+\/informacoes-escolares$/.test(new URL(response.url()).pathname)
        )),
        dialog.getByRole('button', { name: 'Salvar nova versão' }).click(),
    ]);

    await expect(dialog).toBeHidden();
    await expect(page.getByText('Estado atual: Matriculada')).toBeVisible();
    await expect(page.getByText(initialSchool, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(revisedEnrollment, { exact: true })).toBeVisible();
    await expect(page.getByText('Outra: Rede conveniada E2E fictícia', { exact: true })).toBeVisible();
    await expect(page.getByText(/^Registrado por [^·]+ em \d{2}\/\d{2}\/\d{4}/).first()).toBeVisible();

    for (let version = 2; version <= 21; version += 1) {
        await page.getByRole('button', { name: 'Registrar atualização' }).click();
        await dialog.getByLabel('Matrícula').fill(`MATR-E2E-HIST-${account.suffix}-${version}`);
        await Promise.all([
            page.waitForResponse((response) => (
                response.request().method() === 'POST'
                && /\/criancas\/\d+\/informacoes-escolares$/.test(new URL(response.url()).pathname)
            )),
            dialog.getByRole('button', { name: 'Salvar nova versão' }).click(),
        ]);
        await expect(dialog).toBeHidden();
    }

    await expect(page.getByText(`MATR-E2E-HIST-${account.suffix}-21`, { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Carregar versões anteriores' })).toBeVisible();

    const details = page.getByText('Ver todos os campos desta versão', { exact: true });
    await details.last().click();
    await expect(page.getByText(revisedEnrollment, { exact: true })).toBeVisible();
    await expect(page.getByText('Outra: Rede conveniada E2E fictícia', { exact: true }).last()).toBeVisible();
    await expect(page.getByText('30/09/2026', { exact: true }).last()).toBeVisible();

    await page.getByRole('button', { name: 'Carregar versões anteriores' }).click();
    await expect(page.getByRole('button', { name: 'Carregar versões anteriores' })).toBeHidden();
    await expect(details).toHaveCount(21);
    await details.last().click();
    await expect(page.getByText(initialEnrollment, { exact: true })).toBeVisible();
    await expect(page.getByText('Municipal', { exact: true }).last()).toBeVisible();
    await expect(page.getByText('29/09/2026', { exact: true }).last()).toBeVisible();
});

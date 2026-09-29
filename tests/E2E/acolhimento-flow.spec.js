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
    await page.getByRole('combobox', { name: label }).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

async function registrarMovimentacao(page, {
    action,
    effectiveAt,
    reason,
    foundation,
    destination,
}) {
    await page.getByRole('button', { name: action }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Data e hora efetivas *').fill(effectiveAt);

    if (reason) {
        await dialog.getByLabel('Motivo *').fill(reason);
    }
    if (foundation) {
        await dialog.getByLabel('Fundamento').fill(foundation);
    }
    if (destination) {
        await dialog.getByLabel(/Local da internação \*|Destino \*/).fill(destination);
    }

    await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && /\/acolhimentos\/\d+\/movimentacoes$/.test(new URL(response.url()).pathname)
        )),
        dialog.getByRole('button', { name: 'Registrar movimentação' }).click(),
    ]);
    await expect(dialog).toBeHidden();
}

test('ingresso e sequência de movimentações preservam a linha do tempo', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const account = accounts[testInfo.project.name];
    const childName = `Pessoa Acolhimento E2E ${account.suffix} (Fictícia)`;

    await loginWithMfa(page, account);
    await page.goto('/criancas/create');
    await page.getByLabel('Nome completo *').fill(childName);
    await page.getByLabel('Nº do processo').fill(`PROCESSO-INGRESSO-${account.suffix}`);
    await page.getByLabel('Vara').fill(`Vara Ingresso ${account.suffix} Fictícia`);
    await page.getByLabel('Comarca').fill(`Comarca Ingresso ${account.suffix} Fictícia`);
    await Promise.all([
        page.waitForURL(/\/criancas\/\d+$/),
        page.getByRole('button', { name: 'Cadastrar' }).click(),
    ]);

    await expect(page.getByText('Ingresso ainda não registrado', { exact: false }).first()).toBeVisible();
    await page.getByRole('button', { name: 'Registrar ingresso' }).click();
    const ingresso = page.getByRole('dialog');
    await ingresso.getByLabel('Data e hora efetivas *').fill('2026-10-01T09:00');
    await ingresso.getByLabel('Motivo *').fill('Ingresso E2E inteiramente fictício.');
    await ingresso.getByLabel('Fundamento').fill('Fundamento do ingresso E2E fictício.');
    await escolherOpcao(page, 'Origem do encaminhamento *', 'Outro');
    await ingresso.getByLabel('Qual origem? *').fill('Origem temporária E2E fictícia');
    await escolherOpcao(page, 'Origem do encaminhamento *', 'Conselho Tutelar');
    await expect(ingresso.getByLabel('Qual origem? *')).toHaveCount(0);
    await escolherOpcao(page, 'Órgão condutor *', 'Outro');
    await ingresso.getByLabel('Qual órgão? *').fill('Órgão temporário E2E fictício');
    await escolherOpcao(page, 'Órgão condutor *', 'Conselho Tutelar');
    await expect(ingresso.getByLabel('Qual órgão? *')).toHaveCount(0);
    await ingresso.getByLabel('Pessoa condutora *').fill('Pessoa Condutora E2E Fictícia');
    await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && /\/criancas\/\d+\/acolhimentos$/.test(new URL(response.url()).pathname)
        )),
        ingresso.getByRole('button', { name: 'Registrar ingresso' }).click(),
    ]);
    await expect(ingresso).toBeHidden();
    await expect(page.getByText('Na unidade', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Fundamento do ingresso E2E fictício.', { exact: false }).first()).toBeVisible();
    const childUrl = page.url();

    await page.getByRole('link', { name: 'Editar', exact: true }).first().click();
    await page.getByLabel('Nº do processo').fill(`PROCESSO-ATUAL-${account.suffix}`);
    await page.getByLabel('Vara').fill(`Vara Atual ${account.suffix} Fictícia`);
    await page.getByLabel('Comarca').fill(`Comarca Atual ${account.suffix} Fictícia`);
    await Promise.all([
        page.waitForURL(/\/criancas\/\d+$/),
        page.getByRole('button', { name: 'Salvar alterações' }).click(),
    ]);

    const childId = new URL(page.url()).pathname.split('/').at(-1);
    await page.goto(`/pias/create?crianca_id=${childId}`);
    const dadosAcolhimento = page.getByLabel('Motivo e circunstâncias do acolhimento');
    await expect(dadosAcolhimento).toHaveValue(new RegExp(`PROCESSO-INGRESSO-${account.suffix}`));
    await expect(dadosAcolhimento).not.toHaveValue(new RegExp(`PROCESSO-ATUAL-${account.suffix}`));
    await Promise.all([
        page.waitForURL(/\/pias\/\d+$/),
        page.getByRole('button', { name: 'Registrar PIA' }).click(),
    ]);
    await expect(page.getByText(`PROCESSO-INGRESSO-${account.suffix}`, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(`Vara Ingresso ${account.suffix} Fictícia`, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(`Comarca Ingresso ${account.suffix} Fictícia`, { exact: true }).first()).toBeVisible();
    await expect(page.getByText(`PROCESSO-ATUAL-${account.suffix}`, { exact: true })).toHaveCount(0);
    await page.goto(childUrl);

    await registrarMovimentacao(page, {
        action: 'Registrar evasão',
        effectiveAt: '2026-10-01T10:00',
        reason: 'Evasão E2E inteiramente fictícia.',
        foundation: 'Fundamento da evasão E2E fictício.',
    });
    await expect(page.getByText('Evadido', { exact: true }).first()).toBeVisible();

    await registrarMovimentacao(page, {
        action: 'Registrar retorno',
        effectiveAt: '2026-10-01T11:00',
    });
    await registrarMovimentacao(page, {
        action: 'Registrar internação',
        effectiveAt: '2026-10-01T12:00',
        reason: 'Internação E2E inteiramente fictícia.',
        destination: 'Hospital E2E Fictício',
    });
    await expect(page.getByText('Internado', { exact: true }).first()).toBeVisible();

    await registrarMovimentacao(page, {
        action: 'Registrar retorno',
        effectiveAt: '2026-10-01T13:00',
    });
    await registrarMovimentacao(page, {
        action: 'Registrar desacolhimento',
        effectiveAt: '2026-10-01T14:00',
        reason: 'Desacolhimento E2E inteiramente fictício.',
        destination: 'Destino E2E Fictício',
    });

    await expect(page.getByText('Desacolhido', { exact: true }).first()).toBeVisible();
    await expect(
        page.getByRole('list', { name: 'Linha do tempo do acolhimento' }).getByRole('listitem'),
    ).toHaveCount(6);
    await expect(page.getByRole('button', { name: /Registrar evasão|Registrar internação|Registrar retorno/ })).toHaveCount(0);

    await page.getByRole('button', { name: 'Registrar novo ingresso' }).click();
    const reingresso = page.getByRole('dialog');
    await reingresso.getByLabel('Data e hora efetivas *').fill('2026-10-01T15:00');
    await reingresso.getByLabel('Motivo *').fill('Reingresso E2E inteiramente fictício.');
    await reingresso.getByLabel('Fundamento').fill('Fundamento do reingresso E2E fictício.');
    await escolherOpcao(page, 'Origem do encaminhamento *', 'Conselho Tutelar');
    await escolherOpcao(page, 'Órgão condutor *', 'Conselho Tutelar');
    await reingresso.getByLabel('Pessoa condutora *').fill('Pessoa Condutora Reingresso E2E Fictícia');
    await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && /\/criancas\/\d+\/acolhimentos$/.test(new URL(response.url()).pathname)
        )),
        reingresso.getByRole('button', { name: 'Registrar ingresso' }).click(),
    ]);

    await expect(reingresso).toBeHidden();
    await expect(page.getByText('Na unidade', { exact: true }).first()).toBeVisible();
    await expect(
        page.getByRole('list', { name: 'Linha do tempo do acolhimento' }).getByRole('listitem'),
    ).toHaveCount(7);
    await expect(
        page.getByRole('list', { name: 'Linha do tempo do acolhimento' }).getByText('Ingresso', { exact: true }),
    ).toHaveCount(2);
    await expect(page.getByText('Fundamento da evasão E2E fictício.', { exact: false })).toBeVisible();
    await expect(page.getByText('Fundamento do ingresso E2E fictício.', { exact: false })).toBeVisible();
    await expect(page.getByText('Fundamento do reingresso E2E fictício.', { exact: false }).first()).toBeVisible();

});

test('formulário de PIA obsoleto exige atualização antes de vincular episódio', async ({ page, context }, testInfo) => {
    test.setTimeout(120_000);
    const account = accounts[testInfo.project.name];
    const childName = `Pessoa PIA Stale E2E ${account.suffix} (Fictícia)`;

    await loginWithMfa(page, account);
    await page.goto('/criancas/create');
    await page.getByLabel('Nome completo *').fill(childName);
    await Promise.all([
        page.waitForURL(/\/criancas\/\d+$/),
        page.getByRole('button', { name: 'Cadastrar' }).click(),
    ]);

    const childUrl = page.url();
    const childId = new URL(childUrl).pathname.split('/').at(-1);
    const staleForm = await context.newPage();
    await staleForm.goto(`/pias/create?crianca_id=${childId}`);
    await expect(staleForm.getByLabel('Motivo e circunstâncias do acolhimento'))
        .toHaveValue(/Ingresso ainda não registrado/);

    await page.getByRole('button', { name: 'Registrar ingresso' }).click();
    const ingresso = page.getByRole('dialog');
    await ingresso.getByLabel('Data e hora efetivas *').fill('2026-10-02T09:00');
    await ingresso.getByLabel('Motivo *').fill('Ingresso concorrente E2E inteiramente fictício.');
    await escolherOpcao(page, 'Origem do encaminhamento *', 'Conselho Tutelar');
    await escolherOpcao(page, 'Órgão condutor *', 'Conselho Tutelar');
    await ingresso.getByLabel('Pessoa condutora *').fill('Pessoa Condutora Concorrente E2E Fictícia');
    await Promise.all([
        page.waitForResponse((response) => (
            response.request().method() === 'POST'
            && /\/criancas\/\d+\/acolhimentos$/.test(new URL(response.url()).pathname)
        )),
        ingresso.getByRole('button', { name: 'Registrar ingresso' }).click(),
    ]);
    await expect(ingresso).toBeHidden();

    await Promise.all([
        staleForm.waitForResponse((response) => (
            response.request().method() === 'POST'
            && new URL(response.url()).pathname === '/pias'
        )),
        staleForm.getByRole('button', { name: 'Registrar PIA' }).click(),
    ]);
    await expect(staleForm.getByRole('alert').filter({
        hasText: 'O episódio de acolhimento mudou. Atualize o formulário antes de registrar o PIA.',
    })).toBeVisible();
    await expect(staleForm).toHaveURL(/\/pias\/create/);

    await staleForm.reload();
    await expect(staleForm.getByLabel('Motivo e circunstâncias do acolhimento'))
        .toHaveValue(/Ingresso concorrente E2E inteiramente fictício/);
    await Promise.all([
        staleForm.waitForURL(/\/pias\/\d+$/),
        staleForm.getByRole('button', { name: 'Registrar PIA' }).click(),
    ]);
    await expect(staleForm.getByText('Ingresso concorrente E2E inteiramente fictício.', { exact: false }).first())
        .toBeVisible();

    await staleForm.close();
});

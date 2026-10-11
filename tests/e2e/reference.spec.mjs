import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL } from './environment.mjs';

/**
 * Browser coverage for reference cards and cheat sheets (Slice 26).
 *
 * Cards and sheets are created through the client where the journey is under test, and
 * through the API where they are only scaffolding. Everything this file creates is
 * removed again, leaving the seeded dataset as global setup wrote it.
 */

test.describe.configure({ mode: 'serial' });

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const created = { cards: new Set(), sheets: new Set(), tags: new Set() };

async function apiClient() {
    const context = await apiRequest.newContext({ baseURL });
    const response = await context.post('/api/auth/login', {
        data: { email: account.email, password: account.password },
    });
    expect(response.ok(), 'the fixture client could not sign in').toBeTruthy();

    return context;
}

async function apiCreate(path, data) {
    const api = await apiClient();
    try {
        const response = await api.post(path, { data });
        expect(response.status(), `creating ${path} failed`).toBe(201);

        return (await response.json()).data;
    } finally {
        await api.dispose();
    }
}

async function cardIdsTitled(titles) {
    const api = await apiClient();
    try {
        const ids = [];
        for (const title of titles) {
            const response = await api.get(`/api/reference-cards?q=${encodeURIComponent(title)}&limit=100`);
            const { data } = await response.json();
            for (const card of data.items) {
                if (card.title === title) {
                    ids.push(card.id);
                }
            }
        }

        return ids;
    } finally {
        await api.dispose();
    }
}

async function cleanUp() {
    const api = await apiClient();
    try {
        for (const sheetId of created.sheets) {
            await api.delete(`/api/cheat-sheets/${sheetId}`);
        }
        for (const cardId of created.cards) {
            await api.delete(`/api/reference-cards/${cardId}`);
        }
        for (const tagId of created.tags) {
            await api.delete(`/api/tags/${tagId}`);
        }
    } finally {
        created.cards.clear();
        created.sheets.clear();
        created.tags.clear();
        await api.dispose();
    }
}

test.afterEach(async () => {
    await cleanUp();
});

async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openReference(page) {
    await page.getByRole('button', { name: 'Reference', exact: true }).click();
    await expect(page.locator('#reference-view')).toBeVisible();
    await expect(page.locator('#reference-summary')).toHaveText(/^Showing \d+ of \d+ cards$/);
}

async function audit(page) {
    await page.evaluate(axeSource);
    const results = await page.evaluate(async () => window.axe.run(document, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    }));

    return results.violations
        .map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length} node(s))`)
        .join('\n');
}

test('the Reference view explains itself before any card or sheet exists', async ({ page }) => {
    await signIn(page);
    await openReference(page);

    await expect(page.locator('#reference-empty')).toBeVisible();
    await expect(page.locator('#reference-summary')).toHaveText('Showing 0 of 0 cards');
    await expect(page.locator('#cheat-sheet-empty')).toBeVisible();
    await expect(page.locator('#reference-card-detail')).toBeHidden();
    await expect(page.locator('#cheat-sheet-detail')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Previous cards' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Next cards' })).toBeDisabled();
});

test('a dialogue pattern is written once, found by its structured text and read back in blocks', async ({ page }) => {
    await signIn(page);
    await openReference(page);

    const form = page.locator('#reference-card-form');
    await expect(page.locator('#reference-dialogue-fieldset')).toBeHidden();
    await form.getByLabel('Type').selectOption('dialogue_pattern');
    await expect(page.locator('#reference-dialogue-fieldset')).toBeVisible();
    await form.getByLabel('Title').fill('Declining scope creep');
    await form.getByLabel('Summary').fill('Protect the commitment, keep the relationship.');
    await form.getByLabel('Situation').fill('A stakeholder adds work late in the sprint.');
    await form.getByLabel('Aim').fill('Keep the original commitment intact.');
    await form.getByLabel('Approach').fill('Name the trade-off and offer a choice.');
    await form.getByLabel('Rationale').fill('Shared choices preserve trust.');
    await page.getByRole('button', { name: 'Create card' }).click();
    await expect(page.locator('#notice')).toHaveText('Card created.');
    for (const id of await cardIdsTitled(['Declining scope creep'])) {
        created.cards.add(id);
    }

    const detail = page.locator('#reference-card-detail');
    await expect(detail).toBeVisible();
    await expect(page.locator('#reference-card-type')).toHaveText('Dialogue pattern');
    await expect(page.locator('#reference-card-title')).toHaveText('Declining scope creep');
    const fields = page.locator('#reference-card-fields');
    await expect(fields.locator('dt')).toHaveText(['Situation', 'Aim', 'Approach', 'Rationale']);
    await expect(fields.locator('dd').nth(2)).toHaveText('Name the trade-off and offer a choice.');
    await expect(page.locator('#reference-card-body')).toBeHidden();
    await expect(page.locator('#reference-dialogue-fieldset')).toBeHidden();

    // A concept card created alongside shows no dialogue blocks.
    await form.getByLabel('Title').fill('Active listening');
    await form.getByLabel('Text').fill('Paraphrase what you heard,\nthen check it.');
    await page.getByRole('button', { name: 'Create card' }).click();
    await expect(page.locator('#notice')).toHaveText('Card created.');
    for (const id of await cardIdsTitled(['Active listening'])) {
        created.cards.add(id);
    }
    await expect(page.locator('#reference-card-type')).toHaveText('Concept');
    await expect(page.locator('#reference-card-fields')).toBeHidden();
    await expect(page.locator('#reference-card-body')).toHaveText('Paraphrase what you heard,\nthen check it.');
    await expect(page.locator('#reference-list > li')).toHaveCount(2);

    // Search reaches the structured dialogue fields, not only titles.
    await page.getByLabel('Search cards').fill('trade-off');
    await page.getByRole('button', { name: 'Find cards' }).click();
    await expect(page.locator('#reference-summary')).toHaveText('Showing 1 of 1 cards');
    await expect(page.locator('#reference-list h3')).toHaveText(['Declining scope creep']);

    await page.getByLabel('Search cards').fill('');
    await page.locator('#reference-type-filter').selectOption('concept');
    await page.getByRole('button', { name: 'Find cards' }).click();
    await expect(page.locator('#reference-list h3')).toHaveText(['Active listening']);

    // A card's content is inserted as text, never as markup.
    await page.getByRole('button', { name: 'Open Active listening' }).click();
    await page.getByRole('button', { name: 'Edit card' }).click();
    await expect(page.locator('#reference-form-title')).toHaveText('Edit card');
    await form.getByLabel('Summary').fill('<img src=x onerror="window.__xss = 1">');
    await page.getByRole('button', { name: 'Save card' }).click();
    await expect(page.locator('#notice')).toHaveText('Card saved.');
    await expect(page.locator('#reference-card-summary')).toHaveText('<img src=x onerror="window.__xss = 1">');
    expect(await page.evaluate(() => window.__xss)).toBeUndefined();
    await expect(page.locator('#reference-form-title')).toHaveText('Create a card');
});

test('a cheat sheet keeps its cards in the order chosen and survives a reload', async ({ page }) => {
    for (const title of ['Opening the meeting', 'Holding the agenda', 'Closing with actions']) {
        const data = await apiCreate('/api/reference-cards', { title, summary: `${title} summary` });
        created.cards.add(data.card.id);
    }

    await signIn(page);
    await openReference(page);

    await page.getByLabel('Create a cheat sheet').fill('Difficult meetings');
    await page.getByRole('button', { name: 'Create sheet' }).click();
    await expect(page.locator('#notice')).toHaveText('Cheat sheet created.');
    const sheetDetail = page.locator('#cheat-sheet-detail');
    await expect(sheetDetail).toBeVisible();
    await expect(page.locator('#cheat-sheet-title')).toHaveText('Difficult meetings');
    await expect(page.locator('#cheat-sheet-cards-empty')).toBeVisible();
    {
        const api = await apiClient();
        const { data } = await (await api.get('/api/cheat-sheets?limit=100')).json();
        for (const sheet of data.items) {
            if (sheet.name === 'Difficult meetings') {
                created.sheets.add(sheet.id);
            }
        }
        await api.dispose();
    }

    for (const title of ['Opening the meeting', 'Holding the agenda', 'Closing with actions']) {
        await page.getByRole('button', { name: `Open ${title}`, exact: true }).first().click();
        await expect(page.locator('#reference-card-title')).toHaveText(title);
        await page.getByLabel('Add to a cheat sheet').selectOption({ label: 'Difficult meetings' });
        await page.getByRole('button', { name: 'Add to sheet' }).click();
        await expect(page.locator('#notice')).toHaveText('Card added to the cheat sheet.');
    }

    const sheetTitles = page.locator('#cheat-sheet-cards h4');
    await expect(sheetTitles).toHaveText(['Opening the meeting', 'Holding the agenda', 'Closing with actions']);
    await expect(page.getByRole('button', { name: 'Move Opening the meeting up' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Move Closing with actions down' })).toBeDisabled();
    await expect(page.locator('#cheat-sheet-list .status-label')).toHaveText(['3 cards']);

    await page.getByRole('button', { name: 'Move Closing with actions up' }).click();
    await expect(sheetTitles).toHaveText(['Opening the meeting', 'Closing with actions', 'Holding the agenda']);
    await page.getByRole('button', { name: 'Move Opening the meeting down' }).click();
    await expect(sheetTitles).toHaveText(['Closing with actions', 'Opening the meeting', 'Holding the agenda']);

    // Filtering the card list by sheet returns the members only.
    await page.locator('#reference-sheet-filter').selectOption({ label: 'Difficult meetings' });
    await page.getByRole('button', { name: 'Find cards' }).click();
    await expect(page.locator('#reference-summary')).toHaveText('Showing 3 of 3 cards');

    await page.reload();
    await expect(page.locator('#workspace')).toBeVisible();
    await openReference(page);
    await page.getByRole('button', { name: 'Open cheat sheet Difficult meetings' }).click();
    await expect(sheetTitles).toHaveText(['Closing with actions', 'Opening the meeting', 'Holding the agenda']);

    await page.getByRole('button', { name: 'Remove Opening the meeting from this sheet' }).click();
    await expect(page.locator('#notice')).toHaveText('Card removed from the cheat sheet.');
    await expect(sheetTitles).toHaveText(['Closing with actions', 'Holding the agenda']);

    const violations = await audit(page);
    expect(violations, violations).toBe('');

    // Deleting the sheet takes two presses and keeps every card.
    const remove = page.locator('#cheat-sheet-delete');
    await remove.click();
    await expect(remove).toHaveText('Confirm: delete cheat sheet');
    await expect(page.locator('#notice')).toHaveText('This cannot be undone. Press the button again to confirm.');
    await expect(sheetDetail).toBeVisible();
    await remove.click();
    await expect(page.locator('#notice')).toHaveText('Cheat sheet deleted. Its cards are unchanged.');
    await expect(sheetDetail).toBeHidden();
    await expect(page.locator('#cheat-sheet-empty')).toBeVisible();
    await expect(page.locator('#reference-summary')).toHaveText('Showing 3 of 3 cards');
    created.sheets.clear();
});

test('tags classify cards and deleting a card needs confirmation', async ({ page }) => {
    const tag = (await apiCreate('/api/tags', { name: 'reference-e2e' })).tag;
    created.tags.add(tag.id);
    for (const title of ['Tagged card', 'Untagged card']) {
        const data = await apiCreate('/api/reference-cards', { title });
        created.cards.add(data.card.id);
    }

    await signIn(page);
    await openReference(page);

    await page.getByRole('button', { name: 'Open Tagged card' }).click();
    await page.getByLabel('Add a tag').selectOption({ label: 'reference-e2e' });
    await page.getByRole('button', { name: 'Add tag' }).click();
    await expect(page.locator('#notice')).toHaveText('Tag added to the card.');
    await expect(page.locator('#reference-card-tags')).toContainText('reference-e2e');

    await page.locator('#reference-tag-filter').selectOption({ label: 'reference-e2e' });
    await page.getByRole('button', { name: 'Find cards' }).click();
    await expect(page.locator('#reference-list h3')).toHaveText(['Tagged card']);

    await page.getByRole('button', { name: 'Remove tag reference-e2e' }).click();
    await expect(page.locator('#notice')).toHaveText('Tag removed from the card.');
    await expect(page.locator('#reference-empty')).toBeVisible();

    await page.locator('#reference-tag-filter').selectOption('');
    await page.getByRole('button', { name: 'Find cards' }).click();
    await page.getByRole('button', { name: 'Open Untagged card' }).click();
    const remove = page.locator('#reference-delete-card');
    await remove.click();
    await expect(remove).toHaveText('Confirm: delete card');
    await expect(page.locator('#reference-list h3')).toHaveText(['Tagged card', 'Untagged card']);
    await remove.click();
    await expect(page.locator('#notice')).toHaveText('Card deleted.');
    await expect(page.locator('#reference-card-detail')).toBeHidden();
    await expect(page.locator('#reference-list h3')).toHaveText(['Tagged card']);
});

test('a long card and sheet do not overflow the narrowest viewport', async ({ page }) => {
    const unbroken = 'x'.repeat(240);
    const card = (await apiCreate('/api/reference-cards', {
        card_type: 'dialogue_pattern',
        title: `Long ${unbroken}`,
        summary: unbroken,
        approach: unbroken,
        body: unbroken,
    })).card;
    created.cards.add(card.id);
    const sheet = (await apiCreate('/api/cheat-sheets', { name: `Sheet ${unbroken}` })).cheat_sheet;
    created.sheets.add(sheet.id);
    await apiCreate(`/api/cheat-sheets/${sheet.id}/cards`, { reference_card_id: card.id });

    await page.setViewportSize({ width: 320, height: 900 });
    await signIn(page);
    await openReference(page);
    await page.locator('#reference-list button[data-reference-action="open-card"]').first().click();
    await expect(page.locator('#reference-card-detail')).toBeVisible();
    await page.locator('#cheat-sheet-list button[data-reference-action="open-sheet"]').first().click();
    await expect(page.locator('#cheat-sheet-cards > li')).toHaveCount(1);

    const overflow = await page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));
    expect(overflow.scrollWidth, 'the Reference view overflows at 320px').toBeLessThanOrEqual(overflow.clientWidth);
});

test('an armed deletion never applies to a card that is still loading', async ({ page }) => {
    const first = (await apiCreate('/api/reference-cards', { title: 'Armed card' })).card;
    const second = (await apiCreate('/api/reference-cards', { title: 'Loading card' })).card;
    created.cards.add(first.id);
    created.cards.add(second.id);

    await signIn(page);
    await openReference(page);
    await page.getByRole('button', { name: 'Open Armed card' }).click();
    await expect(page.locator('#reference-card-title')).toHaveText('Armed card');
    await page.locator('#reference-delete-card').click();
    await expect(page.locator('#reference-delete-card')).toHaveText('Confirm: delete card');

    let release;
    const held = new Promise((resolve) => {
        release = resolve;
    });
    await page.route(`**/api/reference-cards/${second.id}`, async (route) => {
        await held;
        await route.continue();
    });
    await page.getByRole('button', { name: 'Open Loading card' }).click();

    // The armed panel is withdrawn at once, so nothing on screen can delete the card being loaded.
    await expect(page.locator('#reference-card-detail')).toBeHidden();
    release();
    await expect(page.locator('#reference-card-title')).toHaveText('Loading card');
    await expect(page.locator('#reference-delete-card')).toHaveText('Delete card');
    await expect(page.locator('#reference-list h3')).toHaveText(['Armed card', 'Loading card']);
});

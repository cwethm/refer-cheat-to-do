import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL, freshAccount } from './environment.mjs';

/**
 * Browser coverage for the read-only activity history.
 *
 * The history is actor-only, so the empty state is observed through the account global
 * setup creates and never acts as, and the populated state through the seeded account.
 */

test.describe.configure({ mode: 'serial' });

const created = { todos: [] };

async function apiClient() {
    const context = await apiRequest.newContext({ baseURL });
    const response = await context.post('/api/auth/login', {
        data: { email: account.email, password: account.password },
    });
    expect(response.ok(), 'the fixture client could not sign in').toBeTruthy();

    return context;
}

async function cleanUp() {
    const api = await apiClient();
    try {
        for (const todoId of created.todos) {
            await api.post(`/api/todos/${todoId}/trash`, { data: {} });
            await api.delete(`/api/todos/${todoId}/permanent`);
        }
    } finally {
        created.todos = [];
        await api.dispose();
    }
}

test.afterAll(async () => {
    await cleanUp();
});

async function signIn(page, as = account) {
    await page.goto('/app');
    await page.getByLabel('Email').fill(as.email);
    await page.getByLabel('Password').fill(as.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openActivity(page) {
    await page.getByRole('button', { name: 'Activity', exact: true }).click();
    await expect(page.locator('#activity-view')).toBeVisible();
}

test('an actor who has done nothing sees an explained empty history', async ({ page }) => {
    await signIn(page, freshAccount);
    await openActivity(page);

    await expect(page.locator('#activity-empty')).toBeVisible();
    await expect(page.locator('#activity-summary')).toHaveText('Showing 0 of 0 entries');
    await expect(page.locator('#activity-page-label')).toHaveText('Activity page 1 of 1');
    await expect(page.getByRole('button', { name: 'Previous activity' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Next activity' })).toBeDisabled();
    await expect(page.locator('#activity-view')).toContainText('This history is yours alone');
});

test('the history pages through the actor\'s own entries', async ({ page }) => {
    await signIn(page);
    await openActivity(page);

    await expect(page.locator('#activity-empty')).toBeHidden();
    await expect(page.locator('#activity-list li')).toHaveCount(20);
    await expect(page.locator('#activity-page-label')).toHaveText(/^Activity page 1 of [2-9]\d*$/);

    const previous = page.getByRole('button', { name: 'Previous activity' });
    const next = page.getByRole('button', { name: 'Next activity' });
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();

    const firstEntry = await page.locator('#activity-list li').first().textContent();
    await next.click();
    await expect(page.locator('#activity-page-label')).toHaveText(/^Activity page 2 of [2-9]\d*$/);
    await expect(page.locator('#activity-list li').first()).not.toHaveText(firstEntry);
    await expect(previous).toBeEnabled();

    await previous.click();
    await expect(page.locator('#activity-page-label')).toHaveText(/^Activity page 1 of [2-9]\d*$/);
    await expect(next).toBeEnabled();
});

test('an action taken in the client appears at the top of the history', async ({ page }) => {
    const title = `Recorded ToDo ${Date.now()}`;

    await signIn(page);
    await page.getByLabel('What do you need to remember?').fill(title);
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/api/todos')
            && response.request().method() === 'POST'),
        page.getByRole('button', { name: 'Add to inbox' }).click(),
    ]);
    await expect(page.locator('#notice')).toHaveText('Added to your inbox.');

    const api = await apiClient();
    try {
        const { data } = await (await api.get('/api/todos?limit=1&page=1')).json();
        expect(data.items[0].title, 'the captured ToDo was not the newest one').toBe(title);
        created.todos.push(data.items[0].id);

        await openActivity(page);
        const newest = page.locator('#activity-list li').first();
        await expect(newest).toContainText(`Created a ToDo — ToDo #${data.items[0].id}`);
        await expect(newest).toContainText(/\d{4}-\d{2}-\d{2} \d{2}:\d{2} UTC/);
    } finally {
        await api.dispose();
    }

    await cleanUp();
});

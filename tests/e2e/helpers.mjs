import { expect } from '@playwright/test';
import { e2eEnvironment } from './environment.mjs';

/**
 * Open `/app` and sign in through the visible form, exactly as a person would.
 */
export async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email').fill(e2eEnvironment.email);
    await page.getByLabel('Password').fill(e2eEnvironment.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
    await expect(page.locator('#account-email')).toHaveText(e2eEnvironment.email);
}

/**
 * Remove every ToDo and tag owned by the test account through the public API.
 *
 * The request context shares the browser session cookie, so this exercises the same
 * authorization path the client uses and never touches another account's rows.
 */
export async function resetWorkspace(page) {
    const todos = await page.request.get('/api/todos?page=1&limit=200');
    for (const todo of (await todos.json()).data.items) {
        await page.request.post(`/api/todos/${todo.id}/trash`, { data: {} });
        await page.request.delete(`/api/todos/${todo.id}/permanent`);
    }

    const tags = await page.request.get('/api/tags?page=1&limit=100');
    for (const tag of (await tags.json()).data.items) {
        await page.request.delete(`/api/tags/${tag.id}`);
    }
}

/**
 * Seed ToDos through the API so pagination boundaries can be tested without
 * driving the capture form dozens of times.
 */
export async function seedTodos(page, count, prefix) {
    for (let index = 1; index <= count; index++) {
        const response = await page.request.post('/api/todos', {
            data: { title: `${prefix} ${String(index).padStart(3, '0')}` },
        });
        expect(response.ok()).toBe(true);
    }
}

export async function openView(page, name) {
    await page.getByRole('button', { name, exact: true }).first().click();
}

import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL, soloAccount } from './environment.mjs';

/**
 * Browser coverage for the ToDo detail panel: hierarchy, related links, objectives and
 * results, the archive/trash/restore lifecycle and its confirmed permanent delete.
 *
 * Like the other record specs, everything created here is removed again so the seeded
 * dataset the smoke journey depends on is left exactly as global setup wrote it.
 */

test.describe.configure({ mode: 'serial' });

const created = { todos: [] };

async function apiClient(as = account) {
    const context = await apiRequest.newContext({ baseURL });
    const response = await context.post('/api/auth/login', {
        data: { email: as.email, password: as.password },
    });
    expect(response.ok(), 'the fixture client could not sign in').toBeTruthy();

    return context;
}

async function createTodo(api, title) {
    const response = await api.post('/api/todos', { data: { title } });
    expect(response.ok(), `the fixture ToDo ${title} could not be created`).toBeTruthy();
    const { data } = await response.json();
    created.todos.push(data.todo.id);

    return data.todo.id;
}

async function cleanUp() {
    const api = await apiClient();
    try {
        // Children are detached first: a parent cannot be deleted while it still has one.
        for (const todoId of created.todos) {
            await api.patch(`/api/todos/${todoId}/parent`, { data: { parent_todo_id: null } });
        }
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

function todoCard(page, title) {
    return page.locator('#todo-list li', { hasText: title }).first();
}

/**
 * List the ToDos matching a search term.
 *
 * The parent and related pickers offer the ToDos currently listed, so a spec that links two
 * ToDos has to list both of them.
 */
async function search(page, term, expectedCount) {
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/todos?')),
        page.getByRole('button', { name: 'ToDos', exact: true }).click(),
    ]);
    await expect(page.locator('#inbox-view')).toBeVisible();
    await page.getByRole('searchbox', { name: 'Search ToDos' }).fill(term);
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/todos?')),
        page.getByRole('button', { name: 'Find ToDos' }).click(),
    ]);
    await expect(page.locator('#todo-summary'))
        .toHaveText(`Showing ${expectedCount} of ${expectedCount} ToDos`);
    await page.waitForLoadState('networkidle');
}

async function openDetail(page, title) {
    await todoCard(page, title).getByRole('button', { name: `Open details for ${title}` }).click();
    await expect(page.locator('#todo-detail')).toBeVisible();
    await expect(page.locator('#todo-detail-title')).toHaveText(title);
}

test('a ToDo can be given a parent, a related ToDo, an objective and a reported result', async ({ page }) => {
    const stamp = Date.now();
    const term = `Linked ${stamp}`;
    const child = `${term} child`;
    const parent = `${term} parent`;
    const friend = `${term} friend`;

    const api = await apiClient();
    try {
        await createTodo(api, parent);
        await createTodo(api, child);
        await createTodo(api, friend);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await search(page, term, 3);
    await openDetail(page, child);

    await expect(page.locator('#todo-detail-status')).toHaveText('Status: inbox');
    await expect(page.locator('#todo-children-empty')).toBeVisible();
    await expect(page.locator('#todo-related-empty')).toBeVisible();

    await page.getByLabel('Parent ToDo', { exact: true }).selectOption({ label: parent });
    await page.getByRole('button', { name: 'Save parent' }).click();
    await expect(page.locator('#notice')).toHaveText('Parent set.');
    await expect(page.getByLabel('Parent ToDo', { exact: true })).toHaveValue(/\d+/);

    await page.getByLabel(/^Objective/).fill('The quotes are signed');
    await page.getByRole('button', { name: 'Save objective' }).click();
    await expect(page.locator('#notice')).toHaveText('Objective saved.');
    await expect(page.getByLabel(/^Objective/)).toHaveValue('The quotes are signed');

    await page.getByLabel('Report a result to the parent ToDo').fill('Signed by both parties');
    await page.getByRole('button', { name: 'Report result' }).click();
    await expect(page.locator('#notice')).toHaveText('Result reported.');
    await expect(page.locator('#todo-result-summary')).toHaveText('Reported result: Signed by both parties');

    await page.getByLabel('Link a related ToDo').selectOption({ label: friend });
    await page.getByRole('button', { name: 'Link ToDo' }).click();
    await expect(page.locator('#notice')).toHaveText('ToDos linked.');
    await expect(page.locator('#todo-related-empty')).toBeHidden();
    await expect(page.locator('#todo-related-list')).toContainText(friend);

    // The parent sees the child, and the result the child reported back.
    await search(page, term, 3);
    await openDetail(page, parent);
    await expect(page.locator('#todo-children-empty')).toBeHidden();
    await expect(page.locator('#todo-children-list')).toContainText(child);
    await expect(page.locator('#todo-children-list')).toContainText('Reported: Signed by both parties');

    await search(page, term, 3);
    await openDetail(page, child);
    await page.getByRole('button', { name: `Unlink ${friend}` }).click();
    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(page.locator('#todo-related-empty')).toBeVisible();

    await page.getByLabel('Parent ToDo', { exact: true }).selectOption('');
    await page.getByRole('button', { name: 'Save parent' }).click();
    await expect(page.locator('#notice')).toHaveText('Parent cleared.');

    await cleanUp();
});

test('a ToDo can be archived, trashed, restored and finally deleted after confirmation', async ({ page }) => {
    const stamp = Date.now();
    const title = `Lifecycle ${stamp}`;

    const api = await apiClient();
    let todoId;
    try {
        todoId = await createTodo(api, title);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await search(page, title, 1);
    await openDetail(page, title);

    await expect(page.locator('#todo-lifecycle-explanations'))
        .toContainText('Archiving hides this ToDo from your lists.');

    await page.getByRole('button', { name: `Archive: ${title}` }).click();
    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(page.locator('#todo-detail-status')).toHaveText('Status: archived');
    // Archived ToDos leave the default listing but the open detail panel still shows them.
    await expect(page.locator('#todo-empty')).toBeVisible();

    await page.getByRole('button', { name: `Move to trash: ${title}` }).click();
    await expect(page.locator('#todo-detail-status')).toHaveText('Status: trashed');

    await page.getByRole('button', { name: `Restore: ${title}` }).click();
    await expect(page.locator('#todo-detail-status')).toHaveText('Status: archived');

    await page.getByRole('button', { name: `Move to trash: ${title}` }).click();
    await expect(page.locator('#todo-detail-status')).toHaveText('Status: trashed');

    const destructive = page.getByRole('button', { name: `Delete permanently: ${title}` });
    await destructive.click();
    await expect(page.locator('#notice')).toHaveClass(/is-error/);
    await expect(page.locator('#notice')).toHaveText('This cannot be undone. Press the button again to confirm.');
    await expect(destructive).toHaveText('Confirm: delete permanently');

    await destructive.click();
    await expect(page.locator('#notice')).toHaveText('ToDo permanently deleted.');
    await expect(page.locator('#todo-detail')).toBeHidden();

    const verify = await apiClient();
    try {
        const response = await verify.get(`/api/todos/${todoId}`);
        expect(response.status(), 'the ToDo was not permanently deleted').toBe(404);
    } finally {
        await verify.dispose();
    }
    created.todos = created.todos.filter((id) => id !== todoId);
});

test('a single click never destroys a ToDo', async ({ page }) => {
    const stamp = Date.now();
    const title = `Guarded ${stamp}`;

    const api = await apiClient();
    let todoId;
    try {
        todoId = await createTodo(api, title);
        await api.post(`/api/todos/${todoId}/trash`, { data: {} });
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await page.getByRole('button', { name: 'ToDos', exact: true }).click();
    await page.getByLabel('Status').selectOption('trashed');
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/todos?')),
        page.getByRole('button', { name: 'Find ToDos' }).click(),
    ]);
    await openDetail(page, title);

    const requests = [];
    page.on('request', (request) => {
        if (request.method() === 'DELETE') {
            requests.push(request.url());
        }
    });

    await page.getByRole('button', { name: `Delete permanently: ${title}` }).click();
    await expect(page.locator('#notice')).toHaveText('This cannot be undone. Press the button again to confirm.');
    expect(requests, 'the first click sent a delete request').toEqual([]);

    const verify = await apiClient();
    try {
        const response = await verify.get(`/api/todos/${todoId}`);
        expect(response.status(), 'the ToDo was deleted by one click').toBe(200);
    } finally {
        await verify.dispose();
    }

    await cleanUp();
});

test('a ToDo in the review queue can be snoozed instead of reviewed', async ({ page }) => {
    const title = `Snoozable ${Date.now()}`;

    // The solo account owns nothing else, so this ToDo is the whole review queue.
    const api = await apiClient(soloAccount);
    let todoId;
    try {
        const response = await api.post('/api/todos', { data: { title } });
        expect(response.ok(), 'the fixture ToDo could not be created').toBeTruthy();
        const { data } = await response.json();
        todoId = data.todo.id;
    } finally {
        await api.dispose();
    }

    await signIn(page, soloAccount);
    await page.getByRole('button', { name: 'Review', exact: true }).click();
    await expect(page.locator('#review-view')).toBeVisible();

    const entry = page.locator('#review-list li', { hasText: title }).first();
    await expect(entry).toBeVisible();
    await entry.getByLabel(`Days to snooze ${title}`).fill('30');
    await entry.getByRole('button', { name: `Snooze ${title}` }).click();
    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(page.locator('#notice')).not.toHaveClass(/is-error/);

    const verify = await apiClient(soloAccount);
    try {
        const { data } = await (await verify.get(`/api/todos/${todoId}`)).json();
        expect(data.todo.next_review_at, 'the snooze did not schedule a later review').not.toBeNull();
        expect(new Date(data.todo.next_review_at).getTime()).toBeGreaterThan(Date.now());

        await verify.post(`/api/todos/${todoId}/trash`, { data: {} });
        await verify.delete(`/api/todos/${todoId}/permanent`);
    } finally {
        await verify.dispose();
    }
});

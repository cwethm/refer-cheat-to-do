import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL } from './environment.mjs';

/**
 * Browser coverage for Notebooks, NotebookSections and cross-kind section assignment.
 *
 * Like the Projects spec, every spec removes what it creates so the seeded dataset the
 * smoke journey depends on is left exactly as global setup wrote it.
 */

test.describe.configure({ mode: 'serial' });

const created = { notebooks: [], projects: [], todos: [] };

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
            await api.patch(`/api/todos/${todoId}`, {
                data: { project_section_id: null, notebook_section_id: null },
            });
            await api.post(`/api/todos/${todoId}/trash`, { data: {} });
            await api.delete(`/api/todos/${todoId}/permanent`);
        }
        for (const [path, ids, key] of [
            ['/api/notebooks', created.notebooks, 'notebook'],
            ['/api/projects', created.projects, 'project'],
        ]) {
            for (const id of ids) {
                const response = await api.get(`${path}/${id}`);
                if (!response.ok()) {
                    continue;
                }
                const { data } = await response.json();
                for (const section of data[key].sections ?? []) {
                    await api.delete(`${path}/${id}/sections/${section.id}`);
                }
                await api.delete(`${path}/${id}`);
            }
        }
    } finally {
        created.notebooks = [];
        created.projects = [];
        created.todos = [];
        await api.dispose();
    }
}

test.afterAll(async () => {
    await cleanUp();
});

async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openNotebooks(page) {
    await page.getByRole('button', { name: 'Notebooks', exact: true }).click();
    await expect(page.locator('#notebooks-view')).toBeVisible();
}

function todoCard(page, title) {
    return page.locator('#todo-list li', { hasText: title }).first();
}

async function findTodo(page, title) {
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/todos?')),
        page.getByRole('button', { name: 'ToDos', exact: true }).click(),
    ]);
    await expect(page.locator('#inbox-view')).toBeVisible();
    await page.getByRole('searchbox', { name: 'Search ToDos' }).fill(title);
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/api/todos?')),
        page.getByRole('button', { name: 'Find ToDos' }).click(),
    ]);
    await expect(page.locator('#todo-summary')).toHaveText('Showing 1 of 1 ToDos');
    await expect(todoCard(page, title)).toBeVisible();
    await page.waitForLoadState('networkidle');
}

test('the Notebooks view explains itself before any Notebook exists', async ({ page }) => {
    await signIn(page);
    await openNotebooks(page);

    await expect(page.locator('#notebook-empty')).toBeVisible();
    await expect(page.locator('#notebook-summary')).toHaveText('Showing 0 of 0 Notebooks');
    await expect(page.locator('#notebook-page-label')).toHaveText('Notebooks page 1 of 1');
    await expect(page.getByRole('button', { name: 'Previous Notebooks' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Next Notebooks' })).toBeDisabled();
    await expect(page.locator('#notebook-detail')).toBeHidden();
});

test('a Notebook, its sections and a ToDo assignment can be managed from the client', async ({ page }) => {
    const stamp = Date.now();
    const notebookName = `E2E Notebook ${stamp}`;
    const sectionName = 'Open questions';
    const renamedSection = 'Answered questions';
    const todoTitle = `Fileable ToDo ${stamp}`;

    const api = await apiClient();
    try {
        const todo = await (await api.post('/api/todos', { data: { title: todoTitle } })).json();
        created.todos.push(todo.data.todo.id);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openNotebooks(page);

    await page.getByLabel('Notebook name').fill(notebookName);
    await page.locator('#notebook-form').getByLabel(/^Description/).fill('Created by the browser suite.');
    await page.getByRole('button', { name: 'Add Notebook' }).click();

    await expect(page.locator('#notice')).toHaveText('Notebook added.');
    await expect(page.locator('#notebook-empty')).toBeHidden();
    const notebookItem = page.locator('#notebook-list li', { hasText: notebookName });
    await expect(notebookItem).toHaveCount(1);
    created.notebooks.push(Number(
        await notebookItem.getByRole('button', { name: `Open ${notebookName}` })
            .getAttribute('data-record-id'),
    ));

    await notebookItem.getByRole('button', { name: `Open ${notebookName}` }).click();
    await expect(page.locator('#notebook-detail')).toBeVisible();
    await expect(page.locator('#notebook-detail-title')).toHaveText(notebookName);
    await expect(page.locator('#notebook-section-empty')).toBeVisible();

    await page.getByLabel('Add a section to this Notebook').fill(sectionName);
    await page.getByRole('button', { name: 'Add Notebook section' }).click();

    await expect(page.locator('#notice')).toHaveText('Section added.');
    await expect(page.locator('#notebook-section-empty')).toBeHidden();
    await expect(page.locator('#notebook-section-list')).toContainText(sectionName);

    // The same picker that files a ToDo in a Project section files it in a Notebook section.
    await findTodo(page, todoTitle);
    const card = todoCard(page, todoTitle);
    await expect(card.locator('.section-assignment')).toHaveCount(0);
    await card.getByLabel(`Section for ${todoTitle}`)
        .selectOption({ label: `${notebookName} · ${sectionName}` });
    await card.getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(todoCard(page, todoTitle).locator('.section-assignment'))
        .toHaveText(`In ${notebookName} · ${sectionName}`);

    await openNotebooks(page);
    await expect(page.locator('#notebook-section-list')).toContainText(todoTitle);

    await page.getByLabel(`New name for section ${sectionName}`, { exact: true }).fill(renamedSection);
    await page.getByRole('button', { name: `Save the new name for section ${sectionName}` }).click();
    await expect(page.locator('#notice')).toHaveText('Section renamed.');
    await expect(page.locator('#notebook-section-list')).toContainText(renamedSection);

    await findTodo(page, todoTitle);
    await todoCard(page, todoTitle).getByLabel(`Section for ${todoTitle}`).selectOption('');
    await todoCard(page, todoTitle).getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(todoCard(page, todoTitle).locator('.section-assignment')).toHaveCount(0);

    await openNotebooks(page);
    await page.getByRole('button', { name: `Delete section ${renamedSection}` }).click();
    await expect(page.locator('#notice')).toHaveText('Section deleted.');
    await expect(page.locator('#notebook-section-empty')).toBeVisible();

    await page.getByLabel(`New name for ${notebookName}`, { exact: true }).fill(`${notebookName} renamed`);
    await page.getByRole('button', { name: `Save the new name for ${notebookName}` }).click();
    await expect(page.locator('#notice')).toHaveText('Notebook renamed.');
    await expect(page.locator('#notebook-list')).toContainText(`${notebookName} renamed`);

    await page.getByRole('button', { name: `Delete ${notebookName} renamed` }).click();
    await expect(page.locator('#notice')).toHaveText('Notebook deleted.');
    await expect(page.locator('#notebook-empty')).toBeVisible();
    created.notebooks = [];

    await cleanUp();
});

test('a ToDo moves from a Project section to a Notebook section in one action', async ({ page }) => {
    const stamp = Date.now();
    const projectName = `Move Project ${stamp}`;
    const notebookName = `Move Notebook ${stamp}`;
    const todoTitle = `Moving ToDo ${stamp}`;

    const api = await apiClient();
    try {
        const project = await (await api.post('/api/projects', { data: { name: projectName } })).json();
        created.projects.push(project.data.project.id);
        const projectSection = await (await api.post(
            `/api/projects/${project.data.project.id}/sections`,
            { data: { name: 'Drafting' } },
        )).json();

        const notebook = await (await api.post('/api/notebooks', { data: { name: notebookName } })).json();
        created.notebooks.push(notebook.data.notebook.id);
        await api.post(`/api/notebooks/${notebook.data.notebook.id}/sections`, { data: { name: 'Reference' } });

        const todo = await (await api.post('/api/todos', { data: { title: todoTitle } })).json();
        created.todos.push(todo.data.todo.id);
        const assigned = await api.patch(`/api/todos/${todo.data.todo.id}`, {
            data: { project_section_id: projectSection.data.section.id },
        });
        expect(assigned.ok(), 'the fixture ToDo could not be filed in a Project section').toBeTruthy();
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await findTodo(page, todoTitle);
    const card = todoCard(page, todoTitle);
    await expect(card.locator('.section-assignment')).toHaveText(`In ${projectName} · Drafting`);

    await card.getByLabel(`Section for ${todoTitle}`)
        .selectOption({ label: `${notebookName} · Reference` });
    await card.getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(page.locator('#notice')).not.toHaveClass(/is-error/);

    // The single request cleared the Project assignment, so the server never saw two.
    await findTodo(page, todoTitle);
    await expect(todoCard(page, todoTitle).locator('.section-assignment'))
        .toHaveText(`In ${notebookName} · Reference`);

    const verify = await apiClient();
    try {
        const { data } = await (await verify.get(`/api/todos/${created.todos[0]}`)).json();
        expect(data.todo.project_section_id).toBeNull();
    } finally {
        await verify.dispose();
    }

    await cleanUp();
});

test('the Notebooks list pages at the client page size', async ({ page }) => {
    const pageSize = 20;
    const stamp = Date.now();
    const api = await apiClient();
    try {
        for (let index = 1; index <= pageSize + 1; index++) {
            const response = await api.post('/api/notebooks', {
                data: { name: `Paged Notebook ${stamp} ${String(index).padStart(2, '0')}` },
            });
            const { data } = await response.json();
            created.notebooks.push(data.notebook.id);
        }
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openNotebooks(page);

    const previous = page.getByRole('button', { name: 'Previous Notebooks' });
    const next = page.getByRole('button', { name: 'Next Notebooks' });

    await expect(page.locator('#notebook-page-label')).toHaveText('Notebooks page 1 of 2');
    await expect(page.locator('#notebook-list li')).toHaveCount(pageSize);
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();

    await next.click();
    await expect(page.locator('#notebook-page-label')).toHaveText('Notebooks page 2 of 2');
    await expect(page.locator('#notebook-list li')).toHaveCount(1);
    await expect(next).toBeDisabled();
    await expect(previous).toBeEnabled();

    await previous.click();
    await expect(page.locator('#notebook-page-label')).toHaveText('Notebooks page 1 of 2');

    await cleanUp();
});

test('a refused Notebook deletion is reported rather than silently ignored', async ({ page }) => {
    const stamp = Date.now();
    const notebookName = `Full Notebook ${stamp}`;

    const api = await apiClient();
    try {
        const notebook = await (await api.post('/api/notebooks', { data: { name: notebookName } })).json();
        created.notebooks.push(notebook.data.notebook.id);
        await api.post(`/api/notebooks/${notebook.data.notebook.id}/sections`, { data: { name: 'Still here' } });
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openNotebooks(page);

    await page.getByRole('button', { name: `Delete ${notebookName}` }).click();

    await expect(page.locator('#notice')).toHaveClass(/is-error/);
    await expect(page.locator('#notice')).toHaveText('Notebook still contains sections.');
    await expect(page.locator('#notebook-list')).toContainText(notebookName);

    await cleanUp();
});

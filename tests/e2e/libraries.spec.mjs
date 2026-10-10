import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL } from './environment.mjs';

/**
 * Browser coverage for Library browsing.
 *
 * Libraries are read-oriented in the client, so the fixtures are arranged through the
 * API and removed again, leaving the seeded dataset as global setup wrote it.
 */

test.describe.configure({ mode: 'serial' });

const created = { libraries: [], projects: [], notebooks: [] };

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
        for (const libraryId of created.libraries) {
            await api.delete(`/api/libraries/${libraryId}`);
        }
        for (const projectId of created.projects) {
            await api.delete(`/api/projects/${projectId}`);
        }
        for (const notebookId of created.notebooks) {
            await api.delete(`/api/notebooks/${notebookId}`);
        }
    } finally {
        created.libraries = [];
        created.projects = [];
        created.notebooks = [];
        await api.dispose();
    }
}

test.afterAll(async () => {
    await cleanUp();
});

async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openLibraries(page) {
    await page.getByRole('button', { name: 'Libraries', exact: true }).click();
    await expect(page.locator('#libraries-view')).toBeVisible();
}

test('the Libraries view explains itself before any Library exists', async ({ page }) => {
    await signIn(page);
    await openLibraries(page);

    await expect(page.locator('#library-empty')).toBeVisible();
    await expect(page.locator('#library-summary')).toHaveText('Showing 0 of 0 Libraries');
    await expect(page.locator('#library-page-label')).toHaveText('Libraries page 1 of 1');
    await expect(page.getByRole('button', { name: 'Previous Libraries' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Next Libraries' })).toBeDisabled();
    await expect(page.locator('#library-detail')).toBeHidden();
});

test('a Library can be browsed down to the Projects and Notebooks it groups', async ({ page }) => {
    const stamp = Date.now();
    const libraryName = `E2E Library ${stamp}`;
    const projectName = `Grouped Project ${stamp}`;
    const notebookName = `Grouped Notebook ${stamp}`;

    const api = await apiClient();
    try {
        const library = await (await api.post('/api/libraries', {
            data: { name: libraryName, description: 'Grouped by the browser suite.' },
        })).json();
        created.libraries.push(library.data.library.id);

        const project = await (await api.post('/api/projects', { data: { name: projectName } })).json();
        created.projects.push(project.data.project.id);
        const notebook = await (await api.post('/api/notebooks', { data: { name: notebookName } })).json();
        created.notebooks.push(notebook.data.notebook.id);

        for (const [type, id] of [['project', project.data.project.id], ['notebook', notebook.data.notebook.id]]) {
            const added = await api.post(
                `/api/libraries/${library.data.library.id}/members/${type}/${id}`,
                { data: {} },
            );
            expect(added.ok(), `the fixture ${type} could not join the Library`).toBeTruthy();
        }
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openLibraries(page);

    await expect(page.locator('#library-empty')).toBeHidden();
    await expect(page.locator('#library-summary')).toHaveText('Showing 1 of 1 Libraries');
    const libraryItem = page.locator('#library-list li', { hasText: libraryName });
    await expect(libraryItem).toHaveCount(1);

    await libraryItem.getByRole('button', { name: `Open ${libraryName}` }).click();

    await expect(page.locator('#library-detail')).toBeVisible();
    await expect(page.locator('#library-detail-title')).toHaveText(libraryName);
    await expect(page.locator('#library-detail-description')).toHaveText('Grouped by the browser suite.');
    await expect(page.locator('#library-projects')).toContainText(projectName);
    await expect(page.locator('#library-notebooks')).toContainText(notebookName);
    await expect(page.locator('#library-members-empty')).toBeHidden();

    // Browsing offers no mutation: the Library view has one control per Library.
    await expect(libraryItem.getByRole('button')).toHaveCount(1);

    await cleanUp();
});

test('an empty Library says so rather than showing nothing', async ({ page }) => {
    const libraryName = `Empty Library ${Date.now()}`;

    const api = await apiClient();
    try {
        const library = await (await api.post('/api/libraries', { data: { name: libraryName } })).json();
        created.libraries.push(library.data.library.id);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openLibraries(page);
    await page.getByRole('button', { name: `Open ${libraryName}` }).click();

    await expect(page.locator('#library-members-empty')).toBeVisible();
    await expect(page.locator('#library-projects')).toBeEmpty();
    await expect(page.locator('#library-notebooks')).toBeEmpty();

    await cleanUp();
});

test('the Libraries list pages at the client page size', async ({ page }) => {
    const pageSize = 20;
    const stamp = Date.now();
    const api = await apiClient();
    try {
        for (let index = 1; index <= pageSize + 1; index++) {
            const response = await api.post('/api/libraries', {
                data: { name: `Paged Library ${stamp} ${String(index).padStart(2, '0')}` },
            });
            const { data } = await response.json();
            created.libraries.push(data.library.id);
        }
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openLibraries(page);

    const previous = page.getByRole('button', { name: 'Previous Libraries' });
    const next = page.getByRole('button', { name: 'Next Libraries' });

    await expect(page.locator('#library-page-label')).toHaveText('Libraries page 1 of 2');
    await expect(page.locator('#library-list li')).toHaveCount(pageSize);
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();

    await next.click();
    await expect(page.locator('#library-page-label')).toHaveText('Libraries page 2 of 2');
    await expect(page.locator('#library-list li')).toHaveCount(1);
    await expect(next).toBeDisabled();

    await previous.click();
    await expect(page.locator('#library-page-label')).toHaveText('Libraries page 1 of 2');

    await cleanUp();
});

test('an ended session while browsing Libraries returns the visitor to sign-in', async ({ page, context }) => {
    const libraryName = `Session Library ${Date.now()}`;

    const api = await apiClient();
    try {
        const library = await (await api.post('/api/libraries', { data: { name: libraryName } })).json();
        created.libraries.push(library.data.library.id);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openLibraries(page);
    await expect(page.locator('#library-list')).toContainText(libraryName);

    await context.clearCookies();
    await page.getByRole('button', { name: `Open ${libraryName}` }).click();

    await expect(page.locator('#login-panel')).toBeVisible();
    await expect(page.locator('#workspace')).toBeHidden();
    await expect(page.locator('#notice')).toHaveText('Your session has ended. Please sign in again.');

    await cleanUp();
});

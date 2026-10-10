import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL } from './environment.mjs';

/**
 * Browser coverage for Projects, ProjectSections and ToDo section assignment.
 *
 * The specs run in order and clean up everything they create, so the seeded dataset the
 * smoke journey depends on is left exactly as global setup wrote it. The first spec
 * asserts the empty state, which holds because global setup seeds no Projects and every
 * later spec removes its own.
 */

test.describe.configure({ mode: 'serial' });

const created = { projects: [], notebooks: [], todos: [] };

/**
 * Session-aware API client used to arrange and tear down fixtures the client has no
 * screen for. It exercises the same endpoints the client calls.
 */
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
        for (const projectId of created.projects) {
            const response = await api.get(`/api/projects/${projectId}`);
            if (!response.ok()) {
                continue;
            }
            const { data } = await response.json();
            for (const section of data.project.sections ?? []) {
                await api.delete(`/api/projects/${projectId}/sections/${section.id}`);
            }
            await api.delete(`/api/projects/${projectId}`);
        }
        for (const notebookId of created.notebooks) {
            const response = await api.get(`/api/notebooks/${notebookId}`);
            if (!response.ok()) {
                continue;
            }
            const { data } = await response.json();
            for (const section of data.notebook.sections ?? []) {
                await api.delete(`/api/notebooks/${notebookId}/sections/${section.id}`);
            }
            await api.delete(`/api/notebooks/${notebookId}`);
        }
    } finally {
        created.projects = [];
        created.notebooks = [];
        created.todos = [];
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

async function openProjects(page) {
    await page.getByRole('button', { name: 'Projects', exact: true }).click();
    await expect(page.locator('#projects-view')).toBeVisible();
}

async function captureTodo(page, title) {
    const api = await apiClient();
    try {
        const response = await api.post('/api/todos', { data: { title } });
        expect(response.ok(), 'the fixture ToDo could not be created').toBeTruthy();
        const { data } = await response.json();
        created.todos.push(data.todo.id);

        return data.todo.id;
    } finally {
        await api.dispose();
    }
}

function todoCard(page, title) {
    return page.locator('#todo-list li', { hasText: title }).first();
}

async function findTodo(page, title) {
    // Both the view switch and the filter submit reload the list. Each click waits for
    // its own response so no later render can replace a control mid-interaction.
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

test('the Projects view explains itself before any Project exists', async ({ page }) => {
    await signIn(page);
    await openProjects(page);

    await expect(page.locator('#project-empty')).toBeVisible();
    await expect(page.locator('#project-summary')).toHaveText('Showing 0 of 0 Projects');
    await expect(page.locator('#project-page-label')).toHaveText('Projects page 1 of 1');
    await expect(page.getByRole('button', { name: 'Previous Projects' })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Next Projects' })).toBeDisabled();
    await expect(page.locator('#project-detail')).toBeHidden();
});

test('a Project, its sections and a ToDo assignment can be managed from the client', async ({ page }) => {
    const projectName = `E2E Project ${Date.now()}`;
    const sectionName = 'Waiting on quotes';
    const renamedSection = 'Quotes received';
    const todoTitle = `Assignable ToDo ${Date.now()}`;
    await captureTodo(page, todoTitle);

    await signIn(page);
    await openProjects(page);

    // Creating a Project.
    await page.getByLabel('Project name').fill(projectName);
    await page.getByLabel(/^Description/).fill('Created by the browser suite.');
    await page.getByRole('button', { name: 'Add Project' }).click();

    await expect(page.locator('#notice')).toHaveText('Project added.');
    await expect(page.locator('#project-empty')).toBeHidden();
    const projectItem = page.locator('#project-list li', { hasText: projectName });
    await expect(projectItem).toHaveCount(1);
    created.projects.push(Number(
        await projectItem.getByRole('button', { name: `Open ${projectName}` })
            .getAttribute('data-project-id'),
    ));

    // Opening it reveals its sections, and a section can be added.
    await projectItem.getByRole('button', { name: `Open ${projectName}` }).click();
    await expect(page.locator('#project-detail')).toBeVisible();
    await expect(page.locator('#project-detail-title')).toHaveText(projectName);
    await expect(page.locator('#section-empty')).toBeVisible();

    await page.getByLabel('Add a section').fill(sectionName);
    await page.getByRole('button', { name: 'Add section' }).click();

    await expect(page.locator('#notice')).toHaveText('Section added.');
    await expect(page.locator('#section-empty')).toBeHidden();
    await expect(page.locator('#section-list')).toContainText(sectionName);

    // Assigning a ToDo to that section from the ToDo card.
    await findTodo(page, todoTitle);
    const card = todoCard(page, todoTitle);
    await expect(card.locator('.section-assignment')).toHaveCount(0);
    await card.getByLabel(`Project section for ${todoTitle}`)
        .selectOption({ label: `${projectName} · ${sectionName}` });
    await card.getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(todoCard(page, todoTitle).locator('.section-assignment'))
        .toHaveText(`In ${projectName} · ${sectionName}`);

    // The Project view shows the assignment from the other side.
    await openProjects(page);
    await expect(page.locator('#section-list')).toContainText(todoTitle);

    // A section can be renamed, and the new name reaches the picker.
    await page.getByLabel(`New name for section ${sectionName}`, { exact: true }).fill(renamedSection);
    await page.getByRole('button', { name: `Save the new name for section ${sectionName}` }).click();
    await expect(page.locator('#notice')).toHaveText('Section renamed.');
    await expect(page.locator('#section-list')).toContainText(renamedSection);

    // Clearing the assignment returns the ToDo to no section.
    await findTodo(page, todoTitle);
    await todoCard(page, todoTitle)
        .getByLabel(`Project section for ${todoTitle}`)
        .selectOption('');
    await todoCard(page, todoTitle).getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveText('ToDo updated.');
    await expect(todoCard(page, todoTitle).locator('.section-assignment')).toHaveCount(0);

    // An empty section and then the Project itself can be deleted.
    await openProjects(page);
    await page.getByRole('button', { name: `Delete section ${renamedSection}` }).click();
    await expect(page.locator('#notice')).toHaveText('Section deleted.');
    await expect(page.locator('#section-empty')).toBeVisible();

    await page.getByLabel(`New name for ${projectName}`, { exact: true }).fill(`${projectName} renamed`);
    await page.getByRole('button', { name: `Save the new name for ${projectName}` }).click();
    await expect(page.locator('#notice')).toHaveText('Project renamed.');
    await expect(page.locator('#project-list')).toContainText(`${projectName} renamed`);

    await page.getByRole('button', { name: `Delete ${projectName} renamed` }).click();
    await expect(page.locator('#notice')).toHaveText('Project deleted.');
    await expect(page.locator('#project-empty')).toBeVisible();
    created.projects = [];
});

test('a ToDo already in a Notebook section is refused rather than silently moved', async ({ page }) => {
    const stamp = Date.now();
    const projectName = `Conflict Project ${stamp}`;
    const sectionName = 'Conflict section';
    const todoTitle = `Notebook ToDo ${stamp}`;

    const api = await apiClient();
    try {
        const project = await (await api.post('/api/projects', { data: { name: projectName } })).json();
        created.projects.push(project.data.project.id);
        await api.post(`/api/projects/${project.data.project.id}/sections`, { data: { name: sectionName } });

        const notebook = await (await api.post('/api/notebooks', { data: { name: `Notebook ${stamp}` } })).json();
        created.notebooks.push(notebook.data.notebook.id);
        const section = await (await api.post(
            `/api/notebooks/${notebook.data.notebook.id}/sections`,
            { data: { name: 'Reading' } },
        )).json();

        const todo = await (await api.post('/api/todos', { data: { title: todoTitle } })).json();
        created.todos.push(todo.data.todo.id);
        const assigned = await api.patch(`/api/todos/${todo.data.todo.id}`, {
            data: { notebook_section_id: section.data.section.id },
        });
        expect(assigned.ok(), 'the fixture ToDo could not be filed in a Notebook section').toBeTruthy();
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await findTodo(page, todoTitle);
    const card = todoCard(page, todoTitle);
    await expect(card.locator('.section-assignment')).toHaveText('In a Notebook section');

    await card.getByLabel(`Project section for ${todoTitle}`)
        .selectOption({ label: `${projectName} · ${sectionName}` });
    await card.getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#notice')).toHaveClass(/is-error/);
    await expect(page.locator('#notice'))
        .toHaveText('A ToDo may belong to only one organizational section.');
    await expect(page.locator('#workspace')).toBeVisible();

    // The server refused the move, so the ToDo is still where it was.
    await findTodo(page, todoTitle);
    await expect(todoCard(page, todoTitle).locator('.section-assignment'))
        .toHaveText('In a Notebook section');

    await cleanUp();
});

test('the Projects list pages at the client page size', async ({ page }) => {
    const pageSize = 20;
    const stamp = Date.now();
    const api = await apiClient();
    try {
        for (let index = 1; index <= pageSize + 1; index++) {
            const response = await api.post('/api/projects', {
                data: { name: `Paged Project ${stamp} ${String(index).padStart(2, '0')}` },
            });
            const { data } = await response.json();
            created.projects.push(data.project.id);
        }
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await openProjects(page);

    const previous = page.getByRole('button', { name: 'Previous Projects' });
    const next = page.getByRole('button', { name: 'Next Projects' });

    await expect(page.locator('#project-page-label')).toHaveText('Projects page 1 of 2');
    await expect(page.locator('#project-list li')).toHaveCount(pageSize);
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();

    await next.click();
    await expect(page.locator('#project-page-label')).toHaveText('Projects page 2 of 2');
    await expect(page.locator('#project-list li')).toHaveCount(1);
    await expect(next).toBeDisabled();
    await expect(previous).toBeEnabled();

    await previous.click();
    await expect(page.locator('#project-page-label')).toHaveText('Projects page 1 of 2');

    await cleanUp();
});

test('an ended session during assignment returns the visitor to sign-in', async ({ page, context }) => {
    const stamp = Date.now();
    const projectName = `Session Project ${stamp}`;
    const todoTitle = `Session ToDo ${stamp}`;

    const api = await apiClient();
    try {
        const project = await (await api.post('/api/projects', { data: { name: projectName } })).json();
        created.projects.push(project.data.project.id);
        await api.post(`/api/projects/${project.data.project.id}/sections`, { data: { name: 'Later' } });
        const todo = await (await api.post('/api/todos', { data: { title: todoTitle } })).json();
        created.todos.push(todo.data.todo.id);
    } finally {
        await api.dispose();
    }

    await signIn(page);
    await findTodo(page, todoTitle);
    const card = todoCard(page, todoTitle);
    await card.getByLabel(`Project section for ${todoTitle}`)
        .selectOption({ label: `${projectName} · Later` });

    await context.clearCookies();
    await card.getByRole('button', { name: 'Move', exact: true }).click();

    await expect(page.locator('#login-panel')).toBeVisible();
    await expect(page.locator('#workspace')).toBeHidden();
    await expect(page.locator('#notice')).toHaveText('Your session has ended. Please sign in again.');

    await cleanUp();
});

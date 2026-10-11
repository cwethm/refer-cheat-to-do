import { expect, request as apiRequest, test } from '@playwright/test';
import { account, baseURL, collaboratorAccount } from './environment.mjs';

test.describe.configure({ mode: 'serial' });

const created = { projects: [], notebooks: [] };

async function apiClient(credentials) {
    const api = await apiRequest.newContext({ baseURL });
    const response = await api.post('/api/auth/login', {
        data: { email: credentials.email, password: credentials.password },
    });
    expect(response.ok(), 'the fixture client could not sign in').toBeTruthy();

    return api;
}

async function userId(api) {
    const response = await api.get('/api/auth/me');
    expect(response.ok()).toBeTruthy();

    return (await response.json()).data.user.id;
}

async function createContext(api, type, name) {
    const response = await api.post(`/api/${type}s`, { data: { name } });
    expect(response.ok(), `the ${type} fixture could not be created`).toBeTruthy();
    const data = (await response.json()).data[type];
    created[`${type}s`].push(data.id);

    return data.id;
}

async function signIn(page, credentials) {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(credentials.email);
    await page.getByLabel('Password', { exact: true }).fill(credentials.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openCollaboration(page) {
    await page.getByRole('button', { name: 'Collaboration', exact: true }).click();
    await expect(page.locator('#collaboration-view')).toBeVisible();
}

async function grant(api, resourceType, resourceId, subjectId, capability) {
    const response = await api.post('/api/capabilities', {
        data: {
            resource_type: resourceType,
            resource_id: resourceId,
            subject_user_id: subjectId,
            capability,
        },
    });
    expect(response.ok(), `the ${capability} fixture grant could not be created`).toBeTruthy();
}

test.afterAll(async () => {
    for (const credentials of [account, collaboratorAccount]) {
        const api = await apiClient(credentials);
        try {
            for (const type of ['projects', 'notebooks']) {
                for (const id of created[type]) {
                    const response = await api.delete(`/api/${type}/${id}`);
                    expect([200, 404]).toContain(response.status());
                }
            }
        } finally {
            await api.dispose();
        }
    }
    created.projects = [];
    created.notebooks = [];
});

test('a resource owner can grant and revoke without offering self-granting', async ({ page }) => {
    const collaboratorApi = await apiClient(collaboratorAccount);
    const ownerApi = await apiClient(account);
    let notebookId;
    let ownerId;
    let collaboratorId;
    try {
        collaboratorId = await userId(collaboratorApi);
        ownerId = await userId(ownerApi);
        notebookId = await createContext(collaboratorApi, 'notebook', `Grantable ${Date.now()}`);
    } finally {
        await collaboratorApi.dispose();
        await ownerApi.dispose();
    }

    await signIn(page, collaboratorAccount);
    await openCollaboration(page);
    await page.locator('#collaboration-resource-type').selectOption('notebook');
    await page.locator('#collaboration-resource-id').fill(String(notebookId));
    await page.getByRole('button', { name: 'Show capabilities' }).click();
    await expect(page.locator('#capability-panel')).toBeVisible();
    await expect(page.locator('#capability-empty')).toBeVisible();

    await page.locator('#capability-subject-id').fill(String(ownerId));
    await page.locator('#capability-name').selectOption('read');
    await page.getByRole('button', { name: 'Grant capability' }).click();
    await expect(page.locator('#capability-list')).toContainText(`User #${ownerId} · read`);
    await page.getByRole('button', { name: 'Revoke read from user ' + ownerId }).click();
    await expect(page.locator('#capability-empty')).toBeVisible();

    await page.locator('#capability-subject-id').fill(String(collaboratorId));
    await page.getByRole('button', { name: 'Grant capability' }).click();
    await expect(page.locator('#notice')).toHaveText('You cannot grant a capability to yourself.');
});

test('stale capability lookups do not replace the current resource grants', async ({ page }) => {
    let releaseFirstLookup;
    let firstLookupStarted;
    const firstLookupRequested = new Promise((resolve) => {
        firstLookupStarted = resolve;
    });
    const firstLookupHeld = new Promise((resolve) => {
        releaseFirstLookup = resolve;
    });
    await page.route('**/api/capabilities?*', async (route) => {
        const id = new URL(route.request().url()).searchParams.get('resource_id');
        if (id === '101') {
            firstLookupStarted();
            await firstLookupHeld;
        }
        await route.fulfill({
            json: { data: { items: [{ id: Number(id), subject_user_id: Number(id), capability: 'read' }] } },
        });
    });

    await signIn(page, account);
    await openCollaboration(page);
    await page.locator('#collaboration-resource-type').selectOption('project');
    await page.locator('#collaboration-resource-id').fill('101');
    const staleResponse = page.waitForResponse((response) => (
        new URL(response.url()).searchParams.get('resource_id') === '101'
    ));
    await page.getByRole('button', { name: 'Show capabilities' }).click();
    await firstLookupRequested;

    await page.locator('#collaboration-resource-id').fill('202');
    await page.getByRole('button', { name: 'Show capabilities' }).click();
    await expect(page.locator('#capability-list')).toContainText('User #202 · read');

    releaseFirstLookup();
    await staleResponse;
    await expect(page.locator('#capability-list .record-description')).toHaveText(['User #202 · read']);
});

test('a request can be sent, accepted, declined, and called back', async ({ page, browser }) => {
    const ownerApi = await apiClient(account);
    const collaboratorApi = await apiClient(collaboratorAccount);
    let ownerId;
    let collaboratorId;
    let sourceId;
    let targetId;
    try {
        ownerId = await userId(ownerApi);
        collaboratorId = await userId(collaboratorApi);
        sourceId = await createContext(ownerApi, 'project', `Request source ${Date.now()}`);
        targetId = await createContext(collaboratorApi, 'notebook', `Request target ${Date.now()}`);
        await grant(collaboratorApi, 'notebook', targetId, ownerId, 'send_task');
        await grant(ownerApi, 'project', sourceId, collaboratorId, 'callback');
    } finally {
        await ownerApi.dispose();
        await collaboratorApi.dispose();
    }

    await signIn(page, account);
    await openCollaboration(page);
    await page.locator('#request-source-type').selectOption('project');
    await page.locator('#request-source-id').fill(String(sourceId));
    await page.locator('#request-target-type').selectOption('notebook');
    await page.locator('#request-target-id').fill(String(targetId));
    await page.locator('#request-title').fill('Please review the draft');
    await page.locator('#request-body').fill('Could you share your feedback?');
    await page.getByRole('button', { name: 'Send request' }).click();
    await expect(page.locator('#request-outbox-list')).toContainText('Please review the draft');

    const recipientContext = await browser.newContext();
    const recipientPage = await recipientContext.newPage();
    await signIn(recipientPage, collaboratorAccount);
    await openCollaboration(recipientPage);
    const pendingRequest = recipientPage.locator('#request-inbox-list li', { hasText: 'Please review the draft' });
    await expect(pendingRequest).toBeVisible();
    await pendingRequest.getByRole('button', { name: 'Accept request: Please review the draft' }).click();
    await expect(pendingRequest.getByLabel('Callback summary for Please review the draft')).toBeVisible();
    await pendingRequest.getByLabel('Callback summary for Please review the draft').fill('Reviewed and sent feedback.');
    await pendingRequest.getByRole('button', { name: 'Send callback' }).click();
    await expect(pendingRequest).toContainText('Callback: Reviewed and sent feedback.');

    await page.locator('#request-source-type').selectOption('project');
    await page.locator('#request-source-id').fill(String(sourceId));
    await page.locator('#request-target-type').selectOption('notebook');
    await page.locator('#request-target-id').fill(String(targetId));
    await page.locator('#request-title').fill('Please check the schedule');
    await page.getByRole('button', { name: 'Send request' }).click();
    await expect(page.locator('#request-outbox-list')).toContainText('Please check the schedule');
    await recipientPage.getByRole('button', { name: 'Collaboration', exact: true }).click();
    const declinedRequest = recipientPage.locator('#request-inbox-list li', { hasText: 'Please check the schedule' });
    await expect(declinedRequest).toBeVisible();
    await declinedRequest.getByRole('button', { name: 'Decline request: Please check the schedule' }).click();
    await expect(declinedRequest).toHaveCount(0);
    await recipientContext.close();
});

test('the outbox explains that a referenced target context may have been deleted', async ({ page }) => {
    const ownerApi = await apiClient(account);
    const collaboratorApi = await apiClient(collaboratorAccount);
    let ownerId;
    let sourceId;
    let targetId;
    try {
        ownerId = await userId(ownerApi);
        sourceId = await createContext(ownerApi, 'project', `Deleted target source ${Date.now()}`);
        targetId = await createContext(collaboratorApi, 'notebook', `Deleted target ${Date.now()}`);
    } finally {
        await ownerApi.dispose();
        await collaboratorApi.dispose();
    }

    await signIn(page, account);
    await openCollaboration(page);
    await page.locator('#request-source-type').selectOption('project');
    await page.locator('#request-source-id').fill(String(sourceId));
    await page.locator('#request-target-type').selectOption('notebook');
    await page.locator('#request-target-id').fill(String(targetId));
    await page.locator('#request-title').fill('Request for a removed context');
    await page.getByRole('button', { name: 'Send request' }).click();
    await expect(page.locator('#notice')).toContainText('action was denied or the resource is unavailable');

    const grantor = await apiClient(collaboratorAccount);
    try {
        await grant(grantor, 'notebook', targetId, ownerId, 'send_task');
    } finally {
        await grantor.dispose();
    }
    await page.getByRole('button', { name: 'Send request' }).click();
    await expect(page.locator('#request-outbox-list')).toContainText('Request for a removed context');

    const remover = await apiClient(collaboratorAccount);
    try {
        const response = await remover.delete(`/api/notebooks/${targetId}`);
        expect(response.ok()).toBeTruthy();
    } finally {
        await remover.dispose();
    }

    await page.getByRole('button', { name: 'Collaboration', exact: true }).click();
    const removedTargetRequest = page.locator('#request-outbox-list li', { hasText: 'Request for a removed context' });
    await expect(removedTargetRequest).toContainText(`Notebook #${targetId}`);
    await expect(removedTargetRequest).toContainText('may have been deleted');
});

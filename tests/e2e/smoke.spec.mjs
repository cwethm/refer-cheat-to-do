import { expect, test } from '@playwright/test';
import { account, seededTodoCount } from './environment.mjs';
import { SEEDED_TITLE_PREFIX, seededTitle } from './global-setup.mjs';

/**
 * One browser-level pass over the `/app` client against the real API and database.
 *
 * This is deliberately a single journey: it covers the integration seam that neither
 * the PHPUnit API tests nor the `tests/js` unit tests can reach. Per-feature behavior
 * belongs in those suites, not here.
 */

/**
 * Requests the client is expected to fail on, because an unauthenticated session is
 * exactly what the client probes for when the page loads.
 */
const EXPECTED_FAILURES = [
    { path: '/api/auth/me', status: 401 },
];

function isExpectedFailure(url, status) {
    let path;
    try {
        path = new URL(url).pathname;
    } catch {
        return false;
    }

    return EXPECTED_FAILURES.some(
        (expected) => expected.path === path && (status === null || expected.status === status),
    );
}

test.describe.configure({ mode: 'serial' });

test('the workspace supports capture, tagging, filtering, paging and review', async ({ page }) => {
    const consoleErrors = [];
    const failedRequests = [];

    page.on('console', (message) => {
        // The browser logs a console error for every failed request, including the
        // unauthenticated session probe the client makes on purpose.
        if (message.type() === 'error' && !isExpectedFailure(message.location().url, null)) {
            consoleErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => {
        consoleErrors.push(String(error));
    });
    page.on('response', (response) => {
        if (response.status() >= 400 && !isExpectedFailure(response.url(), response.status())) {
            failedRequests.push(`${response.status()} ${response.url()}`);
        }
    });

    const loginPanel = page.locator('#login-panel');
    const workspace = page.locator('#workspace');
    const notice = page.locator('#notice');
    const todoList = page.locator('#todo-list');
    // Every ToDo card carries an "Add a tag to …" picker, so the filter control is
    // addressed by its exact accessible name.
    const tagFilter = page.getByRole('combobox', { name: 'Tag', exact: true });
    const statusFilter = page.getByRole('combobox', { name: 'Status', exact: true });
    const searchField = page.getByRole('searchbox', { name: 'Search ToDos' });

    // 1. The page opens on the sign-in panel for an anonymous visitor.
    await page.goto('/app');
    await expect(loginPanel).toBeVisible();
    await expect(workspace).toBeHidden();

    // 2. Signing in reveals the workspace with real dashboard counts.
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(workspace).toBeVisible();
    await expect(loginPanel).toBeHidden();
    await expect(page.locator('#account-email')).toHaveText(account.email);

    // The counts must be real numbers from the API, not the placeholder the page ships with.
    const inboxCount = page.locator('#inbox-count');
    await expect(inboxCount).toHaveText(/^\d+$/);
    const inboxBefore = Number(await inboxCount.textContent());
    expect(inboxBefore).toBeGreaterThanOrEqual(seededTodoCount);

    // 3. Quick capture adds a ToDo and surfaces it in the recently touched list.
    const capturedTitle = `Smoke capture ${Date.now()}`;
    await page.getByLabel('What do you need to remember?').fill(capturedTitle);
    await page.getByLabel(/^Notes/).fill('Captured by the end-to-end smoke test.');
    await page.getByRole('button', { name: 'Add to inbox' }).click();

    await expect(notice).toHaveText('Added to your inbox.');
    await expect(page.locator('#recent-todos')).toContainText(capturedTitle);
    await expect(page.locator('#inbox-count')).toHaveText(String(inboxBefore + 1));

    // 4. A new tag reaches both the tag list and the filter control, and can be attached.
    const tagName = `smoke-${Date.now()}`;
    await page.getByRole('button', { name: 'ToDos' }).click();
    await expect(page.locator('#inbox-view')).toBeVisible();

    await page.getByLabel('Create a tag').fill(tagName);
    await page.getByRole('button', { name: 'Add tag' }).click();

    await expect(notice).toHaveText('Tag added.');
    await expect(page.locator('#tag-list')).toContainText(tagName);
    await expect(tagFilter.locator('option', { hasText: tagName })).toHaveCount(1);

    const capturedCard = todoList.locator('li', { hasText: capturedTitle }).first();
    await capturedCard.getByLabel(`Add a tag to ${capturedTitle}`).selectOption({ label: tagName });
    await capturedCard.getByRole('button', { name: 'Attach' }).click();

    await expect(notice).toHaveText('ToDo updated.');
    await expect(todoList.locator('li', { hasText: capturedTitle }).first().locator('.tag-pill'))
        .toContainText(tagName);

    // 5. Filtering narrows the list to the tagged ToDo, and clearing it restores the page.
    await searchField.fill(capturedTitle);
    await statusFilter.selectOption('inbox');
    await tagFilter.selectOption({ label: tagName });
    await page.getByRole('button', { name: 'Find ToDos' }).click();

    await expect(page.locator('#todo-summary')).toHaveText('Showing 1 of 1 ToDos');
    await expect(todoList.locator('li')).toHaveCount(1);
    await expect(todoList).toContainText(capturedTitle);

    await searchField.fill('');
    await statusFilter.selectOption('');
    await tagFilter.selectOption('');
    await page.getByRole('button', { name: 'Find ToDos' }).click();
    await expect(todoList.locator('li')).toHaveCount(50);

    // 6. Paging moves through the full list and keeps the controls in step.
    const previous = page.getByRole('button', { name: 'Previous' });
    const next = page.getByRole('button', { name: 'Next' });

    await expect(page.locator('#page-label')).toHaveText('Page 1 of 2');
    await expect(previous).toBeDisabled();
    await expect(next).toBeEnabled();
    await expect(todoList).toContainText(capturedTitle);

    await next.click();
    await expect(page.locator('#page-label')).toHaveText('Page 2 of 2');
    await expect(previous).toBeEnabled();
    await expect(next).toBeDisabled();
    await expect(todoList).not.toContainText(capturedTitle);
    await expect(todoList).toContainText(seededTitle(1));

    await previous.click();
    await expect(page.locator('#page-label')).toHaveText('Page 1 of 2');
    await expect(todoList).toContainText(capturedTitle);

    // 7. The review queue lists work with human-readable reasons.
    await page.getByRole('button', { name: 'Review' }).click();
    const reviewList = page.locator('#review-list');

    await expect(page.locator('#review-view')).toBeVisible();
    await expect(page.locator('#review-empty')).toBeHidden();
    await expect(reviewList.locator('li').first()).toContainText(SEEDED_TITLE_PREFIX);
    await expect(reviewList.locator('.review-reasons').first()).toContainText('Not in a Project or Notebook section');
    await expect(reviewList.locator('.review-reasons').first()).not.toContainText('no_section');

    // 8. Signing out returns to the sign-in panel.
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(notice).toHaveText('You have signed out.');
    await expect(workspace).toBeHidden();
    await expect(page.locator('#account-actions')).toBeHidden();
    await expect(loginPanel).toBeVisible();

    // 9. The session is really gone: a reload lands back on sign-in.
    await page.reload();
    await expect(loginPanel).toBeVisible();
    await expect(workspace).toBeHidden();

    expect(consoleErrors, 'the client logged console errors').toEqual([]);
    expect(failedRequests, 'the client made unexpected failing requests').toEqual([]);
});

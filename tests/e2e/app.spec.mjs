import { expect, test } from '@playwright/test';
import { e2eEnvironment } from './environment.mjs';
import { openView, resetWorkspace, seedTodos, signIn } from './helpers.mjs';

test.describe('daily workflow', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page);
        await resetWorkspace(page);
        await page.reload();
        await expect(page.locator('#workspace')).toBeVisible();
    });

    test('capture, organize, find, review and complete a ToDo', async ({ page }) => {
        await page.getByLabel('What do you need to remember?').fill('Draft the quarterly letter');
        await page.getByLabel(/^Notes/).fill('Start from last quarter.');
        await page.getByRole('button', { name: 'Add to inbox' }).click();

        await expect(page.locator('#notice')).toHaveText('Added to your inbox.');
        await expect(page.locator('#recent-todos')).toContainText('Draft the quarterly letter');
        await expect(page.locator('#inbox-count')).toHaveText('1');

        await openView(page, 'ToDos');
        await page.getByLabel('Create a tag').fill('Follow up');
        await page.getByRole('button', { name: 'Add tag' }).click();
        await expect(page.locator('#tag-list')).toContainText('Follow up');

        const card = page.locator('.todo-card').filter({ hasText: 'Draft the quarterly letter' });
        await card.getByRole('combobox').selectOption({ label: 'Follow up' });
        await card.getByRole('button', { name: 'Attach' }).click();
        await expect(page.locator('.todo-card').filter({ hasText: 'Draft the quarterly letter' })
            .locator('.tag-pill')).toContainText('Follow up');

        await page.getByLabel('Search ToDos').fill('quarterly');
        await page.locator('#tag-filter').selectOption({ label: 'Follow up' });
        await page.getByRole('button', { name: 'Find ToDos' }).click();
        await expect(page.locator('#todo-summary')).toHaveText('Showing 1 of 1 ToDos');

        await page.locator('.todo-card .tag-remove').first().click();
        await expect(page.locator('#notice')).toHaveText('ToDo updated.');

        await openView(page, 'Review');
        const reviewCard = page.locator('#review-list .todo-card').first();
        await expect(reviewCard.locator('.review-reasons')).toContainText('Not in a Project or Notebook section');
        await reviewCard.getByRole('button', { name: 'Mark reviewed' }).click();
        await expect(page.locator('#notice')).toHaveText('ToDo updated.');

        await openView(page, 'ToDos');
        await page.getByLabel('Search ToDos').fill('');
        await page.locator('#tag-filter').selectOption('');
        await page.getByRole('button', { name: 'Find ToDos' }).click();
        await page.locator('#todo-list').getByRole('button', { name: 'Mark done' }).first().click();
        await expect(page.locator('#todo-list .status-label').first()).toHaveText('done');

        await page.getByRole('button', { name: 'Sign out' }).click();
        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#notice')).toHaveText('You have signed out.');
    });

    test('empty states explain that nothing matched instead of showing a blank list', async ({ page }) => {
        await openView(page, 'ToDos');
        await expect(page.locator('#todo-empty')).toBeVisible();
        await expect(page.locator('#tag-empty')).toBeVisible();

        await seedTodos(page, 1, 'Only item');
        await page.getByRole('button', { name: 'Find ToDos' }).click();
        await expect(page.locator('#todo-empty')).toBeHidden();

        await page.getByLabel('Search ToDos').fill('nothing matches this phrase');
        await page.getByRole('button', { name: 'Find ToDos' }).click();
        await expect(page.locator('#todo-empty')).toBeVisible();
        await expect(page.locator('#todo-summary')).toHaveText('Showing 0 of 0 ToDos');
    });

    test('pagination stops at the first and last page', async ({ page }) => {
        await seedTodos(page, 51, 'Paged');
        await openView(page, 'ToDos');

        await expect(page.locator('#page-label')).toHaveText('Page 1 of 2');
        await expect(page.getByRole('button', { name: 'Previous' })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Next' })).toBeEnabled();
        await expect(page.locator('#todo-list li')).toHaveCount(50);

        await page.getByRole('button', { name: 'Next' }).click();
        await expect(page.locator('#page-label')).toHaveText('Page 2 of 2');
        await expect(page.locator('#todo-list li')).toHaveCount(1);
        await expect(page.getByRole('button', { name: 'Next' })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'Previous' })).toBeEnabled();

        await page.getByRole('button', { name: 'Previous' }).click();
        await expect(page.locator('#page-label')).toHaveText('Page 1 of 2');
        await expect(page.getByRole('button', { name: 'Previous' })).toBeDisabled();
    });

    test('a failed action reports the API message without leaving the workspace', async ({ page }) => {
        await openView(page, 'ToDos');
        await page.getByLabel('Create a tag').fill('Follow up');
        await page.getByRole('button', { name: 'Add tag' }).click();
        await expect(page.locator('#tag-list')).toContainText('Follow up');

        await page.getByLabel('Create a tag').fill('follow up');
        await page.getByRole('button', { name: 'Add tag' }).click();
        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        await expect(page.locator('#notice')).toHaveAttribute('role', 'alert');
        await expect(page.locator('#workspace')).toBeVisible();
    });

    test('an expired session returns the visitor to sign-in instead of failing silently', async ({ page, context }) => {
        await context.clearCookies();
        await page.getByLabel('What do you need to remember?').fill('After the session ended');
        await page.getByRole('button', { name: 'Add to inbox' }).click();

        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#workspace')).toBeHidden();
        await expect(page.locator('#notice')).toHaveText('Your session has ended. Please sign in again.');
    });
});

test.describe('session lifecycle', () => {
    test('an anonymous visit shows the sign-in form and no workspace data', async ({ page }) => {
        await page.goto('/app');

        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#workspace')).toBeHidden();
        await expect(page.locator('#account-actions')).toBeHidden();
    });

    test('invalid credentials are rejected with a readable message', async ({ page }) => {
        await page.goto('/app');
        await page.getByLabel('Email').fill(e2eEnvironment.email);
        await page.getByLabel('Password').fill('definitely-not-the-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        await expect(page.locator('#workspace')).toBeHidden();
    });

    test('signing out ends the session for a fresh page load', async ({ page }) => {
        await signIn(page);
        await page.getByRole('button', { name: 'Sign out' }).click();
        await expect(page.locator('#login-panel')).toBeVisible();

        await page.goto('/app');
        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#workspace')).toBeHidden();
    });
});

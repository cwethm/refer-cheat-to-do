import { expect, test } from '@playwright/test';
import { account, seededTodoCount } from './environment.mjs';

/**
 * Narrow client regressions that the single smoke journey deliberately does not cover.
 *
 * `smoke.spec.mjs` owns the happy path. This file owns the boundaries around it: empty
 * results, paging limits, API errors surfaced to the person, and an ended session. The
 * seeded dataset from global setup is left intact so the smoke journey is unaffected by
 * the order the specs run in.
 */

const PAGE_SIZE = 50;

async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openToDos(page) {
    await page.getByRole('button', { name: 'ToDos', exact: true }).click();
    await expect(page.locator('#inbox-view')).toBeVisible();
}

test.describe('client boundaries', () => {
    test.beforeEach(async ({ page }) => {
        await signIn(page);
    });

    test('a search that matches nothing explains itself instead of showing a blank list', async ({ page }) => {
        await openToDos(page);
        await expect(page.locator('#todo-empty')).toBeHidden();

        await page.getByRole('searchbox', { name: 'Search ToDos' })
            .fill(`no ToDo contains this phrase ${Date.now()}`);
        await page.getByRole('button', { name: 'Find ToDos' }).click();

        await expect(page.locator('#todo-empty')).toBeVisible();
        await expect(page.locator('#todo-summary')).toHaveText('Showing 0 of 0 ToDos');
        await expect(page.locator('#todo-list li')).toHaveCount(0);
    });

    test('paging stops at the first and last page', async ({ page }) => {
        await openToDos(page);

        const previous = page.getByRole('button', { name: 'Previous' });
        const next = page.getByRole('button', { name: 'Next' });
        const summaryText = page.locator('#todo-summary');
        await expect(summaryText).toHaveText(/of \d+ ToDos$/);
        const total = Number((await summaryText.textContent()).match(/of (\d+) ToDos/)[1]);
        const pageCount = Math.ceil(total / PAGE_SIZE);

        expect(total).toBeGreaterThanOrEqual(seededTodoCount);
        expect(pageCount).toBeGreaterThan(1);

        await expect(page.locator('#page-label')).toHaveText(`Page 1 of ${pageCount}`);
        await expect(previous).toBeDisabled();
        await expect(page.locator('#todo-list li')).toHaveCount(PAGE_SIZE);

        for (let current = 2; current <= pageCount; current++) {
            await next.click();
            await expect(page.locator('#page-label')).toHaveText(`Page ${current} of ${pageCount}`);
        }

        await expect(next).toBeDisabled();
        await expect(previous).toBeEnabled();
        await expect(page.locator('#todo-list li')).toHaveCount(total - (pageCount - 1) * PAGE_SIZE);

        await previous.click();
        await expect(page.locator('#page-label')).toHaveText(`Page ${pageCount - 1} of ${pageCount}`);
    });

    test('a rejected request reports the API message without leaving the workspace', async ({ page }) => {
        await openToDos(page);

        // Tag names are unique per user after normalization, so creating the same name
        // twice is a real API rejection rather than a simulated one.
        const name = `duplicate-${Date.now()}`;
        await page.getByLabel('Create a tag').fill(name);
        await page.getByRole('button', { name: 'Add tag' }).click();
        await expect(page.locator('#tag-list')).toContainText(name);

        await page.getByLabel('Create a tag').fill(name.toUpperCase());
        await page.getByRole('button', { name: 'Add tag' }).click();

        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        await expect(page.locator('#notice')).toHaveAttribute('role', 'alert');
        await expect(page.locator('#notice')).not.toBeEmpty();
        await expect(page.locator('#workspace')).toBeVisible();

        await page.getByRole('button', { name: `Delete tag ${name}` }).click();
        await expect(page.locator('#tag-list')).not.toContainText(name);
    });

    test('an ended session returns the visitor to sign-in rather than failing silently', async ({ page, context }) => {
        await context.clearCookies();

        await page.getByLabel('What do you need to remember?').fill('Captured after the session ended');
        await page.getByRole('button', { name: 'Add to inbox' }).click();

        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#workspace')).toBeHidden();
        await expect(page.locator('#notice')).toHaveText('Your session has ended. Please sign in again.');
    });
});

test('invalid credentials are rejected with a readable message', async ({ page }) => {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill('definitely-not-the-password');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.locator('#notice')).toHaveClass(/is-error/);
    await expect(page.locator('#notice')).not.toBeEmpty();
    await expect(page.locator('#workspace')).toBeHidden();
    await expect(page.locator('#login-panel')).toBeVisible();
});

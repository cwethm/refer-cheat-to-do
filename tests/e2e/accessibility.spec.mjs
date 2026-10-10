import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { expect, test } from '@playwright/test';
import { account } from './environment.mjs';

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

async function signIn(page) {
    await page.goto('/app');
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
}

async function openView(page, name) {
    await page.getByRole('button', { name, exact: true }).first().click();
}

const VIEWPORTS = [320, 375, 768, 1280];

/**
 * Run an axe audit against the currently rendered view.
 *
 * Only the WCAG 2.1 A/AA rule sets are enabled; "best practice" rules are advisory and
 * would make the gate fail for stylistic reasons rather than for accessibility defects.
 */
async function audit(page) {
    await page.evaluate(axeSource);

    return page.evaluate(async () => window.axe.run(document, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    }));
}

function describeViolations(results) {
    return results.violations
        .map((violation) => `${violation.id}: ${violation.help} (${violation.nodes.length} node(s))`)
        .join('\n');
}

test.describe('accessibility', () => {
    test('the sign-in view has no WCAG A/AA violations', async ({ page }) => {
        await page.goto('/app');
        await expect(page.locator('#login-panel')).toBeVisible();

        const results = await audit(page);
        expect(describeViolations(results), describeViolations(results)).toBe('');
    });

    for (const view of ['Today', 'ToDos', 'Projects', 'Notebooks', 'Libraries', 'Review', 'Activity']) {
        test(`the ${view} view has no WCAG A/AA violations`, async ({ page }) => {
            await signIn(page);
            await openView(page, view);

            const results = await audit(page);
            expect(describeViolations(results), describeViolations(results)).toBe('');
        });
    }

    test('the open ToDo detail panel has no WCAG A/AA violations', async ({ page }) => {
        await signIn(page);
        await openView(page, 'ToDos');
        await page.locator('#todo-list button[data-todo-action="open-detail"]').first().click();
        await expect(page.locator('#todo-detail')).toBeVisible();

        const results = await audit(page);
        expect(describeViolations(results), describeViolations(results)).toBe('');
    });

    test('the skip link and sign-in form are reachable by keyboard alone', async ({ page }) => {
        await page.goto('/app');
        await expect(page.locator('#login-panel')).toBeVisible();

        await page.keyboard.press('Tab');
        await expect(page.locator('.skip-link')).toBeFocused();

        await page.keyboard.press('Tab');
        await page.keyboard.press('Tab');
        await expect(page.locator('#login-email')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.locator('#login-password')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByRole('button', { name: 'Sign in' })).toBeFocused();
    });
});

test.describe('responsive layout', () => {
    test('the open ToDo detail panel does not overflow the narrowest viewport', async ({ page }) => {
        await page.setViewportSize({ width: 320, height: 900 });
        await signIn(page);
        await openView(page, 'ToDos');
        await page.locator('#todo-list button[data-todo-action="open-detail"]').first().click();
        await expect(page.locator('#todo-detail')).toBeVisible();

        const overflow = await page.evaluate(() => ({
            scrollWidth: document.documentElement.scrollWidth,
            clientWidth: document.documentElement.clientWidth,
        }));
        expect(overflow.scrollWidth, 'the ToDo detail panel overflows at 320px')
            .toBeLessThanOrEqual(overflow.clientWidth);
    });

    for (const width of VIEWPORTS) {
        test(`the workspace has no horizontal overflow at ${width} CSS pixels`, async ({ page }) => {
            await page.setViewportSize({ width, height: 900 });
            await signIn(page);

            for (const view of ['Today', 'ToDos', 'Projects', 'Notebooks', 'Libraries', 'Review', 'Activity']) {
                await openView(page, view);
                const overflow = await page.evaluate(() => ({
                    scrollWidth: document.documentElement.scrollWidth,
                    clientWidth: document.documentElement.clientWidth,
                }));
                expect(
                    overflow.scrollWidth,
                    `${view} overflows horizontally at ${width}px`,
                ).toBeLessThanOrEqual(overflow.clientWidth);
            }
        });
    }
});

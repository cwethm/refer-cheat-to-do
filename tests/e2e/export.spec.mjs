import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { account } from './environment.mjs';

test('a signed-in user receives a downloadable workspace export', async ({ page }) => {
    await page.goto('/app');
    await expect(page.getByRole('link', { name: 'Export my data' })).toBeHidden();
    await page.getByLabel('Email').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Export my data' }).click(),
    ]);
    expect(download.suggestedFilename()).toBe('cheat-to-do-export-v1.jsonl');
    expect(await download.failure()).toBeNull();
    const lines = (await readFile(await download.path(), 'utf8')).trim().split('\n').map(JSON.parse);
    expect(lines[0].schema).toBe('urn:cheat-to-do:export:1');
    expect(lines.some((line) => line.type === 'todos')).toBeTruthy();
    expect(JSON.stringify(lines)).not.toMatch(/"password"|"token"|"password_hash"/);
});

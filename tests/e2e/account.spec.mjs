import { expect, test } from '@playwright/test';
import { account, database } from './environment.mjs';
import { cake, psql } from './global-setup.mjs';
import { countMessagesTo, waitForAccountLink } from './mailbox.mjs';

const INVALID_LINK = 'This link is invalid or has expired. Ask for a new link.';

let sequence = 0;

/**
 * An address no other spec uses, so each test owns the account and the messages sent to it.
 */
function uniqueAddress(label) {
    sequence += 1;

    return `e2e-${label}-${Date.now()}-${sequence}@example.test`;
}

async function createAccount(label, password) {
    const email = uniqueAddress(label);
    await cake(['set_user_password', email, '--create', '--password', password]);

    return { email, password };
}

async function signIn(page, credentials) {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(credentials.email);
    await page.getByLabel('Password', { exact: true }).fill(credentials.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#workspace')).toBeVisible();
    await expect(page.locator('#account-email')).toHaveText(credentials.email);
}

async function signInFails(page, credentials) {
    await page.goto('/app');
    await page.getByLabel('Email', { exact: true }).fill(credentials.email);
    await page.getByLabel('Password', { exact: true }).fill(credentials.password);
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.locator('#notice')).toHaveText('Invalid credentials.');
    await expect(page.locator('#workspace')).toBeHidden();
}

async function signOut(page) {
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page.locator('#notice')).toHaveText('You have signed out.');
}

async function openAccount(page) {
    await page.getByRole('button', { name: 'Account', exact: true }).click();
    await expect(page.locator('#account-view')).toBeVisible();
}

/**
 * Fill a new password and its confirmation. Both the link form and the Account view ask for
 * one, so the form is named explicitly.
 */
async function choosePassword(page, formId, password, confirmation = password) {
    const form = page.locator(`#${formId}`);
    await form.getByLabel('New password', { exact: true }).fill(password);
    await form.getByLabel('Repeat the new password', { exact: true }).fill(confirmation);
}

/**
 * The session in another browser must have ended: reloading it lands on sign-in.
 */
async function expectSignedOut(page) {
    await page.reload();
    await expect(page.locator('#login-panel')).toBeVisible();
    await expect(page.locator('#workspace')).toBeHidden();
}

test.describe('account lifecycle', () => {
    test('an invited person creates an account from the emailed link, which then stops working', async ({ page }) => {
        const invitee = uniqueAddress('invitee');
        const password = 'invited-person-password';

        await signIn(page, account);
        await openAccount(page);
        await page.getByLabel('Their email address').fill(invitee);
        await page.getByRole('button', { name: 'Send invitation' }).click();
        await expect(page.locator('#notice')).toHaveText(
            'If that address does not already have an account, an invitation has been sent to it.',
        );
        await expect(page.getByLabel('Their email address')).toHaveValue('');

        const link = await waitForAccountLink(invitee);
        expect(link.kind).toBe('invite');
        expect(link.data).toContain('expires in 168 hours');

        await signOut(page);

        await page.goto(link.url);
        await expect(page.getByRole('heading', { name: 'Create your account.' })).toBeVisible();
        // The token is taken out of the address bar as soon as it has been read.
        expect(page.url()).not.toContain(link.token);
        await expect(page.locator('#workspace')).toBeHidden();

        await choosePassword(page, 'account-link-form', password, 'a different password');
        await page.getByRole('button', { name: 'Create my account' }).click();
        await expect(page.locator('#notice')).toHaveText('The two new passwords do not match.');
        await expect(page.locator('#notice')).toHaveClass(/is-error/);

        await choosePassword(page, 'account-link-form', password);
        await page.getByRole('button', { name: 'Create my account' }).click();
        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#notice')).toHaveText('Your account is ready. Sign in with your new password.');
        await expect(page.getByLabel('Email', { exact: true })).toHaveValue(invitee);

        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page.locator('#workspace')).toBeVisible();
        await expect(page.locator('#account-email')).toHaveText(invitee);
        await signOut(page);

        // A used invitation is refused the same way as any other bad link.
        await page.goto(link.url);
        await expect(page.getByRole('heading', { name: 'Create your account.' })).toBeVisible();
        await choosePassword(page, 'account-link-form', 'another-invited-password');
        await page.getByRole('button', { name: 'Create my account' }).click();
        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#notice')).toHaveText(INVALID_LINK);
        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        await signIn(page, { email: invitee, password });
    });

    test('changing the password keeps this session and ends every other one', async ({ browser }) => {
        const credentials = await createAccount('change', 'original-change-password');
        const replacement = 'replacement-change-password';
        const here = await browser.newContext();
        const elsewhere = await browser.newContext();
        const page = await here.newPage();
        const other = await elsewhere.newPage();

        try {
            await signIn(page, credentials);
            await signIn(other, credentials);
            await openAccount(page);

            await page.getByLabel('Current password').fill('not-the-current-password');
            await choosePassword(page, 'password-change-form', replacement);
            await page.getByRole('button', { name: 'Change password' }).click();
            await expect(page.locator('#notice')).toHaveText('The current password is incorrect.');
            await expect(page.locator('#notice')).toHaveClass(/is-error/);
            await expect(page.locator('#workspace')).toBeVisible();

            await page.getByLabel('Current password').fill(credentials.password);
            await choosePassword(page, 'password-change-form', 'short', 'short');
            await page.getByRole('button', { name: 'Change password' }).click();
            await expect(page.locator('#notice')).toHaveText('Use at least 8 characters for the new password.');

            await choosePassword(page, 'password-change-form', replacement);
            await page.getByRole('button', { name: 'Change password' }).click();
            await expect(page.locator('#notice')).toHaveText(
                'Your password has been changed. Other sessions have been signed out.',
            );
            await expect(page.getByLabel('Current password')).toHaveValue('');

            await page.reload();
            await expect(page.locator('#workspace')).toBeVisible();
            await expect(page.locator('#account-email')).toHaveText(credentials.email);

            await expectSignedOut(other);
            await signInFails(other, credentials);
            await signIn(other, { email: credentials.email, password: replacement });
        } finally {
            await here.close();
            await elsewhere.close();
        }
    });

    test('a forgotten password is reset from the emailed link, which works only once', async ({ browser }) => {
        const credentials = await createAccount('reset', 'original-reset-password');
        const replacement = 'replacement-reset-password';
        const here = await browser.newContext();
        const elsewhere = await browser.newContext();
        const page = await here.newPage();
        const other = await elsewhere.newPage();

        try {
            await signIn(other, credentials);

            await page.goto('/app');
            await page.getByLabel('Email', { exact: true }).fill(credentials.email);
            await page.getByRole('button', { name: 'Forgot your password?' }).click();
            await expect(page.getByRole('heading', { name: 'Reset your password.' })).toBeVisible();
            await expect(page.getByLabel('Email address', { exact: true })).toHaveValue(credentials.email);
            await expect(page.getByLabel('Email address', { exact: true })).toBeFocused();
            await page.getByRole('button', { name: 'Email me a reset link' }).click();
            await expect(page.locator('#login-panel')).toBeVisible();
            await expect(page.locator('#notice')).toHaveText(
                'If an account uses that address, a link to reset its password has been sent to it.',
            );

            const link = await waitForAccountLink(credentials.email);
            expect(link.kind).toBe('reset');
            expect(link.data).toContain('expires in 60 minutes');

            await page.goto(link.url);
            await expect(page.getByRole('heading', { name: 'Choose a new password.' })).toBeVisible();
            expect(page.url()).not.toContain(link.token);
            await choosePassword(page, 'account-link-form', replacement);
            await page.getByRole('button', { name: 'Save new password' }).click();
            await expect(page.locator('#notice')).toHaveText('Your password has been changed. Sign in with the new password.');
            await expect(page.getByLabel('Email', { exact: true })).toHaveValue(credentials.email);

            await expectSignedOut(other);
            await signInFails(page, credentials);
            await signIn(page, { email: credentials.email, password: replacement });
            await signOut(page);

            await page.goto(link.url);
            await choosePassword(page, 'account-link-form', 'a-third-reset-password');
            await page.getByRole('button', { name: 'Save new password' }).click();
            await expect(page.locator('#notice')).toHaveText(INVALID_LINK);
            await expect(page.locator('#notice')).toHaveClass(/is-error/);
            await signIn(page, { email: credentials.email, password: replacement });
        } finally {
            await here.close();
            await elsewhere.close();
        }
    });

    test('a reset request for an unknown address looks the same and sends nothing', async ({ page }) => {
        const unknown = uniqueAddress('unknown');

        await page.goto('/app');
        await page.getByRole('button', { name: 'Forgot your password?' }).click();
        await page.getByLabel('Email address', { exact: true }).fill(unknown);
        await page.getByRole('button', { name: 'Email me a reset link' }).click();
        await expect(page.locator('#notice')).toHaveText(
            'If an account uses that address, a link to reset its password has been sent to it.',
        );
        await expect(page.locator('#notice')).not.toHaveClass(/is-error/);
        expect(await countMessagesTo(unknown)).toBe(0);
    });

    test('an expired reset link is refused and the password is unchanged', async ({ page }) => {
        const credentials = await createAccount('expired', 'original-expired-password');

        await page.goto('/app');
        await page.getByRole('button', { name: 'Forgot your password?' }).click();
        await page.getByLabel('Email address', { exact: true }).fill(credentials.email);
        await page.getByRole('button', { name: 'Email me a reset link' }).click();
        await expect(page.locator('#login-panel')).toBeVisible();
        const link = await waitForAccountLink(credentials.email);

        // Age the link past its lifetime rather than waiting an hour.
        await psql(
            database.name,
            `UPDATE account_tokens SET expires = expires - interval '2 hours' WHERE email = '${credentials.email}' AND used IS NULL`,
        );

        await page.goto(link.url);
        await choosePassword(page, 'account-link-form', 'never-applied-password');
        await page.getByRole('button', { name: 'Save new password' }).click();
        await expect(page.locator('#notice')).toHaveText(INVALID_LINK);
        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        await signIn(page, credentials);
    });

    test('a malformed link is reported without a request to the server', async ({ page }) => {
        const requests = [];
        page.on('request', (request) => {
            if (request.url().includes('/api/invitations') || request.url().includes('/api/auth/password-reset')) {
                requests.push(request.url());
            }
        });
        await page.goto('/app#invite=not-a-real-token');
        await expect(page.locator('#login-panel')).toBeVisible();
        await expect(page.locator('#notice')).toHaveText('This link is invalid or has expired. Ask for a new one.');
        await expect(page.locator('#notice')).toHaveClass(/is-error/);
        expect(page.url()).not.toContain('not-a-real-token');
        expect(requests).toEqual([]);
    });

    test('signing in replaces any session id the browser already held', async ({ page, context }) => {
        const planted = 'plantedsessionid0123456789abcdefgh';
        await page.goto('/app');
        await context.addCookies([{ name: 'PHPSESSID', value: planted, url: page.url() }]);

        await signIn(page, account);
        const session = (await context.cookies()).find((cookie) => cookie.name === 'PHPSESSID');
        expect(session, 'sign-in did not issue a session cookie').toBeTruthy();
        expect(session.value).not.toBe(planted);
        expect(session.httpOnly).toBe(true);
        expect(session.sameSite).toBe('Strict');
    });
});

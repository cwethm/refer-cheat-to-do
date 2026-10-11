/**
 * Shared environment for the browser-level smoke test.
 *
 * The end-to-end run owns its own PostgreSQL database and its own account so it can
 * drop and reseed freely. It must never point at the `default` or PHPUnit `test`
 * connection, because fixture teardown there would race with a running browser.
 */
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const repositoryRoot = fileURLToPath(new URL('../../', import.meta.url));

export const host = process.env.E2E_HOST ?? '127.0.0.1';
export const port = Number(process.env.E2E_PORT ?? 8765);
export const baseURL = `http://${host}:${port}`;

export const account = {
    email: process.env.E2E_USER_EMAIL ?? 'e2e@example.test',
    password: process.env.E2E_USER_PASSWORD ?? 'e2e-smoke-password',
};

/**
 * A second account that global setup creates and never acts as.
 *
 * The seeded account always has activity history, so the empty state of the actor-only
 * history can only be observed through an actor that has done nothing.
 */
export const freshAccount = {
    email: process.env.E2E_FRESH_USER_EMAIL ?? 'e2e-fresh@example.test',
    password: process.env.E2E_FRESH_USER_PASSWORD ?? 'e2e-fresh-password',
};

/**
 * A third account used by specs that need a short, predictable review queue.
 *
 * The seeded account has more ToDos awaiting review than the review view shows at once,
 * so a ToDo created there cannot be found on screen. This account starts with none.
 */
export const soloAccount = {
    email: process.env.E2E_SOLO_USER_EMAIL ?? 'e2e-solo@example.test',
    password: process.env.E2E_SOLO_USER_PASSWORD ?? 'e2e-solo-password',
};

export const collaboratorAccount = {
    email: process.env.E2E_COLLABORATOR_USER_EMAIL ?? 'e2e-collaborator@example.test',
    password: process.env.E2E_COLLABORATOR_USER_PASSWORD ?? 'e2e-collaborator-password',
};

/**
 * Number of seeded ToDos. The client pages at 50, so this must exceed one page for
 * the pagination step of the smoke test to be meaningful.
 */
export const seededTodoCount = Number(process.env.E2E_SEED_TODOS ?? 55);

/**
 * The local SMTP sink that receives invitation and reset messages during the browser run.
 * It must be known before Playwright starts the web server, so its port is fixed.
 */
export const mailbox = {
    host: '127.0.0.1',
    port: Number(process.env.E2E_SMTP_PORT ?? 8766),
    directory: process.env.E2E_MAILBOX_DIR ?? join(tmpdir(), 'refer-cheat-to-do-e2e-mailbox'),
};

export const database = {
    host: process.env.E2E_DB_HOST ?? process.env.DB_HOST ?? '127.0.0.1',
    port: process.env.E2E_DB_PORT ?? process.env.DB_PORT ?? '5432',
    name: process.env.E2E_DB_DATABASE ?? 'refer_cheat_to_do_e2e',
    username: process.env.E2E_DB_USERNAME ?? process.env.DB_USERNAME ?? 'refer_cheat_to_do',
    password: process.env.E2E_DB_PASSWORD ?? process.env.DB_PASSWORD ?? 'refer_cheat_to_do',
};

/**
 * Environment for the application under test and for the `bin/cake` seeding commands.
 *
 * `DEBUG` stays false so the run exercises production error rendering, which means
 * `APP_FULL_BASE_URL` has to match the served host or HostHeaderMiddleware rejects
 * every request with a 400.
 */
export function applicationEnv() {
    return {
        ...process.env,
        APP_NAME: 'refer-cheat-to-do',
        APP_ENV: 'e2e',
        DEBUG: 'false',
        APP_FULL_BASE_URL: baseURL,
        SECURITY_SALT: process.env.E2E_SECURITY_SALT ?? 'e2e-security-salt-for-browser-tests-only',
        DB_HOST: database.host,
        DB_PORT: database.port,
        DB_DATABASE: database.name,
        DB_USERNAME: database.username,
        DB_PASSWORD: database.password,
        EMAIL_TRANSPORT_DEFAULT_URL: `smtp://${mailbox.host}:${mailbox.port}`,
        EMAIL_FROM: 'noreply@example.test',
    };
}

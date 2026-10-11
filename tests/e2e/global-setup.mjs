/**
 * One-time preparation for the browser-level smoke test.
 *
 * Starts the local mailbox, resets the dedicated end-to-end database, creates the account with
 * the same console command operators use, and seeds more ToDos than fit on a single client page so the
 * pagination step of the smoke test has something to page through.
 */
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import {
    account,
    applicationEnv,
    baseURL,
    database,
    collaboratorAccount,
    freshAccount,
    repositoryRoot,
    seededTodoCount,
    soloAccount,
} from './environment.mjs';
import { startMailbox } from './mailbox.mjs';

const run = promisify(execFile);

export const SEEDED_TITLE_PREFIX = 'Seeded ToDo';

/**
 * Title of a seeded ToDo. Numbers are padded so the newest-first listing order is
 * also the obvious descending order when a failure screenshot is read by a human.
 */
export function seededTitle(index) {
    return `${SEEDED_TITLE_PREFIX} ${String(index).padStart(3, '0')}`;
}

export async function psql(databaseName, statement) {
    await run('psql', [
        '-h', database.host,
        '-p', String(database.port),
        '-U', database.username,
        '-d', databaseName,
        '-v', 'ON_ERROR_STOP=1',
        '-tAc', statement,
    ], { env: { ...process.env, PGPASSWORD: database.password } });
}

export async function cake(args) {
    await run('bin/cake', args, { cwd: repositoryRoot, env: applicationEnv() });
}

async function resetDatabase() {
    await psql('template1', `DROP DATABASE IF EXISTS "${database.name}" WITH (FORCE)`);
    await psql('template1', `CREATE DATABASE "${database.name}" OWNER "${database.username}"`);
    await cake(['migrations', 'migrate', '-c', 'default']);
}

async function waitForServer() {
    const deadline = Date.now() + 60_000;
    for (;;) {
        try {
            const response = await fetch(`${baseURL}/api/health`, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                return;
            }
        } catch {
            // The server is not listening yet.
        }
        if (Date.now() > deadline) {
            throw new Error(`The application under test never became ready at ${baseURL}.`);
        }
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
}

/**
 * Minimal session-aware API client. Node's fetch does not keep cookies, so the
 * session cookie issued by the login response is carried by hand.
 */
function apiClient() {
    let cookie = '';

    return async function request(path, options = {}) {
        const response = await fetch(`${baseURL}${path}`, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(cookie ? { Cookie: cookie } : {}),
                ...options.headers,
            },
        });

        const issued = response.headers.getSetCookie?.() ?? [];
        if (issued.length > 0) {
            cookie = issued.map((value) => value.split(';')[0]).join('; ');
        }

        if (!response.ok) {
            throw new Error(`${options.method ?? 'GET'} ${path} failed with ${response.status}: ${await response.text()}`);
        }

        return response.json();
    };
}

async function seedTodos() {
    const request = apiClient();
    await request('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email: account.email, password: account.password }),
    });

    for (let index = 1; index <= seededTodoCount; index++) {
        await request('/api/todos', {
            method: 'POST',
            body: JSON.stringify({
                title: seededTitle(index),
                notes: 'Seeded by the end-to-end smoke test.',
            }),
        });
    }

    await request('/api/auth/logout', { method: 'POST', body: '{}' });
}

export default async function globalSetup() {
    // Invitation and reset messages are delivered here; the returned function stops it.
    const stopMailbox = await startMailbox();
    await resetDatabase();
    await cake(['set_user_password', account.email, '--create', '--password', account.password]);
    // Created but never acted as, so the activity history has an observable empty state.
    await cake([
        'set_user_password',
        freshAccount.email,
        '--create',
        '--password',
        freshAccount.password,
    ]);
    // Owns no ToDos at first, so specs can rely on a short review queue.
    await cake([
        'set_user_password',
        soloAccount.email,
        '--create',
        '--password',
        soloAccount.password,
    ]);
    await cake([
        'set_user_password',
        collaboratorAccount.email,
        '--create',
        '--password',
        collaboratorAccount.password,
    ]);
    await waitForServer();
    await seedTodos();

    return stopMailbox;
}

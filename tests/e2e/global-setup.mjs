import { execFileSync } from 'node:child_process';
import { e2eEnvironment, serverEnv } from './environment.mjs';

const run = (command, args, env) => execFileSync(command, args, {
    env,
    stdio: 'pipe',
    encoding: 'utf8',
});

/**
 * Rebuild the dedicated browser-test database and seed the single operator account.
 *
 * The database is dropped and recreated so every run starts from the migrated schema
 * with no leftover rows, which keeps counts and pagination assertions deterministic.
 */
export default function globalSetup() {
    const env = serverEnv();
    const psqlEnv = {
        ...env,
        PGPASSWORD: e2eEnvironment.dbPassword,
    };
    const connection = [
        '-h', e2eEnvironment.dbHost,
        '-p', String(e2eEnvironment.dbPort),
        '-U', e2eEnvironment.dbUsername,
    ];

    run('dropdb', [...connection, '--if-exists', e2eEnvironment.database], psqlEnv);
    run('createdb', [...connection, `--owner=${e2eEnvironment.dbUsername}`, e2eEnvironment.database], psqlEnv);

    run('bin/cake', ['migrations', 'migrate', '-c', 'default'], env);
    run('bin/cake', [
        'set_user_password',
        e2eEnvironment.email,
        '--create',
        '--password',
        e2eEnvironment.password,
    ], env);
}

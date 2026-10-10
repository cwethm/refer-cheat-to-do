/**
 * Shared configuration for the browser end-to-end suite.
 *
 * The browser suite owns a dedicated database and account so it can never read or
 * destroy data belonging to the `default` connection or the PHPUnit `test` connection.
 */

const env = (name, fallback) => {
    const value = process.env[name];

    return value === undefined || value === '' ? fallback : value;
};

export const e2eEnvironment = {
    host: env('E2E_HOST', '127.0.0.1'),
    port: Number(env('E2E_PORT', '8766')),
    database: env('E2E_DB_DATABASE', 'refer_cheat_to_do_e2e'),
    dbHost: env('E2E_DB_HOST', env('DB_HOST', '127.0.0.1')),
    dbPort: env('E2E_DB_PORT', env('DB_PORT', '5432')),
    dbUsername: env('E2E_DB_USERNAME', env('DB_USERNAME', 'refer_cheat_to_do')),
    dbPassword: env('E2E_DB_PASSWORD', env('DB_PASSWORD', 'refer_cheat_to_do')),
    securitySalt: env('SECURITY_SALT', 'e2e-security-salt-for-browser-tests-only'),
    email: env('E2E_USER_EMAIL', 'e2e@example.test'),
    password: env('E2E_USER_PASSWORD', 'e2e-browser-password'),
};

export const baseURL = `http://${e2eEnvironment.host}:${e2eEnvironment.port}`;

/**
 * Environment for the PHP server and for `bin/cake` commands.
 *
 * `DEBUG` is false so the suite exercises the production middleware stack, which means
 * `APP_FULL_BASE_URL` must match the served base URL or `HostHeaderMiddleware` rejects
 * every request with a 400 response.
 */
export function serverEnv() {
    return {
        ...process.env,
        APP_NAME: 'refer-cheat-to-do',
        APP_ENV: 'e2e',
        DEBUG: 'false',
        APP_FULL_BASE_URL: baseURL,
        SECURITY_SALT: e2eEnvironment.securitySalt,
        DB_HOST: e2eEnvironment.dbHost,
        DB_PORT: String(e2eEnvironment.dbPort),
        DB_DATABASE: e2eEnvironment.database,
        DB_USERNAME: e2eEnvironment.dbUsername,
        DB_PASSWORD: e2eEnvironment.dbPassword,
    };
}

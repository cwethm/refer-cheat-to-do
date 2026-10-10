import { defineConfig, devices } from '@playwright/test';
import { applicationEnv, baseURL, host, port, repositoryRoot } from './tests/e2e/environment.mjs';

/**
 * Browser-level smoke coverage for the `/app` client.
 *
 * The application is served by PHP's built-in server through CakePHP's front
 * controller. That exercises the client, the API and PostgreSQL together, but not the
 * Apache `.htaccess` rewrites, which remain uncovered by automated tests.
 */
export default defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.spec.mjs',
    globalSetup: './tests/e2e/global-setup.mjs',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: `php -S ${host}:${port} -t webroot webroot/index.php`,
        cwd: repositoryRoot,
        url: `${baseURL}/api/health`,
        env: applicationEnv(),
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});

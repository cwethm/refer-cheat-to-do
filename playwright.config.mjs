import { defineConfig, devices } from '@playwright/test';
import { baseURL, e2eEnvironment, serverEnv } from './tests/e2e/environment.mjs';

/**
 * Browser end-to-end configuration for the client served at `/app`.
 *
 * The suite runs against a PHP server started on a dedicated port and database so it is
 * safe to run alongside a development instance and inside the CI quality gate.
 */
export default defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.spec.mjs',
    globalSetup: './tests/e2e/global-setup.mjs',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
    timeout: 30_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: `php -S ${e2eEnvironment.host}:${e2eEnvironment.port} -t webroot webroot/index.php`,
        url: `${baseURL}/app`,
        env: serverEnv(),
        reuseExistingServer: false,
        stdout: 'pipe',
        stderr: 'pipe',
        timeout: 60_000,
    },
});

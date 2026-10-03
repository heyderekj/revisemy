import { defineConfig, devices } from '@playwright/test';
import { APP, appEnv } from './tests/e2e/support/app';

/**
 * The flows PHPUnit can't see: what a person does on a review in the browser.
 *
 * The app runs against a database of its own, rebuilt on every run
 * (`global-setup.ts`). Each spec makes its own try token and review, but one
 * worker keeps `php artisan serve` from being asked for two pages at once.
 *
 *   npm run test:e2e
 *   npm run test:e2e -- --ui
 */
export default defineConfig({
    testDir: 'tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: APP,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } }, grepInvert: /@phone/ },
        // The review link a person opens from their phone.
        { name: 'phone', use: { ...devices['Pixel 7'] }, grep: /@phone/ },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${new URL(APP).port}`,
        url: `${APP}/up`,
        env: appEnv(),
        reuseExistingServer: false,
        timeout: 60_000,
    },
});

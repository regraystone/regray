import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, CHROME_PATH } from './tests/e2e/message/support/env';

/*
 * Browser end-to-end tests. `globalSetup` refuses to run outside APP_ENV=local
 * or testing, resets and seeds a dedicated database, and starts the app,
 * Reverb and a queue worker against it. See tests/e2e/message/REPORT.md.
 *
 * The three projects share the specs; a spec tagged @layout runs everywhere,
 * @mobile only on `mobile`, and everything else only on `desktop`.
 */
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results/artifacts',
    globalSetup: './tests/e2e/message/support/global-setup.ts',
    globalTeardown: './tests/e2e/message/support/global-teardown.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 120_000,
    // `php artisan serve` handles one request at a time, so UI waits get room.
    expect: { timeout: 15_000 },
    reporter: [
        ['list'],
        ['json', { outputFile: 'test-results/message/results.json' }],
    ],
    use: {
        baseURL: BASE_URL,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        launchOptions: CHROME_PATH ? { executablePath: CHROME_PATH } : {},
    },
    projects: [
        {
            name: 'desktop',
            grepInvert: /@mobile/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
        {
            name: 'mobile',
            grep: /@mobile|@layout/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 390, height: 844 },
                hasTouch: true,
                isMobile: true,
            },
        },
        {
            name: 'narrow',
            grep: /@layout/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 320, height: 640 },
            },
        },
    ],
});

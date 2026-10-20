import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import { BASE_URL, CHROME_PATH } from '../message/support/env';
import messageGlobalSetup from '../message/support/global-setup';
import { artisan } from '../message/support/stack';
import { AUTH_DIR, ROLES, credentialsFor } from './accounts';

/**
 * The setup every E2E suite shares.
 *
 * The message suite's own setup does the refusing (APP_ENV, cached config,
 * database name), resets and seeds the dedicated database, and starts the
 * stack. On top of that this seeds the inquiry suite's accounts and signs each
 * role in once through the login form, saving its cookies under
 * tests/e2e/.auth/ so the inquiry specs start signed in.
 */
export default async function globalSetup(): Promise<void> {
    await messageGlobalSetup();

    artisan('db:seed', '--class=E2EInquirySeeder', '--force');

    mkdirSync(AUTH_DIR, { recursive: true });

    const browser = await chromium.launch(
        CHROME_PATH ? { executablePath: CHROME_PATH } : {},
    );

    try {
        for (const role of ROLES) {
            const { email, password } = credentialsFor(role);
            const context = await browser.newContext({ baseURL: BASE_URL });
            const page = await context.newPage();

            await page.goto('/login');
            await page.locator('#email').fill(email);
            await page.locator('#password').fill(password);
            await page.locator('[data-test="login-button"]').click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'));
            await context.storageState({
                path: join(AUTH_DIR, `${role}.json`),
            });
            await context.close();
        }
    } finally {
        await browser.close();
    }
}

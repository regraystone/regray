import { expect, test } from '../support/fixtures';
import { expectStep, openInquire, stepper } from '../support/inquiry-flow';

/* A. Access: who reaches the Inquire page, and where everyone else is sent. */

const FORBIDDEN_TEXT = "You don't have access to this page";

test('1 · a client opens /quote/inquire and lands on "Step 1 of 5 · Services"', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await openInquire(page);

    await expectStep(page, 1);
    await expect(stepper(page).locator('[aria-current="step"]')).toContainText(
        'Services',
    );
    await expect(page.getByRole('checkbox').first()).toBeVisible();
});

test('2 · a marketing user opens /inquiry/inquire', async ({ actors }) => {
    const { page } = await actors.open('marketing');

    await openInquire(page, '/inquiry/inquire');

    await expect(page).toHaveURL(/\/inquiry\/inquire$/);
    await expectStep(page, 1);
});

test('3 · a sales user gets the forbidden page on both Inquire URLs, never the form', async ({
    actors,
}) => {
    const { page } = await actors.open('sales');
    actors.allowConsole(/status of 403/);

    for (const url of ['/quote/inquire', '/inquiry/inquire']) {
        const response = await page.goto(url);

        expect(response?.status(), url).toBe(403);
        await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
        await expect(stepper(page)).toHaveCount(0);
        await expect(
            page.getByRole('heading', { name: 'Our services' }),
        ).toHaveCount(0);
    }
});

test('4 · a guest is sent to the login page', async ({ actors }) => {
    const { page } = await actors.open('guest');

    for (const url of ['/quote/inquire', '/inquiry/inquire']) {
        await page.goto(url);
        await expect(page).toHaveURL(/\/login$/);
    }
});

test('5 · an unverified user is sent to the verification notice', async ({
    actors,
}) => {
    const { page } = await actors.open('unverified');

    await page.goto('/quote/inquire');

    await expect(page).toHaveURL(/\/email\/verify$/);
    await expect(stepper(page)).toHaveCount(0);
});

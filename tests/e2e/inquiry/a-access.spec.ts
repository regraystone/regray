import { expect, test } from '../support/fixtures';
import {
    expectStep,
    openInquire,
    openStaffInquire,
    stepper,
} from '../support/inquiry-flow';

/* A. Access: who reaches the Inquire page, and where everyone else is sent. */

const FORBIDDEN_TEXT = "You don't have access to this page";

test('1 · a client opens /quote/inquire and lands on "Step 1 of 2 · Your request"', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await openInquire(page);

    await expectStep(page, 1);
    await expect(stepper(page).locator('[aria-current="step"]')).toContainText(
        'Your request',
    );
    await expect(page.getByRole('checkbox').first()).toBeVisible();
});

test("2 · a marketing user opens a lead's inquiry on /inquiry/inquire and lands on step 1", async ({
    actors,
}) => {
    const { page } = await actors.open('marketing');

    await openStaffInquire(page);

    await expect(page).toHaveURL(/\/inquiry\/inquire\?.*lead=LEAD-0007/);
    await expectStep(page, 1);
});

test('3 · a sales user is asked Platform or Direct on /inquiry/inquire, and gets the forbidden page on /quote/inquire', async ({
    actors,
}) => {
    const { page } = await actors.open('sales');
    actors.allowConsole(/status of 403/);

    await page.goto('/dashboard/inquiry/inquire');
    await expect(
        page
            .getByTestId('staff-inquire-dialog')
            .locator('[data-staff-inquire-kind]'),
    ).toHaveCount(2);

    const response = await page.goto('/dashboard/quote/inquire');

    expect(response?.status()).toBe(403);
    await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
    await expect(stepper(page)).toHaveCount(0);
});

test('4 · a guest is sent to the login page', async ({ actors }) => {
    const { page } = await actors.open('guest');

    for (const url of [
        '/dashboard/quote/inquire',
        '/dashboard/inquiry/inquire',
    ]) {
        await page.goto(url);
        await expect(page).toHaveURL(/\/login$/);
    }
});

test('5 · an unverified user is sent to the verification notice', async ({
    actors,
}) => {
    const { page } = await actors.open('unverified');

    await page.goto('/dashboard/quote/inquire');

    await expect(page).toHaveURL(/\/email\/verify$/);
    await expect(stepper(page)).toHaveCount(0);
});

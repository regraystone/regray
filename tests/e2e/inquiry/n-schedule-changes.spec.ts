import type { Browser, Page } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, php, test } from '../support/fixtures';

/*
 * N. Changing an appointment after it is booked: a client updates and cancels
 * their own, marketing approves one, and the roles that only watch the week
 * see no button. Each test makes its own appointment for tomorrow at 10:00 in
 * Manila, with a title no other test uses.
 */

/** Tomorrow, `YYYY-MM-DD`, by the studio's clock. */
const tomorrow = (): string =>
    php<string>("now('Asia/Manila')->addDay()->toDateString()");

/** A client's own booking, tomorrow at 10:00, and its id. */
const appointment = (
    clientId: number,
    title: string,
    status: 'pending' | 'confirmed' = 'pending',
): number =>
    php<number>(
        `(function () { $s = new App\\Models\\Schedule; $s->forceFill([
            'client_id' => ${clientId}, 'booked_by' => ${clientId}, 'booked_by_type' => 'client',
            'title' => '${title}', 'status' => '${status}',
            'arrival_at' => \\Carbon\\CarbonImmutable::parse('${tomorrow()} 10:00', 'Asia/Manila')->utc(),
        ])->save(); return $s->id; })()`,
    );

const scheduleRow = (id: number): Record<string, unknown> =>
    php<Record<string, unknown>>(
        `App\\Models\\Schedule::query()->find(${id})->only(['status', 'change_reason', 'cancel_reason', 'approved_by'])`,
    );

/** Open the visit named `title` from the week, a week on if tomorrow is past it. */
const openVisit = async (page: Page, title: string): Promise<void> => {
    const block = page.getByRole('button', {
        name: new RegExp(`^${title},`),
    });

    if (!(await block.first().isVisible())) {
        await page
            .getByRole('button', { name: /^Go to next (week|day)$/ })
            .click();
    }

    await block.first().click();
    await expect(page.getByRole('dialog')).toContainText(title);
};

/** Sign in as one of DemoAccountsSeeder's role accounts, in a fresh context. */
const signInAs = async (browser: Browser, email: string): Promise<Page> => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));

    return page;
};

test('51 · a client updates their own booking on the Book an appointment page, with a reason that shows on it', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const title = `Update ${Date.now()}`;
    const id = appointment(clientAId, title);
    const { page } = await actors.open('client');

    await page.goto('/dashboard/profile/schedule');
    await openVisit(page, title);
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Update', exact: true })
        .click();

    // The booking page, filled in with the appointment, asking for a reason.
    await expect(page).toHaveURL(
        new RegExp(`/dashboard/profile/schedule/${id}/edit$`),
    );
    await expect(
        page.getByRole('heading', { name: 'Update appointment' }),
    ).toBeVisible();
    await expect(page.locator('#purpose')).toHaveValue(title);

    await page.getByRole('button', { name: 'Update appointment' }).click();
    await expect(
        page.getByText('Say why the appointment is being updated'),
    ).toBeVisible();

    await page.locator('#arrivalTime').click();
    await page.getByRole('option', { name: '2:00 PM' }).click();
    await page.getByLabel(/^Reason for change/).fill('Running late from work');
    await page.getByRole('button', { name: 'Update appointment' }).click();

    await expect(
        page.getByRole('heading', { name: 'Appointment updated' }),
    ).toBeVisible();
    expect(scheduleRow(id)).toMatchObject({
        status: 'pending',
        change_reason: 'Running late from work',
    });

    await page.getByRole('link', { name: 'Back to schedule' }).click();
    await openVisit(page, title);
    await expect(page.getByTestId('schedule-change-reason')).toContainText(
        'Running late from work',
    );
});

test("52 · a client cancels their own booking with a reason, and cannot touch another client's", async ({
    actors,
}) => {
    const { clientAId, clientBId } = inquiryFixtures();
    const title = `Cancel ${Date.now()}`;
    const id = appointment(clientAId, title);
    const othersId = appointment(clientBId, `Not yours ${Date.now()}`);
    const { page } = await actors.open('client');

    await page.goto('/dashboard/profile/schedule');
    await openVisit(page, title);
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Cancel', exact: true })
        .click();

    const cancel = page.getByRole('dialog', { name: 'Cancel appointment' });
    await cancel
        .getByLabel(/^Reason for cancelling/)
        .fill('Found another supplier');
    await cancel.getByRole('button', { name: 'Cancel appointment' }).click();

    await expect(page.getByText('Appointment cancelled')).toBeVisible();
    expect(scheduleRow(id)).toMatchObject({
        status: 'cancelled',
        cancel_reason: 'Found another supplier',
    });

    // Another client's booking is refused, however the request is made.
    const cookies = await page.context().cookies();
    const token = decodeURIComponent(
        cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '',
    );
    const forged = await page.request.post(
        `/dashboard/schedule/${othersId}/cancel`,
        {
            data: { reason: 'Not mine to cancel' },
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            maxRedirects: 0,
        },
    );

    expect(forged.status()).toBe(403);
    expect(scheduleRow(othersId)).toMatchObject({ status: 'pending' });
});

test('53 · marketing approves a pending booking; owner and technical see no button to change one', async ({
    actors,
    browser,
}) => {
    const { clientAId } = inquiryFixtures();
    const title = `Approve ${Date.now()}`;
    const id = appointment(clientAId, title);

    for (const email of ['owner@example.com', 'technical@example.com']) {
        const page = await signInAs(browser, email);

        await page.goto('/dashboard/schedule');
        await openVisit(page, title);

        const dialog = page.getByRole('dialog');
        for (const name of ['Approve', 'Update', 'Cancel']) {
            await expect(
                dialog.getByRole('button', { name, exact: true }),
            ).toHaveCount(0);
        }

        await page.context().close();
    }

    const { page } = await actors.open('marketing');

    await page.goto('/dashboard/schedule');
    await openVisit(page, title);
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Approve' })
        .click();
    await page
        .getByRole('dialog', { name: 'Approve appointment' })
        .getByRole('button', { name: 'Approve' })
        .click();

    await expect(page.getByText('Appointment approved')).toBeVisible();
    expect(scheduleRow(id)).toMatchObject({ status: 'confirmed' });
    expect(scheduleRow(id).approved_by).not.toBeNull();

    // Approved, it offers Update and Cancel but no second approval.
    await openVisit(page, title);
    await expect(
        page.getByRole('dialog').getByRole('button', { name: 'Approve' }),
    ).toHaveCount(0);
    await expect(
        page
            .getByRole('dialog')
            .getByRole('button', { name: 'Update', exact: true }),
    ).toBeVisible();
});

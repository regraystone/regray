import type { Browser, Page } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, php, test } from '../support/fixtures';

/*
 * N. Changing an appointment after it is booked: a client updates and cancels
 * their own, marketing assigns one to sales and sales confirms it, and the
 * roles that only watch the week see no button. Each test makes its own appointment for tomorrow at 10:00 in
 * Manila, with a title no other test uses.
 */

/** Tomorrow, `YYYY-MM-DD`, by the studio's clock. */
const tomorrow = (): string =>
    php<string>("now('Asia/Manila')->addDay()->toDateString()");

/** A booking for the client, tomorrow at 10:00, and its id: their own, or `bookedBy` staff's. */
const appointment = (
    clientId: number,
    title: string,
    status: 'pending' | 'confirmed' = 'pending',
    bookedBy: number | null = null,
): number =>
    php<number>(
        `(function () { $s = new App\\Models\\Schedule; $s->forceFill([
            'client_id' => ${clientId}, 'booked_by' => ${bookedBy ?? clientId}, 'booked_by_type' => '${bookedBy === null ? 'client' : 'staff'}',
            'title' => '${title}', 'status' => '${status}',
            'arrival_at' => \\Carbon\\CarbonImmutable::parse('${tomorrow()} 10:00', 'Asia/Manila')->utc(),
        ])->save(); return $s->id; })()`,
    );

const scheduleRow = (id: number): Record<string, unknown> =>
    php<Record<string, unknown>>(
        `App\\Models\\Schedule::query()->find(${id})->only(['status', 'change_reason', 'cancel_reason', 'approved_by', 'assigned_to'])`,
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

test('53 · marketing assigns a booking to sales, who confirms it; the owner sees no button and technical not the visit', async ({
    actors,
    browser,
}) => {
    const { clientAId, users } = inquiryFixtures();
    const userId = (email: string): number =>
        php<number>(
            `App\\Models\\User::query()->where('email', '${email}')->value('id')`,
        );
    const marketingId = userId(users.marketing);
    const salesId = userId(users.sales);
    const salesName = php<string>(
        `App\\Models\\User::query()->find(${salesId})->name`,
    );
    const title = `Assign ${Date.now()}`;
    // Booked by marketing, so the marketing account reads it whoever holds the client's lead.
    const id = appointment(clientAId, title, 'pending', marketingId);

    const owner = await signInAs(browser, 'owner@example.com');
    await owner.goto('/dashboard/schedule');
    await openVisit(owner, title);
    for (const name of [
        'Assign to sales',
        'Confirm',
        'Decline',
        'Update',
        'Cancel',
    ]) {
        await expect(
            owner
                .getByRole('dialog')
                .getByRole('button', { name, exact: true }),
        ).toHaveCount(0);
    }
    await owner.context().close();

    const technical = await signInAs(browser, 'technical@example.com');
    await technical.goto('/dashboard/schedule');
    await expect(
        technical.getByRole('button', { name: new RegExp(`^${title},`) }),
    ).toHaveCount(0);
    await technical.context().close();

    const { page } = await actors.open('marketing');

    await page.goto('/dashboard/schedule');
    await openVisit(page, title);
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Assign to sales' })
        .click();

    const assign = page.getByRole('dialog', { name: 'Assign to sales' });
    await assign.getByRole('button', { name: 'Assign', exact: true }).click();
    await expect(
        assign.getByText('Choose a sales member to confirm this appointment'),
    ).toBeVisible();
    await assign.getByLabel('Search sales members').fill(salesName);
    await assign
        .getByRole('button', { name: new RegExp(`^${salesName}`) })
        .first()
        .click();
    await assign.getByRole('button', { name: 'Assign', exact: true }).click();

    await expect(page.getByText(`Assigned to ${salesName}`)).toBeVisible();
    expect(scheduleRow(id)).toMatchObject({
        status: 'awaiting_sales',
        assigned_to: salesId,
        approved_by: null,
    });

    // Sales books nothing and changes nothing: it confirms what it is assigned.
    const sales = (await actors.open('sales')).page;

    await sales.goto('/dashboard/schedule');
    await openVisit(sales, title);
    const dialog = sales.getByRole('dialog');
    await expect(dialog.getByTestId('schedule-assigned-sales')).toContainText(
        salesName,
    );
    for (const name of ['Update', 'Cancel', 'Assign to sales']) {
        await expect(
            dialog.getByRole('button', { name, exact: true }),
        ).toHaveCount(0);
    }
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await sales
        .getByRole('dialog', { name: 'Confirm appointment' })
        .getByRole('button', { name: 'Confirm', exact: true })
        .click();

    await expect(sales.getByText('Appointment confirmed')).toBeVisible();
    expect(scheduleRow(id)).toMatchObject({
        status: 'confirmed',
        approved_by: salesId,
    });

    // Confirmed, it offers no second confirmation.
    await openVisit(sales, title);
    await expect(
        sales
            .getByRole('dialog')
            .getByRole('button', { name: 'Confirm', exact: true }),
    ).toHaveCount(0);
});

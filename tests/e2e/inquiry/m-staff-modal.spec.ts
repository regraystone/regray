import { expect, test } from '../support/fixtures';

/*
 * M. The staff Inquire modal: Platform or Direct (walk-in), staff only.
 * Demo seed: Lucas Moreau has marketing's idle lead LEAD-0007, and Carmela
 * Reyes is a direct client with carmela@reyesbakeshop.example.
 */

test('47 · staff are asked Platform or Direct when they press Inquire; Platform goes on to the account and its lead', async ({
    actors,
}) => {
    const { page } = await actors.open('marketing');

    await page.goto('/dashboard/inquiry');
    await page.getByRole('button', { name: 'Add inquiry' }).click();

    const dialog = page.getByTestId('staff-inquire-dialog');
    await expect(dialog.locator('[data-staff-inquire-kind]')).toHaveCount(2);
    await expect(dialog).toContainText('The client has a Regray account.');
    await expect(dialog).toContainText('The client has no account.');

    await dialog.locator('[data-staff-inquire-kind="platform"]').click();
    await page
        .getByRole('searchbox', { name: 'Search client accounts' })
        .fill('Lucas');
    await page
        .getByTestId('client-account-picker')
        .getByRole('button')
        .first()
        .click();
    await page
        .getByTestId('inquiry-lead-picker')
        .getByRole('button')
        .first()
        .click();

    await expect(
        page.getByRole('heading', { name: 'What do you need?' }),
    ).toBeVisible();
    await expect(page.getByTestId('inquire-target')).toContainText('LEAD-0007');
});

test("48 · a client's Inquire goes straight to the stepper, never through the modal", async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await page.goto('/dashboard/quote');
    await page
        .getByRole('button', { name: /Inquire/ })
        .first()
        .click();

    await expect(
        page.getByRole('heading', { name: 'What do you need?' }),
    ).toBeVisible();
    await expect(page.getByTestId('staff-inquire-dialog')).toHaveCount(0);
});

test('49 · the Direct branch offers the client already on file instead of a second one', async ({
    actors,
}) => {
    const { page } = await actors.open('sales');

    await page.goto('/dashboard/inquiry/inquire?kind=direct');
    await page.locator('#direct-full-name').fill('Carmela R.');
    // The phone number is required, and this one is already on file.
    await page
        .getByRole('textbox', { name: 'Phone number' })
        .fill('+63 917 555 0142');
    await page.getByRole('button', { name: 'Continue' }).click();

    await expect(page.getByTestId('direct-client-duplicate')).toContainText(
        'Carmela Reyes',
    );
    await page.getByRole('button', { name: /Use Carmela Reyes/ }).click();

    await expect(
        page.getByRole('heading', { name: 'What do you need?' }),
    ).toBeVisible();
    await expect(page.getByTestId('inquire-target')).toContainText(
        'Direct (walk-in)',
    );
});

test('49b · marketing names the sales owner of a walk-in inquiry before the stepper', async ({
    actors,
}) => {
    const { page } = await actors.open('marketing');

    await page.goto('/dashboard/inquiry/inquire?kind=direct');
    await page.locator('#direct-full-name').fill('Carmela R.');
    await page
        .getByRole('textbox', { name: 'Phone number' })
        .fill('+63 917 555 0142');
    await page.getByRole('button', { name: 'Continue' }).click();
    await page.getByRole('button', { name: /Use Carmela Reyes/ }).click();

    const owners = page.getByTestId('walk-in-owner-picker');

    await expect(owners).toBeVisible();

    const owner = owners.getByRole('button').first();
    const ownerName = (await owner.innerText()).trim();

    await owner.click();

    await expect(
        page.getByRole('heading', { name: 'What do you need?' }),
    ).toBeVisible();
    await expect(page.getByTestId('inquire-target')).toContainText(
        `Owner: ${ownerName}`,
    );
});

test('50 · Back from the Platform or Direct screen returns to the Inquiry list, without asking again', async ({
    actors,
}) => {
    const { page } = await actors.open('marketing');

    for (const kind of ['platform', 'direct']) {
        await page.goto('/dashboard/inquiry');
        await page.getByRole('button', { name: 'Add inquiry' }).click();
        await page
            .getByTestId('staff-inquire-dialog')
            .locator(`[data-staff-inquire-kind="${kind}"]`)
            .click();
        await page.getByRole('button', { name: 'Back', exact: true }).click();

        await expect(page).toHaveURL(/\/inquiry$/);
        await expect(page.getByTestId('staff-inquire-dialog')).toHaveCount(0);
    }
});

import { api, expect, fixtures, shot, test } from './support/fixtures';

/* A. Access and roles (R2, R6, §6) */

test('1 · a client with no inquiry sees "Regray Team" first, empty, with the empty-state text', async ({
    sessions,
}, testInfo) => {
    const { page } = await sessions.open('freshClient');

    await page.goto('/dashboard/inbox');

    const names = page.getByTestId('conversation-name');
    await expect(names.first()).toHaveText('Regray Team');

    await page.getByTestId('conversation-row').first().click();
    await expect(page.getByTestId('thread-title')).toHaveText('Regray Team');
    await expect(page.getByTestId('thread-empty')).toBeVisible();
    await expect(page.getByTestId('thread-empty')).not.toBeEmpty();
    await expect(page.getByTestId('message-bubble')).toHaveCount(0);
    await shot(page, '01-fresh-client', testInfo.project.name);
});

test('2 · marketing sees "New group"; sales does not, and POST /dashboard/inbox/groups is 403 for sales', async ({
    sessions,
}) => {
    const marketing = await sessions.open('marketingA');
    await marketing.page.goto('/dashboard/inbox');
    await expect(
        marketing.page.getByRole('button', { name: 'New group' }),
    ).toBeVisible();

    const sales = await sessions.open('sales');
    sessions.allowConsole(/status of 403/);
    await sales.page.goto('/dashboard/inbox');
    await expect(sales.page.getByTestId('conversation-list')).toBeVisible();
    await expect(
        sales.page.getByRole('button', { name: 'New group' }),
    ).toHaveCount(0);

    const response = await api(sales.page, 'POST', '/dashboard/inbox/groups', {
        name: 'Not allowed',
        staffIds: [1],
        clientId: 1,
    });
    expect(response.status).toBe(403);
});

test("3 · a client cannot open another client's Regray Team thread (403)", async ({
    sessions,
}) => {
    const other = fixtures().support.forbidden;
    const { page } = await sessions.open('client');
    sessions.allowConsole(/status of 403/);
    await page.goto('/dashboard/inbox');

    const response = await api(
        page,
        'GET',
        `/dashboard/inbox/${other.conversationId}/messages`,
    );
    expect(response.status).toBe(403);
    expect(response.json?.messages).toBeUndefined();

    const navigation = await page.request.get(
        `/dashboard/inbox/${other.conversationId}/messages`,
        {
            headers: { Accept: 'application/json' },
        },
    );
    expect(navigation.status()).toBe(403);
});

test('4 · marketing creates a group of two staff and one client; it reaches both lists; a client in the staff list is rejected', async ({
    sessions,
}) => {
    const creator = await sessions.open('marketingA');
    const { page } = creator;
    await page.goto('/dashboard/inbox');

    await page.getByRole('button', { name: 'New group' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Group name').fill('E2E Created Group');

    const people = dialog.getByLabel('Search people');
    await people.fill('Sales');
    await dialog.getByRole('checkbox', { name: 'Sales', exact: true }).check();
    await people.fill('Marketing B');
    await dialog
        .getByRole('checkbox', { name: 'Marketing B', exact: true })
        .check();
    await people.fill('Client');
    await dialog.getByRole('radio', { name: 'Client', exact: true }).check();

    const created = page.waitForResponse(
        (response) =>
            response.url().endsWith('/dashboard/inbox/groups') &&
            response.request().method() === 'POST',
    );
    await dialog.getByRole('button', { name: 'Create group' }).click();
    expect((await created).status()).toBe(201);

    await expect(
        page
            .getByTestId('conversation-name')
            .filter({ hasText: 'E2E Created Group' }),
    ).toHaveCount(1);

    const client = await sessions.open('client');
    await client.page.goto('/dashboard/inbox');
    await expect(
        client.page
            .getByTestId('conversation-name')
            .filter({ hasText: 'E2E Created Group' }),
    ).toHaveCount(1);

    sessions.allowConsole(/status of 422/);
    const clientRow = await api(
        page,
        'GET',
        '/dashboard/inbox/people?search=Card%20Client',
    );
    const clients = (clientRow.json?.clients ?? []) as Array<{
        id: number;
        name: string;
    }>;
    const salesRow = await api(
        page,
        'GET',
        '/dashboard/inbox/people?search=Sales',
    );
    const staff = (salesRow.json?.staff ?? []) as Array<{
        id: number;
        name: string;
    }>;
    expect(clients.length).toBeGreaterThan(0);

    const rejected = await api(page, 'POST', '/dashboard/inbox/groups', {
        name: 'Client as staff',
        staffIds: [clients[0].id],
        clientId: clients[0].id,
    });
    expect(rejected.status).toBe(422);
    expect(staff.some((person) => person.id === clients[0].id)).toBe(false);
});

import type { Page } from '@playwright/test';
import {
    api,
    expect,
    fixtures,
    openConversation,
    test,
} from './support/fixtures';

/*
 * E. Assignment (§5, §6). Each scenario works on its own client's Regray Team
 * thread (E2EMessageSeeder::seedSupportClients), so no claim leaks into the
 * next scenario. Marketing A is "Marketing", marketing B is "Marketing B".
 */

const A_NAME = 'Marketing';

/** Narrow a staff list to one client's thread, so it is on the first page. */
const searchList = async (page: Page, name: string): Promise<void> => {
    await page.goto('/dashboard/inbox');
    const searched = page.waitForResponse(
        (response) =>
            response.url().includes('/dashboard/inbox') &&
            new URL(response.url()).searchParams.get('search') === name,
    );
    await page.getByLabel('Search conversations').fill(name);
    await searched;
};

const rowNamed = (page: Page, name: string) =>
    page.getByTestId('conversation-row').filter({
        has: page
            .getByTestId('conversation-name')
            .getByText(name, { exact: true }),
    });

/**
 * A thread that is open when someone else claims it learns so from one 403 on
 * its catch-up (and on a read receipt already in flight), then closes. That
 * 403 is expected; a second one on the same endpoint is the old loop (BUG-4).
 */
const LOST_ACCESS_403 =
    /status of 403 \(Forbidden\) \[[^\]]*\/dashboard\/inbox\/\d+\/(read|messages)/;

const countLostAccess = (page: Page): (() => Record<string, number>) => {
    const counts: Record<string, number> = {};

    page.on('response', (response) => {
        if (
            response.status() === 403 &&
            /\/dashboard\/inbox\/\d+\/(read|messages)/.test(response.url())
        ) {
            const path = new URL(response.url()).pathname;
            counts[path] = (counts[path] ?? 0) + 1;
        }
    });

    return () => counts;
};

const expectAtMostOneEach = (counts: Record<string, number>): void => {
    for (const [path, count] of Object.entries(counts)) {
        expect(count, `403s on ${path}`).toBeLessThanOrEqual(1);
    }
};

const claimResponse = (page: Page) =>
    page.waitForResponse(
        (response) =>
            /\/dashboard\/inbox\/\d+\/claim$/.test(response.url()) &&
            response.request().method() === 'POST',
    );

test('18 · both marketing users see the "Assign to me" card; the client never does', async ({
    sessions,
}) => {
    const thread = fixtures().support.claim;

    for (const actor of ['marketingA', 'marketingB'] as const) {
        const { page } = await sessions.open(actor);
        await openConversation(page, thread.conversationId);
        await expect(page.getByTestId('assignment-card')).toBeVisible();
        await expect(
            page
                .getByTestId('assignment-card')
                .getByRole('button', { name: 'Assign to me' }),
        ).toBeVisible();
    }

    const client = await sessions.open({ email: thread.email });
    await client.page.goto('/dashboard/inbox?conversation=support');
    await expect(client.page.getByTestId('thread-title')).toHaveText('Regray');
    await expect(client.page.getByTestId('message-bubble')).toHaveCount(1);
    await expect(client.page.getByTestId('assignment-card')).toHaveCount(0);
    await expect(client.page.getByText('Assign to me')).toHaveCount(0);

    const payload = await api(
        client.page,
        'GET',
        `/dashboard/inbox/${thread.conversationId}/messages`,
    );
    expect(payload.status).toBe(200);
    expect(payload.json?.assignment).toBeNull();
});

test('19 · A claims: A can write, and B loses the card and the row without a reload', async ({
    sessions,
}) => {
    const thread = fixtures().support.claim;
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    sessions.allowConsole(LOST_ACCESS_403);
    const lostAccess = countLostAccess(b.page);

    await searchList(b.page, thread.name);
    await rowNamed(b.page, thread.name).click();
    await expect(b.page.getByTestId('assignment-card')).toBeVisible();

    await openConversation(a.page, thread.conversationId);
    await expect(a.page.getByTestId('composer-input')).toBeDisabled();
    const claimed = claimResponse(a.page);
    await a.page
        .getByTestId('assignment-card')
        .getByRole('button', { name: 'Assign to me' })
        .click();
    expect((await claimed).status()).toBe(200);

    await expect(a.page.getByTestId('composer-input')).toBeEnabled();
    await expect(a.page.getByTestId('assignment-card')).toHaveCount(0);

    const bCard = b.page.getByTestId('assignment-card');
    await expect(bCard).toHaveCount(0);
    await expect(rowNamed(b.page, thread.name)).toHaveCount(0);
    expectAtMostOneEach(lostAccess());
});

test('20 · the client sees "Handled by {A}" and the claim message without a reload', async ({
    sessions,
}) => {
    const thread = fixtures().support.names;
    const client = await sessions.open({ email: thread.email });
    await client.page.goto('/dashboard/inbox?conversation=support');
    await expect(client.page.getByTestId('thread-title')).toHaveText('Regray');
    await expect(client.page.getByTestId('system-event')).toHaveCount(0);

    const a = await sessions.open('marketingA');
    await openConversation(a.page, thread.conversationId);
    const claimed = claimResponse(a.page);
    await a.page
        .getByTestId('assignment-card')
        .getByRole('button', { name: 'Assign to me' })
        .click();
    expect((await claimed).status()).toBe(200);

    await expect(client.page.getByTestId('thread-subtitle')).toHaveText(
        `Handled by ${A_NAME}`,
    );
    await expect(client.page.getByTestId('system-event')).toContainText(
        `${A_NAME} is now handling this conversation`,
    );
});

test("21 · no staff name shows before the claim; after it, A's messages carry A's name", async ({
    sessions,
}) => {
    const thread = fixtures().support.forbidden;
    const client = await sessions.open({ email: thread.email });
    await client.page.goto('/dashboard/inbox?conversation=support');
    await expect(client.page.getByTestId('message-bubble')).toHaveCount(1);
    await expect(client.page.getByTestId('sender-name')).toHaveCount(0);

    const a = await sessions.open('marketingA');
    await openConversation(a.page, thread.conversationId);
    await a.page
        .getByTestId('assignment-card')
        .getByRole('button', { name: 'Assign to me' })
        .click();
    await a.page.getByTestId('composer-input').fill('Hello, this is A');
    await a.page.getByRole('button', { name: 'Send message' }).click();

    const bubble = client.page
        .getByTestId('message-bubble')
        .filter({ hasText: 'Hello, this is A' });
    await expect(bubble).toHaveCount(1);
    await expect(client.page.getByTestId('sender-name')).toHaveText([A_NAME]);
});

test('22 · after A claims, B gets 409 "{A} is handling this conversation" on send and 403 on read', async ({
    sessions,
}) => {
    const thread = fixtures().support.claim;
    const b = await sessions.open('marketingB');
    sessions.allowConsole(/status of (403|409)/);
    await b.page.goto('/dashboard/inbox');

    const send = await api(
        b.page,
        'POST',
        `/dashboard/inbox/${thread.conversationId}/messages`,
        {
            body: 'Can I help?',
            clientKey: crypto.randomUUID(),
        },
    );
    const read = await api(
        b.page,
        'GET',
        `/dashboard/inbox/${thread.conversationId}/messages`,
    );

    expect(read.status).toBe(403);
    expect({ status: send.status, message: send.json?.message }).toEqual({
        status: 409,
        message: `${A_NAME} is handling this conversation.`,
    });
});

test('23 · two simultaneous claims: exactly one wins and the other is told it was taken', async ({
    sessions,
}) => {
    const thread = fixtures().support.race;
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    sessions.allowConsole(/status of 409/);
    sessions.allowConsole(LOST_ACCESS_403);
    const lostAccess = [countLostAccess(a.page), countLostAccess(b.page)];
    await openConversation(a.page, thread.conversationId);
    await openConversation(b.page, thread.conversationId);

    const buttons = [a, b].map((session) =>
        session.page
            .getByTestId('assignment-card')
            .getByRole('button', { name: 'Assign to me' }),
    );
    await Promise.all(buttons.map((button) => expect(button).toBeEnabled()));

    const responses = Promise.all([
        claimResponse(a.page),
        claimResponse(b.page),
    ]);
    await Promise.all(buttons.map((button) => button.click()));
    const statuses = (await responses).map((response) => response.status());

    expect([...statuses].sort((left, right) => left - right)).toEqual([
        200, 409,
    ]);

    const loser = statuses[0] === 409 ? a : b;
    const winnerName = loser === a ? 'Marketing B' : A_NAME;
    await expect(
        loser.page.getByText(`${winnerName} already took this conversation.`),
    ).toBeVisible();
    await expect(loser.page.getByTestId('composer-input')).toBeDisabled();
    lostAccess.forEach((counts) => expectAtMostOneEach(counts()));
});

test('24 · A releases: the thread is back in both marketing lists without a reload', async ({
    sessions,
}) => {
    const thread = fixtures().support.release;
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');

    await searchList(b.page, thread.name);
    await expect(rowNamed(b.page, thread.name)).toHaveCount(1);

    await openConversation(a.page, thread.conversationId);
    await a.page
        .getByTestId('assignment-card')
        .getByRole('button', { name: 'Assign to me' })
        .click();
    await expect(a.page.getByTestId('composer-input')).toBeEnabled();
    await expect(rowNamed(b.page, thread.name)).toHaveCount(0);

    const released = a.page.waitForResponse((response) =>
        response.url().endsWith('/release'),
    );
    await a.page.getByRole('button', { name: 'Assignment' }).click();
    await a.page.getByRole('menuitem', { name: 'Release to the team' }).click();
    expect((await released).status()).toBe(200);

    await expect(rowNamed(b.page, thread.name)).toHaveCount(1);
    await expect(rowNamed(a.page, thread.name)).toHaveCount(1);
    await expect(a.page.getByTestId('assignment-card')).toBeVisible();
});

test('25 · the admin reassigns to B: B gets the thread and A loses access', async ({
    sessions,
}) => {
    const thread = fixtures().support.reassign;
    const a = await sessions.open('marketingA');
    await openConversation(a.page, thread.conversationId);
    await a.page
        .getByTestId('assignment-card')
        .getByRole('button', { name: 'Assign to me' })
        .click();
    await expect(a.page.getByTestId('composer-input')).toBeEnabled();

    const b = await sessions.open('marketingB');
    await searchList(b.page, thread.name);
    await expect(rowNamed(b.page, thread.name)).toHaveCount(0);

    const admin = await sessions.open('admin');
    await openConversation(admin.page, thread.conversationId);
    await admin.page.getByRole('button', { name: 'Assignment' }).click();
    await admin.page.getByRole('menuitem', { name: 'Reassign…' }).click();
    const dialog = admin.page.getByRole('dialog');
    await dialog.getByLabel('Search staff').fill('Marketing B');
    await dialog.getByRole('radio', { name: 'Marketing B' }).check();
    const reassigned = admin.page.waitForResponse((response) =>
        response.url().endsWith('/reassign'),
    );
    await dialog.getByRole('button', { name: 'Reassign', exact: true }).click();
    expect((await reassigned).status()).toBe(200);

    await expect(rowNamed(b.page, thread.name)).toHaveCount(1);
    await rowNamed(b.page, thread.name).click();
    await expect(b.page.getByTestId('composer-input')).toBeEnabled();

    sessions.allowConsole(/status of 403/);
    const read = await api(
        a.page,
        'GET',
        `/dashboard/inbox/${thread.conversationId}/messages`,
    );
    expect(read.status).toBe(403);
});

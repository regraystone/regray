import type { Page } from '@playwright/test';
import {
    api,
    expect,
    fixtures,
    openConversation,
    test,
} from './support/fixtures';

/*
 * F. Messaging and real-time (R10, §4). Marketing A ("Marketing"), marketing B
 * ("Marketing B") and sales share "E2E Chat"; A and B share "E2E Scroll".
 */

const A_NAME = 'Marketing';
const B_NAME = 'Marketing B';

const unique = (label: string): string =>
    `${label} ${Date.now()}-${Math.floor(Math.random() * 1000)}`;

const sendResponse = (page: Page) =>
    page.waitForResponse(
        (response) =>
            /\/message\/\d+\/messages$/.test(response.url()) &&
            response.request().method() === 'POST',
    );

/** Type into the composer and press the send button; resolve when the server answered. */
const send = async (page: Page, text: string): Promise<void> => {
    await page.getByTestId('composer-input').fill(text);
    const sent = sendResponse(page);
    await page.getByRole('button', { name: 'Send message' }).click();
    expect((await sent).ok()).toBe(true);
};

const bubble = (page: Page, text: string) =>
    page.getByTestId('message-bubble').filter({ hasText: text });

/** Resolves when the page has been authorized to join `channel`. */
const channelAuthorized = (page: Page, channel: string) =>
    page.waitForResponse(
        (response) =>
            response.url().endsWith('/broadcasting/auth') &&
            (response.request().postData() ?? '').includes(
                encodeURIComponent(channel),
            ),
    );

/** Post a message through the endpoint, as another member would. */
const postAs = (
    page: Page,
    conversationId: number,
    body: string,
    clientKey = crypto.randomUUID(),
) =>
    api(page, 'POST', `/message/${conversationId}/messages`, {
        body,
        clientKey,
    });

test('26 · a message from A reaches B, who has the thread open, within 3 seconds and without a reload', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);

    const text = unique('Live from A');
    // A marker on B's window survives anything but a full reload.
    await b.page.evaluate(
        () => ((window as unknown as { __e2eMarker: number }).__e2eMarker = 26),
    );

    await send(a.page, text);
    await expect(bubble(b.page, text)).toBeVisible({ timeout: 3_000 });
    expect(
        await b.page.evaluate(
            () => (window as unknown as { __e2eMarker?: number }).__e2eMarker,
        ),
    ).toBe(26);
});

test('27 · B sees "{A} is typing…" while A types, and it goes about 4 seconds after A stops; A does not see it', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    const channel = `private-conversation.${chat.conversationId}`;
    const aJoined = channelAuthorized(a.page, channel);
    const bJoined = channelAuthorized(b.page, channel);
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);
    await Promise.all([aJoined, bJoined]);

    // About two seconds of typing, so more than one throttled signal goes out.
    await a.page
        .getByTestId('composer-input')
        .pressSequentially('Typing a thought here', { delay: 100 });
    const stoppedAt = Date.now();

    const indicator = b.page.getByTestId('typing-indicator');
    await expect(indicator).toContainText(`${A_NAME} is typing`);
    await expect(a.page.getByTestId('typing-indicator')).toHaveCount(0);

    await expect(indicator).toHaveCount(0, { timeout: 10_000 });
    const goneAfter = Date.now() - stoppedAt;

    expect(goneAfter).toBeGreaterThanOrEqual(3_000);
    expect(goneAfter).toBeLessThanOrEqual(6_000);
});

test('28 · receipts: A sees "Seen" once B opens the thread; in a direct chat the client sees "Delivered" before "Seen"', async ({
    sessions,
}) => {
    const { chat, direct } = fixtures();
    const a = await sessions.open('marketingA');
    await openConversation(a.page, chat.conversationId);

    const text = unique('Read me');
    await send(a.page, text);
    await expect(a.page.getByTestId('receipt')).toHaveText('Sent');

    const b = await sessions.open('marketingB');
    await openConversation(b.page, chat.conversationId);
    await expect(bubble(b.page, text)).toBeVisible();
    await expect(a.page.getByTestId('receipt')).toHaveAttribute(
        'aria-label',
        new RegExp(`^Seen by .*${B_NAME}`),
    );

    // Direct chat: A is online on the list, the client writes to A.
    const recipient = await sessions.open('marketingA');
    await recipient.page.goto('/message');
    await expect(recipient.page.getByTestId('conversation-list')).toBeVisible();

    const client = await sessions.open('directClient');
    await openConversation(client.page, direct.conversationId);
    await send(client.page, unique('Hello A'));
    await expect(client.page.getByTestId('receipt')).toHaveText('Delivered');

    await openConversation(recipient.page, direct.conversationId);
    await expect(client.page.getByTestId('receipt')).toHaveText('Seen');
});

test('29 · a reply quotes the original, and tapping the quote scrolls back to it', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await b.page.goto('/message');

    const original = unique('Original');
    await postAs(b.page, chat.conversationId, original);

    for (let index = 1; index <= 15; index++) {
        await postAs(b.page, chat.conversationId, `Filler ${index}`);
    }

    await openConversation(a.page, chat.conversationId);
    const target = bubble(a.page, original).filter({
        hasNot: a.page.getByTestId('reply-quote'),
    });
    await target.scrollIntoViewIfNeeded();
    await target.hover();
    await target.getByRole('button', { name: 'Reply' }).click();
    await expect(a.page.getByTestId('reply-bar')).toContainText(original);

    const reply = unique('My reply');
    await send(a.page, reply);

    const quote = bubble(a.page, reply).getByTestId('reply-quote');
    await expect(quote).toContainText(original);
    await expect(quote).toContainText(B_NAME);
    await expect(target).not.toBeInViewport();

    await quote.click();
    await expect(target).toBeInViewport();
});

test('30 · editing one\'s own text shows "Edited" to both; another person\'s message has no Edit', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);

    const text = unique('Edit me');
    const edited = unique('Edited text');
    await send(a.page, text);

    await bubble(a.page, text).hover();
    await bubble(a.page, text).getByRole('button', { name: 'Edit' }).click();
    await expect(a.page.getByTestId('editing-bar')).toBeVisible();
    await a.page.getByTestId('composer-input').fill(edited);
    await a.page.getByRole('button', { name: 'Save edit' }).click();

    for (const page of [a.page, b.page]) {
        await expect(bubble(page, edited)).toBeVisible();
        await expect(
            bubble(page, edited).getByTestId('edited-label'),
        ).toHaveText('Edited');
    }

    await bubble(b.page, edited).hover();
    await expect(
        bubble(b.page, edited).getByRole('button', { name: 'Reply' }),
    ).toBeVisible();
    await expect(
        bubble(b.page, edited).getByRole('button', { name: 'Edit' }),
    ).toHaveCount(0);
    await expect(
        bubble(b.page, edited).getByRole('button', { name: 'Remove' }),
    ).toHaveCount(0);
});

test('31 · removing a message, after confirming, shows "This message was removed" to both', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);

    const text = unique('Remove me');
    await send(a.page, text);
    await expect(bubble(b.page, text)).toBeVisible();

    const ownBubble = bubble(a.page, text);
    const messageId = await ownBubble.evaluate((node) =>
        node.closest('[data-message-id]')?.getAttribute('data-message-id'),
    );
    await ownBubble.hover();
    await ownBubble.getByRole('button', { name: 'Remove' }).click();
    const dialog = a.page.getByRole('dialog');
    await expect(dialog).toContainText('Remove this message?');
    await dialog.getByRole('button', { name: 'Remove', exact: true }).click();

    for (const page of [a.page, b.page]) {
        const removed = page
            .locator(`[data-message-id="${messageId}"]`)
            .getByTestId('message-bubble');
        await expect(removed).toHaveText('This message was removed');
        await expect(page.getByText(text)).toHaveCount(0);
    }
});

test('32 · hovering a bubble shows its toolbar and full date and time; keyboard focus does too', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const { page } = await sessions.open('marketingA');
    await openConversation(page, chat.conversationId);

    const text = unique('Hover me');
    await send(page, text);
    const target = bubble(page, text);
    // The toolbar is always in the DOM and shown by opacity.
    const toolbar = target.getByTestId('message-toolbar');
    await page.getByTestId('composer-input').hover();
    await expect(toolbar).toHaveCSS('opacity', '0');

    await target.hover();
    await expect(toolbar).toHaveCSS('opacity', '1');
    await expect(target.getByTestId('message-timestamp')).toHaveText(
        /^[A-Z][a-z]{2} .+, \d{2}:\d{2}$/,
    );

    await page.getByTestId('composer-input').hover();
    await expect(toolbar).toHaveCSS('opacity', '0');

    await page.getByTestId('composer-input').focus();
    await page.keyboard.press('Shift+Tab');
    await target.focus();
    await expect(target).toBeFocused();
    await expect(toolbar).toHaveCSS('opacity', '1');
});

test('33 · press and hold opens the action sheet; moving more than 10px cancels; no native menu @mobile', async ({
    sessions,
}) => {
    // Touches are sent with CDP Input.dispatchTouchEvent; the page's clock is
    // paused and stepped, so "hold for 450ms" is exact and needs no sleep.
    const { chat } = fixtures();
    const { page } = await sessions.open('marketingA');
    await page.clock.install();
    await openConversation(page, chat.conversationId);

    const target = page.getByTestId('message-bubble').last();
    await target.scrollIntoViewIfNeeded();
    const box = await target.boundingBox();
    expect(box).not.toBeNull();
    const x = (box?.x ?? 0) + (box?.width ?? 0) / 2;
    const y = (box?.y ?? 0) + (box?.height ?? 0) / 2;
    const cdp = await page.context().newCDPSession(page);
    const touch = (
        type: 'touchStart' | 'touchMove' | 'touchEnd',
        at: { x: number; y: number },
    ) =>
        cdp.send('Input.dispatchTouchEvent', {
            type,
            touchPoints: type === 'touchEnd' ? [] : [at],
        });

    const sheet = page.getByTestId('message-action-sheet');

    // Moving 15px before 450ms cancels.
    await page.clock.pauseAt(Date.now() + 1_000);
    await touch('touchStart', { x, y });
    await touch('touchMove', { x, y: y + 15 });
    await page.clock.runFor(600);
    await expect(sheet).toHaveCount(0);
    await touch('touchEnd', { x, y: y + 15 });

    // Holding still for 450ms opens the sheet with the actions and the time.
    await touch('touchStart', { x, y });
    await page.clock.runFor(460);
    await expect(sheet).toBeVisible();
    await expect(sheet.getByRole('button', { name: 'Reply' })).toBeVisible();
    await expect(sheet.locator('time')).toHaveText(
        /^[A-Z][a-z]{2} .+, \d{2}:\d{2}$/,
    );
    await touch('touchEnd', { x, y });
    await page.clock.resume();

    const nativeMenuAllowed = await target.evaluate((node) =>
        node.dispatchEvent(
            new MouseEvent('contextmenu', { bubbles: true, cancelable: true }),
        ),
    );
    expect(nativeMenuAllowed).toBe(false);
});

test('34 · on desktop Enter sends and Shift+Enter adds a line', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const { page } = await sessions.open('marketingA');
    await openConversation(page, chat.conversationId);

    const first = unique('Line one');
    const input = page.getByTestId('composer-input');
    await input.fill(first);
    await input.press('Shift+Enter');
    await input.pressSequentially('Line two');
    await expect(input).toHaveValue(`${first}\nLine two`);

    const sent = sendResponse(page);
    await input.press('Enter');
    expect((await sent).ok()).toBe(true);

    await expect(input).toHaveValue('');
    await expect(bubble(page, first)).toContainText(`${first}\nLine two`);
});

test('34 · on mobile Enter adds a line and only the button sends @mobile', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const { page } = await sessions.open('marketingA');
    await openConversation(page, chat.conversationId);

    const first = unique('Phone line');
    const input = page.getByTestId('composer-input');
    await input.fill(first);
    await input.press('Enter');
    await input.pressSequentially('second');
    await expect(input).toHaveValue(`${first}\nsecond`);
    await expect(bubble(page, first)).toHaveCount(0);

    const sent = sendResponse(page);
    await page.getByRole('button', { name: 'Send message' }).click();
    expect((await sent).ok()).toBe(true);
    await expect(bubble(page, first)).toContainText(`${first}\nsecond`);
});

test('35 · optimistic send: Sending, then Sent; offline it fails with Retry, which sends once; one client key, one message', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const { page } = await sessions.open('marketingA');
    sessions.allowConsole(/net::ERR_FAILED|net::ERR_INTERNET_DISCONNECTED/);
    await openConversation(page, chat.conversationId);

    const isSend = (url: URL) =>
        new RegExp(`/message/${chat.conversationId}/messages$`).test(
            url.pathname,
        );

    // Held: the bubble says Sending… until the server answers.
    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => (release = resolve));
    await page.route(isSend, async (route) => {
        await held;
        await route.continue();
    });

    const slow = unique('Slow send');
    await page.getByTestId('composer-input').fill(slow);
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByTestId('send-state')).toHaveText(/Sending/);
    release();
    await expect(page.getByTestId('send-state')).toHaveCount(0);
    await expect(page.getByTestId('receipt')).toHaveText('Sent');
    await page.unroute(isSend);

    // Blocked: Failed with Retry; Retry sends it exactly once.
    await page.route(isSend, (route) => route.abort('internetdisconnected'));
    const failing = unique('Offline send');
    await page.getByTestId('composer-input').fill(failing);
    await page.getByRole('button', { name: 'Send message' }).click();
    await expect(page.getByTestId('send-state')).toContainText('Failed');
    await page.unroute(isSend);

    const retried = sendResponse(page);
    await page
        .getByTestId('send-state')
        .getByRole('button', { name: 'Retry' })
        .click();
    expect((await retried).status()).toBe(201);
    await expect(page.getByTestId('send-state')).toHaveCount(0);
    await expect(bubble(page, failing)).toHaveCount(1);

    const thread = await api(
        page,
        'GET',
        `/message/${chat.conversationId}/messages?limit=100`,
    );
    const bodies = (
        (thread.json?.messages ?? []) as Array<{ body: string | null }>
    ).map((message) => message.body);
    expect(bodies.filter((body) => body === failing)).toHaveLength(1);

    // The same client key twice: one message.
    const key = crypto.randomUUID();
    const twice = unique('Same key');
    const first = await postAs(page, chat.conversationId, twice, key);
    const second = await postAs(page, chat.conversationId, twice, key);
    expect([first.status, second.status]).toEqual([201, 200]);
    const idOf = (json: Record<string, unknown> | null): number | undefined =>
        (json?.message as { id: number } | undefined)?.id;
    expect(idOf(first.json)).toBeDefined();
    expect(idOf(second.json)).toBe(idOf(first.json));
});

test('36 · a message arriving while scrolled up does not move the view; the pill counts and scrolls down', async ({
    sessions,
}) => {
    const { scroll } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await b.page.goto('/message');
    await openConversation(a.page, scroll.conversationId);

    const thread = a.page.getByTestId('thread-scroll');
    await expect(a.page.getByTestId('message-bubble')).toHaveCount(30);
    await thread.evaluate((element) => element.scrollTo({ top: 150 }));
    const before = await thread.evaluate((element) => element.scrollTop);

    await postAs(b.page, scroll.conversationId, unique('While you read 1'));
    await postAs(b.page, scroll.conversationId, unique('While you read 2'));

    const pill = a.page.getByTestId('new-messages-pill');
    await expect(pill).toHaveText('2 new messages');
    const after = await thread.evaluate((element) => element.scrollTop);
    expect(Math.abs(after - before)).toBeLessThanOrEqual(2);

    await pill.click();
    await expect
        .poll(() =>
            thread.evaluate(
                (element) =>
                    element.scrollHeight -
                    element.scrollTop -
                    element.clientHeight,
            ),
        )
        .toBeLessThanOrEqual(2);
    await expect(pill).toHaveCount(0);
});

import type { Page } from '@playwright/test';
import {
    api,
    expect,
    fixtures,
    openConversation,
    test,
} from './support/fixtures';
import { artisan, startProcess, stopProcess } from './support/stack';

/*
 * G. Reconnect and resilience (§4). These scenarios stop and start the E2E
 * stack's own Reverb and queue worker (support/stack.ts), never anything else.
 */

const unique = (label: string): string => `${label} ${Date.now()}`;

const channelAuthorized = (page: Page, channel: string) =>
    page.waitForResponse(
        (response) =>
            response.url().endsWith('/broadcasting/auth') &&
            (response.request().postData() ?? '').includes(
                encodeURIComponent(channel),
            ),
    );

test('37 · with Reverb down, A sends; when Reverb is back, B catches up without a reload', async ({
    sessions,
}) => {
    test.setTimeout(180_000);
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    sessions.allowConsole(/WebSocket connection to .* failed/);

    const channel = `private-conversation.${chat.conversationId}`;
    const bJoined = channelAuthorized(b.page, channel);
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);
    await bJoined;
    await b.page.evaluate(
        () => ((window as unknown as { __e2eMarker: number }).__e2eMarker = 37),
    );

    const text = unique('Sent while Reverb is down');

    try {
        await stopProcess('reverb');

        await a.page.getByTestId('composer-input').fill(text);
        const sent = a.page.waitForResponse(
            (response) =>
                /\/message\/\d+\/messages$/.test(response.url()) &&
                response.request().method() === 'POST',
        );
        await a.page.getByRole('button', { name: 'Send message' }).click();
        const sendStatus = (await sent).status();
        await expect(
            b.page.getByTestId('message-bubble').filter({ hasText: text }),
        ).toHaveCount(0);

        // Rejoining needs a fresh channel authorization.
        const bRejoined = channelAuthorized(b.page, channel);
        await startProcess('reverb');
        await bRejoined.catch(() => undefined);

        await expect(
            b.page.getByTestId('message-bubble').filter({ hasText: text }),
        ).toHaveCount(1, { timeout: 60_000 });
        expect(
            await b.page.evaluate(
                () =>
                    (window as unknown as { __e2eMarker?: number }).__e2eMarker,
            ),
        ).toBe(37);
        expect(sendStatus, "A's send while Reverb was down").toBe(201);
    } finally {
        await startProcess('reverb').catch(() => undefined);
    }
});

test('38 · a status change made while the queue worker is stopped posts its card once the worker runs, once', async ({
    sessions,
}) => {
    test.setTimeout(180_000);
    const { bound } = fixtures();
    const client = await sessions.open('cardClient');
    await client.page.goto('/message');

    const cards = async (): Promise<number> => {
        const thread = await api(
            client.page,
            'GET',
            `/message/${bound.queue.conversationId}/messages?limit=100`,
        );

        return (
            (thread.json?.messages ?? []) as Array<{
                type: string;
                inquiryCard: { announcedStatus: string } | null;
            }>
        ).filter(
            (message) =>
                message.type === 'inquiry_card' &&
                message.inquiryCard?.announcedStatus === 'cancelled',
        ).length;
    };

    const pendingJobs = (): number =>
        Number(
            artisan(
                'tinker',
                '--execute',
                'echo DB::table("jobs")->count();',
            ).trim(),
        );

    try {
        await stopProcess('queue');

        // A successful cancel redirects back to the page it came from.
        const cancelled = await api(
            client.page,
            'PATCH',
            `/quote/${bound.queue.reference}/cancel`,
            undefined,
            'manual',
        );
        expect(cancelled.redirected, `cancel status ${cancelled.status}`).toBe(
            true,
        );
        test.info().annotations.push({
            type: 'cards before the worker ran',
            description: String(await cards()),
        });

        await startProcess('queue');
        await expect.poll(pendingJobs, { timeout: 60_000 }).toBe(0);

        expect(await cards()).toBe(1);
    } finally {
        await startProcess('queue').catch(() => undefined);
    }
});

import type { Page } from '@playwright/test';
import {
    expect,
    fixtures,
    openConversation,
    shot,
    test,
} from './support/fixtures';

/* I. Layout and console (general). */

const horizontalOverflow = (page: Page) =>
    page.evaluate(() => ({
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
    }));

test('41 · no horizontal page scroll on the list or the thread @layout', async ({
    sessions,
}, testInfo) => {
    const { cards, chat } = fixtures();
    const { page } = await sessions.open('marketingA');

    await page.goto('/message');
    await expect(page.getByTestId('conversation-row').first()).toBeVisible();
    const list = await horizontalOverflow(page);
    expect(
        list.scrollWidth,
        `list at ${list.clientWidth}px`,
    ).toBeLessThanOrEqual(list.clientWidth);
    await shot(page, '41-list', testInfo.project.name);

    for (const conversationId of [chat.conversationId, cards.conversationId]) {
        await openConversation(page, conversationId);
        await expect(
            page
                .getByTestId('message-bubble')
                .or(page.getByTestId('inquiry-card'))
                .first(),
        ).toBeVisible();
        const thread = await horizontalOverflow(page);
        expect(
            thread.scrollWidth,
            `thread ${conversationId} at ${thread.clientWidth}px`,
        ).toBeLessThanOrEqual(thread.clientWidth);
    }

    await shot(page, '41-thread', testInfo.project.name);
});

test('42 · a tour of the page by every role logs no console errors or uncaught exceptions', async ({
    sessions,
}) => {
    // Strict: the KNOWN_BUGS allowances in support/fixtures.ts do not apply
    // here. Every other scenario also fails on any console error outside the
    // allow-list.
    sessions.strictConsole();
    const { cards, chat, support } = fixtures();

    for (const actor of ['marketingA', 'sales', 'admin', 'client'] as const) {
        const { page } = await sessions.open(actor);
        await page.goto('/message');
        await expect(
            page.getByTestId('conversation-row').first(),
        ).toBeVisible();
        const thread = page.waitForResponse((response) =>
            /\/message\/\d+\/messages/.test(response.url()),
        );
        await page.getByTestId('conversation-row').first().click();
        await thread;
        await expect(page.getByTestId('thread-scroll')).toBeVisible();
    }

    const { page } = await sessions.open('marketingA');

    for (const conversationId of [
        chat.conversationId,
        cards.conversationId,
        support.release.conversationId,
    ]) {
        const loaded = page.waitForResponse((response) =>
            response.url().includes(`/message/${conversationId}/messages`),
        );
        await openConversation(page, conversationId);
        await loaded;
        await expect(page.getByTestId('thread-scroll')).toBeVisible();
    }
});

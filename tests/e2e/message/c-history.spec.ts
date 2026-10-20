import type { Page } from '@playwright/test';
import { expect, fixtures, openConversation, test } from './support/fixtures';

/* C. Thread history (R4). "E2E History" holds 100 messages; a page is 30. */

const PAGE = 30;
const TOTAL = 100;

const isOlderPageRequest = (url: string): boolean =>
    /\/message\/\d+\/messages\?.*before=/.test(url);

const scrollThreadToTop = (page: Page) =>
    page
        .getByTestId('thread-scroll')
        .evaluate((element) => element.scrollTo({ top: 0 }));

test('9 · a thread of 100 messages opens on the latest 30, scrolled to the bottom', async ({
    sessions,
}) => {
    const { history } = fixtures();
    const { page } = await sessions.open('lister');
    await openConversation(page, history.conversationId);

    const bubbles = page.getByTestId('message-bubble');
    await expect(bubbles).toHaveCount(PAGE);
    await expect(bubbles.last()).toContainText(history.lastMessage);
    await expect(bubbles.first()).toContainText(`message ${TOTAL - PAGE + 1}`);

    await expect
        .poll(() =>
            page
                .getByTestId('thread-scroll')
                .evaluate(
                    (element) =>
                        element.scrollHeight -
                        element.scrollTop -
                        element.clientHeight,
                ),
        )
        .toBeLessThanOrEqual(2);
});

test('10 · scrolling to the top shows a spinner, loads 30 older messages and keeps the first message in place', async ({
    sessions,
}) => {
    const { history } = fixtures();
    const { page } = await sessions.open('lister');
    await openConversation(page, history.conversationId);
    await expect(page.getByTestId('message-bubble')).toHaveCount(PAGE);

    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => (release = resolve));
    await page.route(
        (url) => isOlderPageRequest(url.toString()),
        async (route) => {
            await held;
            await route.continue();
        },
    );

    const anchor = page
        .getByTestId('message-bubble')
        .filter({
            hasText: `${history.firstMessage.replace(' 1', '')} ${TOTAL - PAGE + 1}`,
        });
    await expect(anchor).toHaveCount(1);

    const requested = page.waitForRequest((request) =>
        isOlderPageRequest(request.url()),
    );
    await scrollThreadToTop(page);
    await requested;
    await expect(page.getByTestId('older-spinner')).toBeVisible();

    const before = await anchor.boundingBox();
    release();

    await expect(page.getByTestId('message-bubble')).toHaveCount(PAGE * 2);
    const after = await anchor.boundingBox();

    expect(before).not.toBeNull();
    expect(after).not.toBeNull();
    expect(Math.abs((after?.y ?? 0) - (before?.y ?? 0))).toBeLessThanOrEqual(2);
});

test('11 · once the oldest message is in, the spinner stops and no more pages are requested', async ({
    sessions,
}) => {
    const { history } = fixtures();
    const { page } = await sessions.open('lister');
    await openConversation(page, history.conversationId);

    const bubbles = page.getByTestId('message-bubble');

    for (const expected of [60, 90, TOTAL]) {
        const loaded = page.waitForResponse((response) =>
            isOlderPageRequest(response.url()),
        );
        await scrollThreadToTop(page);
        await loaded;
        await expect(bubbles).toHaveCount(expected);
    }

    await expect(bubbles.first()).toContainText(history.firstMessage);
    await expect(page.getByTestId('older-spinner')).toHaveCount(0);

    const extra: string[] = [];
    page.on('request', (request) => {
        if (isOlderPageRequest(request.url())) {
            extra.push(request.url());
        }
    });

    await page
        .getByTestId('thread-scroll')
        .evaluate((element) => element.scrollTo({ top: 200 }));
    await scrollThreadToTop(page);
    await page.waitForLoadState('networkidle');

    expect(extra).toEqual([]);
    await expect(page.getByTestId('older-spinner')).toHaveCount(0);
});

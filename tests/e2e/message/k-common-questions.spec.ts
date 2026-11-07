import type { Page } from '@playwright/test';
import { expect, fixtures, openConversation, test } from './support/fixtures';

/*
 * K. The Frequently Asked Questions icon in a client's composer, and the list it opens above
 * the box. "Support Common" has a Regray Team thread of its own that nobody claims,
 * so it stays locked for staff.
 */

const FIRST_QUESTION = 'How long does an order take?';

const toggle = (page: Page) => page.getByTestId('common-questions-toggle');
const block = (page: Page) => page.getByTestId('composer-common');
const input = (page: Page) => page.getByTestId('composer-input');
const sendQuestion = (page: Page, index: number) =>
    page.getByTestId(`composer-common-send-${index}`);

const bubbles = (page: Page, text: string) =>
    page.getByTestId('message-bubble').filter({ hasText: text });

const sendResponse = (page: Page) =>
    page.waitForResponse(
        (response) =>
            /\/dashboard\/inbox\/\d+\/messages$/.test(response.url()) &&
            response.request().method() === 'POST',
    );

const openAsClient = async (sessions: {
    open: (actor: { email: string }) => Promise<{ page: Page }>;
}): Promise<Page> => {
    const { email, conversationId } = fixtures().support.common;
    const { page } = await sessions.open({ email });

    await openConversation(page, conversationId);
    await expect(toggle(page)).toBeVisible();

    return page;
};

test('K1 · the icon sits with the upload icons and opens the questions above the input, and closes them', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);
    const attach = await page
        .getByRole('button', { name: 'Attach a file' })
        .boundingBox();
    const icon = await toggle(page).boundingBox();

    expect(icon?.y).toBe(attach?.y);
    expect(icon?.x ?? 0).toBeGreaterThan(attach?.x ?? 0);
    await expect(toggle(page)).toHaveAccessibleName(
        'Frequently Asked Questions',
    );
    await expect(toggle(page)).not.toContainText('Frequently Asked Questions');

    await expect(block(page)).toBeHidden();

    await toggle(page).click();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
    await expect(block(page)).toBeVisible();
    await expect(
        page
            .getByRole('list', { name: 'Frequently Asked Questions' })
            .getByRole('listitem'),
    ).toHaveText([
        FIRST_QUESTION,
        'Do you offer nationwide installation?',
        'How do I request a quotation?',
    ]);

    await expect
        .poll(async () => {
            const list = await block(page).boundingBox();
            const box = await input(page).boundingBox();

            return (list?.y ?? 0) + (list?.height ?? 0) <= (box?.y ?? 0);
        }, 'the list sits above the input')
        .toBe(true);

    await toggle(page).click();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(block(page)).toBeHidden();
});

test('K1a · scrolling down over the list hides it, and scrolling up does not', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);

    await toggle(page).click();
    await expect(block(page)).toBeVisible();
    await page.waitForTimeout(300);
    await block(page).hover();

    await page.mouse.wheel(0, -200);
    await page.waitForTimeout(300);
    await expect(block(page)).toBeVisible();

    await page.mouse.wheel(0, 200);
    await expect(block(page)).toBeHidden();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');

    // And it opens again from the icon.
    await toggle(page).click();
    await expect(block(page)).toBeVisible();
});

test('K1b · pressing outside hides the list; inside it does not, and the icon still toggles', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);

    await toggle(page).click();
    await expect(block(page)).toBeVisible();

    await block(page).getByText(FIRST_QUESTION).click();
    await page.waitForTimeout(300);
    await expect(block(page)).toBeVisible();

    await page.getByTestId('message-bubble').first().click();
    await expect(block(page)).toBeHidden();

    await toggle(page).click();
    await expect(block(page)).toBeVisible();
    await input(page).click();
    await expect(block(page)).toBeHidden();
    await input(page).blur();

    await toggle(page).click();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
    await toggle(page).click();
    await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
    await expect(block(page)).toBeHidden();
});

test('K2 · a question sends its exact text, leaves the draft alone, and the list stays open', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);
    const before = await bubbles(page, FIRST_QUESTION).count();

    await toggle(page).click();
    await input(page).fill('My own draft');

    const sent = sendResponse(page);
    await sendQuestion(page, 0).click();
    const response = await sent;

    expect(response.ok()).toBe(true);
    expect(response.request().postData()).toContain(FIRST_QUESTION);
    await expect(bubbles(page, FIRST_QUESTION)).toHaveCount(before + 1);
    await expect(input(page)).toHaveValue('My own draft');
    await expect(block(page)).toBeVisible();
});

test('K3 · only the button sends: pressing the question text does not', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);
    const posts: string[] = [];

    page.on('request', (request) => {
        if (
            request.method() === 'POST' &&
            request.url().endsWith('/messages')
        ) {
            posts.push(request.url());
        }
    });

    await toggle(page).click();
    await block(page).getByText(FIRST_QUESTION).click();
    await page.waitForTimeout(500);

    expect(posts).toEqual([]);
    await expect(block(page)).toBeVisible();
});

test('K4 · a question sends a pending reply with it', async ({ sessions }) => {
    const page = await openAsClient(sessions);
    const text = `Reply target ${Date.now()}`;

    await input(page).fill(text);
    await page.getByRole('button', { name: 'Send message' }).click();

    const target = bubbles(page, text);
    await target.hover();
    await target.getByRole('button', { name: 'Reply' }).click();
    await expect(page.getByTestId('reply-bar')).toBeVisible();

    await input(page).blur();
    await toggle(page).click();

    const sent = sendResponse(page);
    await sendQuestion(page, 1).click();
    expect((await sent).request().postData()).toContain('replyToId');
    await expect(page.getByTestId('reply-bar')).toHaveCount(0);
});

test('K5 · the keyboard opens it and reaches each button, and Enter and Space send', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);

    // While closed the buttons take no focus.
    await sendQuestion(page, 0)
        .focus({ timeout: 1000 })
        .catch(() => undefined);
    await expect(sendQuestion(page, 0)).not.toBeFocused();

    await toggle(page).focus();
    await page.keyboard.press('Enter');
    await expect(block(page)).toBeVisible();

    for (const [index, key] of [
        [0, 'Enter'],
        [1, 'Space'],
    ] as const) {
        await sendQuestion(page, index).focus();
        await expect(sendQuestion(page, index)).toBeFocused();

        const sent = sendResponse(page);
        await page.keyboard.press(key);
        expect((await sent).ok()).toBe(true);
    }

    await page.keyboard.press('Tab');
    await expect(sendQuestion(page, 2)).toBeFocused();
});

test('K6 · on a touch screen a tap opens it and sends, a swipe down hides it, and nothing scrolls sideways @mobile', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);

    await toggle(page).tap();
    await expect(block(page)).toBeVisible();
    await page.waitForTimeout(300);
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth >
                document.documentElement.clientWidth,
        ),
    ).toBe(false);

    const posted = sendResponse(page);
    await sendQuestion(page, 2).tap();
    expect((await posted).ok()).toBe(true);
    await expect(
        bubbles(page, 'How do I request a quotation?').first(),
    ).toBeVisible();

    await expect(block(page)).toBeVisible();

    // A real finger, through the browser's own touch input.
    const box = await block(page)
        .getByText('Frequently Asked Questions')
        .boundingBox();
    const x = (box?.x ?? 0) + 40;
    const y = (box?.y ?? 0) + 4;
    const touch = await page.context().newCDPSession(page);

    await touch.send('Input.dispatchTouchEvent', {
        type: 'touchStart',
        touchPoints: [{ x, y }],
    });

    for (const distance of [20, 40, 60, 80]) {
        await touch.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ x, y: y + distance }],
        });
    }

    await touch.send('Input.dispatchTouchEvent', {
        type: 'touchEnd',
        touchPoints: [],
    });
    await expect(block(page)).toBeHidden();
});

test('K7 · staff never see it, on a thread they can write in or one locked for them', async ({
    sessions,
}) => {
    const { chat, support } = fixtures();
    const { page } = await sessions.open('marketingA');

    await openConversation(page, chat.conversationId);
    await expect(input(page)).toBeEnabled();
    await expect(toggle(page)).toHaveCount(0);
    await expect(block(page)).toHaveCount(0);

    await openConversation(page, support.common.conversationId);
    await expect(input(page)).toBeDisabled();
    await expect(page.getByTestId('composer-locked')).toBeVisible();
    await expect(toggle(page)).toHaveCount(0);
    await expect(block(page)).toHaveCount(0);
});

test('K8 · it hides while editing a message, and the icon waits until that is done', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);
    const text = `Edit target ${Date.now()}`;

    await input(page).fill(text);
    await page.getByRole('button', { name: 'Send message' }).click();
    await input(page).blur();
    await toggle(page).click();
    await expect(block(page)).toBeVisible();

    const target = bubbles(page, text);
    await target.hover();
    await target.getByRole('button', { name: 'Edit' }).click();
    await expect(page.getByTestId('editing-bar')).toBeVisible();
    await expect(block(page)).toBeHidden();
    await expect(sendQuestion(page, 0)).not.toBeFocused();

    await page.getByRole('button', { name: 'Cancel editing' }).click();
    await input(page).blur();
    await toggle(page).click();
    await expect(block(page)).toBeVisible();
});

test('K9 · opening the list keeps the newest message in view on a short screen', async ({
    sessions,
}) => {
    const page = await openAsClient(sessions);

    await page.setViewportSize({ width: 390, height: 560 });

    for (let count = 0; count < 8; count += 1) {
        const sent = sendResponse(page);

        await input(page).fill(`Filler ${count} ${Date.now()}`);
        await page.getByRole('button', { name: 'Send message' }).click();
        expect((await sent).ok()).toBe(true);
    }

    const newest = page.getByTestId('message-bubble').last();

    await input(page).blur();
    await expect(newest).toBeInViewport();

    await toggle(page).click();
    await expect(block(page)).toBeVisible();
    await page.waitForTimeout(350);
    await expect(newest).toBeInViewport();
});

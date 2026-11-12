import type { Page, Route } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, inquiryCount, test } from '../support/fixtures';
import {
    addStoneProject,
    openInquire,
    requestQuoteButton,
    sendAndReadReference,
} from '../support/inquiry-flow';

/* H. A send that fails, and a send pressed twice. */

const isStore = (url: URL): boolean =>
    url.pathname === '/dashboard/quote/inquire';

/** Count the POSTs to the store route that leave the page. */
const countStorePosts = (page: Page): (() => number) => {
    let count = 0;
    page.on('request', (request) => {
        if (request.method() === 'POST' && isStore(new URL(request.url()))) {
            count += 1;
        }
    });

    return () => count;
};

test('31 · a 500 shows the error and keeps the answers; retrying without it saves exactly one inquiry', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 500/);
    await openInquire(page);
    await addStoneProject(page, { location: 'Pasay', notes: 'Keep me' });
    const before = inquiryCount(clientAId);

    const failOnce = async (route: Route) => {
        if (route.request().method() !== 'POST') {
            return route.fallback();
        }

        return route.fulfill({
            status: 500,
            contentType: 'text/html',
            body: '<html><body>Server Error</body></html>',
        });
    };
    await page.route(isStore, failOnce);

    await requestQuoteButton(page).click();

    const panel = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: "We couldn't send your request",
        }),
    });
    await expect(panel).toBeVisible();
    await expect
        .soft(panel, 'the message the scenario names')
        .toContainText(
            'We could not save your inquiry. Your answers are still here',
        );
    expect(inquiryCount(clientAId)).toBe(before);

    // The page's own dialog is the answer; nothing should sit on top of it.
    const inertiaDialog = page.locator('#inertia-error-dialog');
    await expect
        .soft(inertiaDialog, 'a second error dialog over the page')
        .toHaveCount(0);

    if ((await inertiaDialog.count()) > 0) {
        await page.keyboard.press('Escape');
        await expect(inertiaDialog).toHaveCount(0);
    }

    await panel.getByRole('button', { name: 'Back to review' }).click();
    await expect(page.getByText('Keep me', { exact: true })).toBeVisible();
    await expect(page.getByText('Pasay', { exact: true })).toBeVisible();

    await page.unroute(isStore, failOnce);
    await sendAndReadReference(page);
    expect(inquiryCount(clientAId)).toBe(before + 1);
});

test('32 · Send pressed twice quickly saves exactly one inquiry', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page, { location: 'Manila' });
    const before = inquiryCount(clientAId);
    const posts = countStorePosts(page);

    // Two clicks in the same task, before React can re-render the button.
    await requestQuoteButton(page).evaluate((button: HTMLButtonElement) => {
        button.click();
        button.click();
    });

    await expect(
        page.getByRole('heading', { name: 'Quote requested' }),
    ).toBeVisible();
    expect(posts()).toBe(1);
    expect(inquiryCount(clientAId)).toBe(before + 1);
});

test('33 · the Send button is disabled while the request is in flight', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page, { location: 'Marikina' });

    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => {
        release = resolve;
    });
    const hold = async (route: Route) => {
        if (route.request().method() !== 'POST') {
            return route.fallback();
        }

        await held;

        return route.continue();
    };
    await page.route(isStore, hold);

    await requestQuoteButton(page).click();

    await expect(requestQuoteButton(page)).toBeDisabled();
    await expect(requestQuoteButton(page)).toHaveText('Sending...');

    release();
    await expect(
        page.getByRole('heading', { name: 'Quote requested' }),
    ).toBeVisible();
    await page.unroute(isStore, hold);
});

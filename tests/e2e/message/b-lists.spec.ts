import { expect, fixtures, test } from './support/fixtures';

/* B. Lists and lazy loading (R3). The list tester belongs to exactly 45 groups. */

const LIST_SIZE = 45;

test('5 · 45 conversations load 20 at a time as the list scrolls, with no duplicates', async ({
    sessions,
}) => {
    const { page } = await sessions.open('lister');
    await page.goto('/dashboard/inbox');

    const rows = page.getByTestId('conversation-row');
    const list = page.getByTestId('conversation-list');
    await expect(rows).toHaveCount(20);

    const pageRequests: string[] = [];
    page.on('request', (request) => {
        if (
            request.url().includes('/dashboard/inbox') &&
            request.headers()['x-inertia']
        ) {
            pageRequests.push(request.url());
        }
    });

    await list.evaluate((element) =>
        element.scrollTo({ top: element.scrollHeight }),
    );
    await expect(rows).toHaveCount(40);

    await list.evaluate((element) =>
        element.scrollTo({ top: element.scrollHeight }),
    );
    await expect(rows).toHaveCount(LIST_SIZE);

    const names = await page.getByTestId('conversation-name').allTextContents();
    expect(names).toHaveLength(LIST_SIZE);
    expect(new Set(names).size).toBe(LIST_SIZE);
    expect(pageRequests).toHaveLength(2);
});

test('6 · skeleton rows show while the first page of the list loads, then go away', async ({
    sessions,
}) => {
    const { page } = await sessions.open('lister');
    await page.goto('/profile');

    let release: () => void = () => undefined;
    const held = new Promise<void>((resolve) => (release = resolve));
    // The list is a deferred prop: the page arrives first, and its own request
    // for `conversations` is the one held.
    await page.route(
        (url) => url.pathname === '/dashboard/inbox',
        async (route) => {
            const partial =
                route.request().headers()['x-inertia-partial-data'] ?? '';

            if (partial.split(',').includes('conversations')) {
                await held;
            }

            await route.continue();
        },
    );

    await page.locator('a[href$="/dashboard/inbox"]').first().click();

    // Six placeholder rows (SKELETON_ROW_COUNT) while the response is held.
    await expect(page.getByTestId('conversation-skeleton-row')).toHaveCount(6);

    release();
    await expect(page.getByTestId('conversation-row')).toHaveCount(20);
    await expect(page.getByTestId('conversation-skeleton-row')).toHaveCount(0);
});

test('7 · search runs on the server and returns only matching names', async ({
    sessions,
}) => {
    const { page } = await sessions.open('lister');
    await page.goto('/dashboard/inbox');
    await expect(page.getByTestId('conversation-row')).toHaveCount(20);

    const searched = page.waitForRequest(
        (request) =>
            request.url().includes('/dashboard/inbox') &&
            new URL(request.url()).searchParams.get('search') === 'List 07',
    );
    await page.getByLabel('Search conversations').fill('List 07');
    await searched;

    await expect(page.getByTestId('conversation-name')).toHaveText([
        'E2E List 07',
    ]);
});

test('8 · the Unread filter shows only unread rows, with badges equal to their unread messages', async ({
    sessions,
}) => {
    const { page } = await sessions.open('lister');
    await page.goto('/dashboard/inbox');

    const filtered = page.waitForRequest(
        (request) =>
            request.url().includes('/dashboard/inbox') &&
            new URL(request.url()).searchParams.get('filter') === 'unread',
    );
    await page.getByRole('tab', { name: 'Unread' }).click();
    await filtered;

    const expected = fixtures().unread;
    const rows = page.getByTestId('conversation-row');
    await expect(rows).toHaveCount(expected.length);

    for (const { name, count } of expected) {
        const row = rows.filter({
            has: page
                .getByTestId('conversation-name')
                .getByText(name, { exact: true }),
        });
        // The badge reads "{count}" with a screen-reader-only " unread" after it.
        await expect(row.getByTestId('unread-badge')).toHaveText(
            `${count} unread`,
        );
    }
});

import type { Page } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, php, postAsUser, test } from '../support/fixtures';
import {
    addVisitProject,
    openInquire,
    sendAndReadReference,
} from '../support/inquiry-flow';

/*
 * K. The card a sent inquiry posts. With no group chat bound to the inquiry
 * yet, it goes to the client's Regray Team thread. The queue worker posts it,
 * so the thread is reopened until it shows.
 */

/**
 * The inquiry cards for `reference` in the thread at `url`, once `count` of
 * them have arrived.
 */
const cardsFor = async (
    page: Page,
    url: string,
    reference: string,
    count = 1,
) => {
    const cards = page
        .getByTestId('inquiry-card')
        .filter({ hasText: reference });

    await expect(async () => {
        await page.goto(url);
        await expect(cards).toHaveCount(count, { timeout: 3_000 });
    }).toPass({ timeout: 45_000 });

    return cards;
};

/** The "New Inquiry" card for `reference` in the thread at `url`. */
const expectCard = async (
    page: Page,
    url: string,
    reference: string,
): Promise<void> => {
    const card = (await cardsFor(page, url, reference)).first();

    await expect(card).toContainText('New Inquiry');
    await expect(card.getByTestId('inquiry-card-status')).toContainText(
        'Pending',
    );
};

test('40 · a sent inquiry posts a "New Inquiry" card into the Regray Team thread, for the client and for the support pool', async ({
    actors,
}) => {
    test.setTimeout(180_000);
    const { clientAId } = inquiryFixtures();
    const client = await actors.open('client');

    await openInquire(client.page);
    await addVisitProject(client.page, { location: 'Pasig' });
    const reference = await sendAndReadReference(client.page);

    await expectCard(client.page, '/message?conversation=support', reference);
    await expect(client.page.getByTestId('thread-title')).toHaveText(
        'Regray Team',
    );

    const threadId = php<number>(
        `App\\Models\\Conversation::query()->where('support_client_id', ${clientAId})->value('id')`,
    );
    const marketing = await actors.open('marketing');

    await expectCard(
        marketing.page,
        `/message?conversation=${threadId}`,
        reference,
    );
});

test('41 · without a group chat, a status change posts its card into the Regray Team thread too', async ({
    actors,
}) => {
    test.setTimeout(180_000);
    const { page } = await actors.open('client');

    await openInquire(page);
    await addVisitProject(page, { location: 'Taguig' });
    const reference = await sendAndReadReference(page);

    await page.goto('/quote');
    const cancelled = await postAsUser(page, `/quote/${reference}/cancel`, {
        _method: 'PATCH',
    });
    expect([302, 303], `cancel answered ${cancelled.status}`).toContain(
        cancelled.status,
    );
    await page.reload();
    const listed = page.locator('article').filter({ hasText: reference });
    await expect(listed).toContainText('Cancelled');

    const cards = await cardsFor(
        page,
        '/message?conversation=support',
        reference,
        2,
    );

    await expect(cards.nth(0)).toContainText('New Inquiry');
    await expect(cards.nth(1)).toContainText('Inquiry cancelled');
    await expect(cards.nth(1).getByTestId('inquiry-card-status')).toContainText(
        'Cancelled',
    );
});

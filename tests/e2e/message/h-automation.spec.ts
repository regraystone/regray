import { api, expect, fixtures, test } from './support/fixtures';

/*
 * H. Inquiry and status automation (R8, R9). Groups are not bound to inquiries
 * by the product yet (TODO(inquiry-binding)); E2EMessageSeeder binds a group to
 * INQ-E2E-B1..B3 directly, and leaves INQ-E2E-F1 without one.
 */

type ThreadMessage = {
    type: string;
    inquiryCard: { reference: string; announcedStatus: string } | null;
};

test('39 · "Message the team" shows only for an inquiry with a group chat the viewer is in', async ({
    sessions,
}) => {
    const { bound } = fixtures();
    const { page } = await sessions.open('cardClient');

    await page.goto(`/quote/${bound.shown.reference}`);
    const link = page.getByRole('link', { name: 'Message the team' });
    await expect(link).toBeVisible();
    await link.click();
    await page.waitForURL(
        (url) =>
            url.pathname === '/message' &&
            url.searchParams.get('conversation') ===
                String(bound.shown.conversationId),
    );
    await expect(page.getByTestId('thread-title')).toHaveText(bound.shown.name);

    await page.goto(`/quote/${bound.free.reference}`);
    await expect(page.getByRole('heading').first()).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Message the team' }),
    ).toHaveCount(0);
});

test('40 · cancelling an inquiry posts one card into its group; repeating it posts none', async ({
    sessions,
}) => {
    const { bound } = fixtures();
    const { page } = await sessions.open('cardClient');
    sessions.allowConsole(/status of 422/);
    await page.goto('/message');

    const cards = async (): Promise<ThreadMessage[]> => {
        const thread = await api(
            page,
            'GET',
            `/message/${bound.cancel.conversationId}/messages?limit=100`,
        );

        return ((thread.json?.messages ?? []) as ThreadMessage[]).filter(
            (message) => message.type === 'inquiry_card',
        );
    };

    expect(await cards()).toHaveLength(0);

    const first = await api(
        page,
        'PATCH',
        `/quote/${bound.cancel.reference}/cancel`,
    );
    expect(first.status).toBe(200);
    await expect.poll(async () => (await cards()).length).toBe(1);

    const [card] = await cards();
    expect(card.inquiryCard).toMatchObject({
        reference: bound.cancel.reference,
        announcedStatus: 'cancelled',
    });

    const again = await api(
        page,
        'PATCH',
        `/quote/${bound.cancel.reference}/cancel`,
    );
    expect(again.status).toBe(422);
    expect(await cards()).toHaveLength(1);
});

import type { Locator, Page } from '@playwright/test';
import {
    expect,
    fixtures,
    openConversation,
    shot,
    test,
} from './support/fixtures';

/*
 * D. Inquiry and schedule cards (R5, R5b). "E2E Cards" holds an inquiry card
 * for each of the six statuses, one with a 100-character project name, a visit
 * card for each status and booking type, and one with a 150-character title.
 */

const HEADLINES: Record<string, Record<'client' | 'staff', string>> = {
    requested: { client: 'Booking requested', staff: 'Booking requested' },
    confirmed: { client: 'Booking approved', staff: 'Visit booked' },
    completed: { client: 'Visit completed', staff: 'Visit completed' },
    cancelled: { client: 'Booking cancelled', staff: 'Booking cancelled' },
};

/** Load every message of the thread, so all cards are in the DOM. */
const openCards = async (page: Page): Promise<void> => {
    await openConversation(page, fixtures().cards.conversationId);
    await expect(page.getByTestId('inquiry-card')).toHaveCount(7);
    await expect(page.getByTestId('schedule-card')).toHaveCount(9);
};

const boxes = async (cards: Locator) => {
    const result: Array<{ width: number; height: number }> = [];

    for (const card of await cards.all()) {
        await card.scrollIntoViewIfNeeded();
        const box = await card.boundingBox();
        expect(box).not.toBeNull();
        result.push({
            width: Math.round(box?.width ?? 0),
            height: Math.round(box?.height ?? 0),
        });
    }

    return result;
};

const lineHeight = (element: Locator) =>
    element.evaluate((node) => {
        const style = getComputedStyle(node);
        const line = parseFloat(style.lineHeight);

        return Number.isNaN(line) ? parseFloat(style.fontSize) * 1.5 : line;
    });

test('12 · every inquiry card and every visit card is the same size @layout', async ({
    sessions,
}, testInfo) => {
    const { page } = await sessions.open('sales');
    await openCards(page);

    const inquiry = await boxes(page.getByTestId('inquiry-card'));
    const visit = await boxes(page.getByTestId('schedule-card'));

    expect(
        new Set(inquiry.map((box) => box.width)).size,
        `inquiry widths ${JSON.stringify(inquiry)}`,
    ).toBe(1);
    expect(
        new Set(inquiry.map((box) => box.height)).size,
        `inquiry heights ${JSON.stringify(inquiry)}`,
    ).toBe(1);
    expect(
        new Set(visit.map((box) => box.width)).size,
        `visit widths ${JSON.stringify(visit)}`,
    ).toBe(1);
    expect(
        new Set(visit.map((box) => box.height)).size,
        `visit heights ${JSON.stringify(visit)}`,
    ).toBe(1);

    const viewport = page.viewportSize()?.width ?? 0;
    await page.getByTestId('inquiry-card').first().scrollIntoViewIfNeeded();
    await shot(page, `12-inquiry-cards-${viewport}`, testInfo.project.name);
    await page.getByTestId('schedule-card').first().scrollIntoViewIfNeeded();
    await shot(page, `12-schedule-cards-${viewport}`, testInfo.project.name);
    testInfo.annotations.push({
        type: 'sizes',
        description: JSON.stringify({
            viewport,
            inquiry: inquiry[0],
            visit: visit[0],
        }),
    });
});

test('13 · long names truncate on one line and the badge row does not wrap @layout', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('sales');
    await openCards(page);

    const longInquiry = page
        .getByTestId('inquiry-card')
        .filter({ hasText: cards.longReference });
    const title = longInquiry.getByTestId('inquiry-card-title');
    await title.scrollIntoViewIfNeeded();
    await expect(title).toHaveText(cards.longProject);

    const titleMetrics = await title.evaluate((node) => ({
        height: node.getBoundingClientRect().height,
        overflows: node.scrollWidth > node.clientWidth,
    }));
    expect(titleMetrics.overflows).toBe(true);
    expect(titleMetrics.height).toBeLessThanOrEqual(
        (await lineHeight(title)) + 1,
    );

    const badge = longInquiry.getByTestId('inquiry-card-status');
    const badgeHeight = (await badge.boundingBox())?.height ?? 0;
    const shortBadgeHeight =
        (
            await page
                .getByTestId('inquiry-card')
                .first()
                .getByTestId('inquiry-card-status')
                .boundingBox()
        )?.height ?? 0;
    expect(Math.abs(badgeHeight - shortBadgeHeight)).toBeLessThanOrEqual(1);
    expect(badgeHeight).toBeLessThanOrEqual(
        (await lineHeight(badge)) * 1.5 + 8,
    );

    const longVisitTitle = page
        .getByTestId('schedule-card-title')
        .filter({ hasText: cards.longScheduleTitle });
    await longVisitTitle.scrollIntoViewIfNeeded();
    const visitMetrics = await longVisitTitle.evaluate((node) => ({
        height: node.getBoundingClientRect().height,
        overflows: node.scrollWidth > node.clientWidth,
    }));
    expect(visitMetrics.overflows).toBe(true);
    expect(visitMetrics.height).toBeLessThanOrEqual(
        (await lineHeight(longVisitTitle)) + 1,
    );
});

test('14 · the inquiry heading is the first project and the sub-line counts projects with the reference', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('sales');
    await openCards(page);

    const statusCards = page
        .getByTestId('inquiry-card')
        .filter({ hasText: cards.reference });
    await expect(statusCards).toHaveCount(6);

    for (const card of await statusCards.all()) {
        await expect(card.getByTestId('inquiry-card-title')).toHaveText(
            cards.firstProject,
        );
        await expect(card.getByTestId('inquiry-card-subline')).toHaveText(
            `${cards.projectCount} projects · Inquiry No: ${cards.reference}`,
        );
    }
});

test('15 · visit cards have the headline for their status and booking type, and a name only for staff', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('sales');
    await openCards(page);

    for (const schedule of cards.schedules) {
        const card = page.getByTestId('schedule-card').filter({
            has: page
                .getByTestId('schedule-card-title')
                .getByText(schedule.title, { exact: true }),
        });
        await expect(card).toHaveCount(1);
        await expect(card.getByTestId('card-headline')).toHaveText(
            HEADLINES[schedule.status][schedule.bookedByType],
        );
        await expect(card.getByTestId('schedule-card-booked-by')).toHaveText(
            schedule.bookedByType === 'staff'
                ? `Booked by ${cards.staffName}`
                : 'Booked by client',
        );
    }
});

test('16 · a visit card opens /schedule for staff and /profile/schedule for the client', async ({
    sessions,
}) => {
    const staff = await sessions.open('sales');
    await openCards(staff.page);
    await staff.page
        .getByTestId('schedule-card')
        .first()
        .getByRole('link')
        .click();
    await staff.page.waitForURL((url) => url.pathname === '/schedule');

    const client = await sessions.open('cardClient');
    await openCards(client.page);
    await client.page
        .getByTestId('schedule-card')
        .first()
        .getByRole('link')
        .click();
    await client.page.waitForURL((url) => url.pathname === '/profile/schedule');
});

test('17 · the cards show the seeded rows, not sample data', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('cardClient');
    await openCards(page);

    const titles = await page
        .getByTestId('schedule-card-title')
        .allTextContents();
    expect(titles.sort()).toEqual(
        [
            ...cards.schedules.map((schedule) => schedule.title),
            cards.longScheduleTitle,
        ].sort(),
    );

    const sublines = await page
        .getByTestId('inquiry-card-subline')
        .allTextContents();
    expect(
        sublines.filter((line) => line.endsWith(cards.reference)),
    ).toHaveLength(6);
    expect(
        sublines.filter((line) => line.endsWith(cards.longReference)),
    ).toHaveLength(1);

    const inquiryTitles = await page
        .getByTestId('inquiry-card-title')
        .allTextContents();
    expect(new Set(inquiryTitles)).toEqual(
        new Set([cards.firstProject, cards.longProject]),
    );
});

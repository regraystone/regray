import type { Locator, Page } from '@playwright/test';
import {
    expect,
    fixtures,
    openConversation,
    shot,
    test,
} from './support/fixtures';

/*
 * D. Inquiry, quotation and schedule cards (R5, R5b). "E2E Cards" holds an
 * inquiry card for each display status, one with a 100-character project name,
 * one for a service-only inquiry, two quotation cards (one with a long file
 * name), a visit card for each status and booking type, and one with a
 * 150-character title.
 */

const HEADLINES: Record<string, Record<'client' | 'staff', string>> = {
    pending: { client: 'Booking pending', staff: 'Booking pending' },
    confirmed: { client: 'Booking approved', staff: 'Appointment booked' },
    served: { client: 'Appointment served', staff: 'Appointment served' },
    cancelled: { client: 'Booking cancelled', staff: 'Booking cancelled' },
    expired: { client: 'Booking expired', staff: 'Booking expired' },
};

/** Load every message of the thread, so all cards are in the DOM. */
const openCards = async (page: Page): Promise<void> => {
    const { cards } = fixtures();
    await openConversation(page, cards.conversationId);
    await expect(page.getByTestId('inquiry-card')).toHaveCount(
        cards.statusCount + 2,
    );
    await expect(page.getByTestId('quotation-card')).toHaveCount(2);
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

    // The service-only card too: the inquiry card is one fixed height.
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
    // The heading is the inquiry's name: a 100-character one truncates on one line.
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

    const longFile = page
        .getByTestId('quotation-card-filename')
        .filter({ hasText: cards.longQuotationFileName });
    await longFile.scrollIntoViewIfNeeded();
    await expect(longFile).toHaveAttribute(
        'title',
        cards.longQuotationFileName,
    );
    const fileMetrics = await longFile.evaluate((node) => ({
        height: node.getBoundingClientRect().height,
        overflows: node.scrollWidth > node.clientWidth,
    }));
    expect(fileMetrics.overflows).toBe(true);
    expect(fileMetrics.height).toBeLessThanOrEqual(
        (await lineHeight(longFile)) + 1,
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

test('14 · the inquiry heading is the project, the reference is the label, the rows are Materials and Service, and the status row is two badges', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('sales');
    await openCards(page);

    const statusCards = page
        .getByTestId('inquiry-card')
        .filter({ hasText: cards.reference });
    await expect(statusCards).toHaveCount(cards.statusCount);

    for (const card of await statusCards.all()) {
        await expect(card.getByTestId('inquiry-card-title')).toHaveText(
            cards.projectName,
        );
        await expect(card.getByTestId('inquiry-card-reference')).toHaveText(
            cards.reference,
        );
        await expect(card.getByTestId('inquiry-card-materials')).toHaveText(
            /Materials\s*1 item$/,
        );
        await expect(card.getByTestId('inquiry-card-service')).toContainText(
            'Service',
        );
        await expect(card).not.toContainText('Application');
        await expect(card).not.toContainText('Estimated Area');
        // The inquiry status, then the payment status once accounting's stage
        // had started: before the client's approval there is no payment badge.
        const status = card.getByTestId('inquiry-card-status');
        const shown = await status
            .locator('[data-status-group="inquiry"]')
            .getAttribute('data-status');
        const hasPayment = [
            'quoted',
            'closed',
            'order_received',
            'measure',
            'sourcing',
            'production',
            'quality_check',
            'ready',
            'dispatched',
            'installation',
            'delayed',
        ].includes(shown ?? '');

        await expect(
            status.locator('[data-status-group="payment"]'),
        ).toHaveCount(hasPayment ? 1 : 0);
        await expect(status.locator('[data-status]')).toHaveCount(
            hasPayment ? 2 : 1,
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

    const inquiryReferences = await page
        .getByTestId('inquiry-card-reference')
        .allTextContents();
    expect(
        inquiryReferences.filter((reference) => reference === cards.reference),
    ).toHaveLength(cards.statusCount);
    expect(
        inquiryReferences.filter(
            (reference) => reference === cards.longReference,
        ),
    ).toHaveLength(1);

    const fileNames = await page
        .getByTestId('quotation-card-filename')
        .allTextContents();
    expect(fileNames.sort()).toEqual(
        [cards.quotation.fileName, cards.longQuotationFileName].sort(),
    );
});

/** The quotation card for the Kitchen island inquiry's document. */
const quotationCard = (page: Page) =>
    page.getByTestId('quotation-card').filter({
        has: page
            .getByTestId('quotation-card-filename')
            .getByText(fixtures().cards.quotation.fileName, { exact: true }),
    });

test('18 · a quotation card has the file, the sub-line, the size, and exactly View PDF and Download', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('cardClient');
    await openCards(page);

    const card = quotationCard(page);
    await card.scrollIntoViewIfNeeded();

    await expect(card.getByTestId('card-headline')).toHaveText('Quotation');
    await expect(card.getByTestId('quotation-card-subline')).toHaveText(
        `1 material · ${cards.projectName}`,
    );
    await expect(card.getByTestId('quotation-card-size')).toHaveText(/^\d+ B$/);
    // The PDF icon, never a picture of the document.
    await expect(card.getByTestId('quotation-card-preview')).toHaveAttribute(
        'aria-hidden',
        'true',
    );
    await expect(card.locator('img')).toHaveCount(0);

    await expect(card.getByRole('link')).toHaveCount(2);
    await expect(card.getByRole('button')).toHaveCount(0);
    await expect(
        card.getByRole('link', {
            name: `View PDF ${cards.quotation.fileName} (opens in new tab)`,
        }),
    ).toHaveAttribute('target', '_blank');
    await expect(
        card.getByRole('link', {
            name: new RegExp(`^Download ${cards.quotation.fileName}, `),
        }),
    ).toHaveAttribute('download', cards.quotation.fileName);
    await expect(card).not.toContainText('Valid for');
    await expect(card).not.toContainText('Request Revision');
});

test('19 · View PDF opens the document in a new tab and Download saves it', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('cardClient');
    await openCards(page);

    const card = quotationCard(page);
    await card.scrollIntoViewIfNeeded();

    const [tab] = await Promise.all([
        page.context().waitForEvent('page'),
        card.getByTestId('quotation-card-view').click(),
    ]);
    await tab.waitForLoadState('domcontentloaded');
    expect(new URL(tab.url()).pathname).toContain(
        `/files/${cards.quotation.storedName}`,
    );
    await tab.close();

    const [download] = await Promise.all([
        page.waitForEvent('download'),
        card.getByTestId('quotation-card-download').click(),
    ]);
    expect(download.suggestedFilename()).toBe(cards.quotation.fileName);
    expect(new URL(download.url()).searchParams.get('download')).toBe('1');
});

test('20 · a service-only inquiry card shows the service in place of pictures and no Materials row', async ({
    sessions,
}) => {
    const { cards } = fixtures();
    const { page } = await sessions.open('sales');
    await openCards(page);

    const card = page
        .getByTestId('inquiry-card')
        .filter({ hasText: cards.serviceOnlyReference });
    await card.scrollIntoViewIfNeeded();

    await expect(card.getByTestId('inquiry-card-service-tile')).toHaveText(
        cards.serviceName,
    );
    await expect(card.getByTestId('inquiry-card-stones')).toHaveCount(0);
    await expect(card.getByTestId('inquiry-card-materials')).toHaveCount(0);
    await expect(card).not.toContainText('0 items');
    await expect(card.getByTestId('inquiry-card-service')).toContainText(
        cards.serviceName,
    );
});

/**
 * The card is one row once it has room for it (a 512px container, `@lg`),
 * whatever the window: at 768 the thread pane beside the list is narrower.
 */
const ROW_FROM = 512;

test('21 · inquiry and quotation cards stack in a narrow thread and sit in one row in a wide one, never scrolling sideways', async ({
    sessions,
}, testInfo) => {
    const { page } = await sessions.open('sales');

    for (const width of [375, 768, 1280]) {
        await page.setViewportSize({ width, height: 900 });
        await openCards(page);

        const inquiry = page
            .getByTestId('inquiry-card')
            .filter({ has: page.getByTestId('inquiry-card-materials') })
            .first();
        const quotation = quotationCard(page);

        for (const card of [inquiry, quotation]) {
            await card.scrollIntoViewIfNeeded();
            const surface = card.locator(':scope > :nth-child(2)');
            const [box, media, details] = await Promise.all([
                card.boundingBox(),
                surface.locator(':scope > :nth-child(1)').boundingBox(),
                surface.locator(':scope > :nth-child(2)').boundingBox(),
            ]);
            expect(media).not.toBeNull();
            expect(details).not.toBeNull();

            if (box!.width < ROW_FROM) {
                // Stacked: the media on top, the details under it.
                expect(details!.y).toBeGreaterThanOrEqual(
                    media!.y + media!.height - 1,
                );
            } else {
                // One row: the media on the left, the details beside it.
                expect(details!.x).toBeGreaterThanOrEqual(
                    media!.x + media!.width - 1,
                );
                expect(Math.abs(details!.y - media!.y)).toBeLessThanOrEqual(1);
            }

            const overflows = await card.evaluate(
                (node) => node.scrollWidth > node.clientWidth + 1,
            );
            expect(overflows, `card overflows at ${width}px`).toBe(false);
        }

        // One family: both cards are the same width.
        const [inquiryBox, quotationBox] = await Promise.all([
            inquiry.boundingBox(),
            quotation.boundingBox(),
        ]);
        expect(Math.round(inquiryBox!.width)).toBe(
            Math.round(quotationBox!.width),
        );

        const scrolls = await page
            .getByTestId('thread-scroll')
            .evaluate((node) => node.scrollWidth > node.clientWidth + 1);
        expect(scrolls, `thread scrolls sideways at ${width}px`).toBe(false);

        await shot(page, `21-cards-${width}`, testInfo.project.name);
    }
});

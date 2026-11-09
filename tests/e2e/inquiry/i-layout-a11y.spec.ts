import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, shot, test } from '../support/fixtures';
import {
    CATALOG,
    QUESTIONS,
    REVIEW,
    answerStoneRow,
    answerDeadline,
    chooseLocation,
    expectStep,
    fillSlabSizes,
    futureDate,
    hasHorizontalScroll,
    openInquire,
    pickService,
    pickStone,
    pressNext,
    sendAndReadReference,
    stepper,
    walkToStones,
} from '../support/inquiry-flow';

/* I. Layout, keyboard and accessibility. */

/**
 * Walk a project of stone through every step and run `check` on each one,
 * ending on the result panel.
 */
const walkEveryStep = async (
    page: Page,
    check: (label: string) => Promise<void>,
): Promise<void> => {
    await openInquire(page);
    await pickService(page);
    await check(QUESTIONS[0]);

    await pressNext(page, 1);
    await check(QUESTIONS[1]);
    await pickStone(page, CATALOG.stones[0]);

    await pressNext(page, 2);
    await answerStoneRow(page, 0, {
        quantity: 2,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await fillSlabSizes(page);
    await chooseLocation(page, 'Las Piñas');
    await answerDeadline(page);
    await check(QUESTIONS[2]);
    await page.getByRole('button', { name: 'Continue to review' }).click();

    await expectStep(page, REVIEW);
    await check('Review');
    await sendAndReadReference(page);
    await check('Result');
};

test('34 · no horizontal page scroll on any step @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;

    await walkEveryStep(page, async (label) => {
        const scrolls = await hasHorizontalScroll(page);

        if (scrolls) {
            await shot(page, `34-${label.toLowerCase()}-${width}`, testInfo);
        }

        expect
            .soft(scrolls, `${label} at ${width}px scrolls sideways`)
            .toBe(false);
    });
});

test('35 · at phone width the stepper is the one-line "Step N of 2 · label", not the rail @mobile', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    await expect(stepper(page).locator('p')).toBeVisible();
    await expect(stepper(page).locator('p')).toHaveText(
        'Step 1 of 2 · Your request',
    );
    await expect(stepper(page).locator('ol')).toBeHidden();

    await walkToStones(page);
    await expect(stepper(page).locator('p')).toHaveText(
        'Step 1 of 2 · Your request',
    );
    await expect(stepper(page).locator('ol')).toBeHidden();
});

/** What has focus, and whether the browser draws a focus ring for it. */
const focused = (page: Page) =>
    page.evaluate(() => {
        const element = document.activeElement as HTMLElement | null;

        return {
            name:
                element?.getAttribute('aria-label') ??
                element?.textContent?.trim() ??
                '',
            id: element?.id ?? '',
            tag: element?.tagName ?? '',
            visible: element?.matches(':focus-visible') ?? false,
        };
    });

/** Press Tab until focus is on the control `matches` describes. */
const tabTo = async (
    page: Page,
    matches: (target: { name: string; id: string; tag: string }) => boolean,
    description: string,
): Promise<void> => {
    // Every stone on the page is a stop or two on the way to the action bar.
    for (let presses = 0; presses < 300; presses += 1) {
        await page.keyboard.press('Tab');
        const target = await focused(page);

        if (matches(target)) {
            expect(target.visible, `focus ring on ${description}`).toBe(true);

            return;
        }
    }

    throw new Error(`Tab never reached ${description}.`);
};

test('36 · keyboard only: choose the service, pick a stone, fill the details and send, with focus visible throughout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    for (const [id, label] of [
        ['inquiry-service-type', 'the service type'],
        ['inquiry-service', 'the service'],
    ] as const) {
        await tabTo(page, (t) => t.id === id, label);
        await page.keyboard.press('Enter');
        await expect(page.getByRole('option').first()).toBeVisible();
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await expect(page.getByRole('option')).toHaveCount(0);
    }

    // Next shows the stones and moves focus to their heading.
    await tabTo(page, (t) => t.name === 'Next: Which stones?', 'Next');
    await page.keyboard.press('Enter');
    await expectStep(page, 2);
    await expect(
        page.getByRole('heading', { name: QUESTIONS[1] }),
    ).toBeFocused();

    await tabTo(page, (t) => t.name === CATALOG.stones[0], 'the first stone');
    await page.keyboard.press('Space');
    await expect(
        page.getByRole('checkbox', { name: CATALOG.stones[0], exact: true }),
    ).toHaveAttribute('aria-checked', 'true');

    await tabTo(page, (t) => t.name === 'Next: Other details', 'Next');
    await page.keyboard.press('Enter');
    await expectStep(page, 3);

    await tabTo(page, (t) => t.id === 'requirements-slab-size-0', 'Slab size');
    await page.keyboard.type('120 x 60 cm');

    await tabTo(
        page,
        (t) => t.id.startsWith('requirements-location'),
        'Location',
    );
    await page.keyboard.type('Makat');
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('combobox', { name: /^Location/ })).toHaveValue(
        'Makati',
    );

    const [year, month, day] = futureDate(9).split('-');
    await tabTo(
        page,
        (t) =>
            t.id.startsWith('requirements-needed-by-') &&
            !t.id.includes('time'),
        'the date',
    );
    await page.keyboard.type(`${month}${day}${year}`);

    await tabTo(page, (t) => t.name === 'Continue to review', 'the next step');
    await page.keyboard.press('Enter');
    await expectStep(page, REVIEW);

    await tabTo(page, (t) => t.name === 'Send my request', 'Send my request');
    await page.keyboard.press('Enter');
    await expect(
        page.getByRole('heading', { name: 'Quote requested' }),
    ).toBeVisible();
});

test('36a · the stone type filter follows the width: a dropdown from md up, a scrolling row of pills below it @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;

    await openInquire(page);
    await walkToStones(page);

    const select = page.getByTestId('stone-type-select');
    const pills = page.getByTestId('stone-type-pills');

    await shot(page, `36a-stones-${width}`, testInfo);

    if (width >= 768) {
        await expect(select).toBeVisible();
        await expect(pills).toBeHidden();
    } else {
        await expect(select).toBeHidden();
        await expect(pills).toBeVisible();
        await expect(pills).toHaveAttribute('tabindex', '0');
        await expect(pills.getByRole('button').first()).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(
            await pills.evaluate((row) => row.scrollWidth > row.clientWidth),
            'the pills run past the edge and scroll sideways',
        ).toBe(true);
        expect(await hasHorizontalScroll(page)).toBe(false);
    }
});

test('37 · axe finds no serious or critical violations on any step', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await walkEveryStep(page, async (label) => {
        const { violations } = await new AxeBuilder({ page }).analyze();
        const severe = violations
            .filter(
                ({ impact }) => impact === 'serious' || impact === 'critical',
            )
            .map(
                ({ id, impact, nodes }) =>
                    `${impact} ${id}: ${nodes
                        .slice(0, 3)
                        .map(
                            (node) =>
                                `${node.target.join(' ')} ${node.html.slice(0, 160)} ${node.failureSummary ?? ''}`,
                        )
                        .join(' | ')}`,
            );

        expect.soft(severe, `axe on ${label}`).toEqual([]);
    });
});

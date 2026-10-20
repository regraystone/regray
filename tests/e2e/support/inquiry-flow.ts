import type { Locator, Page } from '@playwright/test';
import { expect } from './fixtures';

/*
 * The Inquire page, step by step, through the controls a reader uses: the
 * service, stone and application cards are checkboxes named after what they
 * pick, the location is a combobox, edge and finish are selects.
 *
 * The names below are the catalogue that DatabaseSeeder writes.
 */

export const CATALOG = {
    visitService: 'Site Ocular & Technical Assessment',
    otherVisitServices: [
        'Stone Relocation, Dismantling & Reinstallation',
        'Post-Installation Quality Care',
    ],
    stoneService: 'Natural Stone Supply',
    stoneServiceSlug: 'natural-stone-supply',
    stones: ['Warm Beige Granite', 'Flamed - Gray'],
    stoneSlug: 'warm-beige-granite',
    applications: ['Kitchen Islands', 'Countertops & Backsplash'],
    applicationSlug: 'kitchen-islands',
    edges: ['Straight Edge', 'Full Bevel'],
    adviseEdge: 'Not sure yet, advise me',
    finishes: ['Polished', 'Honed'],
} as const;

export const STEP_LABELS = [
    'Services',
    'Stones',
    'Application',
    'Requirements',
    'Review',
] as const;

/** A date `days` from today in the browser's own calendar, as YYYY-MM-DD. */
export const futureDate = (days: number): string => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
};

export const stepper = (page: Page): Locator =>
    page.getByRole('navigation', { name: 'Inquiry progress' });

/** The one-line "Step N of 5 · Label", present at every width. */
export const expectStep = async (page: Page, step: number): Promise<void> => {
    await expect(stepper(page).locator('p')).toHaveText(
        `Step ${step} of 5 · ${STEP_LABELS[step - 1]}`,
    );
};

/** Open the Inquire page and wait for the skeleton to give way to step 1. */
export const openInquire = async (
    page: Page,
    url = '/quote/inquire',
): Promise<void> => {
    await page.goto(url);
    await expect(
        page.getByRole('heading', { name: 'Our services' }),
    ).toBeVisible();
};

export const checkbox = (page: Page, name: string): Locator =>
    page.getByRole('checkbox', { name, exact: true });

export const continueButton = (page: Page): Locator =>
    page.getByRole('button', { name: 'Continue', exact: true });

export const pickService = async (page: Page, name: string): Promise<void> => {
    await checkbox(page, name).click();
    await expect(checkbox(page, name)).toHaveAttribute('aria-checked', 'true');
};

export const pickStone = async (page: Page, name: string): Promise<void> => {
    const search = page.getByRole('searchbox', { name: 'Search stones' });
    await search.fill(name);
    await checkbox(page, name).click();
    await expect(checkbox(page, name)).toHaveAttribute('aria-checked', 'true');
    await search.fill('');
};

export const pickApplication = async (
    page: Page,
    name: string,
): Promise<void> => {
    await checkbox(page, name).click();
    await expect(checkbox(page, name)).toHaveAttribute('aria-checked', 'true');
};

export const locationField = (page: Page): Locator =>
    page.getByRole('combobox', { name: /^Location/ });

export const chooseLocation = async (
    page: Page,
    city: string,
): Promise<void> => {
    const field = locationField(page);
    await field.fill(city.slice(0, 4));
    await page.getByRole('option', { name: city, exact: true }).click();
    await expect(field).toHaveValue(city);
};

/** Pick `option` in the Radix select whose trigger has `id`. */
export const chooseInSelect = async (
    page: Page,
    id: string,
    option: string,
): Promise<void> => {
    await page.locator(`#${id}`).click();
    await page.getByRole('option', { name: option, exact: true }).click();
    await expect(page.locator(`#${id}`)).toContainText(option);
};

export type StoneRowAnswer = {
    quantity?: number;
    edge: string;
    finish: string;
};

export const answerStoneRow = async (
    page: Page,
    index: number,
    answer: StoneRowAnswer,
): Promise<void> => {
    if (answer.quantity !== undefined) {
        await page
            .locator(`#requirements-quantity-${index}`)
            .fill(String(answer.quantity));
    }

    await chooseInSelect(page, `requirements-edge-${index}`, answer.edge);
    await chooseInSelect(page, `requirements-finish-${index}`, answer.finish);
};

export const deadlineDate = (page: Page): Locator =>
    page.getByLabel(/^Target deadline/);

export const deadlineTime = (page: Page): Locator =>
    page.getByLabel('Time of day');

export type VisitAnswers = {
    service?: string;
    location?: string;
    landmark?: string;
    date?: string;
    time?: string;
    notes?: string;
};

/**
 * A standalone visit from step 1 to Review: the service, Stones and
 * Application left empty, then the site and the slot.
 */
export const addVisitProject = async (
    page: Page,
    answers: VisitAnswers = {},
): Promise<void> => {
    await pickService(page, answers.service ?? CATALOG.visitService);
    await continueButton(page).click();
    await expectStep(page, 2);
    await continueButton(page).click();
    await expectStep(page, 3);
    await continueButton(page).click();
    await expectStep(page, 4);
    await chooseLocation(page, answers.location ?? 'Makati');

    if (answers.landmark !== undefined) {
        await page.getByLabel('Landmark').fill(answers.landmark);
    }

    await deadlineDate(page).fill(answers.date ?? futureDate(10));
    await deadlineTime(page).fill(answers.time ?? '10:00');

    if (answers.notes !== undefined) {
        await page.getByLabel('Additional notes').fill(answers.notes);
    }

    await page.getByRole('button', { name: 'Add to inquiry' }).click();
    await expectStep(page, 5);
};

export type StoneProjectAnswers = {
    name?: string;
    stones?: string[];
    applications?: string[];
    rows?: StoneRowAnswer[];
    location?: string;
    landmark?: string;
    notes?: string;
};

/** A project of stone from step 1 up to the Requirements form, not committed. */
export const walkToRequirements = async (
    page: Page,
    answers: StoneProjectAnswers = {},
): Promise<void> => {
    await pickService(page, CATALOG.stoneService);
    await continueButton(page).click();
    await expectStep(page, 2);

    for (const stone of answers.stones ?? [CATALOG.stones[0]]) {
        await pickStone(page, stone);
    }

    await continueButton(page).click();
    await expectStep(page, 3);

    for (const application of answers.applications ?? [
        CATALOG.applications[0],
    ]) {
        await pickApplication(page, application);
    }

    await continueButton(page).click();
    await expectStep(page, 4);
};

/** A project of stone from step 1 to Review. */
export const addStoneProject = async (
    page: Page,
    answers: StoneProjectAnswers = {},
): Promise<void> => {
    await walkToRequirements(page, answers);
    await page
        .getByLabel('Project name')
        .fill(answers.name ?? 'Kitchen counters');

    const rows = answers.rows ?? [
        { quantity: 4, edge: CATALOG.edges[0], finish: CATALOG.finishes[0] },
    ];

    for (const [index, row] of rows.entries()) {
        await answerStoneRow(page, index, row);
    }

    await chooseLocation(page, answers.location ?? 'Makati');

    if (answers.landmark !== undefined) {
        await page.getByLabel('Landmark').fill(answers.landmark);
    }

    if (answers.notes !== undefined) {
        await page.getByLabel('Additional notes').fill(answers.notes);
    }

    await page.getByRole('button', { name: 'Add to inquiry' }).click();
    await expectStep(page, 5);
};

export const requestQuoteButton = (page: Page): Locator =>
    page.getByRole('button', { name: /Request a Quote|Sending/ });

/** Press Request a Quote and return the reference the result panel shows. */
export const sendAndReadReference = async (page: Page): Promise<string> => {
    await requestQuoteButton(page).click();
    await expect(
        page.getByRole('heading', { name: 'Quote requested' }),
    ).toBeVisible();
    const reference = await page
        .getByRole('status')
        .filter({ hasText: 'Your reference is' })
        .locator('span.font-bold')
        .innerText();

    expect(reference).toMatch(/^INQ-/);

    return reference;
};

/** A valid project in the shape the page posts, for requests that bypass it. */
export const projectPayload = (
    overrides: Record<string, unknown> = {},
): Record<string, unknown> => ({
    name: 'Bypass project',
    serviceIds: [CATALOG.stoneServiceSlug],
    applicationIds: [CATALOG.applicationSlug],
    stones: [
        {
            stoneId: CATALOG.stoneSlug,
            quantity: 4,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        },
    ],
    location: 'Makati',
    landmark: '',
    areaSqm: '',
    neededBy: futureDate(10),
    neededByTime: '',
    notes: '',
    ...overrides,
});

/** Whether the page scrolls sideways. */
export const hasHorizontalScroll = (page: Page): Promise<boolean> =>
    page.evaluate(
        () =>
            document.documentElement.scrollWidth >
            document.documentElement.clientWidth,
    );

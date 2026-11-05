import type { Locator, Page } from '@playwright/test';
import { expect } from './fixtures';

/*
 * The Inquire page through the controls a reader uses: two steps, the first being
 * four questions, one open at a time. The service, stone and application cards
 * are checkboxes named after what they pick, the location is a combobox, edge and
 * finish are selects that stay folded away until asked for.
 *
 * The names below are the catalogue that DatabaseSeeder writes.
 */

export const CATALOG = {
    visitService: 'Stone Relocation, Dismantling & Reinstallation',
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

/** The four questions of the first step, in order. Number 5 means Review. */
export const QUESTIONS = [
    'What do you need?',
    'Which stones do you like?',
    'Where will the stone be used?',
    'A few last details',
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

/** Where the reader is: which of the two steps, and which question is open. */
export const expectStep = async (page: Page, step: number): Promise<void> => {
    if (step === 5) {
        await expect(stepper(page).locator('p')).toHaveText(
            'Step 2 of 2 · Review and send',
        );

        return;
    }

    await expect(stepper(page).locator('p')).toHaveText(
        'Step 1 of 2 · Your request',
    );
    await expect(page.locator('[data-state="open"] h2')).toHaveText(
        QUESTIONS[step - 1],
    );
};

export const changeQuestion = (page: Page, step: number): Locator =>
    page.getByRole('button', { name: `Change: ${QUESTIONS[step - 1]}` });

/** Open the Inquire page and wait for the skeleton to give way to step 1. */
export const openInquire = async (
    page: Page,
    url = '/dashboard/quote/inquire',
): Promise<void> => {
    await page.goto(url);
    await expect(
        page.getByRole('heading', { name: QUESTIONS[0] }),
    ).toBeVisible();
};

/**
 * Staff open the stepper on the Platform branch, for a lead's client, the way
 * the Leads page's "Create inquiry" does: past the modal, the account and the
 * lead. LEAD-0007 is marketing's idle lead in the demo seed.
 */
export const openStaffInquire = async (
    page: Page,
    lead = 'LEAD-0007',
): Promise<void> => {
    await page.goto(`/dashboard/inquiry/inquire?kind=platform&lead=${lead}`);
    await expect(
        page.getByRole('heading', { name: QUESTIONS[0] }),
    ).toBeVisible();
    await expect(page.getByTestId('inquire-target')).toContainText(lead);
};

export const checkbox = (page: Page, name: string): Locator =>
    page.getByRole('checkbox', { name, exact: true });

export const continueButton = (page: Page): Locator =>
    page.getByRole('button', { name: 'Next', exact: true });

export const continueToReview = (page: Page): Locator =>
    page.getByRole('button', { name: 'Continue to review' });

/**
 * Tick a service and take the first kind of work in the dropdown it opens
 * (`kind` to pick another), which the question needs before Next.
 */
export const pickService = async (
    page: Page,
    name: string,
    kind?: string,
): Promise<void> => {
    await checkbox(page, name).click();
    await expect(checkbox(page, name)).toHaveAttribute('aria-checked', 'true');

    const dropdown = checkbox(page, name)
        .locator('xpath=..')
        .getByTestId('service-option');
    await dropdown.click();
    const option =
        kind === undefined
            ? page.getByRole('option').first()
            : page.getByRole('option', { name: kind, exact: true });
    await option.click();
    await expect(dropdown).not.toContainText('Choose one');
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

/** Unfold the optional answers: landmark, area, files and notes. */
export const openMoreDetails = async (page: Page): Promise<void> => {
    const toggle = page.getByRole('button', {
        name: 'Add more details (optional)',
    });

    if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
        await toggle.click();
    }
};

export const landmarkField = (page: Page): Locator =>
    page.getByLabel('A nearby landmark');

export const notesField = (page: Page): Locator =>
    page.getByLabel('Anything else we should know?');

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

    const edge = page.locator(`#requirements-edge-${index}`);

    if ((await edge.count()) === 0) {
        await page
            .locator('li')
            .filter({ has: page.locator(`#requirements-quantity-${index}`) })
            .getByRole('button', { name: /^Choose edge and finish/ })
            .click();
    }

    await chooseInSelect(page, `requirements-edge-${index}`, answer.edge);
    await chooseInSelect(page, `requirements-finish-${index}`, answer.finish);
};

export const deadlineDate = (page: Page): Locator =>
    page.getByLabel(/^When do you need it/);

export const deadlineTime = (page: Page): Locator =>
    page.getByLabel('Time of day');

/**
 * Every project needs a date: sending books it as a schedule. Only a visit
 * shows the time field.
 */
export const answerDeadline = async (
    page: Page,
    date: string = futureDate(10),
    time = '10:00',
): Promise<void> => {
    await deadlineDate(page).fill(date);

    if ((await deadlineTime(page).count()) > 0) {
        await deadlineTime(page).fill(time);
    }
};

export type VisitAnswers = {
    service?: string;
    location?: string;
    landmark?: string;
    date?: string;
    time?: string;
    notes?: string;
};

/**
 * A standalone visit from the first question to Review: the service, then the
 * site and the slot. A visit is not asked about stones or where they are used.
 */
export const addVisitProject = async (
    page: Page,
    answers: VisitAnswers = {},
): Promise<void> => {
    await pickService(page, answers.service ?? CATALOG.visitService);
    await continueButton(page).click();
    await expectStep(page, 4);
    await chooseLocation(page, answers.location ?? 'Makati');

    if (answers.landmark !== undefined) {
        await openMoreDetails(page);
        await landmarkField(page).fill(answers.landmark);
    }

    await deadlineDate(page).fill(answers.date ?? futureDate(10));
    await deadlineTime(page).fill(answers.time ?? '10:00');

    if (answers.notes !== undefined) {
        await openMoreDetails(page);
        await notesField(page).fill(answers.notes);
    }

    await continueToReview(page).click();
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

/** A project of stone from the first question up to the last, not committed. */
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

/**
 * A project of stone from the first question to Review. It is named automatically,
 * so a `name` is given the way the reader gives one: on Review.
 */
export const addStoneProject = async (
    page: Page,
    answers: StoneProjectAnswers = {},
): Promise<void> => {
    await walkToRequirements(page, answers);

    const rows = answers.rows ?? [
        { quantity: 4, edge: CATALOG.edges[0], finish: CATALOG.finishes[0] },
    ];

    for (const [index, row] of rows.entries()) {
        await answerStoneRow(page, index, row);
    }

    await chooseLocation(page, answers.location ?? 'Makati');
    await answerDeadline(page);

    if (answers.landmark !== undefined) {
        await openMoreDetails(page);
        await landmarkField(page).fill(answers.landmark);
    }

    if (answers.notes !== undefined) {
        await openMoreDetails(page);
        await notesField(page).fill(answers.notes);
    }

    await continueToReview(page).click();
    await expectStep(page, 5);

    if (answers.name !== undefined) {
        await renameProject(page, answers.name);
    }
};

/** Give the project on Review a name of the reader's own. */
export const renameProject = async (
    page: Page,
    name: string,
): Promise<void> => {
    await page.getByRole('button', { name: /^Change the name of / }).click();
    await page.getByLabel('Project name').fill(name);
    await page.getByRole('button', { name: 'Save name' }).click();
    await expect(page.getByText(name, { exact: true }).first()).toBeVisible();
};

export const requestQuoteButton = (page: Page): Locator =>
    page.getByRole('button', { name: /Send my request|Sending/ });

/** Press Send my request and return the reference the result panel shows. */
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
    serviceOptions: { [CATALOG.stoneServiceSlug]: 'supply-only' },
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

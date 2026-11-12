import type { Locator, Page } from '@playwright/test';
import { artisan } from '../message/support/stack';
import { expect } from './fixtures';

/*
 * The Inquire page through the controls a reader uses: two steps, the first being
 * three questions on one page, shown one by one: Next under the last one shown
 * adds the next, and every question shown stays open. The service type, the service and the delivery
 * method are selects, the stone cards are checkboxes named after the stone, the
 * location is a combobox, and what the service asks of each stone sits on its
 * row (Slab Supply Only: a slab size, edge and finish, each a dropdown; a slab
 * size also takes Other with an entry of one's own). The service's own
 * questions sit under Other details, with the optional ones, the files and
 * the notes always on show under the required ones.
 *
 * The names below are the catalogue that DatabaseSeeder writes. Both stones are
 * offered for Slab Supply Only and for Dining Tables: a granite and a marble.
 * Dining Tables asks for the number of seaters and the table base besides.
 */

export const CATALOG = {
    serviceType: 'Natural Stone & Applications',
    service: 'Slab Supply Only',
    serviceSlug: 'slab-supply-only',
    furnitureType: 'Custom Stone Furniture',
    furniture: 'Dining Tables',
    furnitureSlug: 'dining-tables',
    stones: ['Cosmic Black', 'Arabescato White'],
    stoneSlug: 'cosmic-black',
    edges: ['Straight Edge', 'Full Bevel'],
    adviseEdge: 'Not sure yet, advise me',
    finishes: ['Polished', 'Honed'],
} as const;

/** The three questions of the first step, in order. Number 4 means Review. */
export const QUESTIONS = [
    'What do you need?',
    'Which stones?',
    'Other details',
] as const;

export const REVIEW = 4;

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

/** Where the reader is: which of the two steps, and that the question is there. */
export const expectStep = async (page: Page, step: number): Promise<void> => {
    if (step === REVIEW) {
        await expect(stepper(page).locator('p')).toHaveText(
            'Step 2 of 2 · Review and send',
        );

        return;
    }

    await expect(stepper(page).locator('p')).toHaveText(
        'Step 1 of 2 · Your request',
    );
    await expect(
        page.getByRole('heading', { name: QUESTIONS[step - 1] }),
    ).toBeVisible();
};

/**
 * Open the Inquire page and wait for the skeleton to give way to step 1. The suite
 * sends far faster than a person, so the app's limit on sending starts afresh.
 */
export const openInquire = async (
    page: Page,
    url = '/dashboard/quote/inquire',
): Promise<void> => {
    artisan('cache:clear');
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

export const continueToReview = (page: Page): Locator =>
    page.getByRole('button', { name: 'Continue to review' });

/** Next, under the last question shown, named after the question it shows. */
export const nextButton = (page: Page): Locator =>
    page.getByRole('button', { name: /^Next: / });

/** Press Next under question `from` and see the one after it appear. */
export const pressNext = async (page: Page, from: number): Promise<void> => {
    await nextButton(page).click();
    await expectStep(page, from + 1);
};

export const SERVICE_TYPE_ID = 'inquiry-service-type';
export const SERVICE_ID = 'inquiry-service';

/** Answer the first question: a service type, then one of its services. */
export const pickService = async (
    page: Page,
    serviceType: string = CATALOG.serviceType,
    service: string = CATALOG.service,
): Promise<void> => {
    await chooseInSelect(page, SERVICE_TYPE_ID, serviceType);
    await chooseInSelect(page, SERVICE_ID, service);
};

/** The first question answered and Next pressed; the stones are under it. */
export const walkToStones = async (
    page: Page,
    serviceType?: string,
    service?: string,
): Promise<void> => {
    await pickService(page, serviceType, service);
    await pressNext(page, 1);
};

export const pickStone = async (page: Page, name: string): Promise<void> => {
    const search = page.getByRole('searchbox', { name: 'Search stones' });
    await search.fill(name);
    await checkbox(page, name).click();
    await expect(checkbox(page, name)).toHaveAttribute('aria-checked', 'true');
    await search.fill('');
};

export const DELIVERY_METHOD_ID = 'inquiry-delivery-method';

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

export const notesField = (page: Page): Locator =>
    page.getByLabel('Anything else we should know?');

export const SLAB_SIZE = '120 x 60 cm';

/**
 * Every stone row of a service that asks for a slab size needs one: pick
 * `size`, one of the suggestions, on each row still unanswered.
 */
export const fillSlabSizes = async (
    page: Page,
    size: string = SLAB_SIZE,
): Promise<void> => {
    const triggers = page.locator(
        'button[id^="requirements-slab-size-"]:not([id$="-custom"])',
    );

    for (const trigger of await triggers.all()) {
        if ((await trigger.innerText()).trim() === 'Choose one') {
            const id = await trigger.getAttribute('id');

            if (id !== null) {
                await chooseInSelect(page, id, size);
            }
        }
    }
};

/** A slab size of the reader's own, through Other. */
export const typeSlabSize = async (
    page: Page,
    index: number,
    size: string,
): Promise<void> => {
    await chooseInSelect(page, `requirements-slab-size-${index}`, 'Other');
    await page.locator(`#requirements-slab-size-${index}-custom`).fill(size);
};

/**
 * Answer one of the service's own questions under Other details, by the slug
 * in its id (`seaters`, `table-base`, `area-sqm`): pick `answer` from its
 * list, and through Other when it is a value of one's own.
 */
export const answerQuestion = async (
    page: Page,
    slug: string,
    answer: string,
    { own = false }: { own?: boolean } = {},
): Promise<void> => {
    const id = `requirements-${slug}-1`;

    if (!own) {
        await chooseInSelect(page, id, answer);

        return;
    }

    await chooseInSelect(page, id, 'Other');
    await page.locator(`#${id}-custom`).fill(answer);
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
    page.getByLabel(/^When do you need it/);

/** Every project needs a date: sending books it as a schedule. */
export const answerDeadline = async (
    page: Page,
    date: string = futureDate(10),
): Promise<void> => {
    await deadlineDate(page).fill(date);
};

export type StoneProjectAnswers = {
    name?: string;
    stones?: string[];
    serviceType?: string;
    service?: string;
    rows?: StoneRowAnswer[];
    location?: string;
    notes?: string;
    /** The service's own questions, by the slug in their id, with a listed answer. */
    questions?: Record<string, string>;
};

/** A project of stone from the first question up to the last, not committed. */
export const walkToRequirements = async (
    page: Page,
    answers: StoneProjectAnswers = {},
): Promise<void> => {
    await walkToStones(page, answers.serviceType, answers.service);

    for (const stone of answers.stones ?? [CATALOG.stones[0]]) {
        await pickStone(page, stone);
    }

    await pressNext(page, 2);
    await fillSlabSizes(page);
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

    for (const [slug, answer] of Object.entries(answers.questions ?? {})) {
        await answerQuestion(page, slug, answer);
    }

    if (answers.notes !== undefined) {
        await notesField(page).fill(answers.notes);
    }

    await continueToReview(page).click();
    await expectStep(page, REVIEW);

    if (answers.name !== undefined) {
        await renameProject(page, answers.name);
    }
};

/** Give the project on Review a name of the reader's own. */
export const renameProject = async (
    page: Page,
    name: string,
): Promise<void> => {
    await page.getByRole('button', { name: /^Change name: / }).click();
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
        .getByRole('dialog')
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
    serviceId: CATALOG.serviceSlug,
    stones: [
        {
            stoneId: CATALOG.stoneSlug,
            quantity: 4,
            slabSize: SLAB_SIZE,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        },
    ],
    deliveryMethod: 'delivery',
    location: 'Makati',
    neededBy: futureDate(10),
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

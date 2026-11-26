import type { Locator, Page } from '@playwright/test';
import { artisan } from '../message/support/stack';
import { inquiryFixtures } from './accounts';
import { expect } from './fixtures';

/*
 * The Inquire page through the controls a reader uses: two steps, the first being
 * three questions on one page, shown one by one: Next under the last one shown
 * adds the next, and every question shown stays open. The service type, the service and the delivery
 * method are selects, the stone cards are checkboxes named after the stone, the
 * location is a combobox, and what the service asks of each stone sits on its
 * row (Slab Supply Only: a slab size, thickness, edge and finish, each a
 * dropdown). The slab size lists the row's stone's own sizes and the
 * thickness the ones that size is cut in, each with Custom size or Custom
 * thickness, which opens a measurement of one's own: Width, Length and a unit for a size
 * (`-width`, `-length`, `-unit`), a value and a unit for a thickness
 * (`-value`, `-unit`). The service's own questions sit under Other details,
 * with the optional ones, the files and the notes always on show under the
 * required ones; no furniture service asks the project area, and Countertop
 * and Backsplash asks fascia and the area.
 *
 * The names below are the catalogue that DatabaseSeeder writes. Both stones are
 * offered for Slab Supply Only and for Dining Tables: a granite and a marble.
 * Dining Tables asks for the project area, the number of seaters and the
 * table base besides. E2EInquirySeeder fixes both stones' slabs, so
 * SLAB_SIZE at THICKNESS is a slab of each:
 * - Cosmic Black: 3.15 x 1.98 m and 160 x 320 cm in 20 mm and 0.75 in,
 *   60 x 120 cm in 20 mm only;
 * - Arabescato White: 3.07 x 2.01 m and 160 x 320 cm in 18 mm and 20 mm,
 *   120 x 240 cm in 18 mm only.
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

/**
 * The brand question, asked before the three below. openInquire() and
 * openStaffInquire() answer it (No preference) and press Next, so a spec
 * starts at What do you need? (question 1) unless it opens with `brands`.
 */
export const BRAND_QUESTION = 'Which brands would you like?';

/** The three questions after the brands, in order. Number 4 means Review. */
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
    { brands = 'answer' }: { brands?: 'answer' | 'leave' } = {},
): Promise<void> => {
    artisan('cache:clear');
    await page.goto(url);
    await expect(
        page.getByRole('heading', { name: BRAND_QUESTION }),
    ).toBeVisible();

    if (brands === 'answer') {
        await answerNoBrandPreference(page);
    }
};

/** A brand card, a toggle named after the brand (or "No preference"). */
export const brandCard = (page: Page, name: string): Locator =>
    page.getByRole('button', { name, exact: true });

/** Answer the brand question with No preference, and go on to What do you need? */
export const answerNoBrandPreference = async (page: Page): Promise<void> => {
    await brandCard(page, 'No preference').click();
    await expect(brandCard(page, 'No preference')).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await nextButton(page).click();
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
    await expect(page.getByTestId('inquire-target')).toContainText(lead);
    await answerNoBrandPreference(page);
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

/**
 * A client's saved addresses on the details question, a radio group named
 * "Delivery address" or "Installation address". E2EInquirySeeder gives client
 * A two: Home (Makati, the default) and Site (Antipolo).
 */
export const locationField = (page: Page): Locator =>
    page.getByRole('radiogroup', { name: /address/ });

/** The saved address a city stands for in the older specs: Makati is Home, any other Site. */
const SAVED_ADDRESS_FOR: Record<string, string> = { Makati: 'Home' };

/**
 * Say how the project is got and where: Delivery (asked only of a service
 * delivered or picked up), then the client's saved address for `city`.
 */
export const chooseLocation = async (
    page: Page,
    city: string,
): Promise<void> => {
    const method = page.locator(`#${DELIVERY_METHOD_ID}`);

    if (
        (await method.count()) > 0 &&
        !(await method.textContent())?.includes('Delivery')
    ) {
        await chooseInSelect(page, DELIVERY_METHOD_ID, 'Delivery');
    }

    const label = SAVED_ADDRESS_FOR[city] ?? 'Site';
    const choice = locationField(page).getByRole('radio', {
        name: new RegExp(`^${label}`),
    });

    await choice.click();
    await expect(choice).toBeChecked();
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

export const SLAB_SIZE = '160 x 320 cm';
export const THICKNESS = '20 mm';

/** A row's dropdown trigger itself, not the unit list of a measurement of one's own. */
const listTriggers = (page: Page, prefix: string): Locator =>
    page.locator(
        `button[id^="${prefix}"]:not([id$="-custom"]):not([id$="-unit"]):not([id$="-value"])`,
    );

/** Pick `option` on every row of `prefix` still unanswered. */
const fillUnanswered = async (
    page: Page,
    prefix: string,
    option: string,
): Promise<void> => {
    for (const trigger of await listTriggers(page, prefix).all()) {
        if ((await trigger.innerText()).trim() === 'Choose one') {
            const id = await trigger.getAttribute('id');

            if (id !== null) {
                await chooseInSelect(page, id, option);
            }
        }
    }
};

/**
 * Every stone row of a service that asks for a slab size needs one: pick
 * `size`, one of the row's stone's sizes, on each row still unanswered.
 */
export const fillSlabSizes = async (
    page: Page,
    size: string = SLAB_SIZE,
): Promise<void> => fillUnanswered(page, 'requirements-slab-size-', size);

/**
 * Every stone row of a service that asks for a thickness needs one: pick
 * `thickness`, one the row's size is cut in, on each row still unanswered.
 * Fill the sizes first, since the list follows the size.
 */
export const fillThicknesses = async (
    page: Page,
    thickness: string = THICKNESS,
): Promise<void> => fillUnanswered(page, 'requirements-thickness-', thickness);

/** A slab size of the reader's own, through Custom size: its width, length and unit. */
export const typeSlabSize = async (
    page: Page,
    index: number,
    width: string,
    length: string,
    unit: 'cm' | 'm' | 'in' | 'ft',
): Promise<void> => {
    const id = `requirements-slab-size-${index}`;

    await chooseInSelect(page, id, 'Custom size');
    await page.locator(`#${id}-width`).fill(width);
    await page.locator(`#${id}-length`).fill(length);
    await chooseInSelect(page, `${id}-unit`, unit);
};

/** A thickness of the reader's own, through Custom thickness: its value and unit. */
export const typeThickness = async (
    page: Page,
    index: number,
    value: string,
    unit: 'mm' | 'cm' | 'in',
): Promise<void> => {
    const id = `requirements-thickness-${index}`;

    await chooseInSelect(page, id, 'Custom thickness');
    await page.locator(`#${id}-value`).fill(value);
    await chooseInSelect(page, `${id}-unit`, unit);
};

/**
 * Answer one of the service's own questions under Other details, by the slug
 * in its id (`seaters`, `fascia`, `area-sqm`): pick `answer` from its
 * list, and through Other when it is a value of one's own.
 */
/** What a question's own answer reads, by the slug in its id (its `customLabel`). */
const OWN_ANSWER_LABELS: Record<string, string> = {
    'area-sqm': 'Custom area',
    seaters: 'Custom number',
    size: 'Custom size',
};

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

    await chooseInSelect(page, id, OWN_ANSWER_LABELS[slug] ?? 'Other');
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
    await fillThicknesses(page);
};

/** The furniture services, measured by their size and seaters, never in square metres. */
export const FURNITURE_SLUGS = [
    'center-tables',
    'side-tables',
    'dining-tables',
    'other-customized-furniture',
] as const;

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

    const questions = answers.questions ?? {};

    for (const [slug, answer] of Object.entries(questions)) {
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
        page.getByRole('heading', { name: "We've received your order" }),
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
            thickness: THICKNESS,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        },
    ],
    brandNoPreference: '1',
    deliveryMethod: 'delivery',
    // Client A's default saved address (Home, Makati).
    addressId: String(
        inquiryFixtures().addresses?.clientA.find(({ isDefault }) => isDefault)
            ?.id ?? '',
    ),
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

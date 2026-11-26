import type { Page } from '@playwright/test';
import { artisan } from '../message/support/stack';
import { inquiryFixtures } from '../support/accounts';
import {
    expect,
    inquiryReferences,
    php,
    postAsUser,
    test,
} from '../support/fixtures';
import {
    CATALOG,
    AREA_FURNITURE_SERVICES,
    SMALL_FURNITURE_SLUGS,
    REVIEW,
    SLAB_SIZE,
    THICKNESS,
    addStoneProject,
    answerDeadline,
    answerQuestion,
    answerStoneRow,
    chooseInSelect,
    chooseLocation,
    continueToReview,
    expectStep,
    fillSlabSizes,
    fillThicknesses,
    hasHorizontalScroll,
    pickStone,
    pressNext,
    projectPayload,
    sendAndReadReference,
    typeSlabSize,
    typeThickness,
    walkToRequirements,
    walkToStones,
} from '../support/inquiry-flow';

/*
 * O. A stone's own slabs on the Inquire page: each row lists the sizes its
 * stone comes in and the thicknesses its chosen size is cut in, with Other
 * for a measurement of one's own; the services that ask a thickness, fascia
 * or the project area; and the profile and brand filters that read the same
 * slabs. E2EInquirySeeder fixes the two catalogue stones' slabs (see
 * support/inquiry-flow.ts); the Crazy Cut and Arctic Pink checks read
 * StoneSeeder's fixed specs.
 */

const COSMIC_SIZES = ['60 x 120 cm', '160 x 320 cm', '3.15 x 1.98 m'];
const COSMIC_THICKNESSES = ['20 mm', '0.75 in'];
const ARABESCATO_SIZES = ['120 x 240 cm', '160 x 320 cm', '3.07 x 2.01 m'];
const ARABESCATO_THICKNESSES = ['18 mm', '20 mm'];

const SLAB_SERVICE_TYPE = CATALOG.serviceType;
const SLAB_AND_INSTALLATION = 'Slab Supply and Installation';
const COUNTERTOP = 'Countertop and Backsplash';

/** What the Radix list whose trigger has `id` offers, in order; closed again after. */
const listedOptions = async (page: Page, id: string): Promise<string[]> => {
    await page.locator(`#${id}`).click();
    const options = await page.getByRole('option').allInnerTexts();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('option')).toHaveCount(0);

    return options.map((option) => option.trim());
};

/**
 * The Inquire page with no draft: a walk through the form saves one, and the
 * next walk in the same test would otherwise reopen it at its last step.
 */
const freshInquire = async (page: Page): Promise<void> => {
    await page.goto('/dashboard/quote');
    await page.evaluate(() => window.localStorage.clear());
    await freshInquire(page);
};

const sizeId = (row: number) => `requirements-slab-size-${row}`;
const thicknessId = (row: number) => `requirements-thickness-${row}`;

/** The stone row of the newest inquiry `reference`, as stored. */
const storedRows = (reference: string) =>
    php<Array<Record<string, string | null>>>(
        `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first()->stones()->orderBy('sort_order')->get(['slab_width', 'slab_length', 'slab_unit', 'thickness', 'thickness_unit'])->map(fn ($row) => $row->getAttributes())`,
    );

/** A project answer of the inquiry, by question slug, as stored. */
const storedAnswer = (reference: string, slug: string) =>
    php<Record<string, string | boolean | null>>(
        `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', '${slug}'))->first(['number_value', 'boolean_value', 'text_value'])?->toArray()`,
    );

/** The first stone of a type, by StoneSeeder's order. */
const firstStoneOf = (type: string) =>
    php<{ slug: string; name: string }>(
        `App\\Models\\Stone::query()->whereHas('type', fn ($query) => $query->where('slug', '${type}'))->orderBy('sort_order')->first(['slug', 'name'])`,
    );

/** Walk a slab service with both catalogue stones, up to their rows. */
const walkToSlabRows = async (page: Page, service = CATALOG.service) => {
    await walkToStones(page, SLAB_SERVICE_TYPE, service);
    await pickStone(page, CATALOG.stones[0]);
    await pickStone(page, CATALOG.stones[1]);
    await pressNext(page, 2);
};

test("54 · on Slab Supply Only, each row lists only its stone's sizes, and thicknesses by the size", async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToSlabRows(page);

    expect(await listedOptions(page, sizeId(0))).toEqual([
        ...COSMIC_SIZES,
        'Other',
    ]);
    expect(await listedOptions(page, sizeId(1))).toEqual([
        ...ARABESCATO_SIZES,
        'Other',
    ]);
    // With no size chosen yet, every thickness of the stone.
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        ...COSMIC_THICKNESSES,
        'Other',
    ]);
    expect(await listedOptions(page, thicknessId(1))).toEqual([
        ...ARABESCATO_THICKNESSES,
        'Other',
    ]);

    await chooseInSelect(page, sizeId(1), '120 x 240 cm');
    expect(await listedOptions(page, thicknessId(1))).toEqual([
        '18 mm',
        'Other',
    ]);
});

test("55 · Other takes a size and thickness of one's own, in other units, and stores them as numbers and units", async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');

    for (const entry of [
        {
            size: ['5', '10', 'ft'] as const,
            thickness: ['1.5', 'in'] as const,
            labels: ['5 x 10 ft', '1.5 in'],
            stored: {
                slab_width: '5.00',
                slab_length: '10.00',
                slab_unit: 'ft',
                thickness: '1.50',
                thickness_unit: 'in',
            },
        },
        {
            size: ['1.6', '3.2', 'm'] as const,
            thickness: ['2', 'cm'] as const,
            labels: ['1.6 x 3.2 m', '2 cm'],
            stored: {
                slab_width: '1.60',
                slab_length: '3.20',
                slab_unit: 'm',
                thickness: '2.00',
                thickness_unit: 'cm',
            },
        },
    ]) {
        await freshInquire(page);
        await walkToStones(page);
        await pickStone(page, CATALOG.stones[0]);
        await pressNext(page, 2);

        await typeSlabSize(page, 0, ...entry.size);
        await typeThickness(page, 0, ...entry.thickness);
        await answerStoneRow(page, 0, {
            quantity: 2,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        });
        await chooseLocation(page, 'Makati');
        await answerDeadline(page);
        await continueToReview(page).click();

        await expectStep(page, REVIEW);
        await expect(
            page.getByText(`Slab size: ${entry.labels[0]}`),
        ).toBeVisible();
        await expect(
            page.getByText(`Thickness: ${entry.labels[1]}`),
        ).toBeVisible();

        const reference = await sendAndReadReference(page);

        expect(storedRows(reference)).toEqual([entry.stored]);
    }
});

test('56 · the thickness is required and has to be a measurement; nothing is saved without one', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    const before = inquiryReferences(clientAId);

    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);
    await fillSlabSizes(page);
    await answerStoneRow(page, 0, {
        quantity: 1,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);

    await continueToReview(page).click();
    await expect(page.locator(`#${thicknessId(0)}-error`)).toHaveText(
        'Enter the thickness.',
    );
    await expect(page.locator(`#${thicknessId(0)}`)).toBeFocused();
    await expectStep(page, 3);

    const [row] = projectPayload().stones as Array<Record<string, unknown>>;
    // The suite sends far faster than a person: each try starts the limit afresh.
    const send = (stone: Record<string, unknown>) => {
        artisan('cache:clear');

        return postAsUser(page, '/dashboard/quote/inquire', {
            projects: [projectPayload({ stones: [stone] })],
        });
    };
    const { thickness: _left, ...noThickness } = row;

    for (const [stone, key] of [
        [noThickness, 'thickness'],
        [{ ...row, thickness: '0 mm' }, 'thickness'],
        [{ ...row, thickness: '20' }, 'thickness'],
        [{ ...row, thickness: '20 ft' }, 'thickness'],
        [{ ...row, thickness: 'abc' }, 'thickness'],
        [{ ...row, thickness: '20.555 mm' }, 'thickness'],
        [{ ...row, slabSize: '160x320' }, 'slabSize'],
        [{ ...row, slabSize: '160 x 320 yd' }, 'slabSize'],
    ] as const) {
        const answer = await send(stone);

        expect(answer.status, JSON.stringify(stone)).toBe(422);
        expect(
            Object.keys((answer.json?.errors ?? {}) as Record<string, unknown>),
        ).toContain(`projects.0.stones.0.${key}`);
    }

    expect(inquiryReferences(clientAId)).toEqual(before);
});

test('57 · Slab Supply and Installation lists the same slabs and sends a thickness', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToSlabRows(page, SLAB_AND_INSTALLATION);

    expect(await listedOptions(page, thicknessId(0))).toEqual([
        ...COSMIC_THICKNESSES,
        'Other',
    ]);
    expect(await listedOptions(page, sizeId(1))).toEqual([
        ...ARABESCATO_SIZES,
        'Other',
    ]);

    await fillSlabSizes(page);
    await fillThicknesses(page);

    for (const index of [0, 1]) {
        await answerStoneRow(page, index, {
            quantity: 1,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        });
    }

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await continueToReview(page).click();
    await expectStep(page, REVIEW);

    const reference = await sendAndReadReference(page);

    expect(storedRows(reference).map((stored) => stored.thickness)).toEqual([
        '20.00',
        '20.00',
    ]);
});

test('58 · services that do not ask a thickness show none and refuse one', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');

    for (const [serviceType, service] of [
        [CATALOG.furnitureType, CATALOG.furniture],
        [SLAB_SERVICE_TYPE, COUNTERTOP],
    ] as const) {
        await freshInquire(page);
        await walkToRequirements(page, { serviceType, service });
        await expect(page.locator(`#${thicknessId(0)}`)).toHaveCount(0);
        await expect(page.locator(`#${sizeId(0)}`)).toHaveCount(0);
    }

    const answer = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [
            projectPayload({
                serviceId: CATALOG.furnitureSlug,
                stones: [
                    {
                        stoneId: CATALOG.stoneSlug,
                        thickness: THICKNESS,
                        edge: CATALOG.edges[0],
                        finish: CATALOG.finishes[0],
                    },
                ],
                answers: { area_sqm: '2', seaters: '6', table_base: 'wood' },
            }),
        ],
    });

    expect(answer.status).toBe(422);
    expect(
        ((answer.json?.errors ?? {}) as Record<string, string[]>)[
            'projects.0.stones.0.thickness'
        ],
    ).toEqual(['This service does not ask for the thickness.']);
});

test('59 · Countertop and Backsplash asks a fascia, yes or no, and needs it', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToRequirements(page, {
        serviceType: SLAB_SERVICE_TYPE,
        service: COUNTERTOP,
    });
    await answerStoneRow(page, 0, {
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await answerQuestion(page, 'area-sqm', '2');

    expect(await listedOptions(page, 'requirements-fascia-1')).toEqual([
        'Yes',
        'No',
    ]);

    await continueToReview(page).click();
    await expect(page.locator('#requirements-fascia-1-error')).toHaveText(
        'Choose the fascia.',
    );
    await expectStep(page, 3);

    await answerQuestion(page, 'fascia', 'Yes');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    await expect(page.getByTestId('review-answers')).toContainText('FasciaYes');

    const reference = await sendAndReadReference(page);

    expect(storedAnswer(reference, 'fascia')?.boolean_value).toBe(true);

    await page.goto(`/dashboard/quote/${reference}`);
    await expect(page.getByTestId('project-answers')).toContainText(
        'FasciaYes',
    );

    const answer = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [
            projectPayload({
                serviceId: 'countertop-backsplash',
                stones: [
                    {
                        stoneId: CATALOG.stoneSlug,
                        edge: CATALOG.edges[0],
                        finish: CATALOG.finishes[0],
                    },
                ],
                answers: { area_sqm: '2' },
            }),
        ],
    });

    expect(answer.status).toBe(422);
    expect(Object.keys(answer.json?.errors ?? {})).toContain(
        'projects.0.answers.fascia',
    );
});

test('60 · the larger furniture asks "Project area (sqm)" and needs it; a small piece never asks it', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');

    for (const service of AREA_FURNITURE_SERVICES) {
        await freshInquire(page);
        await walkToRequirements(page, {
            serviceType: CATALOG.furnitureType,
            service,
            stones: [CATALOG.stones[1]],
        });
        await expect(
            page.locator('label[for="requirements-area-sqm-1"]'),
        ).toContainText('Project area (sqm) *');

        await continueToReview(page).click();
        await expect(page.locator('#requirements-area-sqm-1-error')).toHaveText(
            'Enter the project area (sqm).',
        );
        await expectStep(page, 3);
    }

    // On the last one, a value of one's own through Other.
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await answerQuestion(page, 'area-sqm', '3.75', { own: true });
    await answerQuestion(page, 'size', '60 x 60 cm');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);

    const reference = await sendAndReadReference(page);

    expect(storedAnswer(reference, 'area_sqm')?.number_value).toBe('3.75');

    await page.goto(`/dashboard/quote/${reference}`);
    await expect(page.getByTestId('project-answers')).toContainText(
        'Project area (sqm)3.75',
    );

    for (const serviceId of ['dining-tables', 'other-customized-furniture']) {
        artisan('cache:clear');
        const answer = await postAsUser(page, '/dashboard/quote/inquire', {
            projects: [
                projectPayload({
                    serviceId,
                    stones: [
                        {
                            stoneId: 'arabescato-white',
                            edge: CATALOG.edges[0],
                            finish: CATALOG.finishes[0],
                        },
                    ],
                    answers: {},
                }),
            ],
        });

        expect(answer.status, serviceId).toBe(422);
        expect(Object.keys(answer.json?.errors ?? {}), serviceId).toContain(
            'projects.0.answers.area_sqm',
        );
    }

    // A small piece is measured by its size: an area is refused.
    for (const serviceId of SMALL_FURNITURE_SLUGS) {
        artisan('cache:clear');
        const answer = await postAsUser(page, '/dashboard/quote/inquire', {
            projects: [
                projectPayload({
                    serviceId,
                    stones: [
                        {
                            stoneId: 'arabescato-white',
                            edge: CATALOG.edges[0],
                            finish: CATALOG.finishes[0],
                        },
                    ],
                    answers: { area_sqm: '0.5', size: '60 x 60 cm' },
                }),
            ],
        });

        expect(answer.status, serviceId).toBe(422);
        expect(Object.keys(answer.json?.errors ?? {}), serviceId).toContain(
            'projects.0.answers',
        );
    }
});

test('61 · the edit page keeps a listed and a custom size and thickness, and changes them in place', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);
    await chooseInSelect(page, sizeId(0), SLAB_SIZE);
    await typeThickness(page, 0, '25', 'mm');
    await answerStoneRow(page, 0, {
        quantity: 3,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    const reference = await sendAndReadReference(page);

    await page.goto(`/dashboard/quote/${reference}/edit`);
    await expectStep(page, 3);
    // The listed size reads back as picked; the custom thickness as its parts.
    await expect(page.locator(`#${sizeId(0)}`)).toContainText(SLAB_SIZE);
    await expect(page.locator(`#${thicknessId(0)}`)).toContainText('Other');
    await expect(page.locator(`#${thicknessId(0)}-value`)).toHaveValue('25');
    await expect(page.locator(`#${thicknessId(0)}-unit`)).toContainText('mm');

    await chooseInSelect(page, thicknessId(0), '0.75 in');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));
    await expect(page.locator('main')).toContainText('Thickness: 0.75 in');

    expect(storedRows(reference)).toEqual([
        {
            slab_width: '160.00',
            slab_length: '320.00',
            slab_unit: 'cm',
            thickness: '0.75',
            thickness_unit: 'in',
        },
    ]);

    // A custom size reads back as its parts too.
    await page.goto(`/dashboard/quote/${reference}/edit`);
    await expectStep(page, 3);
    await typeSlabSize(page, 0, '150', '300', 'cm');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));
    await page.goto(`/dashboard/quote/${reference}/edit`);
    await expectStep(page, 3);
    await expect(page.locator(`#${sizeId(0)}-width`)).toHaveValue('150');
    await expect(page.locator(`#${sizeId(0)}-length`)).toHaveValue('300');
    await expect(page.locator(`#${sizeId(0)}-unit`)).toContainText('cm');

    // Fascia, Yes to No.
    await freshInquire(page);
    await addStoneProject(page, {
        serviceType: SLAB_SERVICE_TYPE,
        service: COUNTERTOP,
        rows: [{ edge: CATALOG.edges[0], finish: CATALOG.finishes[0] }],
        questions: { 'area-sqm': '2', fascia: 'Yes' },
    });
    const countertop = await sendAndReadReference(page);

    await page.goto(`/dashboard/quote/${countertop}/edit`);
    await expectStep(page, 3);
    await expect(page.locator('#requirements-fascia-1')).toContainText('Yes');
    await chooseInSelect(page, 'requirements-fascia-1', 'No');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(new RegExp(`/quote/${countertop}$`));
    await expect(page.getByTestId('project-answers')).toContainText('FasciaNo');
    expect(storedAnswer(countertop, 'fascia')?.boolean_value).toBe(false);

    // A furniture project's area.
    await freshInquire(page);
    await addStoneProject(page, {
        serviceType: CATALOG.furnitureType,
        service: CATALOG.furniture,
        rows: [{ edge: CATALOG.edges[0], finish: CATALOG.finishes[0] }],
        questions: { seaters: '6', 'table-base': 'Wood' },
    });
    const table = await sendAndReadReference(page);

    expect(storedAnswer(table, 'area_sqm')?.number_value).toBe('2.00');

    await page.goto(`/dashboard/quote/${table}/edit`);
    await expectStep(page, 3);
    await answerQuestion(page, 'area-sqm', '10');
    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(new RegExp(`/quote/${table}$`));
    expect(storedAnswer(table, 'area_sqm')?.number_value).toBe('10.00');
});

test('62 · a draft keeps a listed and a custom size and thickness, fascia and area across a reload', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToSlabRows(page);
    await chooseInSelect(page, sizeId(0), SLAB_SIZE);
    await chooseInSelect(page, thicknessId(0), '0.75 in');
    await typeSlabSize(page, 1, '150', '300', 'cm');
    await typeThickness(page, 1, '25', 'mm');
    await page.locator(`#${thicknessId(1)}-value`).blur();

    await page.reload();
    await expectStep(page, 3);
    await expect(page.locator(`#${sizeId(0)}`)).toContainText(SLAB_SIZE);
    await expect(page.locator(`#${thicknessId(0)}`)).toContainText('0.75 in');
    await expect(page.locator(`#${sizeId(1)}-width`)).toHaveValue('150');
    await expect(page.locator(`#${sizeId(1)}-length`)).toHaveValue('300');
    await expect(page.locator(`#${thicknessId(1)}-value`)).toHaveValue('25');

    // A draft written before thickness was asked: the key is not there at all.
    // Leave the page first, so its own last save is not written over the edit.
    await page.goto('/dashboard/quote');
    await page.evaluate((key) => {
        const record = JSON.parse(window.localStorage.getItem(key) ?? '{}');

        for (const row of record.draft?.stones ?? []) {
            delete row.thickness;
        }

        window.localStorage.setItem(key, JSON.stringify(record));
    }, `regray.inquiry-draft.v9:${clientAId}`);
    await page.goto('/dashboard/quote/inquire');
    await expectStep(page, 3);
    await expect(page.locator(`#${thicknessId(0)}`)).toContainText(
        'Choose one',
    );
    await expect(page.locator(`#${sizeId(0)}`)).toContainText(SLAB_SIZE);
    await continueToReview(page).click();
    await expect(page.locator(`#${thicknessId(0)}-error`)).toHaveText(
        'Enter the thickness.',
    );

    // Fascia and area on a countertop draft.
    await freshInquire(page);
    await walkToRequirements(page, {
        serviceType: SLAB_SERVICE_TYPE,
        service: COUNTERTOP,
    });
    await answerQuestion(page, 'area-sqm', '3.5', { own: true });
    await answerQuestion(page, 'fascia', 'No');
    await page.reload();
    await expectStep(page, 3);
    await expect(page.locator('#requirements-area-sqm-1-custom')).toHaveValue(
        '3.5',
    );
    await expect(page.locator('#requirements-fascia-1')).toContainText('No');
});

test('63 · "Apply to all stones" is gone, and "Add it again" keeps the size and thickness', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');

    for (const [serviceType, service] of [
        [SLAB_SERVICE_TYPE, CATALOG.service],
        [SLAB_SERVICE_TYPE, COUNTERTOP],
        [CATALOG.furnitureType, CATALOG.furniture],
    ] as const) {
        await freshInquire(page);
        await walkToRequirements(page, {
            serviceType,
            service,
            stones: [...CATALOG.stones],
        });
        await expect(
            page.getByRole('button', { name: 'Apply to all stones' }),
        ).toHaveCount(0);
        await expect(page.getByText(/to every other row/)).toHaveCount(0);
    }

    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);
    await chooseInSelect(page, sizeId(0), '3.15 x 1.98 m');
    await chooseInSelect(page, thicknessId(0), '0.75 in');
    await page
        .getByRole('button', {
            name: `Add it again with another finish: ${CATALOG.stones[0]}`,
        })
        .click();

    await expect(page.locator(`#${sizeId(1)}`)).toContainText('3.15 x 1.98 m');
    await expect(page.locator(`#${thicknessId(1)}`)).toContainText('0.75 in');
    await expect(page.locator('#requirements-edge-1')).toContainText(
        'Choose one',
    );
});

test('64 · the stone profile lists its slabs, the brand filters read them, and a row with Other open fits @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await page.goto('/dashboard/brands/regray-stone/stones/cosmic-black');
    const slabs = page.getByTestId('available-slabs');
    // In the stone's own order: the measured size first.
    await expect(slabs.locator('tbody tr')).toHaveText([
        /^3\.15 x 1\.98 m\s*20 mm · 0\.75 in$/,
        /^160 x 320 cm\s*20 mm · 0\.75 in$/,
        /^60 x 120 cm\s*20 mm$/,
    ]);
    expect(await hasHorizontalScroll(page)).toBe(false);

    /** Whether the brand collection, filtered by `query`, lists the stone. */
    const lists = async (
        stone: { slug: string; name: string },
        query: string,
    ): Promise<boolean> => {
        await page.goto(
            `/dashboard/brands/regray-stone?collection=stones&search=${encodeURIComponent(stone.name)}&${query}`,
        );
        await expect(
            page.getByRole('heading', { name: 'Our Collection' }),
        ).toBeVisible();

        return (
            (await page
                .locator(`main a[href$="/stones/${stone.slug}"]`)
                .count()) > 0
        );
    };
    const cosmicBlack = { slug: 'cosmic-black', name: CATALOG.stones[0] };
    const arabescatoWhite = {
        slug: 'arabescato-white',
        name: CATALOG.stones[1],
    };

    expect(await lists(cosmicBlack, 'thickness=0.75-in')).toBe(true);
    expect(await lists(arabescatoWhite, 'thickness=0.75-in')).toBe(false);
    expect(await lists(cosmicBlack, 'size=160x320-cm')).toBe(true);
    expect(await lists(arabescatoWhite, 'size=160x320-cm')).toBe(true);
    // 60 x 120 cm is cut in 20 mm only: with 0.75 in it is no slab of Cosmic Black.
    expect(await lists(cosmicBlack, 'size=60x120-cm&thickness=0.75-in')).toBe(
        false,
    );
    expect(await lists(cosmicBlack, 'size=60x120-cm&thickness=20-mm')).toBe(
        true,
    );

    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);
    await typeSlabSize(page, 0, '150', '300', 'cm');
    await typeThickness(page, 0, '25', 'mm');
    await expect(page.locator(`#${sizeId(0)}-unit`)).toBeVisible();
    await expect(page.locator(`#${thicknessId(0)}-unit`)).toBeVisible();
    expect(await hasHorizontalScroll(page)).toBe(false);
});

test('65 · the profile shows every slab: a measured size in all its thicknesses, and Crazy Cut by its note @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    const arcticPink = php<{ slug: string; thicknesses: string[] }>(
        `(fn ($stone) => ['slug' => $stone->slug, 'thicknesses' => $stone->thicknesses->map->label->values()])(App\\Models\\Stone::query()->where('name', 'Arctic Pink')->sole())`,
    );

    await page.goto(`/dashboard/brands/regray-stone/stones/${arcticPink.slug}`);
    await expect(
        page.getByTestId('available-slabs').locator('tbody tr').filter({
            hasText: '3.13 x 1.97 m',
        }),
    ).toHaveText(
        new RegExp(
            `^3\\.13 x 1\\.97 m\\s*${arcticPink.thicknesses.join(' · ').replaceAll('.', '\\.')}$`,
        ),
    );
    expect(await hasHorizontalScroll(page)).toBe(false);

    const crazyCut = firstStoneOf('crazy-cut');

    await page.goto(`/dashboard/brands/regray-stone/stones/${crazyCut.slug}`);
    await expect(
        page.getByTestId('available-slabs').locator('tbody tr'),
    ).toHaveText([/^Diameter 15–40 cm\s*2 cm · 3 cm · 4 cm$/]);
    expect(await hasHorizontalScroll(page)).toBe(false);
});

test('66 · a Crazy Cut row offers no set size, only Other with how big the pieces run, and its three thicknesses', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    const crazyCut = firstStoneOf('crazy-cut');

    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, crazyCut.name);
    await pressNext(page, 2);

    expect(await listedOptions(page, sizeId(0))).toEqual(['Other']);
    await expect(page.locator(`#${sizeId(0)}-hint`)).toHaveText(
        'Diameter 15–40 cm',
    );
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '2 cm',
        '3 cm',
        '4 cm',
        'Other',
    ]);
});

test('67 · the thickness follows the size on Cosmic Black, across a reload and on the edit page', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');
    await freshInquire(page);
    await walkToStones(page);
    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);

    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        '0.75 in',
        'Other',
    ]);

    await chooseInSelect(page, sizeId(0), '60 x 120 cm');
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        'Other',
    ]);

    await chooseInSelect(page, sizeId(0), '160 x 320 cm');
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        '0.75 in',
        'Other',
    ]);

    // A listed thickness the new size is not cut in is cleared, and needed again.
    await chooseInSelect(page, thicknessId(0), '0.75 in');
    await chooseInSelect(page, sizeId(0), '60 x 120 cm');
    await expect(page.locator(`#${thicknessId(0)}`)).toContainText(
        'Choose one',
    );
    await answerStoneRow(page, 0, {
        quantity: 1,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await continueToReview(page).click();
    await expect(page.locator(`#${thicknessId(0)}-error`)).toHaveText(
        'Enter the thickness.',
    );
    await expectStep(page, 3);

    // A thickness of one's own stays through a size change.
    await typeThickness(page, 0, '25', 'mm');
    await chooseInSelect(page, sizeId(0), '160 x 320 cm');
    await expect(page.locator(`#${thicknessId(0)}-value`)).toHaveValue('25');

    // A size of one's own lists every thickness of the stone.
    await typeSlabSize(page, 0, '150', '300', 'cm');
    await chooseInSelect(page, thicknessId(0), '0.75 in');
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        '0.75 in',
        'Other',
    ]);

    // Back on 60 x 120 cm, and the narrowed list survives a reload.
    await chooseInSelect(page, sizeId(0), '60 x 120 cm');
    await chooseInSelect(page, thicknessId(0), '20 mm');
    await page.reload();
    await expectStep(page, 3);
    await expect(page.locator(`#${sizeId(0)}`)).toContainText('60 x 120 cm');
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        'Other',
    ]);

    await continueToReview(page).click();
    await expectStep(page, REVIEW);
    const reference = await sendAndReadReference(page);

    await page.goto(`/dashboard/quote/${reference}/edit`);
    await expectStep(page, 3);
    await expect(page.locator(`#${thicknessId(0)}`)).toContainText('20 mm');
    expect(await listedOptions(page, thicknessId(0))).toEqual([
        '20 mm',
        'Other',
    ]);
});

test('68 · the details page tells a standard slab from a custom size or thickness', async ({
    actors,
}) => {
    // Several walks through the form, against a one-request-at-a-time server.
    test.slow();
    const { page } = await actors.open('client');
    const send = async (stone: { size: string; thickness?: string }) => {
        await freshInquire(page);
        await walkToStones(page);
        await pickStone(page, CATALOG.stones[0]);
        await pressNext(page, 2);

        if (stone.size === 'own') {
            await typeSlabSize(page, 0, '150', '300', 'cm');
        } else {
            await chooseInSelect(page, sizeId(0), stone.size);
        }

        if (stone.thickness === undefined) {
            await typeThickness(page, 0, '25', 'mm');
        } else {
            await chooseInSelect(page, thicknessId(0), stone.thickness);
        }

        await answerStoneRow(page, 0, {
            quantity: 1,
            edge: CATALOG.edges[0],
            finish: CATALOG.finishes[0],
        });
        await chooseLocation(page, 'Makati');
        await answerDeadline(page);
        await continueToReview(page).click();
        await expectStep(page, REVIEW);
        const reference = await sendAndReadReference(page);
        await page.goto(`/dashboard/quote/${reference}`);

        return page.getByTestId('slab-kind');
    };

    await expect(
        await send({ size: SLAB_SIZE, thickness: THICKNESS }),
    ).toHaveText('Standard slab');
    await expect(await send({ size: 'own', thickness: THICKNESS })).toHaveText(
        'Custom size/thickness',
    );
    await expect(await send({ size: SLAB_SIZE })).toHaveText(
        'Custom size/thickness',
    );
});

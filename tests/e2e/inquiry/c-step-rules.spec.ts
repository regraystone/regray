import type { Page } from '@playwright/test';
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
    DELIVERY_METHOD_ID,
    REVIEW,
    SERVICE_ID,
    SERVICE_TYPE_ID,
    SLAB_SIZE,
    THICKNESS,
    answerQuestion,
    answerStoneRow,
    checkbox,
    continueToReview,
    answerDeadline,
    chooseInSelect,
    chooseLocation,
    expectStep,
    nextButton,
    locationField,
    notesField,
    openInquire,
    pickService,
    pickStone,
    pressNext,
    projectPayload,
    sendAndReadReference,
    fillThicknesses,
    typeSlabSize,
    walkToRequirements,
    walkToStones,
} from '../support/inquiry-flow';

/* C. The rules each step holds the reader to. */

/** A message in the page's own error style: an alert in the error colour. */
const pageError = (page: Page, text: string) =>
    page.getByRole('alert').filter({ hasText: text });

const addToInquiry = (page: Page) =>
    page.getByRole('button', { name: 'Continue to review' });

test('11 · What do you need: a type, then its services, then what the service includes', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    const info = page.getByTestId('service-info');

    await nextButton(page).click();
    await expect(pageError(page, 'Choose what you need.')).toBeVisible();
    await expectStep(page, 1);

    await expect(page.locator(`#${SERVICE_ID}`)).toHaveCount(0);
    await expect(info).toHaveCount(0);

    await page.locator(`#${SERVICE_TYPE_ID}`).click();
    await expect(page.getByRole('option')).toHaveText([
        CATALOG.serviceType,
        CATALOG.furnitureType,
    ]);
    await page.keyboard.press('Escape');

    await chooseInSelect(page, SERVICE_TYPE_ID, CATALOG.serviceType);
    await expect(page.getByText('Which application?')).toBeVisible();
    await expect(page.locator(`label[for="${SERVICE_ID}"]`)).toContainText(
        'Which application? *',
    );
    await expect(info).toHaveCount(0);

    await page.locator(`#${SERVICE_ID}`).click();
    await expect(page.getByRole('option')).toHaveText([
        'Slab Supply Only',
        'Slab Supply and Installation',
        'Installation Only',
        'Countertop and Backsplash',
        'Wall Cladding',
        'Flooring',
        'Staircase',
        'Kitchen Islands',
        'Lavatories',
    ]);
    await page.keyboard.press('Escape');

    await nextButton(page).click();
    await expect(pageError(page, 'Choose what you need.')).toBeVisible();
    await expectStep(page, 1);

    await chooseInSelect(page, SERVICE_ID, 'Countertop and Backsplash');
    await expect(info).toContainText(
        'Natural stone countertops cut and finished to your dimensions.',
    );
    await expect(info.getByRole('listitem')).toHaveText([
        'Precision cutting and fabrication',
        'Custom dimensions',
        'Edge profiling and finishing',
    ]);
    await expect(page.locator(`#${SERVICE_ID}`)).toHaveAttribute(
        'aria-describedby',
        (await info.getAttribute('id')) ?? '',
    );

    // Another type clears the choice made under the first, and what it showed.
    await chooseInSelect(page, SERVICE_TYPE_ID, CATALOG.furnitureType);
    await expect(page.getByText('Which furniture?')).toBeVisible();
    await expect(page.locator(`#${SERVICE_ID}`)).toContainText('Choose one');
    await expect(info).toHaveCount(0);

    await page.locator(`#${SERVICE_ID}`).click();
    await expect(page.getByRole('option')).toHaveText([
        'Center Tables',
        'Side Tables',
        'Dining Tables',
        'Pedestal',
        'Other Customized Furniture',
    ]);
    await page.keyboard.press('Escape');

    await chooseInSelect(page, SERVICE_ID, CATALOG.furniture);
    await expect(pageError(page, 'Choose what you need.')).toHaveCount(0);
    await pressNext(page, 1);
});

test('11a · the stone card has no save or view control, and the type filter combines with the search and the tabs @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToStones(page);

    const cards = page.getByTestId('stone-card');
    const first = cards.first();

    await expect(first.getByRole('checkbox')).toHaveCount(1);
    await expect(first.getByRole('button')).toHaveCount(0);
    await expect(first.getByRole('link')).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^Save / })).toHaveCount(0);
    await expect(page.getByRole('link', { name: /^View / })).toHaveCount(0);

    const all = await cards.count();
    const pills = page.getByTestId('stone-type-pills');
    const isPhone = await pills.isVisible();

    const chooseType = async (label: string) => {
        if (isPhone) {
            await pills
                .getByRole('button', { name: label, exact: true })
                .click();

            return;
        }

        await page.getByTestId('stone-type-select').click();
        await page.getByRole('option', { name: label, exact: true }).click();
    };

    await chooseType('Marble');
    await expect(cards.first()).toContainText('Marble');

    for (const text of await cards.allInnerTexts()) {
        expect(text).toContain('Marble');
    }

    if (isPhone) {
        await expect(
            pills.getByRole('button', { name: 'Marble', exact: true }),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(
            pills.getByRole('button', {
                name: 'All Type',
                exact: true,
            }),
        ).toHaveAttribute('aria-pressed', 'false');
    }

    const search = page.getByRole('searchbox', { name: 'Search stones' });

    await search.fill('no such stone');
    await expect(page.getByText('No stones found')).toBeVisible();
    await page.getByRole('button', { name: 'Clear filters' }).click();
    await expect(cards).toHaveCount(all);
    await expect(search).toHaveValue('');

    // Saved holds this reader's stones only, under whichever type is chosen.
    await chooseType('Marble');
    await page
        .getByRole('tab', { name: 'Saved', exact: true })
        .locator('visible=true')
        .click();
    await expect(
        page.getByText('No stones found').or(cards.first()),
    ).toBeVisible();

    for (const text of await cards.allInnerTexts()) {
        expect(text).toContain('Marble');
    }
});

test('11c · the server sends the stones a page at a time as the row is scrolled to its end, cards as tall as the tallest, and a picked stone outlives its page @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    const pageRequests: string[] = [];

    page.on('request', (request) => {
        if (new URL(request.url()).searchParams.has('page')) {
            pageRequests.push(request.url());
        }
    });

    await openInquire(page);
    await walkToStones(page);

    const total = php<number>('App\\Models\\Stone::query()->active()->count()');
    const batch = (page.viewportSize()?.width ?? 0) >= 768 ? 24 : 16;
    const cards = page.getByTestId('stone-card');

    await expect(cards).toHaveCount(batch);
    expect(pageRequests).toEqual([]);

    // One row that scrolls sideways: every card is as tall as the tallest.
    const heights = await cards.evaluateAll((all) =>
        all.map((card) => Math.round(card.getBoundingClientRect().height)),
    );
    expect(new Set(heights).size).toBe(1);

    // Each card's picture starts at its top, however tall the row makes it.
    const pictureOffsets = await cards.evaluateAll((all) =>
        all.map((card) =>
            Math.round(
                (card.querySelector('img')?.getBoundingClientRect().top ?? 0) -
                    card.getBoundingClientRect().top,
            ),
        ),
    );
    expect(Math.max(...pictureOffsets)).toBeLessThanOrEqual(2);
    const [first, second] = await cards.evaluateAll((all) =>
        all.slice(0, 2).map((card) => card.getBoundingClientRect().top),
    );
    expect(second).toBe(first);

    // Arrows on every screen: no "previous" at the first card, "next" only
    // while there is more.
    const back = page.getByRole('button', { name: 'Earlier stones' });
    const onward = page.getByRole('button', { name: 'More stones' });
    await expect(back).toHaveCount(0);
    await expect(onward).toBeVisible();
    await onward.click();
    await expect(back).toBeVisible();
    await back.click();
    await expect(back).toHaveCount(0);

    // Scrolling the page past the row loads nothing; reaching its end does.
    await page.evaluate(() =>
        window.scrollTo(0, document.documentElement.scrollHeight),
    );
    await page.waitForTimeout(500);
    expect(pageRequests).toEqual([]);

    await page
        .getByTestId('stones-row')
        .evaluate((row) => row.scrollTo({ left: row.scrollWidth }));
    await expect(cards).toHaveCount(Math.min(batch * 2, total));
    expect(pageRequests.length).toBe(1);

    await cards.last().getByRole('checkbox').click();
    await expect(page.getByText('1 stone selected')).toBeVisible();
    const picked = await cards
        .last()
        .getByRole('checkbox')
        .getAttribute('aria-label');

    // A search is the server's too, and a new list starts from its first page.
    const search = page.getByRole('searchbox', { name: 'Search stones' });

    await search.fill('Warm Beige Granite');
    await expect(cards).toHaveCount(1);
    await expect(page).toHaveURL(/stone_search=Warm/);
    await search.fill('');
    await expect(cards).toHaveCount(batch);
    await expect(page.getByText('1 stone selected')).toBeVisible();

    // The picked stone is on no page in view now, and is still drawn further on.
    await pressNext(page, 2);
    await expect(
        page.getByText(picked ?? '', { exact: true }).first(),
    ).toBeVisible();
});

test('11d · a restored draft gets its stones back from the server, whichever page they are on', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await openInquire(page);
    await walkToStones(page);

    const stoneName = php<string>(
        "App\\Models\\Stone::query()->active()->orderBy('sort_order')->orderBy('name')->orderBy('id')->skip(70)->value('name')",
    );

    await pickStone(page, stoneName);
    await pressNext(page, 2);
    await page.waitForTimeout(2500);

    await page.reload();
    await expectStep(page, 3);
    await expect(
        page.getByText(stoneName, { exact: true }).first(),
    ).toBeVisible();
});

test('11b · the same stone, edge and finish sent twice are added together, and a bare request needs a service', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/dashboard/quote');

    const noService = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [projectPayload({ serviceId: '' })],
    });

    expect(noService.status).toBe(422);
    expect(noService.json?.errors).toEqual({
        'projects.0.serviceId': ['Choose what you need.'],
    });

    const row = {
        stoneId: CATALOG.stoneSlug,
        quantity: 2,
        slabSize: SLAB_SIZE,
        thickness: THICKNESS,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    };
    const sent = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [projectPayload({ stones: [row, row] })],
    });

    expect([302, 303], `store answered ${sent.status}`).toContain(sent.status);

    const [reference] = inquiryReferences(clientAId);

    expect(
        php<number>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first()->stones()->sole()->quantity`,
        ),
    ).toBe(4);
});

test('12 · Stones: no stone or no slab size blocks Continue to review; quantity 0 and 10000 are refused, 1 and 9999 accepted', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToStones(page);

    await nextButton(page).click();
    await expect(pageError(page, 'Select at least one stone.')).toBeVisible();
    await expectStep(page, 2);

    await pickStone(page, CATALOG.stones[0]);
    await expect(pageError(page, 'Select at least one stone.')).toHaveCount(0);
    await pressNext(page, 2);

    const quantity = page.locator('#requirements-quantity-0');
    const quantityError = page.locator('#requirements-quantity-0-error');

    for (const [value, message] of [
        ['0', 'Enter at least 1 piece.'],
        ['10000', 'Enter 9999 pieces or fewer.'],
        ['1', ''],
        ['9999', ''],
    ] as const) {
        await quantity.fill(value);
        await quantity.press('Tab');
        await expect(quantityError, `quantity ${value}`).toHaveText(message);
    }

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);

    await addToInquiry(page).click();
    await expect(page.locator('#requirements-slab-size-0-error')).toHaveText(
        'Enter the slab size.',
    );
    await expectStep(page, 3);

    await expect(page.locator('#requirements-thickness-0-error')).toHaveText(
        'Enter the thickness.',
    );

    // A size of one's own, through Other, beside the stone's own sizes.
    await typeSlabSize(page, 0, '90', '45', 'cm');
    await fillThicknesses(page);
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
    await expect(page.getByText('9999 pcs')).toBeVisible();
    await expect(page.getByText('Slab size: 90 x 45 cm')).toBeVisible();
});

test('13 · Stone rows: edge and finish start as "advise me", a blank row needs answers, and a 21st row is blocked', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    // Each stone's slab size (required), edge and finish are on show, with no
    // "optional"; the notes and files are on show too, with nothing to unfold.
    await expect(page.locator('#requirements-slab-size-0')).toBeVisible();
    await expect(
        page.locator('label[for="requirements-slab-size-0"]'),
    ).toContainText('Slab size *');
    await expect(
        page.locator('label[for="requirements-edge-0"]'),
    ).toContainText('Edge profile *');
    await expect(page.getByText('(optional)')).toHaveCount(0);
    await expect(notesField(page)).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Add more details' }),
    ).toHaveCount(0);

    // The only stone can't be taken away here.
    const removeRow = page.getByRole('button', {
        name: `Remove this ${CATALOG.stones[0]} row`,
    });
    await expect(removeRow).toHaveCount(0);

    await expect(page.locator('#requirements-edge-0')).toContainText(
        CATALOG.adviseEdge,
    );

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);

    const duplicate = page
        .getByRole('button', {
            name: `Add it again with another finish: ${CATALOG.stones[0]}`,
        })
        .first();

    await duplicate.click();
    await expect(
        page.locator('input[id^="requirements-quantity-"]'),
    ).toHaveCount(2);

    // The new row starts with no edge or finish, and keeps the slab size.
    await expect(page.locator('#requirements-edge-1')).toContainText(
        'Choose one',
    );
    await expect(page.locator('#requirements-slab-size-1')).toContainText(
        SLAB_SIZE,
    );
    await expect(removeRow).toHaveCount(2);

    // Left unanswered, it would be the same stone worked the same way twice.
    await addToInquiry(page).click();
    await expect(
        pageError(
            page,
            'Another row of this stone has the same slab size, thickness, edge profile and finish',
        ),
    ).toBeVisible();
    await expectStep(page, 3);

    for (let rows = 2; rows < 20; rows += 1) {
        await duplicate.click();
        await expect(
            page.locator('input[id^="requirements-quantity-"]'),
        ).toHaveCount(rows + 1);
    }

    await expect(duplicate).toBeDisabled();
    await expect(duplicate).toHaveAttribute(
        'title',
        'Maximum 20 rows per project',
    );
    await expect(
        page.locator('input[id^="requirements-quantity-"]'),
    ).toHaveCount(20);
});

test('14 · Requirements: the method and the address are required, and the saved address picked is the one sent', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);
    await answerDeadline(page);

    await addToInquiry(page).click();
    await expect(
        pageError(page, 'Choose how you would like to get it.'),
    ).toBeVisible();

    await chooseLocation(page, 'Antipolo');
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
    await expect(
        page.getByText(/^Delivery to .*City of Antipolo/),
    ).toBeVisible();

    const reference = await sendAndReadReference(page);
    expect(inquiryReferences(clientAId)[0]).toBe(reference);
    expect(
        php<string>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->value('address_city_name')`,
        ),
    ).toBe('City of Antipolo');
});

test('15 · Requirements: notes take 1000 and refuse 1001, and no landmark or area is asked', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 422/);
    await openInquire(page);
    await walkToRequirements(page);

    const notes = notesField(page);

    // Slab Supply Only asks no area, and nothing asks for a landmark.
    await expect(page.getByLabel(/landmark/i)).toHaveCount(0);
    await expect(page.getByLabel(/area/i)).toHaveCount(0);

    // The counter under the notes is the limit made visible, so it has to
    // follow what is typed: first five keys by hand, then the whole limit.
    const counter = page.getByText(/^\d+ of 1000 characters used$/);
    await notes.pressSequentially('Notes');
    await expect
        .soft(counter, 'counter after typing 5 keys')
        .toHaveText('5 of 1000 characters used');
    await notes.fill('N'.repeat(1001));
    await expect(notes).toHaveValue('N'.repeat(1000));
    await expect
        .soft(counter, 'counter at the limit')
        .toHaveText('1000 of 1000 characters used');

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);

    // The server holds the same limits when the field's own cap is bypassed.
    for (const [field, length] of [['notes', 1001]] as const) {
        const response = await postAsUser(page, '/dashboard/quote/inquire', {
            projects: [projectPayload({ [field]: 'X'.repeat(length) })],
        });

        expect(response.status, field).toBe(422);
        expect(Object.keys(response.json?.errors ?? {})).toEqual([
            `projects.0.${field}`,
        ]);
    }
});

test('16 · a pickup is asked no address, and the saved address comes back with a delivery', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    // Asked, not assumed.
    await expect(page.locator(`#${DELIVERY_METHOD_ID}`)).toContainText(
        'Choose one',
    );

    await chooseLocation(page, 'Antipolo');

    await chooseInSelect(page, DELIVERY_METHOD_ID, 'Pickup');
    await expect(locationField(page)).toHaveCount(0);

    // Changing back brings back the address picked.
    await chooseInSelect(page, DELIVERY_METHOD_ID, 'Delivery');
    await expect(
        locationField(page).getByRole('radio', { name: /^Site/ }),
    ).toBeChecked();
});

test('16a · Installation Only skips the stones and sends with none; Wall Cladding asks an area and a finish, and no edge', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);

    await pickService(page, CATALOG.serviceType, 'Installation Only');
    await expect(nextButton(page)).toHaveAccessibleName(/Other details/);
    await nextButton(page).click();

    // The details are the second question: there is no stones question.
    await expect(
        page.getByRole('heading', { name: 'Which stones?' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'Other details' }),
    ).toBeVisible();
    await expect(page.getByTestId('stones-empty-state')).toHaveCount(0);
    await expect(page.locator('[id^="requirements-quantity-"]')).toHaveCount(0);

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
    await expect(page.getByTestId('review-stones')).toHaveCount(0);

    const reference = await sendAndReadReference(page);
    expect(inquiryReferences(clientAId)[0]).toBe(reference);
    expect(
        php<number>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first()->stones()->count()`,
        ),
    ).toBe(0);

    await openInquire(page);
    await walkToRequirements(page, { service: 'Wall Cladding' });
    await expect(page.locator('#requirements-edge-0')).toHaveCount(0);
    await expect(page.locator('#requirements-slab-size-0')).toHaveCount(0);
    await expect(page.locator('#requirements-finish-0')).toBeVisible();

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await addToInquiry(page).click();
    await expect(page.locator('#requirements-area-sqm-1-error')).toHaveText(
        'Enter the project area (sqm).',
    );
    await expectStep(page, 3);

    await answerQuestion(page, 'area-sqm', '5');
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
    await expect(page.getByTestId('review-answers')).toContainText(
        'Project area (sqm)5',
    );
});

test('17 · every question is on one page, and filling the details keeps the answers above', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page, {
        stones: [...CATALOG.stones],
        serviceType: CATALOG.furnitureType,
        service: CATALOG.furniture,
    });
    await answerStoneRow(page, 0, {
        edge: CATALOG.edges[1],
        finish: CATALOG.finishes[1],
    });
    await chooseLocation(page, 'Pasig');
    await answerDeadline(page);

    for (const stone of CATALOG.stones) {
        await expect(checkbox(page, stone)).toHaveAttribute(
            'aria-checked',
            'true',
        );
    }

    await expect(page.locator(`#${SERVICE_TYPE_ID}`)).toContainText(
        CATALOG.furnitureType,
    );
    await expect(page.locator(`#${SERVICE_ID}`)).toContainText(
        CATALOG.furniture,
    );

    await expect(page.getByRole('button', { name: 'Next' })).toHaveCount(0);
    // Dining Tables asks no piece count.
    await expect(page.locator('#requirements-quantity-0')).toHaveCount(0);
    await expect(page.locator('#requirements-edge-0')).toContainText(
        CATALOG.edges[1],
    );
    await expect(page.locator('#requirements-finish-0')).toContainText(
        CATALOG.finishes[1],
    );
    await expect(locationField(page)).toHaveValue('Pasig');
});

test('17a · Help me choose moves on with no stone, and picking a stone takes it back', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToStones(page);

    await page.getByRole('button', { name: 'Help me choose' }).click();
    await expectStep(page, 3);
    await expect(
        page.getByText('Stone to be recommended by Regray').first(),
    ).toBeVisible();
    // No stones to ask about, and no prompt to pick one either.
    await expect(page.getByTestId('stones-empty-state')).toHaveCount(0);

    await pickStone(page, CATALOG.stones[0]);
    await expectStep(page, 3);
    await expect(
        page.getByText('Stone to be recommended by Regray'),
    ).toHaveCount(0);
    await expect(
        page.getByText(CATALOG.stones[0], { exact: true }).first(),
    ).toBeVisible();
});

test('17e · the questions appear one by one with Next, and every question shown keeps all its fields @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    const stonesHeading = page.getByRole('heading', { name: 'Which stones?' });
    const detailsHeading = page.getByRole('heading', { name: 'Other details' });

    // Only the first question, with Next as its way on.
    await expect(stonesHeading).toHaveCount(0);
    await expect(detailsHeading).toHaveCount(0);
    await expect(nextButton(page)).toHaveAccessibleName('Next: Which stones?');
    await expect(continueToReview(page)).toHaveCount(0);

    await nextButton(page).click();
    await expect(pageError(page, 'Choose what you need.')).toBeVisible();
    await expect(stonesHeading).toHaveCount(0);

    await chooseInSelect(page, SERVICE_TYPE_ID, CATALOG.serviceType);
    await chooseInSelect(page, SERVICE_ID, CATALOG.service);
    await pressNext(page, 1);

    // The first question stays open with its fields, and there is no Change.
    await expect(page.locator(`#${SERVICE_TYPE_ID}`)).toContainText(
        CATALOG.serviceType,
    );
    await expect(page.locator(`#${SERVICE_ID}`)).toContainText(CATALOG.service);
    await expect(page.getByRole('button', { name: /^Change/ })).toHaveCount(0);
    await expect(detailsHeading).toHaveCount(0);
    await expect(nextButton(page)).toHaveAccessibleName('Next: Other details');

    await nextButton(page).click();
    await expect(pageError(page, 'Select at least one stone.')).toBeVisible();
    await expect(detailsHeading).toHaveCount(0);

    await pickStone(page, CATALOG.stones[0]);
    await pressNext(page, 2);

    // All three open at once, and the way on is Continue to review.
    await expect(nextButton(page)).toHaveCount(0);
    await expect(continueToReview(page)).toBeVisible();
    await expect(page.locator(`#${SERVICE_ID}`)).toBeVisible();
    await expect(checkbox(page, CATALOG.stones[0])).toHaveAttribute(
        'aria-checked',
        'true',
    );
    await expect(page.locator('#requirements-quantity-0')).toBeVisible();

    // An earlier answer is changed where it was given.
    await chooseInSelect(page, SERVICE_ID, 'Flooring');
    await expect(checkbox(page, CATALOG.stones[0])).toHaveAttribute(
        'aria-checked',
        'true',
    );
    await expect(detailsHeading).toBeVisible();
});

test('17c · with no stone picked, the details ask for stones first and point back to them', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    const prompt = page.getByTestId('stones-empty-state');

    await expect(prompt).toHaveCount(0);
    await expect(page.locator('#requirements-quantity-0')).toBeVisible();

    // Taken away again, the stones' rows give way to the prompt.
    await page.getByRole('button', { name: 'Clear', exact: true }).click();
    await expect(prompt).toContainText(
        'Choose the stones you want in step 2 first. What each of them needs will show here.',
    );
    await expect(
        page.locator('input[id^="requirements-quantity-"]'),
    ).toHaveCount(0);
    await expect(notesField(page)).toBeVisible();

    await prompt
        .getByRole('button', { name: 'Choose stones in step 2' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Which stones?' }),
    ).toBeInViewport();

    // Left to Regray, there is nothing about stones to ask: no rows, no prompt.
    await page.getByRole('button', { name: 'Help me choose' }).click();
    await expect(prompt).toHaveCount(0);
    await expect(
        page.locator('input[id^="requirements-quantity-"]'),
    ).toHaveCount(0);
});

test('17d · the stone type filter and the stones follow the service, and a stone the new service does not offer is taken off with a notice @layout', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToStones(page, CATALOG.serviceType, 'Flooring');

    const pills = page.getByTestId('stone-type-pills');
    const isPhone = await pills.isVisible();
    const typeOptions = async (): Promise<string[]> => {
        if (isPhone) {
            return pills.getByRole('button').allInnerTexts();
        }

        await page.getByTestId('stone-type-select').click();
        const options = await page.getByRole('option').allInnerTexts();
        await page.keyboard.press('Escape');

        return options;
    };

    const flooring = await typeOptions();
    expect(flooring).toContain('Cobblestone');
    expect(flooring).not.toContain('Onyx');

    const cobblestone = 'Flamed + Brushed - Black';
    await pickStone(page, cobblestone);
    await pickStone(page, CATALOG.stones[0]);

    await chooseInSelect(page, SERVICE_ID, 'Countertop and Backsplash');

    await expect(page.getByTestId('stone-notice')).toHaveText(
        `Removed ${cobblestone} for this service; it is not offered for Countertop and Backsplash.`,
    );
    await expect(page.getByTestId('stones-selected')).toHaveText(
        '1 stone selected',
    );
    await expect(page).toHaveURL(/stone_service=countertop/);

    const countertop = await typeOptions();
    expect(countertop).toContain('Onyx');
    expect(countertop).not.toContain('Cobblestone');

    for (const text of await page.getByTestId('stone-card').allInnerTexts()) {
        expect(text).not.toContain('Cobblestone');
    }
});

test('17b · a press that fixes a refused answer is not lost when the complaint goes', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    await addToInquiry(page).click();
    await expect(pageError(page, 'Say when this is needed.')).toBeVisible();

    await chooseLocation(page, 'Makati');
    await answerDeadline(page);

    // One press, straight after the answer, with the field still focused.
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
});

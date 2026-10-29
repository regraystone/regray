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
    answerStoneRow,
    checkbox,
    chooseInSelect,
    answerDeadline,
    chooseLocation,
    continueButton,
    deadlineDate,
    deadlineTime,
    expectStep,
    futureDate,
    locationField,
    openInquire,
    pickApplication,
    pickService,
    pickStone,
    projectPayload,
    sendAndReadReference,
    walkToRequirements,
} from '../support/inquiry-flow';

/* C. The rules each step holds the reader to. */

/** A message in the page's own error style: an alert in the error colour. */
const pageError = (page: Page, text: string) =>
    page.getByRole('alert').filter({ hasText: text });

const addToInquiry = (page: Page) =>
    page.getByRole('button', { name: 'Add to inquiry' });

test('11 · Services: Continue is disabled until a service is picked, and the objection is in the page style', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    // The scenario asks for a disabled button. Soft, so the rest of the step's
    // behaviour is still recorded when it is not.
    await expect
        .soft(continueButton(page), 'Continue with no service picked')
        .toBeDisabled();

    if (await continueButton(page).isEnabled()) {
        await continueButton(page).click();
        const message = pageError(page, 'Select at least one service.');
        await expect(message).toBeVisible();
        await expect(message).toHaveClass(/text-red-600/);
        await expectStep(page, 1);
    }

    await pickService(page, CATALOG.stoneService);
    await expect(continueButton(page)).toBeEnabled();
    await expect(pageError(page, 'Select at least one service.')).toHaveCount(
        0,
    );
    await continueButton(page).click();
    await expectStep(page, 2);
});

test('12 · Stones: no stone blocks Continue; quantity 0 and 10000 are refused, 1 and 9999 accepted', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await pickService(page, CATALOG.stoneService);
    await continueButton(page).click();
    await expectStep(page, 2);

    await continueButton(page).click();
    await expect(pageError(page, 'Select at least one stone.')).toBeVisible();
    await expectStep(page, 2);

    await pickStone(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 3);
    await pickApplication(page, CATALOG.applications[0]);
    await continueButton(page).click();
    await expectStep(page, 4);

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

    await page.getByLabel('Project name').fill('Quantity bounds');
    await answerStoneRow(page, 0, {
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await addToInquiry(page).click();
    await expectStep(page, 5);
    await expect(page.getByText('9999 pcs')).toBeVisible();
});

test('13 · Stone rows: edge and finish are required, "advise me" is accepted, and a 21st row is blocked', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);
    await page.getByLabel('Project name').fill('Row rules');
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);

    await addToInquiry(page).click();
    await expect(pageError(page, 'Choose an edge profile.')).toBeVisible();
    await expect(pageError(page, 'Choose a finish.')).toBeVisible();
    await expectStep(page, 4);

    await chooseInSelect(page, 'requirements-edge-0', CATALOG.adviseEdge);
    await expect(pageError(page, 'Choose an edge profile.')).toHaveCount(0);

    await page.locator('#requirements-finish-0').click();
    const finishOptions = await page.getByRole('option').allInnerTexts();
    await page.keyboard.press('Escape');
    const adviseFinish = finishOptions.filter((option) =>
        /advise/i.test(option),
    );
    expect
        .soft(
            adviseFinish,
            `an "advise me" finish among: ${finishOptions.join(', ')}`,
        )
        .toHaveLength(1);

    if (adviseFinish.length === 1) {
        await chooseInSelect(page, 'requirements-finish-0', adviseFinish[0]);
        await expect(pageError(page, 'Choose a finish.')).toHaveCount(0);
    }

    const duplicate = page
        .getByRole('button', { name: `Duplicate ${CATALOG.stones[0]}` })
        .first();

    for (let rows = 1; rows < 20; rows += 1) {
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

test('14 · Requirements: location is required, and "Parañaque" survives the round trip to the server', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);
    await pickService(page, CATALOG.visitService);
    await continueButton(page).click();
    await continueButton(page).click();
    await continueButton(page).click();
    await expectStep(page, 4);
    await deadlineDate(page).fill(futureDate(6));
    await deadlineTime(page).fill('11:00');

    await addToInquiry(page).click();
    await expect(pageError(page, 'Choose where the site is.')).toBeVisible();
    await expect(locationField(page)).toBeFocused();

    await chooseLocation(page, 'Parañaque');
    await addToInquiry(page).click();
    await expectStep(page, 5);
    await expect(page.getByText('Parañaque')).toBeVisible();

    const reference = await sendAndReadReference(page);
    expect(inquiryReferences(clientAId)[0]).toBe(reference);
    expect(
        php<string>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->value('location')`,
        ),
    ).toBe('Parañaque');
});

test('15 · Requirements: landmark takes 150 characters and refuses 151; notes take 1000 and refuse 1001', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 422/);
    await openInquire(page);
    await pickService(page, CATALOG.visitService);
    await continueButton(page).click();
    await continueButton(page).click();
    await continueButton(page).click();
    await expectStep(page, 4);

    const landmark = page.getByLabel('Landmark');
    const notes = page.getByLabel('Additional notes');

    await landmark.fill('L'.repeat(151));
    await expect(landmark).toHaveValue('L'.repeat(150));

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
    await deadlineDate(page).fill(futureDate(6));
    await deadlineTime(page).fill('11:00');
    await addToInquiry(page).click();
    await expectStep(page, 5);
    await expect(
        page.getByText('L'.repeat(150), { exact: true }),
    ).toBeVisible();

    // The server holds the same limits when the field's own cap is bypassed.
    for (const [field, length] of [
        ['landmark', 151],
        ['notes', 1001],
    ] as const) {
        const response = await postAsUser(page, '/quote/inquire', {
            projects: [projectPayload({ [field]: 'X'.repeat(length) })],
        });

        expect(response.status, field).toBe(422);
        expect(Object.keys(response.json?.errors ?? {})).toEqual([
            `projects.0.${field}`,
        ]);
    }
});

test('16 · the needed-by time shows only for a service attended at an hour, and is required with it', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    await walkToRequirements(page);
    await expect(deadlineDate(page)).toBeVisible();
    await expect(deadlineTime(page)).toHaveCount(0);

    await page.getByRole('button', { name: 'Change' }).click();
    await expectStep(page, 1);
    await checkbox(page, CATALOG.stoneService).click();
    await pickService(page, CATALOG.visitService);
    await continueButton(page).click();
    await continueButton(page).click();
    await continueButton(page).click();
    await expectStep(page, 4);

    await expect(deadlineTime(page)).toBeVisible();
    await chooseLocation(page, 'Makati');
    await deadlineDate(page).fill(futureDate(6));
    await addToInquiry(page).click();
    await expect(pageError(page, 'Say what time of day works.')).toBeVisible();
    await expectStep(page, 4);

    await deadlineTime(page).fill('14:15');
    await addToInquiry(page).click();
    await expectStep(page, 5);
    await expect(page.getByText(/at 14:15$/)).toBeVisible();
});

test('17 · Back keeps the answers on every step', async ({ actors }) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page, {
        stones: [...CATALOG.stones],
        applications: [CATALOG.applications[1]],
    });
    await page.getByLabel('Project name').fill('Back and forth');
    await answerStoneRow(page, 0, {
        quantity: 12,
        edge: CATALOG.edges[1],
        finish: CATALOG.finishes[1],
    });
    await chooseLocation(page, 'Pasig');
    await answerDeadline(page);

    const back = page.getByRole('button', { name: 'Back', exact: true });

    await back.click();
    await expectStep(page, 3);
    await expect(checkbox(page, CATALOG.applications[1])).toHaveAttribute(
        'aria-checked',
        'true',
    );

    await back.click();
    await expectStep(page, 2);
    for (const stone of CATALOG.stones) {
        await expect(checkbox(page, stone)).toHaveAttribute(
            'aria-checked',
            'true',
        );
    }

    await back.click();
    await expectStep(page, 1);
    await expect(checkbox(page, CATALOG.stoneService)).toHaveAttribute(
        'aria-checked',
        'true',
    );

    await continueButton(page).click();
    await continueButton(page).click();
    await continueButton(page).click();
    await expectStep(page, 4);
    await expect(page.getByLabel('Project name')).toHaveValue('Back and forth');
    await expect(page.locator('#requirements-quantity-0')).toHaveValue('12');
    await expect(page.locator('#requirements-edge-0')).toContainText(
        CATALOG.edges[1],
    );
    await expect(page.locator('#requirements-finish-0')).toContainText(
        CATALOG.finishes[1],
    );
    await expect(locationField(page)).toHaveValue('Pasig');
});

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
    answerStoneRow,
    changeQuestion,
    checkbox,
    answerDeadline,
    chooseInSelect,
    chooseLocation,
    continueButton,
    expectStep,
    landmarkField,
    locationField,
    notesField,
    openInquire,
    openMoreDetails,
    pickStone,
    pickWork,
    projectPayload,
    workRow,
    sendAndReadReference,
    walkToRequirements,
} from '../support/inquiry-flow';

/* C. The rules each step holds the reader to. */

/** A message in the page's own error style: an alert in the error colour. */
const pageError = (page: Page, text: string) =>
    page.getByRole('alert').filter({ hasText: text });

const addToInquiry = (page: Page) =>
    page.getByRole('button', { name: 'Continue to review' });

test('11 · Work: the list is in order, each stone needs its work, and furniture needs its type', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    const [stone] = CATALOG.stones;
    await openInquire(page);
    await pickStone(page, stone);
    await continueButton(page).click();
    await expectStep(page, 2);

    await continueButton(page).click();
    await expect(
        pageError(page, `Choose the work for ${stone}.`),
    ).toBeVisible();
    await expectStep(page, 2);

    await workRow(page, stone).getByTestId('service-option').click();
    await expect(page.getByRole('option')).toHaveText([
        'Stone supply, fabrication and installation',
        'Stone supply and fabrication',
        'Stone supply and installation',
        'Stone supply only',
        'Customized furniture',
        'Full kitchen fit-out',
        'Cabinetry with stone countertop and installation',
        'Cabinetry with stone countertop',
    ]);
    await page.keyboard.press('Escape');

    await pickWork(page, stone, CATALOG.furnitureWork);
    await continueButton(page).click();
    await expect(
        pageError(page, `Choose the furniture type for ${stone}.`),
    ).toBeVisible();
    await expectStep(page, 2);

    await pickWork(page, stone, CATALOG.furnitureWork, 'Tables');
    await continueButton(page).click();
    await expectStep(page, 3);
});

test('12 · Stones: no stone blocks Next; quantity 0 and 10000 are refused, 1 and 9999 accepted', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    await continueButton(page).click();
    await expect(pageError(page, 'Select at least one stone.')).toBeVisible();
    await expectStep(page, 1);

    await pickStone(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 2);
    await pickWork(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 3);

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
    await expectStep(page, REVIEW);
    await expect(page.getByText('9999 pcs')).toBeVisible();
});

test('13 · Stone rows: edge and finish start as "advise me", a blank row needs answers, and a 21st row is blocked', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    // Nothing to answer until the reader asks: it goes through as "advise me".
    await expect(
        page.getByText("Edge and finish: we'll advise you"),
    ).toBeVisible();
    await expect(page.locator('#requirements-edge-0')).toHaveCount(0);

    // The only stone can't be taken away here.
    const removeRow = page.getByRole('button', {
        name: `Remove this ${CATALOG.stones[0]} row`,
    });
    await expect(removeRow).toHaveCount(0);

    await page.getByRole('button', { name: /^Choose edge and finish/ }).click();
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
    await expect(removeRow).toHaveCount(2);

    // Left unanswered, it would be the same stone worked the same way twice.
    await addToInquiry(page).click();
    await expect(
        pageError(page, 'Another row of this stone has the same edge profile'),
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

test('14 · Requirements: location is required, and "Parañaque" survives the round trip to the server', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);
    await answerDeadline(page);

    await addToInquiry(page).click();
    await expect(pageError(page, 'Choose where the site is.')).toBeVisible();
    await expect(locationField(page)).toBeFocused();

    await chooseLocation(page, 'Parañaque');
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
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
    await walkToRequirements(page);

    await openMoreDetails(page);

    const landmark = landmarkField(page);
    const notes = notesField(page);

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
    await answerDeadline(page);
    await addToInquiry(page).click();
    await expectStep(page, REVIEW);
    await expect(
        page.getByText('L'.repeat(150), { exact: true }),
    ).toBeVisible();

    // The server holds the same limits when the field's own cap is bypassed.
    for (const [field, length] of [
        ['landmark', 151],
        ['notes', 1001],
    ] as const) {
        const response = await postAsUser(page, '/dashboard/quote/inquire', {
            projects: [projectPayload({ [field]: 'X'.repeat(length) })],
        });

        expect(response.status, field).toBe(422);
        expect(Object.keys(response.json?.errors ?? {})).toEqual([
            `projects.0.${field}`,
        ]);
    }
});

test('16 · a pickup is asked no location, with the landmark kept either way', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page);

    await expect(page.locator(`#${DELIVERY_METHOD_ID}`)).toContainText(
        'Delivery',
    );

    await chooseLocation(page, 'Makati');
    await openMoreDetails(page);
    await landmarkField(page).fill('Near the church');

    await chooseInSelect(page, DELIVERY_METHOD_ID, 'Pickup');
    await expect(locationField(page)).toHaveCount(0);
    await expect(landmarkField(page)).toHaveValue('Near the church');

    // Changing back brings back what was typed.
    await chooseInSelect(page, DELIVERY_METHOD_ID, 'Delivery');
    await expect(locationField(page)).toHaveValue('Makati');
    await expect(landmarkField(page)).toHaveValue('Near the church');
});

test('17 · Change opens an answered question and keeps every answer', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await walkToRequirements(page, {
        stones: [...CATALOG.stones],
        work: [
            { option: CATALOG.work },
            { option: CATALOG.furnitureWork, furnitureType: 'Tables' },
        ],
    });
    await answerStoneRow(page, 0, {
        quantity: 12,
        edge: CATALOG.edges[1],
        finish: CATALOG.finishes[1],
    });
    await chooseLocation(page, 'Pasig');
    await answerDeadline(page);

    await changeQuestion(page, 2).click();
    await expectStep(page, 2);
    await expect(workRow(page, CATALOG.stones[0])).toContainText(CATALOG.work);
    await expect(workRow(page, CATALOG.stones[1])).toContainText('Tables');

    await changeQuestion(page, 1).click();
    await expectStep(page, 1);
    for (const stone of CATALOG.stones) {
        await expect(checkbox(page, stone)).toHaveAttribute(
            'aria-checked',
            'true',
        );
    }

    await continueButton(page).click();
    await continueButton(page).click();
    await expectStep(page, 3);
    await expect(page.locator('#requirements-quantity-0')).toHaveValue('12');
    // Folded away again, and said back in a line.
    await expect(
        page.getByText(
            `Edge: ${CATALOG.edges[1]}. Finish: ${CATALOG.finishes[1]}.`,
        ),
    ).toBeVisible();
    await expect(locationField(page)).toHaveValue('Pasig');
});

test('17a · Help me choose moves on with no stone, and picking a stone takes it back', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    await page.getByRole('button', { name: 'Help me choose' }).click();
    await expectStep(page, 2);
    await pickWork(page, 'Stone to be recommended by Regray');
    await continueButton(page).click();
    await expectStep(page, 3);
    await expect(
        page.getByText('Stone to be recommended by Regray').first(),
    ).toBeVisible();

    await changeQuestion(page, 1).click();
    await pickStone(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 2);
    await pickWork(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 3);
    await expect(
        page.getByText('Stone to be recommended by Regray'),
    ).toHaveCount(0);
    await expect(
        page.getByText(CATALOG.stones[0], { exact: true }).first(),
    ).toBeVisible();
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

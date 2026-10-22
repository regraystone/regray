import { inquiryFixtures } from '../support/accounts';
import {
    expect,
    inquiryReferences,
    php,
    postAsUser,
    shot,
    test,
} from '../support/fixtures';
import {
    CATALOG,
    addStoneProject,
    addVisitProject,
    answerDeadline,
    chooseLocation,
    continueButton,
    deadlineDate,
    deadlineTime,
    expectStep,
    futureDate,
    openInquire,
    pickApplication,
    pickService,
    pickStone,
    projectPayload,
    sendAndReadReference,
    answerStoneRow,
} from '../support/inquiry-flow';

/* B. Happy path: from the first click to the saved inquiry. */

const OPTIONAL_NOTE = 'Optional — extra information for this visit';

test('6 · a standalone visit walks the optional steps empty and sends @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;
    const name = (step: string) => `06-${step}-${width}`;

    await openInquire(page);
    await pickService(page, CATALOG.visitService);
    await shot(page, name('1-services'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 2);
    await expect(page.getByText(OPTIONAL_NOTE)).toBeVisible();
    await shot(page, name('2-stones'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 3);
    await expect(page.getByText(OPTIONAL_NOTE)).toBeVisible();
    await shot(page, name('3-application'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 4);
    await chooseLocation(page, 'Taguig');
    await deadlineDate(page).fill(futureDate(7));
    await deadlineTime(page).fill('09:30');
    await shot(page, name('4-requirements'), testInfo);
    await page.getByRole('button', { name: 'Add to inquiry' }).click();

    await expectStep(page, 5);
    await shot(page, name('5-review'), testInfo);
    const reference = await sendAndReadReference(page);

    await expect(page.getByRole('status')).toContainText(
        `We've received your request for ${CATALOG.visitService}.`,
    );
    expect(reference).toMatch(/^INQ-/);
    await shot(page, name('6-result'), testInfo);
});

test('7 · a project of stone with two rows and two applications sends @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;
    const name = (step: string) => `07-${step}-${width}`;

    await openInquire(page);
    await pickService(page, CATALOG.stoneService);
    await shot(page, name('1-services'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 2);
    await expect(page.getByText(OPTIONAL_NOTE)).toHaveCount(0);
    await pickStone(page, CATALOG.stones[0]);
    await pickStone(page, CATALOG.stones[1]);
    await shot(page, name('2-stones'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 3);
    await pickApplication(page, CATALOG.applications[0]);
    await pickApplication(page, CATALOG.applications[1]);
    await shot(page, name('3-application'), testInfo);
    await continueButton(page).click();

    await expectStep(page, 4);
    await page.getByLabel('Project name').fill('Kitchen and island');
    await answerStoneRow(page, 0, {
        quantity: 3,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await answerStoneRow(page, 1, {
        quantity: 5,
        edge: CATALOG.edges[1],
        finish: CATALOG.finishes[1],
    });
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await shot(page, name('4-requirements'), testInfo);
    await page.getByRole('button', { name: 'Add to inquiry' }).click();

    await expectStep(page, 5);
    await expect(page.getByText(/8 pieces of 2 stones/)).toBeVisible();
    await shot(page, name('5-review'), testInfo);

    const reference = await sendAndReadReference(page);

    await expect(page.getByRole('status')).toContainText(
        "We've received your request for Kitchen and island.",
    );
    expect(reference).toMatch(/^INQ-/);
    await shot(page, name('6-result'), testInfo);
});

test('8 · the reference on the result page is the one new inquiries row for that client', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    const before = inquiryReferences(clientAId);

    await openInquire(page);
    await addVisitProject(page, { location: 'Pasig' });
    const reference = await sendAndReadReference(page);

    const after = inquiryReferences(clientAId);
    const added = after.filter((entry) => !before.includes(entry));

    expect(added).toEqual([reference]);
});

test('9 · the new inquiry is Pending in the Quote list, and its details show the project, the quantity and the requirements', async ({
    actors,
}) => {
    const { page } = await actors.open('client');

    await openInquire(page);
    await addStoneProject(page, {
        name: 'Guest bath vanity',
        rows: [
            {
                quantity: 7,
                edge: CATALOG.edges[1],
                finish: CATALOG.finishes[1],
            },
        ],
        location: 'Quezon City',
        landmark: 'Beside the chapel',
        notes: 'Second floor, no lift.',
    });
    const reference = await sendAndReadReference(page);

    await page.goto('/quote');
    const card = page.locator('article').filter({ hasText: reference });
    await expect(card).toHaveCount(1);
    await expect(card).toContainText('Pending');

    await card.getByRole('link').first().click();
    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));

    const main = page.locator('main');
    await expect(main).toContainText(reference);
    await expect(main).toContainText('Guest bath vanity');
    await expect(main).toContainText(CATALOG.stones[0]);
    await expect(main).toContainText('7 pcs');
    await expect(main).toContainText('Quezon City');
    await expect(main).toContainText('Beside the chapel');
    await expect(main).toContainText('Second floor, no lift.');
});

test('10 · cancelling a pending inquiry from the Quote list turns it Cancelled', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/quote');

    const sent = await postAsUser(page, '/quote/inquire', {
        projects: [projectPayload({ name: 'To be cancelled' })],
    });
    expect([302, 303], `store answered ${sent.status}`).toContain(sent.status);
    const [reference] = inquiryReferences(clientAId);

    await page.reload();
    const card = page.locator('article').filter({ hasText: reference });
    await expect(card).toContainText('Pending');

    await card
        .getByRole('button', { name: `Cancel inquiry ${reference}` })
        .click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText(`Cancel inquiry ${reference}?`);
    await dialog.getByRole('button', { name: 'Cancel inquiry' }).click();

    await expect(dialog).toHaveCount(0);
    await expect(card).toContainText('Cancelled');
    await expect(
        card.getByRole('button', { name: `Cancel inquiry ${reference}` }),
    ).toHaveCount(0);
    expect(
        php<string>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->value('status')`,
        ),
    ).toBe('cancelled');
});

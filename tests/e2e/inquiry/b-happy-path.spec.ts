import { credentialsFor, inquiryFixtures } from '../support/accounts';
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
    DELIVERY_METHOD_ID,
    REVIEW,
    addStoneProject,
    answerDeadline,
    chooseInSelect,
    chooseLocation,
    expectStep,
    fillSlabSizes,
    landmarkField,
    locationField,
    openInquire,
    openMoreDetails,
    pickService,
    pickStone,
    pressNext,
    renameProject,
    projectPayload,
    sendAndReadReference,
    answerStoneRow,
} from '../support/inquiry-flow';

/* B. Happy path: from the first click to the saved inquiry. */

test('6 · a pickup asks for no location, keeps its landmark, and sends @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;
    const name = (step: string) => `06-${step}-${width}`;

    await openInquire(page);
    await pickService(page);
    await shot(page, name('1-service'), testInfo);

    await pressNext(page, 1);
    await pickStone(page, CATALOG.stones[0]);
    await shot(page, name('2-stones'), testInfo);

    await pressNext(page, 2);
    await expect(locationField(page)).toBeVisible();
    await fillSlabSizes(page);
    await chooseInSelect(page, DELIVERY_METHOD_ID, 'Pickup');
    await expect(locationField(page)).toHaveCount(0);
    await openMoreDetails(page);
    await landmarkField(page).fill('Near the studio gate');
    await answerDeadline(page);
    await shot(page, name('3-details'), testInfo);
    await page.getByRole('button', { name: 'Continue to review' }).click();

    await expectStep(page, REVIEW);
    await expect(page.getByText('Pickup', { exact: true })).toBeVisible();
    await expect(
        page.getByText('Near the studio gate', { exact: true }),
    ).toBeVisible();
    await shot(page, name('4-review'), testInfo);
    const reference = await sendAndReadReference(page);

    await expect(
        page.getByRole('dialog', { name: 'Quote requested' }),
    ).toContainText(`We've received your request for ${CATALOG.service}.`);
    await shot(page, name('5-result'), testInfo);

    expect(
        php<Record<string, string | null>>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first(['delivery_method', 'location', 'landmark'])->toArray()`,
        ),
    ).toEqual({
        delivery_method: 'pickup',
        location: null,
        landmark: 'Near the studio gate',
    });
});

test('7 · two stones under one furniture service send, and Review shows the type and the service @layout', async ({
    actors,
}, testInfo) => {
    const { page } = await actors.open('client');
    const width = page.viewportSize()?.width ?? 0;
    const name = (step: string) => `07-${step}-${width}`;

    await openInquire(page);
    await pickService(page, CATALOG.furnitureType, CATALOG.furniture);
    await shot(page, name('1-service'), testInfo);

    await pressNext(page, 1);
    await pickStone(page, CATALOG.stones[0]);
    await pickStone(page, CATALOG.stones[1]);
    await shot(page, name('2-stones'), testInfo);

    await pressNext(page, 2);
    await answerStoneRow(page, 0, {
        quantity: 3,
        edge: CATALOG.edges[0],
        finish: CATALOG.finishes[0],
    });
    await page.locator('#requirements-slab-size-0').fill('120 x 60 cm');
    await answerStoneRow(page, 1, {
        quantity: 5,
        edge: CATALOG.edges[1],
        finish: CATALOG.finishes[1],
    });
    await page.locator('#requirements-slab-size-1').fill('60 x 60 cm');
    await chooseLocation(page, 'Makati');
    await answerDeadline(page);
    await shot(page, name('3-details'), testInfo);
    await page.getByRole('button', { name: 'Continue to review' }).click();

    await expectStep(page, REVIEW);
    await expect(page.getByText(/8 pieces of 2 stones/)).toBeVisible();
    await expect(page.getByText('Size: 120 x 60 cm')).toBeVisible();
    await expect(page.getByTestId('review-service')).toContainText(
        CATALOG.furnitureType,
    );
    await expect(page.getByTestId('review-service')).toContainText(
        CATALOG.furniture,
    );
    await renameProject(page, 'Kitchen and island');
    await shot(page, name('4-review'), testInfo);

    const reference = await sendAndReadReference(page);

    await expect(
        page.getByRole('dialog', { name: 'Quote requested' }),
    ).toContainText("We've received your request for Kitchen and island.");
    await shot(page, name('5-result'), testInfo);

    expect(
        php<string>(
            `App\\Models\\Inquiry::query()->with('service')->where('reference', '${reference}')->first()->service->slug`,
        ),
    ).toBe(CATALOG.furnitureSlug);
    expect(
        php<string[]>(
            `App\\Models\\Inquiry::query()->with('stones.stone')->where('reference', '${reference}')->first()->stones->sortBy('sort_order')->pluck('stone.name')->values()`,
        ),
    ).toEqual([...CATALOG.stones]);
});

test('8 · the reference on the result page is the one new inquiries row for that client', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    const before = inquiryReferences(clientAId);

    await openInquire(page);
    await addStoneProject(page, { location: 'Pasig' });
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

    await page.goto('/dashboard/quote');
    const card = page.locator('article').filter({ hasText: reference });
    await expect(card).toHaveCount(1);
    await expect(card).toContainText('Pending');

    await card.getByRole('link').first().click();
    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));

    const main = page.locator('main');
    await expect(main).toContainText(reference);
    await expect(main).toContainText('Guest bath vanity');
    await expect(main).toContainText(CATALOG.stones[0]);
    await expect(main).toContainText(CATALOG.serviceType);
    await expect(main).toContainText(CATALOG.service);
    await expect(main).toContainText('7 pcs');
    await expect(main).toContainText('Quezon City');
    await expect(main).toContainText('Beside the chapel');
    await expect(main).toContainText('Second floor, no lift.');
});

test('9a · Log out asks first, and only leaves when told to', async ({
    actors,
}) => {
    const { email, password } = credentialsFor('client');
    const { page } = await actors.open('guest');

    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    await page.goto('/dashboard/profile');

    await page.locator('[data-test="logout-button"]').click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Log out?');

    await dialog.getByRole('button', { name: 'Stay logged in' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page).toHaveURL(/\/dashboard\/profile/);

    await page.locator('[data-test="logout-button"]').click();
    await dialog.getByRole('button', { name: 'Log out' }).click();
    await expect(page).not.toHaveURL(/\/dashboard/);
});

test('10 · Cancel on the Quote card asks first, and only cancels when told to', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/dashboard/quote');

    const sent = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [projectPayload({ name: 'To be cancelled' })],
    });
    expect([302, 303], `store answered ${sent.status}`).toContain(sent.status);
    const [reference] = inquiryReferences(clientAId);

    await page.reload();
    const card = page.locator('article').filter({ hasText: reference });
    await expect(card).toContainText('Pending');

    const cancel = card.getByRole('button', { name: 'Cancel', exact: true });
    const dialog = page.getByRole('dialog');

    await cancel.click();
    await expect(dialog).toContainText('Cancel this inquiry?');
    await dialog.getByRole('button', { name: 'Keep inquiry' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(card).toContainText('Pending');

    await cancel.click();
    await dialog.getByRole('button', { name: 'Cancel inquiry' }).click();
    await expect(dialog).toHaveCount(0);

    await page.reload();
    await expect(card).toContainText('Cancelled');
    expect(
        php<string>(
            `App\\Models\\Inquiry::query()->where('reference', '${reference}')->first()->status->value`,
        ),
    ).toBe('cancelled');
});

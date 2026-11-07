import { expect, test } from '../support/fixtures';
import {
    CATALOG,
    REVIEW,
    SERVICE_ID,
    SERVICE_TYPE_ID,
    addStoneProject,
    checkbox,
    chooseInSelect,
    continueButton,
    expectStep,
    openInquire,
    renameProject,
    sendAndReadReference,
} from '../support/inquiry-flow';

/* L. Editing a sent inquiry through its edit page. */

test("42 · the edit page opens the inquiry's project at step 1, it is edited through to Review, and Save changes shows it on the details page", async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page, { name: 'Kitchen counters' });
    const reference = await sendAndReadReference(page);

    await page.goto(`/dashboard/quote/${reference}/edit`);

    await expect(page).toHaveURL(new RegExp(`/quote/${reference}/edit$`));
    await expect(
        page.getByRole('heading', { name: `Update ${reference}` }),
    ).toBeVisible();
    // An inquiry is one project: the edit opens it at the first step, with
    // every answer already in place.
    await expectStep(page, 1);
    await expect(
        page.getByRole('link', { name: 'Back to Quote' }),
    ).toHaveAttribute('href', /\/quote$/);

    await expect(page.locator(`#${SERVICE_TYPE_ID}`)).toContainText(
        CATALOG.serviceType,
    );
    await expect(page.locator(`#${SERVICE_ID}`)).toContainText(CATALOG.service);
    await chooseInSelect(page, SERVICE_ID, 'Flooring');

    await continueButton(page).click();
    await expectStep(page, 2);
    await expect(checkbox(page, CATALOG.stones[0])).toHaveAttribute(
        'aria-checked',
        'true',
    );

    await continueButton(page).click();
    await expectStep(page, 3);

    await page.getByRole('button', { name: 'Continue to review' }).click();

    await expectStep(page, REVIEW);
    await expect(
        page.getByText('Kitchen counters', { exact: true }).first(),
    ).toBeVisible();
    await renameProject(page, 'Kitchen and island');
    await expect(page.getByRole('button', { name: /^Remove / })).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: /Add (a|another) project/ }),
    ).toHaveCount(0);
    await expect(
        page.getByText('Nothing is saved until you save changes'),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));
    await expect(page.getByText('Kitchen and island').first()).toBeVisible();
    await expect(page.locator('main')).toContainText('Flooring');
});

import { expect, test } from '../support/fixtures';
import {
    addStoneProject,
    expectStep,
    openInquire,
    sendAndReadReference,
} from '../support/inquiry-flow';

/* L. Editing a sent inquiry from the Quote page. */

test('42 · Update opens Review, one project is edited, and Save changes shows it on the details page', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page, { name: 'Kitchen counters' });
    const reference = await sendAndReadReference(page);

    await page.goto('/quote');
    await page
        .locator('article')
        .filter({ hasText: reference })
        .getByRole('link', { name: 'Update' })
        .click();

    await expect(page).toHaveURL(new RegExp(`/quote/${reference}/edit$`));
    await expect(
        page.getByRole('heading', { name: `Update ${reference}` }),
    ).toBeVisible();
    await expectStep(page, 5);
    await expect(
        page.getByRole('link', { name: 'Back to Quote' }),
    ).toHaveAttribute('href', /\/quote$/);
    await expect(page.getByText('Kitchen counters')).toBeVisible();
    await expect(page.getByRole('button', { name: /^Remove / })).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: /Add (a|another) project/ }),
    ).toHaveCount(0);
    await expect(
        page.getByText('Nothing is saved until you save changes'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Edit Kitchen counters' }).click();
    await expectStep(page, 4);
    await page.getByLabel('Project name').fill('Kitchen and island');
    await page.getByRole('button', { name: 'Back to Review' }).click();

    await expectStep(page, 5);
    await expect(page.getByText('Kitchen and island')).toBeVisible();
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page).toHaveURL(new RegExp(`/quote/${reference}$`));
    await expect(page.getByText('Kitchen and island').first()).toBeVisible();
});

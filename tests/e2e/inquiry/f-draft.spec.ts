import type { Page } from '@playwright/test';
import {
    credentialsFor,
    inquiryFixtures,
    type Role,
} from '../support/accounts';
import { expect, test } from '../support/fixtures';
import {
    CATALOG,
    checkbox,
    continueButton,
    expectStep,
    openInquire,
    pickService,
    pickStone,
} from '../support/inquiry-flow';

/* F. The draft kept in this browser between visits. */

const draftKey = (userId: number): string =>
    `regray.inquiry-draft.v4:${userId}`;

const storedDraft = (page: Page, userId: number) =>
    page.evaluate((key) => window.localStorage.getItem(key), draftKey(userId));

const signInThroughForm = async (page: Page, role: Role): Promise<void> => {
    const { email, password } = credentialsFor(role);
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
};

/** Sign out the way the app does: a POST to /logout with the XSRF token. */
const signOut = async (page: Page): Promise<void> => {
    const status = await page.evaluate(async () => {
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((part) => part.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length) ?? '',
        );
        const response = await fetch('/logout', {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        return response.status;
    });

    expect([200, 204, 302]).toContain(status);
};

test('25 · after a reload the draft is back, on a step its answers justify', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);

    await pickService(page, CATALOG.stoneService);
    await continueButton(page).click();
    await expectStep(page, 2);
    await pickStone(page, CATALOG.stones[0]);
    await continueButton(page).click();
    await expectStep(page, 3);
    await expect.poll(() => storedDraft(page, clientAId)).not.toBeNull();

    await page.reload();
    await expectStep(page, 3);

    // A stored step further on than the answers allow is pulled back.
    await page.evaluate((key) => {
        const record = JSON.parse(window.localStorage.getItem(key) ?? '{}');
        window.localStorage.setItem(
            key,
            JSON.stringify({ ...record, step: 5 }),
        );
    }, draftKey(clientAId));
    await page.reload();
    await expectStep(page, 3);
    await expect(
        page.getByRole('heading', { name: 'Application' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Back', exact: true }).click();
    await expectStep(page, 2);
    await expect(checkbox(page, CATALOG.stones[0])).toHaveAttribute(
        'aria-checked',
        'true',
    );
    await page.getByRole('button', { name: 'Back', exact: true }).click();
    await expectStep(page, 1);
    await expect(checkbox(page, CATALOG.stoneService)).toHaveAttribute(
        'aria-checked',
        'true',
    );
});

test('26 · "Start new" clears the draft, and a reload stays empty', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openInquire(page);
    await pickService(page, CATALOG.stoneService);
    await continueButton(page).click();
    await expectStep(page, 2);
    await expect.poll(() => storedDraft(page, clientAId)).not.toBeNull();

    await page.goto('/quote');
    await page
        .getByRole('button', { name: 'Inquire', exact: true })
        .first()
        .click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Continue your draft?');
    await dialog.getByRole('button', { name: 'Start new' }).click();

    await expect(page).toHaveURL(/\/quote\/inquire$/);
    await expectStep(page, 1);
    await expect(checkbox(page, CATALOG.stoneService)).toHaveAttribute(
        'aria-checked',
        'false',
    );

    await page.reload();
    await expectStep(page, 1);
    await expect(page.getByRole('checkbox', { checked: true })).toHaveCount(0);
    expect(await storedDraft(page, clientAId)).toBeNull();
});

test("27 · on a shared browser, client B never sees client A's draft", async ({
    actors,
}) => {
    const { clientAId, clientBId } = inquiryFixtures();
    const { page } = await actors.open('guest');

    await signInThroughForm(page, 'client');
    await openInquire(page);
    await pickService(page, CATALOG.visitService);
    await continueButton(page).click();
    await expectStep(page, 2);
    await expect.poll(() => storedDraft(page, clientAId)).not.toBeNull();

    await signOut(page);
    await signInThroughForm(page, 'clientB');
    await openInquire(page);

    await expectStep(page, 1);
    await expect(page.getByRole('checkbox', { checked: true })).toHaveCount(0);
    expect(await storedDraft(page, clientBId)).toBeNull();
});

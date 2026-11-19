import type { Page } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, shot, test } from '../support/fixtures';
import { MAPBOX_PLACE } from '../support/mapbox-mock';
import {
    BRAND_QUESTION,
    CATALOG,
    DELIVERY_METHOD_ID,
    QUESTIONS,
    brandCard,
    chooseInSelect,
    locationField,
    nextButton,
    openInquire,
    pickService,
    pickStone,
    pressNext,
} from '../support/inquiry-flow';

/*
 * P. The brand question, the stones' Brand filter, how the work is got by
 * service, the address book with its map, and the walk-in form.
 *
 * Mapbox is answered in the browser (support/mapbox-mock.ts) and the
 * browser's location is set, so nothing here reaches the network. The map
 * only shows when the build has a Mapbox token and the browser has WebGL;
 * without either the picker is its fields alone, and the map's own checks
 * are skipped with a note.
 */

/** The widths the layout is checked at: a phone, a tablet and a desktop. */
const WIDTHS = [375, 768, 1280] as const;

const comboboxFor = (page: Page, label: RegExp) =>
    page.getByRole('combobox', { name: label });

/** Pick `option` in one of the picker's searched lists, typing `query` first. */
const pickPlace = async (
    page: Page,
    label: RegExp,
    query: string,
    option: string,
): Promise<void> => {
    const box = comboboxFor(page, label);

    await box.click();
    await box.fill(query);
    await page.getByRole('option', { name: option, exact: true }).click();
    await expect(box).toHaveValue(option);
};

const noSideScroll = async (page: Page): Promise<void> => {
    expect(
        await page.evaluate(
            () =>
                document.documentElement.scrollWidth <=
                document.documentElement.clientWidth,
        ),
        'no horizontal scroll',
    ).toBe(true);
};

test.describe('the brand question', () => {
    test('comes first, is required, and No preference excludes the brands', async ({
        actors,
    }) => {
        const { page } = await actors.open('client');

        await openInquire(page, undefined, { brands: 'leave' });
        await expect(
            page.getByRole('heading', { name: QUESTIONS[0] }),
        ).toHaveCount(0);

        await nextButton(page).click();
        await expect(
            page.getByText('Choose at least one brand, or No preference.'),
        ).toBeVisible();

        const regray = brandCard(page, 'Regray Stone');
        const none = brandCard(page, 'No preference');

        await regray.click();
        await expect(regray).toHaveAttribute('aria-pressed', 'true');

        await none.click();
        await expect(none).toHaveAttribute('aria-pressed', 'true');
        await expect(regray).toHaveAttribute('aria-pressed', 'false');

        await regray.click();
        await expect(none).toHaveAttribute('aria-pressed', 'false');

        await nextButton(page).click();
        await expect(
            page.getByRole('heading', { name: QUESTIONS[0] }),
        ).toBeVisible();
        // Still open above the next question, as every answered question stays.
        await expect(
            page.getByRole('heading', { name: BRAND_QUESTION }),
        ).toBeVisible();
    });
});

test.describe('the stones Brand filter', () => {
    test('asks the server with stone_brands[], and says when no stone matches', async ({
        actors,
    }) => {
        const { page } = await actors.open('client');

        await openInquire(page);
        await pickService(page);
        await pressNext(page, 1);

        const filter = page.getByTestId('stone-brand-filter');

        await expect(filter).toContainText('All brands');

        // Cucine Lube makes kitchens: no stone of its own.
        await filter.click();
        await page
            .getByRole('menuitemcheckbox', { name: 'Cucine Lube' })
            .click();
        await page.keyboard.press('Escape');

        await expect(page).toHaveURL(
            /stone_brands%5B%5D=cucine|stone_brands\[\]=cucine/,
        );
        await expect(
            page.getByText('No stones from these brands'),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Clear filters' }).click();
        await expect(filter).toContainText('All brands');
        await expect(page).not.toHaveURL(/stone_brands/);
        await pickStone(page, CATALOG.stones[0]);
    });
});

test.describe('how the work is got', () => {
    test('a delivered service asks Delivery or Pickup, and a pickup asks no address', async ({
        actors,
    }) => {
        const { page } = await actors.open('client');

        await openInquire(page);
        await pickService(page, CATALOG.furnitureType, CATALOG.furniture);
        await pressNext(page, 1);
        await pickStone(page, CATALOG.stones[0]);
        await pressNext(page, 2);

        await chooseInSelect(page, DELIVERY_METHOD_ID, 'Delivery');

        const addresses = locationField(page);

        await expect(addresses).toBeVisible();
        await expect(addresses).toHaveAccessibleName(/Delivery address/);
        // The default is already picked.
        await expect(
            addresses.getByRole('radio', { name: /^Home/ }),
        ).toBeChecked();

        await chooseInSelect(page, DELIVERY_METHOD_ID, 'Pickup');
        await expect(addresses).toHaveCount(0);
    });

    test('an installed service asks only the installation address', async ({
        actors,
    }) => {
        const { page } = await actors.open('client');

        await openInquire(page);
        await pickService(page, CATALOG.serviceType, 'Installation Only');
        await pressNext(page, 1);

        await expect(page.locator(`#${DELIVERY_METHOD_ID}`)).toHaveCount(0);
        await expect(locationField(page)).toHaveAccessibleName(
            /Installation address/,
        );
    });

    test('a client with no saved address is sent to add one, and comes back to the draft', async ({
        actors,
    }) => {
        const { page } = await actors.open('noAddresses');

        await openInquire(page);
        await pickService(page, CATALOG.serviceType, 'Installation Only');
        await pressNext(page, 1);

        await expect(
            page.getByText('Add an address to continue'),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Add an address' }).click();

        await expect(page).toHaveURL(
            /\/dashboard\/profile\/addresses\?from=inquire/,
        );
        await page.getByRole('link', { name: 'Back to your request' }).click();

        // The draft came back: the service is still chosen.
        await expect(page.locator('#inquiry-service')).toContainText(
            'Installation Only',
        );
    });
});

test.describe('the address book', () => {
    for (const width of WIDTHS) {
        test(`adds an address with the picker and the map at ${width}px`, async ({
            actors,
        }, testInfo) => {
            const { page, context } = await actors.open('clientB');

            await page.setViewportSize({ width, height: 900 });
            await context.grantPermissions(['geolocation']);
            await context.setGeolocation({
                latitude: MAPBOX_PLACE.lat,
                longitude: MAPBOX_PLACE.lng,
            });

            await page.goto('/dashboard/profile/addresses');
            await expect(
                page.locator('[data-test="address-card"]'),
            ).toHaveCount(inquiryFixtures().addresses?.clientB.length ?? 1);
            await noSideScroll(page);

            await page.locator('[data-test="addresses-add"]').click();

            const dialog = page.getByRole('dialog');

            await expect(dialog).toBeVisible();
            await dialog.getByRole('button', { name: 'Site' }).click();

            await pickPlace(
                page,
                /^Region/,
                'national',
                'National Capital Region',
            );
            // NCR has no provinces: the province is not asked.
            await pickPlace(
                page,
                /^City or municipality/,
                'makati',
                'City of Makati',
            );
            await pickPlace(page, /^Barangay/, 'bel', 'Bel-Air');

            const map = page.getByTestId('address-map');

            if ((await map.count()) > 0) {
                const fields = comboboxFor(page, /^Region/);
                const [fieldBox, mapBox] = [
                    await fields.boundingBox(),
                    await map.boundingBox(),
                ];

                if (width >= 1024) {
                    // The map first: on the fields' left from lg.
                    expect(mapBox!.x + mapBox!.width).toBeLessThanOrEqual(
                        fieldBox!.x,
                    );
                } else {
                    // Above them below it.
                    expect(mapBox!.y + mapBox!.height).toBeLessThanOrEqual(
                        fieldBox!.y,
                    );
                }

                await page
                    .getByRole('button', { name: 'Use my current location' })
                    .click();
                await expect(comboboxFor(page, /^Barangay/)).toHaveValue(
                    'Bel-Air',
                );
            } else {
                testInfo.annotations.push({
                    type: 'note',
                    description:
                        'No map: the build has no Mapbox token or the browser no WebGL. The fields were checked alone.',
                });
            }

            await noSideScroll(page);
            await shot(page, `p-address-dialog-${width}`, testInfo);

            await dialog.locator('[data-test="address-save"]').click();
            await expect(dialog).toHaveCount(0);
            await expect(
                page
                    .locator('[data-test="address-card"]')
                    .filter({ hasText: 'Bel-Air' }),
            ).toBeVisible();
            await noSideScroll(page);
        });
    }

    test('says why the location could not be used', async ({ actors }) => {
        const { page, context } = await actors.open('clientB');

        await context.clearPermissions();
        await page.goto('/dashboard/profile/addresses');
        await page.locator('[data-test="addresses-add"]').click();

        const locate = page.getByRole('button', {
            name: 'Use my current location',
        });

        test.skip((await locate.count()) === 0, 'No map in this build.');

        await locate.click();
        await expect(
            page.getByText(
                /Location access is turned off|couldn't find your location/,
            ),
        ).toBeVisible();
    });
});

test.describe('the walk-in form', () => {
    test('asks only the name, the phone and an optional email', async ({
        actors,
    }) => {
        const { page } = await actors.open('sales');

        await page.goto('/dashboard/inquiry/inquire?kind=direct');

        const form = page.getByTestId('direct-client-form');

        await expect(form.getByLabel(/^Full name/)).toBeVisible();
        await expect(form.getByLabel(/^Phone number/)).toBeVisible();
        await expect(form.getByLabel('Email (optional)')).toBeVisible();
        await expect(form.getByLabel(/Company/)).toHaveCount(0);
        await expect(form.getByLabel(/Address/)).toHaveCount(0);
        await expect(
            form.getByRole('button', { name: 'Add another contact' }),
        ).toHaveCount(0);
    });
});

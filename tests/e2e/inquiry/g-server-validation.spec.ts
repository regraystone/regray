import { inquiryFixtures } from '../support/accounts';
import { expect, inquiryCount, postAsUser, test } from '../support/fixtures';
import { CATALOG, projectPayload } from '../support/inquiry-flow';

/*
 * G. The server's own validation, with the page bypassed: requests sent with
 * the signed-in client's cookies straight to POST /quote/inquire.
 */

test('28 · an inactive service slug is refused with 422 at its form path, and nothing is saved', async ({
    actors,
}) => {
    const { clientAId, retiredService } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/quote/inquire', {
        projects: [projectPayload({ serviceIds: [retiredService] })],
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors).toEqual({
        'projects.0.serviceIds.0': ['That service is no longer available.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

test('29 · a quantity of 10000 and an edge not in the catalogue are refused with 422, and nothing is saved', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/quote/inquire', {
        projects: [
            projectPayload({
                stones: [
                    {
                        stoneId: CATALOG.stoneSlug,
                        quantity: 10000,
                        edge: 'Laser Etched Edge',
                        finish: CATALOG.finishes[0],
                    },
                ],
            }),
        ],
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors).toEqual({
        'projects.0.stones.0.quantity': ['Enter 9999 pieces or fewer.'],
        'projects.0.stones.0.edge': ['Choose an edge profile.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

test('30 · an 11-project request is refused by the server', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/quote/inquire', {
        projects: Array.from({ length: 11 }, (_, index) =>
            projectPayload({ name: `Project ${index + 1}` }),
        ),
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors).toEqual({
        projects: ['An inquiry can hold up to 10 projects.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

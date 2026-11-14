import { inquiryFixtures } from '../support/accounts';
import { expect, inquiryCount, postAsUser, test } from '../support/fixtures';
import {
    CATALOG,
    SLAB_SIZE,
    THICKNESS,
    projectPayload,
} from '../support/inquiry-flow';

/*
 * G. The server's own validation, with the page bypassed: requests sent with
 * the signed-in client's cookies straight to POST /quote/inquire.
 */

test('28 · a service that is not offered is refused with 422 at its form path, and nothing is saved', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/dashboard/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [projectPayload({ serviceId: 'consultation-only' })],
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors).toEqual({
        'projects.0.serviceId': ['That choice is no longer available.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

test('29 · a quantity of 10000 and an edge not in the catalogue are refused with 422, and nothing is saved', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/dashboard/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [
            projectPayload({
                stones: [
                    {
                        stoneId: CATALOG.stoneSlug,
                        quantity: 10000,
                        slabSize: SLAB_SIZE,
                        thickness: THICKNESS,
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
        'projects.0.stones.0.edge': ['Choose the edge profile from the list.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

test('30 · a request with a second project is refused by the server', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await page.goto('/dashboard/quote');
    const before = inquiryCount(clientAId);

    const response = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: Array.from({ length: 2 }, (_, index) =>
            projectPayload({ name: `Project ${index + 1}` }),
        ),
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors).toEqual({
        projects: ['An inquiry can only have one project.'],
    });
    expect(inquiryCount(clientAId)).toBe(before);
});

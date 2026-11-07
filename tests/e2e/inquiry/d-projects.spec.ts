import { expect, postAsUser, test } from '../support/fixtures';
import {
    CATALOG,
    addStoneProject,
    openInquire,
    projectPayload,
    renameProject,
} from '../support/inquiry-flow';

/* D. An inquiry is one project. */

test('18 · Review offers no second project, and the server refuses one', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 422/);
    await openInquire(page);
    await addStoneProject(page);

    await expect(
        page.getByRole('button', { name: /Add (a|another) project/ }),
    ).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^Remove / })).toHaveCount(0);

    const response = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: [projectPayload(), projectPayload()],
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors?.projects).toEqual([
        'An inquiry can only have one project.',
    ]);
});

test('19 · the project is named from its answers, and the name can be changed on Review', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page);

    await expect(
        page.getByText(CATALOG.service, { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: `Change name: ${CATALOG.service}` }),
    ).toBeVisible();

    await renameProject(page, 'Our new kitchen');
    await expect(
        page.getByRole('button', { name: `Change name: ${CATALOG.service}` }),
    ).toHaveCount(0);
});

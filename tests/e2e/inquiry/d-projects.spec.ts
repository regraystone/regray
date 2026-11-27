import { expect, postAsUser, test } from '../support/fixtures';
import {
    CATALOG,
    addStoneProject,
    openInquire,
    projectPayload,
    renameProject,
} from '../support/inquiry-flow';

/* D. A sending holds up to five projects, each its own inquiry. */

test('18 · Review adds and removes a project, and the server refuses a sixth', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 422/);
    await openInquire(page);
    await addStoneProject(page);

    const addProject = page.getByRole('button', {
        name: 'Add another project',
    });
    await expect(addProject).toBeVisible();
    // With one project there is nothing to number, and nothing left to remove it for.
    await expect(page.getByText('Project 1', { exact: true })).toHaveCount(0);

    const response = await postAsUser(page, '/dashboard/quote/inquire', {
        projects: Array.from({ length: 6 }, () => projectPayload()),
    });

    expect(response.status).toBe(422);
    expect(response.json?.errors?.projects).toEqual([
        'Send up to 5 projects at a time.',
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

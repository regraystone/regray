import type { Page } from '@playwright/test';
import { expect, test } from '../support/fixtures';
import {
    CATALOG,
    addStoneProject,
    addVisitProject,
    expectStep,
    locationField,
    openInquire,
} from '../support/inquiry-flow';

/* D. More than one project in one inquiry. */

const addAnother = (page: Page) =>
    page.getByRole('button', { name: /^Add (another|a) project$/ });

/** The project headings on Review, in order. */
const projectHeadings = (page: Page) => page.getByRole('heading', { level: 3 });

test('18 · up to 10 projects can be added; an 11th is blocked', async ({
    actors,
}) => {
    test.setTimeout(240_000);
    const { page } = await actors.open('client');
    await openInquire(page);

    await addVisitProject(page);

    for (let count = 2; count <= 10; count += 1) {
        await expect(addAnother(page)).toBeEnabled();
        await addAnother(page).click();
        await expectStep(page, 1);
        await addVisitProject(page, { landmark: `Site ${count}` });
        await expect(projectHeadings(page)).toHaveCount(count);
    }

    await expect(addAnother(page)).toBeDisabled();
    await expect(
        page.getByText(
            'This request already has 10 projects, which is the most it can hold.',
        ),
    ).toBeVisible();
    await expect(projectHeadings(page).last()).toContainText('Project 10');
});

test('19 · removing a project in the middle keeps the others’ answers and renumbers them', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    const projects = [
        { name: 'Kitchen counters', location: 'Makati', landmark: 'First' },
        { name: 'Lobby wall', location: 'Taguig', landmark: 'Second' },
        { name: 'Bathroom vanity', location: 'Pasig', landmark: 'Third' },
    ];

    for (const [index, project] of projects.entries()) {
        if (index > 0) {
            await addAnother(page).click();
        }

        await addStoneProject(page, project);
    }

    await expect(projectHeadings(page)).toHaveCount(3);

    await page
        .getByRole('button', { name: `Remove ${projects[1].name}` })
        .click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toContainText('Remove Project 2?');
    await dialog.getByRole('button', { name: 'Remove project' }).click();

    await expect(projectHeadings(page)).toHaveCount(2);
    await expect(projectHeadings(page).nth(0)).toHaveText(
        new RegExp(`^Project 1\\s*${escape(projects[0].name)}$`),
    );
    await expect(projectHeadings(page).nth(1)).toHaveText(
        new RegExp(`^Project 2\\s*${escape(projects[2].name)}$`),
    );

    for (const project of [projects[0], projects[2]]) {
        const card = page
            .locator('section')
            .filter({
                has: page.getByRole('heading', { name: project.name }),
            })
            .last();
        await expect(card).toContainText(project.location);
        await expect(card).toContainText(project.landmark);
    }

    await expect(
        page.getByText(projects[1].landmark, { exact: true }),
    ).toHaveCount(0);
});

test("20 · each project's requirements are its own: project 1's location is not on project 2", async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openInquire(page);

    await addVisitProject(page, {
        location: 'Makati',
        landmark: 'Only on one',
    });
    await addAnother(page).click();
    await expectStep(page, 1);

    await page
        .getByRole('checkbox', {
            name: CATALOG.visitService,
            exact: true,
        })
        .click();
    for (let step = 1; step <= 3; step += 1) {
        await page
            .getByRole('button', { name: 'Continue', exact: true })
            .click();
    }
    await expectStep(page, 4);

    await expect(locationField(page)).toHaveValue('');
    await expect(page.getByLabel('Landmark')).toHaveValue('');
    await expect(page.getByLabel(/^Target deadline/)).toHaveValue('');
});

const escape = (text: string): string =>
    text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

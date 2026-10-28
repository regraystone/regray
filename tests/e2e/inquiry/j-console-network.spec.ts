import { allowedHosts, expect, test } from '../support/fixtures';
import {
    addStoneProject,
    addVisitProject,
    openInquire,
    openStaffInquire,
    sendAndReadReference,
} from '../support/inquiry-flow';

/*
 * J. Console and network. Every spec in this suite already fails on a console
 * error outside CONSOLE_ALLOW_LIST and on a request to a foreign host (see
 * support/fixtures.ts). These two walk the whole flow for each role so the
 * checks also cover the pages around it.
 */

test('38 · the whole flow, for the client and for marketing, logs no console errors or page errors', async ({
    actors,
}) => {
    const client = await actors.open('client');
    await openInquire(client.page);
    await addStoneProject(client.page, { name: 'Console tour' });
    const reference = await sendAndReadReference(client.page);
    await client.page.goto('/quote');
    await client.page.goto(`/quote/${reference}`);
    await expect(client.page.locator('main')).toContainText(reference);

    const marketing = await actors.open('marketing');
    await openStaffInquire(marketing.page);
    await addVisitProject(marketing.page);

    expect(client.errors).toEqual([]);
    expect(marketing.errors).toEqual([]);
});

test('39 · the flow talks only to the app and its asset host', async ({
    actors,
}) => {
    const { page, requests } = await actors.open('client');
    await openInquire(page);
    await addStoneProject(page, { name: 'Network tour' });
    await sendAndReadReference(page);

    const hosts = allowedHosts();
    const web = requests.filter((url) => /^https?:/.test(url));

    expect(web.length).toBeGreaterThan(0);
    expect(
        [...new Set(web.map((url) => new URL(url).host))].filter(
            (host) => !hosts.has(host),
        ),
    ).toEqual([]);
});

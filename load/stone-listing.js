/*
 * Scenario 3: 50 clients browse the Home stone feed with filters at once.
 *
 * Each virtual user signs in as its own client (load-client-1..50@example.com,
 * made by LoadTestSeeder), so per-user limits see 50 people, not one. Each
 * iteration is the Inertia visit a filter change makes, then a pause.
 *
 * Fails when p95 of the listing is over 800 ms or 5xx are over 0.5%.
 */
import { check, sleep } from 'k6';
import http from 'k6/http';
import {
    BASE_URL,
    assertSafeTarget,
    inertiaHeaders,
    login,
    sharedThresholds,
    track,
} from './lib/common.js';

export const options = {
    // Keep each VU signed in across iterations; k6 empties the jar each one by default.
    noCookiesReset: true,
    scenarios: {
        browse: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '20s', target: 50 },
                { duration: '2m', target: 50 },
                { duration: '10s', target: 0 },
            ],
            gracefulRampDown: '10s',
        },
    },
    thresholds: {
        ...sharedThresholds,
        'http_req_duration{name:stone-listing}': ['p(95)<800'],
    },
};

const SEARCHES = ['', '', 'marble', 'granite', 'white', 'quartz'];

let version = null;
let filterOptions = null;

const pick = (list) => list[Math.floor(Math.random() * list.length)];

/** A random mix of the feed's own filters, each sometimes left off. */
const randomQuery = () => {
    const query = { collection: 'stones' };
    const search = pick(SEARCHES);

    if (search) {
        query.search = search;
    }

    for (const [key, options] of [
        ['category', filterOptions.categories],
        ['size', filterOptions.sizes],
        ['thickness', filterOptions.thicknesses],
    ]) {
        if (options.length > 0 && Math.random() < 0.4) {
            query[key] = pick(options).value;
        }
    }

    if (Math.random() < 0.2) {
        query.page = 2;
    }

    return Object.entries(query)
        .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
        .join('&');
};

export default function () {
    if (version === null) {
        assertSafeTarget();
        version = login(`load-client-${__VU}@example.com`);

        const first = http.get(`${BASE_URL}/home`, {
            headers: inertiaHeaders(version),
            tags: { name: 'stone-listing-first' },
        });
        filterOptions = first.json('props.filterOptions');
    }

    const response = track(
        http.get(`${BASE_URL}/home?${randomQuery()}`, {
            headers: inertiaHeaders(version),
            tags: { name: 'stone-listing' },
        }),
    );

    check(response, { 'listing answered 200': (r) => r.status === 200 });

    sleep(1 + Math.random());
}

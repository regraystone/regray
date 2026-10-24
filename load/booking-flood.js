/*
 * Scenario 1: one client floods POST /schedule/book with valid bookings.
 *
 * Expect: no 5xx. With the `booking` limiter on (10 a minute per user), the
 * first 10 in a minute are saved (302) and the rest are refused with 429.
 */
import { check } from 'k6';
import http from 'k6/http';
import {
    BASE_URL,
    assertSafeTarget,
    inertiaHeaders,
    sharedSession,
    sharedThresholds,
    track,
    useSession,
} from './lib/common.js';

export const options = {
    // Keep each VU signed in across iterations; k6 empties the jar each one by default.
    noCookiesReset: true,
    scenarios: {
        flood: {
            executor: 'constant-arrival-rate',
            rate: 2,
            timeUnit: '1s',
            duration: '60s',
            preAllocatedVUs: 5,
            maxVUs: 10,
        },
    },
    thresholds: {
        ...sharedThresholds,
        'http_req_duration{name:booking}': ['p(95)<800'],
    },
};

const CLIENT = __ENV.BOOKING_EMAIL || 'client@example.com';

/** A week from now, by the studio's clock (Asia/Manila, UTC+8), as `YYYY-MM-DD`. */
const bookingDay = () =>
    new Date(Date.now() + 8 * 3600 * 1000 + 7 * 86400 * 1000)
        .toISOString()
        .slice(0, 10);

export function setup() {
    assertSafeTarget();

    return sharedSession(CLIENT);
}

export default function (session) {
    if (__ITER === 0) {
        useSession(session);
    }

    const response = track(
        http.post(
            `${BASE_URL}/schedule/book`,
            JSON.stringify({
                date: bookingDay(),
                arrivalTime: '10:00',
                purpose: 'Load test booking',
                notes: '',
            }),
            {
                headers: {
                    ...inertiaHeaders(session.version),
                    'Content-Type': 'application/json',
                },
                redirects: 0,
                tags: { name: 'booking' },
            },
        ),
    );

    check(response, {
        'saved (302) or rate limited (429)': (r) =>
            r.status === 302 || r.status === 429,
    });
}

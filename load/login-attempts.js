/*
 * Scenario 4: ten wrong-password logins for one account from one IP.
 *
 * Expect: attempts 1 to 5 are refused as invalid (422), and from the sixth
 * on the `login` limiter answers 429. No 5xx.
 */
import { check } from 'k6';
import http from 'k6/http';
import {
    BASE_URL,
    assertSafeTarget,
    jsonHeaders,
    sharedThresholds,
    track,
} from './lib/common.js';

export const options = {
    // Keep each VU signed in across iterations; k6 empties the jar each one by default.
    noCookiesReset: true,
    scenarios: {
        attempts: {
            executor: 'shared-iterations',
            vus: 1,
            iterations: 10,
            maxDuration: '1m',
        },
    },
    thresholds: {
        ...sharedThresholds,
        'http_req_duration{name:login-attempt}': ['p(95)<800'],
        'checks{check:limited from the sixth attempt}': ['rate==1'],
    },
};

const EMAIL = __ENV.LOGIN_EMAIL || 'client@example.com';

export function setup() {
    assertSafeTarget();
}

export default function () {
    if (__ITER === 0) {
        http.get(`${BASE_URL}/login`, { tags: { name: 'login-page' } });
    }

    const response = track(
        http.post(
            `${BASE_URL}/login`,
            JSON.stringify({ email: EMAIL, password: 'not-the-password' }),
            {
                headers: jsonHeaders(),
                redirects: 0,
                tags: { name: 'login-attempt' },
            },
        ),
    );

    const attempt = __ITER + 1;

    if (attempt <= 5) {
        check(response, {
            'refused as invalid up to the fifth attempt': (r) =>
                r.status === 422,
        });
    } else {
        check(response, {
            'limited from the sixth attempt': (r) => r.status === 429,
        });
    }
}

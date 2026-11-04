/*
 * Scenario 2: one client sends a burst of messages into their Regray Team
 * thread, from several tabs at once.
 *
 * Expect: no 5xx. The `messages` limiter (60 a minute per user) answers the
 * rest with 429.
 */
import { check, fail } from 'k6';
import http from 'k6/http';
import {
    BASE_URL,
    assertSafeTarget,
    inertiaHeaders,
    jsonHeaders,
    sharedSession,
    sharedThresholds,
    track,
    useSession,
    uuid,
} from './lib/common.js';

export const options = {
    // Keep each VU signed in across iterations; k6 empties the jar each one by default.
    noCookiesReset: true,
    scenarios: {
        burst: {
            executor: 'constant-arrival-rate',
            rate: 5,
            timeUnit: '1s',
            duration: '30s',
            preAllocatedVUs: 10,
            maxVUs: 20,
        },
    },
    thresholds: {
        ...sharedThresholds,
        'http_req_duration{name:message-send}': ['p(95)<800'],
    },
};

const CLIENT = __ENV.MESSAGE_EMAIL || 'client@example.com';

/** The id of the first thread on the client's Inbox page: their Regray Team thread. */
const firstConversationId = (version) => {
    const response = http.get(`${BASE_URL}/dashboard/inbox`, {
        headers: inertiaHeaders(version, {
            component: 'inbox',
            only: ['conversations'],
        }),
        tags: { name: 'message-list' },
    });
    const conversations = response.json('props.conversations.data');

    if (!Array.isArray(conversations) || conversations.length === 0) {
        fail(
            `No thread to send in (GET /dashboard/inbox answered ${response.status})`,
        );
    }

    return conversations[0].id;
};

export function setup() {
    assertSafeTarget();

    const session = sharedSession(CLIENT);

    return { ...session, conversationId: firstConversationId(session.version) };
}

export default function (session) {
    if (__ITER === 0) {
        useSession(session);
    }

    const response = track(
        http.post(
            `${BASE_URL}/dashboard/inbox/${session.conversationId}/messages`,
            JSON.stringify({
                body: `Load test message ${uuid()}`,
                replyToId: null,
                clientKey: uuid(),
            }),
            { headers: jsonHeaders(), tags: { name: 'message-send' } },
        ),
    );

    check(response, {
        'sent (2xx) or rate limited (429)': (r) =>
            (r.status >= 200 && r.status < 300) || r.status === 429,
    });
}

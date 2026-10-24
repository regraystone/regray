/*
 * Shared helpers for the k6 scripts in load/. See load/RESULTS.md for how to
 * bring up the stack they run against and how to run them.
 *
 * These scripts flood the app on purpose. They refuse any target but the
 * local load stack unless STAGING_HOST names the one other host allowed.
 * Never point them at production.
 */
import { check, fail } from 'k6';
import http from 'k6/http';
import { Counter, Rate } from 'k6/metrics';

export const BASE_URL = (__ENV.BASE_URL || 'http://web').replace(/\/$/, '');

const LOCAL_HOSTS = ['web', 'localhost', '127.0.0.1', 'host.docker.internal'];

/** Stops the run before a single request unless BASE_URL is the load stack or the named staging host. */
export const assertSafeTarget = () => {
    const host = BASE_URL.replace(/^https?:\/\//, '').split(/[:/]/)[0];
    const allowed = [
        ...LOCAL_HOSTS,
        ...(__ENV.STAGING_HOST ? [__ENV.STAGING_HOST] : []),
    ];

    if (!allowed.includes(host)) {
        fail(
            `Refusing to load-test ${host}. Allowed: ${allowed.join(', ')}. Set STAGING_HOST to allow a staging host. Never production.`,
        );
    }
};

/** Every demo and load account's password (UserFactory's default). */
export const PASSWORD = __ENV.LOAD_PASSWORD || 'password';

/** Responses the server failed on. The run fails when more than 0.5% are. */
export const serverErrors = new Rate('server_errors');

/** Requests answered 429 Too Many Requests. */
export const rateLimited = new Counter('rate_limited');

/** The thresholds every scenario shares: under 0.5% 5xx. */
export const sharedThresholds = {
    server_errors: ['rate<0.005'],
};

/** Records a response in the shared metrics. */
export const track = (response) => {
    serverErrors.add(response.status >= 500);

    if (response.status === 429) {
        rateLimited.add(1);
    }

    return response;
};

/** The decoded XSRF-TOKEN cookie this VU holds, for the X-XSRF-TOKEN header. */
export const xsrfToken = () => {
    const cookies = http.cookieJar().cookiesForURL(`${BASE_URL}/`);
    const token = cookies['XSRF-TOKEN'];

    return token && token.length > 0 ? decodeURIComponent(token[0]) : '';
};

/** Headers for a JSON request the way resources/js/lib/message-api.ts sends one. */
export const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

/** The Inertia page object embedded in a full HTML response. */
export const inertiaPageFrom = (response) => {
    const match =
        /<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/.exec(
            response.body || '',
        );

    return match ? JSON.parse(match[1]) : null;
};

/** Headers for an Inertia visit, so the server answers with JSON props. */
export const inertiaHeaders = (version, partial = null) => ({
    Accept: 'text/html, application/xhtml+xml',
    'X-Requested-With': 'XMLHttpRequest',
    'X-Inertia': 'true',
    'X-Inertia-Version': version,
    'X-XSRF-TOKEN': xsrfToken(),
    ...(partial
        ? {
              'X-Inertia-Partial-Component': partial.component,
              'X-Inertia-Partial-Data': partial.only.join(','),
          }
        : {}),
});

/**
 * Signs this VU in as `email` and returns the Inertia asset version from the
 * page it lands on. Fails the VU when the login is refused.
 */
export const login = (email) => {
    track(http.get(`${BASE_URL}/login`, { tags: { name: 'login-page' } }));

    const response = track(
        http.post(
            `${BASE_URL}/login`,
            JSON.stringify({ email, password: PASSWORD }),
            { headers: jsonHeaders(), redirects: 0, tags: { name: 'login' } },
        ),
    );

    if (
        !check(response, {
            'login accepted': (r) =>
                r.status === 200 || r.status === 204 || r.status === 302,
        })
    ) {
        fail(`Login as ${email} answered ${response.status}`);
    }

    const page = inertiaPageFrom(
        http.get(`${BASE_URL}/dashboard`, { tags: { name: 'landing' } }),
    );

    return page ? page.version : '';
};

/**
 * Signs in once (in setup()) and returns the session for every VU to share,
 * so a one-account scenario does not trip the login limiter with a login per
 * VU.
 */
export const sharedSession = (email) => {
    const version = login(email);
    const cookies = http.cookieJar().cookiesForURL(`${BASE_URL}/`);

    return {
        version,
        cookies: Object.fromEntries(
            Object.entries(cookies).map(([name, values]) => [name, values[0]]),
        ),
    };
};

/** Puts a sharedSession() into this VU's cookie jar. */
export const useSession = (session) => {
    const jar = http.cookieJar();

    for (const [name, value] of Object.entries(session.cookies)) {
        jar.set(`${BASE_URL}/`, name, value);
    }
};

/** A random v4 UUID, for a message's clientKey. */
export const uuid = () =>
    'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;

        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });

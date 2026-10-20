import { mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import {
    expect,
    test as base,
    type Browser,
    type BrowserContext,
    type Page,
} from '@playwright/test';
import { type Actor, passwordFor } from './env';
import { FIXTURES_FILE, ROOT } from './stack';

/** What E2EMessageSeeder wrote to storage/app/e2e/message-fixtures.json. */
export type Fixtures = {
    users: Record<Actor, string>;
    support: Record<
        'claim' | 'race' | 'release' | 'reassign' | 'names' | 'forbidden',
        { email: string; name: string; conversationId: number }
    >;
    history: {
        conversationId: number;
        firstMessage: string;
        lastMessage: string;
    };
    unread: Array<{ name: string; count: number }>;
    cards: {
        conversationId: number;
        reference: string;
        firstProject: string;
        projectCount: number;
        longReference: string;
        longProject: string;
        longScheduleTitle: string;
        schedules: Array<{
            status: string;
            bookedByType: 'client' | 'staff';
            title: string;
        }>;
        staffName: string;
    };
    chat: { conversationId: number; name: string };
    scroll: { conversationId: number; name: string };
    direct: { conversationId: number };
    bound: Record<
        'shown' | 'cancel' | 'queue',
        { reference: string; conversationId: number; name: string }
    > & {
        free: { reference: string };
    };
};

let cached: Fixtures | null = null;

export const fixtures = (): Fixtures => {
    cached ??= JSON.parse(readFileSync(FIXTURES_FILE, 'utf8')) as Fixtures;

    return cached;
};

/**
 * The console allow-list. Everything else logged as an error, and every
 * uncaught exception, fails the test (scenario 42).
 *
 * - The browser logs every non-2xx fetch as "Failed to load resource". The
 *   scenarios that expect a 403, 404, 409 or 422 allow that status themselves
 *   with `allowConsole`.
 * - KNOWN_BUGS are errors already reported in REPORT.md. They are tolerated so
 *   that every other scenario is judged on its own assertions, and only
 *   scenario 42 (which calls `strictConsole`) fails on them.
 */
const KNOWN_BUGS: Array<{ bug: string; pattern: RegExp }> = [
    // BUG-1: the page joins private-support.pool for every staff member, and the
    // channel refuses anyone without support.claim.
    {
        bug: 'BUG-1',
        pattern: /status of 403 \(Forbidden\) \[[^\]]*\/broadcasting\/auth\]/,
    },
    // BUG-4: once a claim takes a thread from a pool member, their open thread
    // keeps posting read receipts and catching up, and gets 403 each time.
    {
        bug: 'BUG-4',
        pattern:
            /status of 403 \(Forbidden\) \[[^\]]*\/message\/\d+\/(read|messages\?after=)/,
    },
];

export type ActorSession = {
    context: BrowserContext;
    page: Page;
    errors: string[];
    email: string;
};

type Sessions = {
    /** Log `actor` in, in a context of its own. A second call for the same actor opens another context. */
    open: (actor: Actor | { email: string }) => Promise<ActorSession>;
    /** Tolerate console errors matching `pattern` for the rest of this test. */
    allowConsole: (pattern: RegExp) => void;
    /** Fail on the KNOWN_BUGS errors too (scenario 42). */
    strictConsole: () => void;
};

type StorageState = Awaited<ReturnType<BrowserContext['storageState']>>;

/**
 * Each account signs in once per worker, through the login form, and later
 * contexts for that account start from its saved cookies. Signing in for every
 * test trips Fortify's limit of five attempts a minute.
 */
const signedIn = new Map<string, StorageState>();

const signIn = async (
    browser: Browser,
    contextOptions: Parameters<Browser['newContext']>[0],
    email: string,
    password: string,
): Promise<StorageState> => {
    const cached = signedIn.get(email);

    if (cached !== undefined) {
        return cached;
    }

    const context = await browser.newContext(contextOptions);
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('[data-test="login-button"]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'));
    const state = await context.storageState();
    await context.close();
    signedIn.set(email, state);

    return state;
};

const newSession = async (
    browser: Browser,
    contextOptions: Parameters<Browser['newContext']>[0],
    email: string,
    password: string,
): Promise<ActorSession> => {
    const storageState = await signIn(browser, contextOptions, email, password);
    const context = await browser.newContext({
        ...contextOptions,
        storageState,
    });
    const page = await context.newPage();
    const errors: string[] = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            const source = message.location().url;
            errors.push(
                `console: ${message.text()}${source ? ` [${source}]` : ''}`,
            );
        }
    });
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));

    return { context, page, errors, email };
};

export const test = base.extend<{ sessions: Sessions }>({
    sessions: async (
        { browser, contextOptions, viewport, isMobile, hasTouch, baseURL },
        use,
        testInfo,
    ) => {
        const opened: ActorSession[] = [];
        const allowed: RegExp[] = [];
        let strict = false;

        await use({
            open: async (actor) => {
                const email =
                    typeof actor === 'string'
                        ? fixtures().users[actor]
                        : actor.email;
                const password =
                    typeof actor === 'string'
                        ? passwordFor(actor)
                        : passwordFor('client');
                const session = await newSession(
                    browser,
                    {
                        ...contextOptions,
                        viewport,
                        isMobile,
                        hasTouch,
                        baseURL,
                    },
                    email,
                    password,
                );
                opened.push(session);

                return session;
            },
            allowConsole: (pattern) => allowed.push(pattern),
            strictConsole: () => {
                strict = true;
            },
        });

        const tolerated = strict
            ? allowed
            : [...allowed, ...KNOWN_BUGS.map(({ pattern }) => pattern)];

        const unexpected = opened.flatMap((session) =>
            session.errors
                .filter(
                    (error) =>
                        !tolerated.some((pattern) => pattern.test(error)),
                )
                .map((error) => `${session.email}: ${error}`),
        );

        if (testInfo.status !== testInfo.expectedStatus) {
            for (const [index, session] of opened.entries()) {
                await shot(
                    session.page,
                    `failure-${slug(testInfo.title)}-${index}`,
                    testInfo.project.name,
                ).catch(() => undefined);
            }
        }

        for (const session of opened) {
            await session.context.close();
        }

        expect(
            unexpected,
            'console errors and uncaught exceptions (scenario 42)',
        ).toEqual([]);
    },
});

const slug = (title: string): string =>
    title
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 60);

/** Save a screenshot under test-results/message/{project}/. */
export const shot = async (
    page: Page,
    name: string,
    project: string,
): Promise<string> => {
    const directory = join(ROOT, 'test-results/message', project);
    mkdirSync(directory, { recursive: true });
    const path = join(directory, `${name}.png`);
    await page.screenshot({ path, fullPage: false });

    return path;
};

/**
 * Call an app endpoint from inside the page, with the session cookie and the
 * XSRF token, the way the page itself does.
 */
export const api = async (
    page: Page,
    method: string,
    url: string,
    body?: unknown,
): Promise<{ status: number; json: Record<string, unknown> | null }> =>
    page.evaluate(
        async ({ method, url, body }) => {
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((part) => part.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length) ?? '',
            );
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
            });
            const text = await response.text();
            let json: Record<string, unknown> | null = null;

            try {
                json = JSON.parse(text);
            } catch {
                json = null;
            }

            return { status: response.status, json };
        },
        { method, url, body },
    );

/** Open the Message page on one conversation. */
export const openConversation = async (
    page: Page,
    conversationId: number | string,
): Promise<void> => {
    await page.goto(`/message?conversation=${conversationId}`);
    await expect(page.getByTestId('thread')).toBeVisible();
};

export { expect };

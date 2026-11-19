import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { MAPBOX_HOSTS, mockMapbox } from './mapbox-mock';
import { join } from 'node:path';
import {
    expect,
    test as base,
    type BrowserContext,
    type Page,
    type TestInfo,
} from '@playwright/test';
import { BASE_URL } from '../message/support/env';
import { ROOT, artisan } from '../message/support/stack';
import { type Role, storageStateFor } from './accounts';

/*
 * The fixture the inquiry specs share: one browser context per actor, already
 * signed in from the global setup, with every console error, page error and
 * request recorded. At the end of each test the errors are held against the
 * allow-list (scenario 38) and the request hosts against the app and its asset
 * hosts (scenario 39).
 */

/**
 * Console errors that are not a fault of the page under test. Each entry says
 * why it is here. A test that expects a refused request adds its own pattern
 * with `allowConsole`; nothing else is tolerated.
 */
export const CONSOLE_ALLOW_LIST: Array<{ why: string; pattern: RegExp }> = [];

/**
 * The hosts a page may talk to: the app itself, and the Vite dev server that
 * serves its assets (read from public/hot, as Laravel reads it). ASSET_URL
 * adds a host when the app is configured with one.
 */
export const allowedHosts = (): Set<string> => {
    const hosts = new Set([new URL(BASE_URL).host]);
    const hot = join(ROOT, 'public/hot');

    if (existsSync(hot)) {
        hosts.add(new URL(readFileSync(hot, 'utf8').trim()).host);
    }

    if (process.env.ASSET_URL) {
        hosts.add(new URL(process.env.ASSET_URL).host);
    }

    // Answered in the browser by mockMapbox(): nothing leaves the machine.
    MAPBOX_HOSTS.forEach((host) => hosts.add(host));

    return hosts;
};

export type Session = {
    context: BrowserContext;
    page: Page;
    errors: string[];
    requests: string[];
    label: string;
};

type Actors = {
    /** A context signed in as `role`, or a guest for `'guest'`. */
    open: (role: Role | 'guest') => Promise<Session>;
    /** Tolerate console errors matching `pattern` for the rest of this test. */
    allowConsole: (pattern: RegExp) => void;
};

const watch = (page: Page, errors: string[], requests: string[]): void => {
    page.on('console', (message) => {
        if (message.type() === 'error') {
            const source = message.location().url;
            errors.push(
                `console: ${message.text()}${source ? ` [${source}]` : ''}`,
            );
        }
    });
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('request', (request) => requests.push(request.url()));
};

export const test = base.extend<{ actors: Actors }>({
    actors: async (
        { browser, contextOptions, viewport, isMobile, hasTouch, baseURL },
        use,
        testInfo,
    ) => {
        const opened: Session[] = [];
        const allowed: RegExp[] = CONSOLE_ALLOW_LIST.map(
            ({ pattern }) => pattern,
        );

        await use({
            open: async (role) => {
                const context = await browser.newContext({
                    ...contextOptions,
                    viewport,
                    isMobile,
                    hasTouch,
                    baseURL,
                    ...(role === 'guest'
                        ? {}
                        : { storageState: storageStateFor(role) }),
                });
                await mockMapbox(context);
                const page = await context.newPage();
                const session: Session = {
                    context,
                    page,
                    errors: [],
                    requests: [],
                    label: role,
                };

                watch(page, session.errors, session.requests);
                opened.push(session);

                return session;
            },
            allowConsole: (pattern) => allowed.push(pattern),
        });

        const unexpected = opened.flatMap((session) =>
            session.errors
                .filter(
                    (error) => !allowed.some((pattern) => pattern.test(error)),
                )
                .map((error) => `${session.label}: ${error}`),
        );

        const hosts = allowedHosts();
        const foreign = opened.flatMap((session) =>
            session.requests
                .filter((url) => /^https?:/.test(url))
                .filter((url) => !hosts.has(new URL(url).host))
                .map((url) => `${session.label}: ${url}`),
        );

        if (testInfo.status !== testInfo.expectedStatus) {
            for (const [index, session] of opened.entries()) {
                await shot(
                    session.page,
                    `failure-${slug(testInfo.title)}-${index}`,
                    testInfo,
                ).catch(() => undefined);
            }
        }

        for (const session of opened) {
            await session.context.close();
        }

        expect(
            unexpected,
            'console errors and uncaught exceptions (scenario 38)',
        ).toEqual([]);
        expect(
            [...new Set(foreign)],
            'requests to hosts other than the app and its asset host (scenario 39)',
        ).toEqual([]);
    },
});

const slug = (title: string): string =>
    title
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 60);

/** Save a screenshot under test-results/inquiry/{project}/. */
export const shot = async (
    page: Page,
    name: string,
    testInfo: TestInfo,
): Promise<string> => {
    const directory = join(ROOT, 'test-results/inquiry', testInfo.project.name);
    mkdirSync(directory, { recursive: true });
    const path = join(directory, `${name}.png`);
    await page.screenshot({ path, fullPage: true });

    return path;
};

/**
 * Evaluate a PHP expression against the E2E database and return it decoded
 * from JSON. For the checks a page cannot show: rows counted, files on disk.
 */
export const php = <T>(expression: string): T => {
    const output = artisan(
        'tinker',
        '--execute',
        `echo json_encode(${expression});`,
    );
    const line = output.trim().split(/\r?\n/).filter(Boolean).pop() ?? 'null';

    return JSON.parse(line) as T;
};

/** How many inquiries `clientId` has. */
export const inquiryCount = (clientId: number): number =>
    php<number>(
        `App\\Models\\Inquiry::query()->where('client_id', ${clientId})->count()`,
    );

/** `clientId`'s inquiry references, newest first. */
export const inquiryReferences = (clientId: number): string[] =>
    php<string[]>(
        `App\\Models\\Inquiry::query()->where('client_id', ${clientId})->orderByDesc('id')->pluck('reference')`,
    );

/**
 * Send a request as the signed-in user of `page`, with its cookies and XSRF
 * token, bypassing the form. JSON in and out, so validation errors come back
 * as `{ errors: { 'projects.0.stones.1.quantity': [...] } }`.
 */
export const postAsUser = async (
    page: Page,
    url: string,
    body: unknown,
): Promise<{ status: number; json: Record<string, unknown> | null }> => {
    const cookies = await page.context().cookies();
    const token = decodeURIComponent(
        cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '',
    );
    const response = await page.request.post(url, {
        data: body,
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        maxRedirects: 0,
    });
    let json: Record<string, unknown> | null = null;

    try {
        json = (await response.json()) as Record<string, unknown>;
    } catch {
        json = null;
    }

    return { status: response.status(), json };
};

export { expect };

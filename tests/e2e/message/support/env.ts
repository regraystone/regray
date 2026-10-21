/*
 * Where the E2E stack runs and who logs in. Everything can be overridden from
 * the environment; the defaults match DemoAccountsSeeder and E2EMessageSeeder,
 * whose users all get UserFactory's password.
 */

const env = (name: string, fallback: string): string => {
    const value = process.env[name];

    return value === undefined || value === '' ? fallback : value;
};

export const APP_PORT = Number(env('E2E_APP_PORT', '8001'));
export const REVERB_PORT = Number(env('E2E_REVERB_PORT', '8081'));
export const BASE_URL = env('E2E_BASE_URL', `http://127.0.0.1:${APP_PORT}`);
export const DB_DATABASE = env('E2E_DB_DATABASE', 'regray-studio-e2e');
export const APP_ENV = env('E2E_APP_ENV', 'local');
export const CHROME_PATH = env(
    'E2E_CHROME_PATH',
    process.platform === 'win32'
        ? 'C:/Program Files/Google/Chrome/Application/chrome.exe'
        : '',
);

/** UserFactory's password, which every seeded account has. */
export const DEFAULT_PASSWORD = env('E2E_PASSWORD', 'password');

export type Actor =
    | 'marketingA'
    | 'marketingB'
    | 'sales'
    | 'admin'
    | 'client'
    | 'freshClient'
    | 'cardClient'
    | 'directClient'
    | 'lister';

/** Per-actor password override, e.g. E2E_PASSWORD_MARKETINGA. */
export const passwordFor = (actor: Actor): string =>
    env(`E2E_PASSWORD_${actor.toUpperCase()}`, DEFAULT_PASSWORD);

/**
 * The environment every process of the E2E stack runs with: the dedicated
 * database, Reverb on its own port, and the app URL the browser uses.
 */
export const stackEnv = (): NodeJS.ProcessEnv => ({
    ...process.env,
    APP_ENV,
    APP_URL: BASE_URL,
    DB_DATABASE,
    BROADCAST_CONNECTION: 'reverb',
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: String(REVERB_PORT),
    REVERB_SCHEME: 'http',
    REVERB_SERVER_PORT: String(REVERB_PORT),
    REVERB_CLIENT_HOST: '127.0.0.1',
    REVERB_CLIENT_PORT: String(REVERB_PORT),
    REVERB_CLIENT_SCHEME: 'http',
    QUEUE_CONNECTION: 'database',
});

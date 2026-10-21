import { execFileSync, spawn } from 'node:child_process';
import {
    existsSync,
    mkdirSync,
    openSync,
    readFileSync,
    writeFileSync,
} from 'node:fs';
import { createConnection } from 'node:net';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { APP_PORT, REVERB_PORT, stackEnv } from './env';

/*
 * The processes behind the E2E stack: the app, Reverb and a queue worker. Their
 * pids are kept in a file so a spec (which runs in a worker process) can stop
 * and start Reverb or the worker, as the reconnect and queue scenarios do.
 */

export const ROOT = resolve(
    dirname(fileURLToPath(import.meta.url)),
    '../../../..',
);
const STATE_DIR = join(ROOT, 'storage/app/e2e');
const PID_FILE = join(STATE_DIR, 'stack-pids.json');
const LOG_DIR = join(ROOT, 'test-results/message/logs');

export type StackProcess = 'app' | 'reverb' | 'queue';

const COMMANDS: Record<StackProcess, string[]> = {
    // --no-reload passes this process's environment through to the server.
    app: [
        'artisan',
        'serve',
        '--host=127.0.0.1',
        `--port=${APP_PORT}`,
        '--no-reload',
    ],
    reverb: [
        'artisan',
        'reverb:start',
        '--host=127.0.0.1',
        `--port=${REVERB_PORT}`,
    ],
    queue: ['artisan', 'queue:work', '--sleep=1', '--tries=3', '--name=e2e'],
};

const PORTS: Partial<Record<StackProcess, number>> = {
    app: APP_PORT,
    reverb: REVERB_PORT,
};

const readPids = (): Partial<Record<StackProcess, number>> =>
    existsSync(PID_FILE) ? JSON.parse(readFileSync(PID_FILE, 'utf8')) : {};

const writePids = (pids: Partial<Record<StackProcess, number>>): void => {
    mkdirSync(STATE_DIR, { recursive: true });
    writeFileSync(PID_FILE, JSON.stringify(pids, null, 2));
};

/** Run an artisan command against the E2E database and return its output. */
export const artisan = (...args: string[]): string =>
    execFileSync('php', ['artisan', ...args], {
        cwd: ROOT,
        env: stackEnv(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });

const isPortOpen = (port: number): Promise<boolean> =>
    new Promise((done) => {
        const socket = createConnection({ host: '127.0.0.1', port });
        socket.once('connect', () => {
            socket.destroy();
            done(true);
        });
        socket.once('error', () => done(false));
    });

/** Wait until `port` accepts (or refuses, with `open: false`) connections. */
export const waitForPort = async (
    port: number,
    open: boolean,
    timeoutMs = 20_000,
): Promise<void> => {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        if ((await isPortOpen(port)) === open) {
            return;
        }

        await new Promise((tick) => setTimeout(tick, 100));
    }

    throw new Error(
        `Port ${port} did not ${open ? 'open' : 'close'} within ${timeoutMs}ms.`,
    );
};

const launchDetached = (name: StackProcess): number => {
    const log = openSync(join(LOG_DIR, `${name}.log`), 'a');
    const child = spawn('php', COMMANDS[name], {
        cwd: ROOT,
        env: stackEnv(),
        stdio: ['ignore', log, log],
        detached: true,
    });
    child.unref();

    return child.pid ?? 0;
};

/**
 * On Windows a child of the test worker dies with it, and Playwright ends the
 * worker after a failed test. Start-Process gives the server a parent that
 * exits at once, so it outlives the worker until stopProcess ends it.
 *
 * The server inherits PowerShell's handles, so PowerShell's output must not be
 * a pipe: execFileSync would wait for the server to exit before returning. The
 * pid goes through a file instead.
 */
const launchDetachedOnWindows = (name: StackProcess): number => {
    const quote = (value: string): string => `'${value.replace(/'/g, "''")}'`;
    const pidFile = join(STATE_DIR, `${name}.pid`);
    const command = [
        `$p = Start-Process -FilePath php -ArgumentList ${quote(COMMANDS[name].join(' '))}`,
        `-WorkingDirectory ${quote(ROOT)} -WindowStyle Hidden -PassThru`,
        `-RedirectStandardOutput ${quote(join(LOG_DIR, `${name}.log`))}`,
        `-RedirectStandardError ${quote(join(LOG_DIR, `${name}.error.log`))};`,
        `[IO.File]::WriteAllText(${quote(pidFile)}, [string]$p.Id)`,
    ].join(' ');

    mkdirSync(STATE_DIR, { recursive: true });
    execFileSync(
        'powershell',
        ['-NoProfile', '-NonInteractive', '-Command', command],
        {
            cwd: ROOT,
            env: stackEnv(),
            stdio: 'ignore',
        },
    );

    return Number(readFileSync(pidFile, 'utf8').trim());
};

/** Start `name` unless it is already running; safe to call twice. */
export const startProcess = async (name: StackProcess): Promise<void> => {
    const port = PORTS[name];

    if (
        readPids()[name] !== undefined &&
        (port === undefined || (await isPortOpen(port)))
    ) {
        return;
    }

    mkdirSync(LOG_DIR, { recursive: true });
    const pid =
        process.platform === 'win32'
            ? launchDetachedOnWindows(name)
            : launchDetached(name);
    writePids({ ...readPids(), [name]: pid });

    if (port !== undefined) {
        await waitForPort(port, true);
    }
};

export const stopProcess = async (name: StackProcess): Promise<void> => {
    const pids = readPids();
    const pid = pids[name];

    if (pid === undefined) {
        return;
    }

    try {
        if (process.platform === 'win32') {
            execFileSync('taskkill', ['/PID', String(pid), '/T', '/F'], {
                stdio: 'ignore',
            });
        } else {
            process.kill(-pid, 'SIGTERM');
        }
    } catch {
        // Already gone.
    }

    delete pids[name];
    writePids(pids);

    const port = PORTS[name];

    if (port !== undefined) {
        await waitForPort(port, false);
    }
};

export const isRunning = (name: StackProcess): boolean =>
    readPids()[name] !== undefined;

export const FIXTURES_FILE = join(STATE_DIR, 'message-fixtures.json');

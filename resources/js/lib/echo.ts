import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { readXsrfToken } from '@/lib/xsrf';
import type { Realtime } from '@/types/message';

/**
 * The one Echo connection to Reverb, set up on first use from the shared
 * `realtime` prop. The server hands the browser its connection details at
 * runtime, so the same build works on every host; an empty host or port means
 * the page's own, which is how it runs behind Caddy.
 */
let instance: Echo<'reverb'> | null = null;

export const getEcho = (
    realtime: Realtime | undefined,
): Echo<'reverb'> | null => {
    if (instance !== null) {
        return instance;
    }

    if (!realtime || typeof window === 'undefined' || !realtime.key) {
        return null;
    }

    const scheme = realtime.scheme ?? window.location.protocol.replace(':', '');
    const secure = scheme === 'https';
    const port =
        realtime.port ??
        (window.location.port !== ''
            ? Number(window.location.port)
            : secure
              ? 443
              : 80);

    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    instance = new Echo<'reverb'>({
        broadcaster: 'reverb',
        key: realtime.key,
        wsHost: realtime.host ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
        /*
         * Authorize each private channel with a fresh XSRF token. The token
         * can rotate while the page is open, so it is read at the moment of
         * asking rather than once when the connection was made.
         */
        authorizer: (channel: { name: string }) => ({
            authorize: (
                socketId: string,
                callback: (
                    error: Error | null,
                    data: {
                        auth: string;
                        channel_data?: string;
                        shared_secret?: string;
                    } | null,
                ) => void,
            ) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': readXsrfToken(),
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then(async (response) => {
                        if (!response.ok) {
                            throw new Error(
                                `Channel authorization failed (${response.status})`,
                            );
                        }

                        callback(
                            null,
                            (await response.json()) as {
                                auth: string;
                                channel_data?: string;
                            },
                        );
                    })
                    .catch((error: Error) => callback(error, null));
            },
        }),
    });

    return instance;
};

type ListenableChannel = {
    listen: (event: string, callback: CallableFunction) => unknown;
    stopListening: (event: string, callback?: CallableFunction) => unknown;
};

/**
 * Listen for each event in `handlers` on `channel`, and return a function that
 * removes exactly those listeners. A whisper is the event `.client-{name}`.
 *
 * Call it before `echo.leave()`. Leaving a channel whose join is still waiting
 * on /broadcasting/auth only cancels the join: pusher-js keeps the channel and
 * its listeners, and the next join of the same name gets that object back. A
 * cleanup that only left would let the old listeners fire again next to the
 * new ones, so every event would arrive twice.
 */
export const listenTo = (
    channel: ListenableChannel,
    handlers: Record<string, CallableFunction>,
): (() => void) => {
    for (const [event, callback] of Object.entries(handlers)) {
        channel.listen(event, callback);
    }

    return () => {
        for (const [event, callback] of Object.entries(handlers)) {
            channel.stopListening(event, callback);
        }
    };
};

/** The socket id, so the server can leave the sender out of a broadcast. */
export const currentSocketId = (): string | undefined =>
    instance?.socketId() ?? undefined;

type PusherConnection = {
    bind: (
        event: string,
        callback: (states: { previous: string; current: string }) => void,
    ) => void;
    unbind: (
        event: string,
        callback: (states: { previous: string; current: string }) => void,
    ) => void;
};

const connectionOf = (echo: Echo<'reverb'>): PusherConnection | null =>
    (
        echo.connector as unknown as {
            pusher?: { connection?: PusherConnection };
        }
    ).pusher?.connection ?? null;

/**
 * Call `callback` every time the socket comes back after being lost. Socket
 * delivery is never a guarantee, so this is when the page catches up over HTTP.
 */
export const onReconnect = (
    echo: Echo<'reverb'>,
    callback: () => void,
): (() => void) => {
    const connection = connectionOf(echo);

    if (connection === null) {
        return () => {};
    }

    let wasConnected = false;
    const handler = ({ current }: { previous: string; current: string }) => {
        if (current === 'connected') {
            if (wasConnected) {
                callback();
            }

            wasConnected = true;
        }
    };

    connection.bind('state_change', handler);

    return () => connection.unbind('state_change', handler);
};

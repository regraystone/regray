/*
 * What to say and do when the server answers 429 Too Many Requests.
 * Import-free, so tests/js can load it.
 *
 * Laravel's throttle middleware sends Retry-After as a whole number of
 * seconds. The limits themselves live in config/rate-limits.php. The page
 * never retries a 429 by itself: it says how long to wait and holds its retry
 * button until then, so a reader cannot extend their own lockout.
 */

export const TOO_MANY_REQUESTS = 429;

/** A 429 as the page needs it: the sentence, and when a retry may go (a Date.now() timestamp). */
export type RateLimit = { message: string; retryAt: number | null };

/** Retry-After as whole seconds, or null when it is missing or not a positive number. */
export const retryAfterSeconds = (
    retryAfter: string | null | undefined,
): number | null => {
    const seconds = Number.parseInt(retryAfter ?? '', 10);

    return Number.isFinite(seconds) && seconds > 0 ? seconds : null;
};

/** How long a wait of `seconds` reads: "45 seconds", "1 minute", "3 minutes". */
const waitLabel = (seconds: number): string => {
    if (seconds < 60) {
        return seconds === 1 ? '1 second' : `${seconds} seconds`;
    }

    const minutes = Math.ceil(seconds / 60);

    return minutes === 1 ? '1 minute' : `${minutes} minutes`;
};

/** The sentence for a 429, from its Retry-After header. */
export const tooManyRequestsMessage = (
    retryAfter: string | null | undefined,
): string => {
    const seconds = retryAfterSeconds(retryAfter);

    return seconds === null
        ? "You're doing that too often. Please wait a moment and try again."
        : `You're doing that too often. Please wait ${waitLabel(seconds)} and try again.`;
};

/**
 * The 429 for a response, or null for any other status. `headers` are
 * Inertia's response headers, whose names are lower-cased.
 */
export const rateLimitFrom = (
    response: { status: number; headers: Record<string, string> },
    now: number = Date.now(),
): RateLimit | null => {
    if (response.status !== TOO_MANY_REQUESTS) {
        return null;
    }

    const retryAfter = response.headers['retry-after'];
    const seconds = retryAfterSeconds(retryAfter);

    return {
        message: tooManyRequestsMessage(retryAfter),
        retryAt: seconds === null ? null : now + seconds * 1000,
    };
};

/** Whole seconds left until `retryAt`, rounded up: 0 once it has passed, or when there is none. */
export const secondsUntil = (retryAt: number | null, now: number): number =>
    retryAt === null ? 0 : Math.max(0, Math.ceil((retryAt - now) / 1000));

/** A retry button's label: "Try again", or "Try again in 42s" while the wait runs. */
export const retryLabel = (secondsLeft: number, label = 'Try again'): string =>
    secondsLeft > 0 ? `${label} in ${secondsLeft}s` : label;

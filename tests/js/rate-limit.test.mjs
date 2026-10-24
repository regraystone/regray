/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    rateLimitFrom,
    retryAfterSeconds,
    retryLabel,
    secondsUntil,
    tooManyRequestsMessage,
} from '../../resources/js/lib/rate-limit.ts';

describe('retryAfterSeconds', () => {
    it('reads whole seconds, and nothing else', () => {
        assert.equal(retryAfterSeconds('45'), 45);

        for (const header of [null, undefined, '', 'soon', '0', '-3']) {
            assert.equal(retryAfterSeconds(header), null);
        }
    });
});

describe('tooManyRequestsMessage', () => {
    it('says how long to wait, from the Retry-After seconds', () => {
        assert.equal(
            tooManyRequestsMessage('45'),
            "You're doing that too often. Please wait 45 seconds and try again.",
        );
        assert.match(tooManyRequestsMessage('1'), /wait 1 second and/);
        assert.match(tooManyRequestsMessage('60'), /wait 1 minute and/);
        assert.match(tooManyRequestsMessage('125'), /wait 3 minutes and/);
    });

    it('asks for a moment when the header is missing or not seconds', () => {
        for (const header of [null, undefined, '', 'soon', '0']) {
            assert.match(
                tooManyRequestsMessage(header),
                /wait a moment and try again/,
            );
        }
    });
});

describe('rateLimitFrom', () => {
    const now = 1_000_000;

    it('answers a 429 with its sentence and when a retry may go', () => {
        assert.deepEqual(
            rateLimitFrom(
                { status: 429, headers: { 'retry-after': '30' } },
                now,
            ),
            {
                message:
                    "You're doing that too often. Please wait 30 seconds and try again.",
                retryAt: now + 30_000,
            },
        );
    });

    it('has no retry time when the 429 gave none', () => {
        assert.equal(
            rateLimitFrom({ status: 429, headers: {} }, now).retryAt,
            null,
        );
    });

    it('answers nothing for any other status', () => {
        assert.equal(
            rateLimitFrom(
                { status: 500, headers: { 'retry-after': '30' } },
                now,
            ),
            null,
        );
    });
});

describe('secondsUntil and retryLabel', () => {
    it('counts down to the retry time, rounding up, and stops at 0', () => {
        assert.equal(secondsUntil(10_500, 0), 11);
        assert.equal(secondsUntil(10_000, 10_000), 0);
        assert.equal(secondsUntil(10_000, 20_000), 0);
        assert.equal(secondsUntil(null, 0), 0);
    });

    it('labels the button with the wait while it runs', () => {
        assert.equal(retryLabel(42), 'Try again in 42s');
        assert.equal(retryLabel(0), 'Try again');
        assert.equal(retryLabel(5, 'Retry'), 'Retry in 5s');
    });
});

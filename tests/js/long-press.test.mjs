/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    LONG_PRESS_DELAY_MS,
    LONG_PRESS_IDLE,
    longPress,
} from '../../resources/js/lib/long-press.ts';

/** Feed the events in order; return the last state and whether any step opened the sheet. */
const run = (events) =>
    events.reduce(
        (acc, event) => {
            const next = longPress(acc.state, event);

            return { state: next.state, opened: acc.opened || next.open };
        },
        { state: LONG_PRESS_IDLE, opened: false },
    );

describe('longPress', () => {
    it('waits about 450ms before it opens', () => {
        assert.equal(LONG_PRESS_DELAY_MS, 450);
    });

    it('opens the sheet when the finger holds still until the timer', () => {
        const result = run([{ type: 'down', x: 0, y: 0 }, { type: 'timer' }]);

        assert.equal(result.opened, true);
        assert.equal(result.state.phase, 'fired');
    });

    it('still opens after a wobble of 10 pixels or less', () => {
        const result = run([
            { type: 'down', x: 0, y: 0 },
            { type: 'move', x: 6, y: 8 },
            { type: 'timer' },
        ]);

        assert.equal(result.opened, true);
    });

    it('cancels when the finger moves more than 10 pixels', () => {
        const result = run([
            { type: 'down', x: 0, y: 0 },
            { type: 'move', x: 0, y: 11 },
            { type: 'timer' },
        ]);

        assert.equal(result.opened, false);
        assert.equal(result.state.phase, 'cancelled');
    });

    it('cancels when the page scrolls', () => {
        const result = run([
            { type: 'down', x: 0, y: 0 },
            { type: 'scroll' },
            { type: 'timer' },
        ]);

        assert.equal(result.opened, false);
    });

    it('treats lifting the finger early as a tap', () => {
        const result = run([
            { type: 'down', x: 0, y: 0 },
            { type: 'up' },
            { type: 'timer' },
        ]);

        assert.equal(result.opened, false);
        assert.deepEqual(result.state, LONG_PRESS_IDLE);
    });

    it('ignores a timer that fires with no press', () => {
        assert.equal(run([{ type: 'timer' }]).opened, false);
    });
});

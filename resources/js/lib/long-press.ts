/*
 * Press-and-hold on a message bubble, as a pure state machine. The component
 * feeds it pointer events and a timer; this decides what they mean. No
 * imports, for tests/js.
 *
 * The rules:
 * - holding still for `delay` ms (about 450) opens the action sheet,
 * - moving more than `tolerance` px (10) before then cancels it,
 * - a page scroll cancels it,
 * - lifting the finger early is a tap, not a press.
 */

export const LONG_PRESS_DELAY_MS = 450;
export const LONG_PRESS_TOLERANCE_PX = 10;

export type LongPressState =
    | { phase: 'idle' }
    | { phase: 'pressing'; startX: number; startY: number }
    | { phase: 'fired' }
    | { phase: 'cancelled' };

export type LongPressEvent =
    | { type: 'down'; x: number; y: number }
    | { type: 'move'; x: number; y: number }
    | { type: 'scroll' }
    | { type: 'timer' }
    | { type: 'up' };

export const LONG_PRESS_IDLE: LongPressState = { phase: 'idle' };

/** The next state, and whether the sheet should open on this step. */
export const longPress = (
    state: LongPressState,
    event: LongPressEvent,
    tolerance: number = LONG_PRESS_TOLERANCE_PX,
): { state: LongPressState; open: boolean } => {
    switch (event.type) {
        case 'down':
            return {
                state: { phase: 'pressing', startX: event.x, startY: event.y },
                open: false,
            };
        case 'move':
            if (state.phase !== 'pressing') {
                return { state, open: false };
            }

            return Math.hypot(event.x - state.startX, event.y - state.startY) >
                tolerance
                ? { state: { phase: 'cancelled' }, open: false }
                : { state, open: false };
        case 'scroll':
            return state.phase === 'pressing'
                ? { state: { phase: 'cancelled' }, open: false }
                : { state, open: false };
        case 'timer':
            return state.phase === 'pressing'
                ? { state: { phase: 'fired' }, open: true }
                : { state, open: false };
        case 'up':
            return { state: LONG_PRESS_IDLE, open: false };
    }
};

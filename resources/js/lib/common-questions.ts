/*
 * The questions a client can send with one press, from the Frequently Asked Questions icon
 * in the composer. Pure, with no imports, for tests/js.
 */
export const COMMON_QUESTIONS: readonly string[] = [
    'How long does an order take?',
    'Do you offer nationwide installation?',
    'How do I request a quotation?',
];

/** How far, in pixels, a scroll or a swipe down has to go to hide them. */
export const HIDE_DISTANCE_PX = 40;

/**
 * How far a mouse or trackpad scroll has pulled at the list so far: only scrolling
 * down counts, and only once the list has no more of itself to scroll through.
 */
export const wheelPull = (
    pulled: number,
    deltaY: number,
    list: { scrollTop: number; clientHeight: number; scrollHeight: number },
): number =>
    deltaY <= 0 || list.scrollTop + list.clientHeight < list.scrollHeight - 1
        ? 0
        : pulled + deltaY;

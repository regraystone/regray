/*
 * Scroll arithmetic for the thread. Pure, with no imports, for tests/js.
 */

/** How close to the bottom still counts as "at the bottom", in pixels. */
export const NEAR_BOTTOM_PX = 120;

/** How close to the top asks for older messages, in pixels. */
export const NEAR_TOP_PX = 80;

/**
 * The scrollTop that keeps the same message under the reader's eye after
 * older messages were added above it: the content grew by the difference in
 * height, so the view moves down by exactly that much.
 */
export const anchorAfterPrepend = ({
    previousScrollHeight,
    previousScrollTop,
    nextScrollHeight,
}: {
    previousScrollHeight: number;
    previousScrollTop: number;
    nextScrollHeight: number;
}): number =>
    Math.max(0, previousScrollTop + (nextScrollHeight - previousScrollHeight));

export const isNearBottom = (
    {
        scrollTop,
        clientHeight,
        scrollHeight,
    }: { scrollTop: number; clientHeight: number; scrollHeight: number },
    threshold: number = NEAR_BOTTOM_PX,
): boolean => scrollHeight - (scrollTop + clientHeight) <= threshold;

export const isNearTop = (
    scrollTop: number,
    threshold: number = NEAR_TOP_PX,
): boolean => scrollTop <= threshold;

/**
 * Whether a new message should pull the view to the bottom: the reader's own
 * message always does, anyone else's only when the reader is already there.
 * Otherwise it is counted on the "New messages" pill.
 */
export const shouldAutoScroll = ({
    nearBottom,
    isOwn,
}: {
    nearBottom: boolean;
    isOwn: boolean;
}): boolean => isOwn || nearBottom;

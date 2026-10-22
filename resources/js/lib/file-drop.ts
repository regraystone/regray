/*
 * Dragging files onto a thread. Pure, with no imports, for tests/js.
 *
 * The browser fires dragenter and dragleave for every child the pointer
 * crosses, so "is something being dragged over the thread" is a count of
 * enters not yet matched by a leave, not the last event seen.
 */

/** Whether a drag carries files (rather than text or a link from the page). */
export const isFileDrag = (types: readonly string[]): boolean =>
    types.includes('Files');

export type DragEventKind = 'enter' | 'leave' | 'drop';

/** The new count after one event; the overlay shows while it is above 0. */
export const nextDragDepth = (depth: number, event: DragEventKind): number => {
    if (event === 'enter') {
        return depth + 1;
    }

    if (event === 'leave') {
        return Math.max(0, depth - 1);
    }

    return 0;
};

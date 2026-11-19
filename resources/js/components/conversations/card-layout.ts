/*
 * The frame the inquiry card and the quotation card share, so a thread of
 * them reads as one family: the same width, radius, border, surface, media
 * tile and padding. Written out in full rather than built up: Tailwind only
 * sees class names it can find in the source.
 *
 * The column is a container, and the card follows its own width rather than
 * the window's: below 512px (`@lg`) it is stacked, the media a full-width
 * strip on top; from there it is one wide row, the media on the left and the
 * details beside it. The thread pane beside the conversation list is narrower
 * than a tablet's window, so a viewport breakpoint would squeeze the details.
 */

/** The column a card sits in, header included: centred, up to `max-w-2xl`. */
export const CARD_COLUMN = '@container mx-auto w-full max-w-2xl space-y-1.5';

/** The card itself. */
export const CARD_SURFACE =
    'flex w-full flex-col overflow-hidden rounded-md border bg-secondary text-left @lg:flex-row';

/** The media tile: the stone pictures, the service, or the PDF icon. */
export const CARD_MEDIA =
    'relative h-32 w-full shrink-0 overflow-hidden bg-sidebar-border @lg:h-auto @lg:min-h-36 @lg:w-52';

/** Everything beside the media. `min-w-0` lets each line truncate. */
export const CARD_DETAILS = 'flex min-w-0 flex-1 flex-col gap-2 p-3';

/** The focus ring every card control uses. */
export const CARD_FOCUS =
    'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

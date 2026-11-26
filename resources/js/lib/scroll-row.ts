/**
 * The Inquire page's sideways rows of picture cards (the stones, a table's
 * shape and base design): one look and one feel for all of them. Cards snap
 * into place, two to a view on a phone and three from `md`, and an arrow at
 * either end shows while there is more that way.
 */

/** The row's spacing, the same as the grid it replaced. */
export const ROW_GAP_CLASSES = 'gap-3.5 md:gap-4 lg:gap-5';

/**
 * A card's width: two to a view on a phone and three from `md`, as the grid had.
 * Measured against the row itself (`@container`), less the gaps between cards.
 */
export const CARD_WIDTH_CLASSES =
    'w-[calc((100cqw-0.875rem)/2)] shrink-0 snap-start md:w-[calc((100cqw-2rem)/3)] lg:w-[calc((100cqw-2.5rem)/3)]';

/** How far an arrow moves the row: most of a view, so one card stays in sight. */
export const ARROW_SCROLL_SHARE = 0.8;

/** How far a card may sit past the row's edge and still count as in view. */
export const IN_VIEW_SLACK_PX = 2;

/**
 * An arrow over the pictures: a solid circle the size of a question's number,
 * its chevron in the middle.
 */
export const ARROW_CLASSES =
    'absolute top-[calc(50%-1.5rem)] flex size-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md transition-opacity hover:opacity-90 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none';

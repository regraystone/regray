import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { StatusBadge, STATUS_LABELS } from '@/components/inquiry-status-badge';
import { stones, type FeedItem, type Inquiry } from '@/data/constant';
import { index as inquiryIndex } from '@/routes/inquiry';

/**
 * Two tiles, not three. The card is a preview, and two photographs already say
 * "a material palette"; a third only makes the tiles narrower than the title
 * they carry. Whatever is left over is counted on the second tile instead of
 * being drawn.
 */
const MAX_HERO_STONES = 2;

/**
 * Written out rather than interpolated: Tailwind only sees class names it can
 * find in the source, so `grid-cols-${n}` would compile to nothing.
 */
const HERO_COLUMNS = ['grid-cols-1', 'grid-cols-2'] as const;

/**
 * Weekday and time, the way a chat dates a run of messages.
 *
 * `Intl` rather than `toISOString()`, which reports the UTC day: a reader east
 * of Greenwich would be shown the day before the one the inquiry was raised.
 * `undefined` as the locale is the reader's own, for the same reason.
 */
const threadTimestampFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    hour: 'numeric',
    minute: '2-digit',
});

type InquiryThreadCardProps = {
    inquiry: Inquiry;
};

/**
 * The inquiry a conversation was opened for, shown as the thread's first card.
 *
 * It is a link preview rather than a message: the same shape a shared link takes
 * in a chat, because that is what a reader recognises as "this is the thing we
 * came here about". It is derived from the inquiry and never added to `messages`,
 * so it cannot be answered, counted as unread or re-sent, and the grouping of
 * the real messages above it stays exactly as it was.
 *
 * The card is one link end to end. Nesting the details button inside it the way
 * the Quote page's card does would put a link inside a link, which a screen
 * reader announces as an unreadable nest and a keyboard cannot tab through.
 */
const InquiryThreadCard = ({ inquiry }: InquiryThreadCardProps) => {
    const inquiryStones = inquiry.stoneIds
        .map((stoneId) => stones.find((stone) => stone.id === stoneId))
        .filter((stone): stone is FeedItem => stone !== undefined);

    const heroStones = inquiryStones.slice(0, MAX_HERO_STONES);
    const extraStones = inquiryStones.length - heroStones.length;
    const detailsHref = inquiryIndex.url({ query: { inquiry: inquiry.id } });
    const serviceCount = inquiry.services.length;
    const hasHero = heroStones.length > 0;

    /*
     * The name and the reference, sitting on the photograph rather than under
     * it, which is what makes the card read as a preview of a link instead of a
     * summary panel. The scrim is the same `bg-foreground/60` the Quote card
     * puts over an overflow tile, so white text stays legible on any stone.
     *
     * Held as one node because it is placed twice: over the photograph when
     * there is one, and in normal flow when there is not, where the wrapper
     * would have no height to be measured against.
     */
    const title = (
        <span className="block space-y-0.5 bg-foreground/60 p-3">
            <span className="line-clamp-2 block text-sm font-bold text-background">
                {inquiry.projectName}
            </span>

            <span className="block text-xs text-background/80">
                Inquiry No:{' '}
                <span className="font-bold">{inquiry.reference}</span>
            </span>
        </span>
    );

    return (
        <>
            <p className="text-center text-xs text-secondary-foreground">
                <time dateTime={inquiry.createdAt}>
                    {threadTimestampFormatter.format(
                        new Date(inquiry.createdAt),
                    )}
                </time>
            </p>

            {/*
             * Centred in the thread and with no avatar. The card is the opening
             * of the conversation rather than something anyone said, so there is
             * no sender to sit in the left column and hang the card off: it is
             * the one thing in the thread that belongs to the thread itself.
             */}
            <span className="block text-center text-xs font-bold text-secondary-foreground">
                New project
            </span>

            <Link
                href={detailsHref}
                aria-label={`New project ${inquiry.projectName}, inquiry ${inquiry.reference}, ${STATUS_LABELS[inquiry.status]}. View details`}
                className="mx-auto block w-full max-w-xs overflow-hidden bg-secondary text-left transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:max-w-sm"
            >
                {hasHero ? (
                    <span className="relative block">
                        {/*
                         * The strip is the project in one photograph, and it is
                         * taller than a plain header image because it now carries
                         * the title. A single stone fills the width; two are laid
                         * out as equal columns, with the grid's own background
                         * showing through the gap as the 1px divider.
                         */}
                        <span
                            className={`grid h-32 ${HERO_COLUMNS[heroStones.length - 1]} gap-px bg-sidebar-border`}
                        >
                            {heroStones.map((stone, index) => {
                                const isLastWithOverflow =
                                    extraStones > 0 &&
                                    index === heroStones.length - 1;

                                return (
                                    <span key={stone.id} className="relative">
                                        <img
                                            src={stone.thumbnail}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover"
                                        />
                                        {/*
                                         * The second tile is dimmed and counted
                                         * when there is more behind it. The count
                                         * sits along the top of the tile, because
                                         * the title band owns the bottom of the
                                         * strip and would swallow a centred one.
                                         */}
                                        {isLastWithOverflow ? (
                                            <span
                                                aria-hidden="true"
                                                className="absolute inset-0 flex items-start justify-center bg-foreground/60 pt-2 text-xs font-bold text-background"
                                            >
                                                +{extraStones}
                                            </span>
                                        ) : null}
                                    </span>
                                );
                            })}
                        </span>

                        <span className="absolute inset-x-0 bottom-0">
                            {title}
                        </span>
                    </span>
                ) : (
                    title
                )}

                <span className="block space-y-1 p-3">
                    <span className="flex items-center gap-1.5 text-xs text-secondary-foreground">
                        <StatusBadge status={inquiry.status} />
                        <span aria-hidden="true">·</span>
                        <span>
                            {serviceCount}{' '}
                            {serviceCount === 1 ? 'service' : 'services'}
                        </span>
                    </span>

                    <span className="flex items-center gap-1 text-xs text-secondary-foreground">
                        View details
                        <ArrowRight className="size-3" aria-hidden />
                    </span>
                </span>
            </Link>
        </>
    );
};

export default InquiryThreadCard;

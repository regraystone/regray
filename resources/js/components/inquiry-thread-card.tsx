import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { StatusBadge, STATUS_LABELS } from '@/components/inquiry-status-badge';
import {
    inquiryTitle,
    stones,
    type FeedItem,
    type Inquiry,
    type InquiryStatus,
} from '@/data/constant';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { show as inquiryShow } from '@/routes/inquiry';
import { show as quoteShow } from '@/routes/quote';

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

/**
 * The headline a card announces when it is reporting a status change.
 *
 * The opening card of a thread reports no change: the project is arriving, and it
 * reads "New project" whatever the inquiry has since become. A card posted again
 * later is announcing something, and there the status is the news and the project
 * is only the context for it — so "Project cancelled", "Project quoted".
 *
 * Keyed by status rather than passed in as a string so that two cards reporting
 * the same change are worded identically, and so a status added later cannot be
 * posted with no wording at all.
 */
const STATUS_HEADLINES: Record<InquiryStatus, string> = {
    draft: 'Project drafted',
    pending: 'Project submitted',
    quoted: 'Project quoted',
    paid: 'Project paid',
    completed: 'Project completed',
    cancelled: 'Project cancelled',
};

type InquiryThreadCardProps = {
    inquiry: Inquiry;
    /**
     * The status this card announces. Leave it off for the card that opens a
     * thread, which reports the project rather than a change to it; pass it to
     * post the card again when the inquiry moves on.
     *
     * Its absence is what tells the two apart, so the default is deliberately
     * not `inquiry.status`: an opening card would then announce whatever state
     * the inquiry happens to be in now, which is not what it was reporting when
     * it was posted.
     */
    status?: InquiryStatus;
    /**
     * When this card was posted into the thread. Defaults to when the inquiry was
     * raised, which is right for the opening card and wrong for a later one: a
     * status change is announced when it happened, not when the project began.
     */
    occurredAt?: string;
};

/**
 * An inquiry shown as a card inside a conversation.
 *
 * It is a link preview rather than a message: the same shape a shared link takes
 * in a chat, because that is what a reader recognises as "this is the thing we
 * came here about". It is derived from the inquiry and never added to `messages`,
 * so it cannot be answered, counted as unread or re-sent, and the grouping of
 * the real messages around it stays exactly as it was.
 *
 * One component covers every status, because the status is a prop rather than
 * something read off the inquiry. Posting the card again after the inquiry has
 * moved on needs no second component: it is this card with a `status` and an
 * `occurredAt`, and it reads as the announcement it is.
 *
 * The card is one link end to end. Nesting the details button inside it the way
 * the Quote page's card does would put a link inside a link, which a screen
 * reader announces as an unreadable nest and a keyboard cannot tab through.
 */
const InquiryThreadCard = ({
    inquiry,
    status,
    occurredAt = inquiry.createdAt,
}: InquiryThreadCardProps) => {
    const { can } = usePermissions();
    /*
     * Every stone the inquiry mentions, deduplicated: one stone wanted in two of
     * its projects is still one material, and a photo strip with the same picture
     * in it twice reads as a mistake. The counts and the specifications are on
     * the details page.
     */
    const inquiryStones = [
        ...new Set(
            inquiry.projects.flatMap((project) =>
                project.stones.map((selection) => selection.stoneId),
            ),
        ),
    ]
        .map((stoneId) => stones.find((stone) => stone.id === stoneId))
        .filter((stone): stone is FeedItem => stone !== undefined);

    const heroStones = inquiryStones.slice(0, MAX_HERO_STONES);
    const extraStones = inquiryStones.length - heroStones.length;
    /*
     * The details page sits under whichever list the reader works from, and the
     * two lists are guarded separately, so the link follows the permission rather
     * than a guess: a client reading their own quotations goes to the quote URL,
     * everybody else to the staff one.
     */
    const detailsHref = can(permissions.quotationsView)
        ? quoteShow.url({ inquiry: inquiry.id })
        : inquiryShow.url({ inquiry: inquiry.id });
    const serviceCount = inquiry.services.length;
    const hasHero = heroStones.length > 0;
    /*
     * The badge always shows the status being reported, which for the opening
     * card is the inquiry's own: that is the live state a reader opening the
     * thread is there to see. The headline is narrower, and only speaks up when
     * there is a change to announce.
     */
    const badgeStatus = status ?? inquiry.status;
    const headline = status ? STATUS_HEADLINES[status] : 'New project';

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
                {inquiryTitle(inquiry)}
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
                <time dateTime={occurredAt}>
                    {threadTimestampFormatter.format(new Date(occurredAt))}
                </time>
            </p>

            {/*
             * Centred in the thread and with no avatar. The card is something the
             * system posted rather than something anyone said, so there is no
             * sender to sit in the left column and hang the card off: it belongs
             * to the thread itself, whichever point in the thread that is.
             */}
            <span className="block text-center text-xs font-bold text-secondary-foreground">
                {headline}
            </span>

            <Link
                href={detailsHref}
                aria-label={`${headline} ${inquiryTitle(inquiry)}, inquiry ${inquiry.reference}, ${STATUS_LABELS[badgeStatus]}. View details`}
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
                        <StatusBadge status={badgeStatus} />
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

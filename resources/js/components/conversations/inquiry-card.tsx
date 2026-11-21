import { Link } from '@inertiajs/react';
import { ArrowRight, Images, Wrench } from 'lucide-react';
import {
    CARD_COLUMN,
    CARD_DETAILS,
    CARD_FOCUS,
    CARD_MEDIA,
    CARD_SURFACE,
} from '@/components/conversations/card-layout';
import CardHeader from '@/components/conversations/card-header';
import { StatusBadge } from '@/components/inquiry-status-badge';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { statusLabel } from '@/lib/inquiry-statuses';
import {
    extraStoneCount,
    inquiryHeadline,
    materialItemsLabel,
} from '@/lib/thread-cards';
import { show as inquiryShow } from '@/routes/inquiry';
import { show as quoteShow } from '@/routes/quote';
import type { InquiryCard as InquiryCardData } from '@/types/message';

/**
 * Written out rather than interpolated: Tailwind only sees class names it can
 * find in the source. One column per picture, up to three.
 */
const HERO_COLUMNS = ['grid-cols-1', 'grid-cols-2', 'grid-cols-3'] as const;

/**
 * Every inquiry card is the same height, whatever it holds: room for the
 * fullest one (pictures, both rows, the badges) with a few pixels to spare,
 * 296px stacked and 176px as a row. A card with fewer rows keeps the height,
 * its status row pinned to the bottom (`mt-auto`).
 */
const CARD_HEIGHT = 'h-74 @lg:h-44';

/**
 * An inquiry as a card in a thread, built from the `inquiry_card` message.
 *
 * The frame is the quotation card's (card-layout): stacked in a narrow
 * thread, one wide row once the card has 512px. The media is, in this order:
 * - the stone pictures, up to three, the last carrying "+N" for the stones it
 *   counts but does not draw;
 * - "Stone to be recommended", when the client asked Regray to choose;
 * - the service, for a service-only inquiry;
 * - nothing at all, never an empty strip.
 *
 * Beside it: the INQUIRY label and the reference, the inquiry's name, a
 * Materials row (only when it has stones) and a Service row (only when it has
 * a service), then the status row with "View". Every line truncates rather
 * than wraps, and the card is one fixed height (CARD_HEIGHT), so a thread of
 * them reads as a column of identical tiles.
 *
 * The headline and the badges, the inquiry status and then the payment status
 * (once accounting's stage had started), are the snapshot the card was posted
 * for, not whatever the inquiry is now: a card is a record of a change.
 *
 * The link opens the details page: a client's own inquiry on Quote, and for
 * staff the inquiry on the Inquiry list (InquiryDetailsService::forStaff()),
 * which finds it when they read it (Inquiry::scopeVisibleTo()). Staff who read
 * the group chat read its inquiry by the same rule, so the link works for
 * them; a card for an inquiry they do not read (one posted in a client's
 * Regray Team thread) ends in a 404.
 */
const InquiryCard = ({ card }: { card: InquiryCardData }) => {
    const { can } = usePermissions();
    const headline = inquiryHeadline(
        card.announcedStatus,
        card.announcedSalesStatus,
    );
    const extraStones = extraStoneCount(card.stoneCount, card.stones.length);
    const materials =
        card.stoneCount > 0 && !card.stoneAdviceRequested
            ? materialItemsLabel(card.stoneCount)
            : null;
    const detailsHref = can(permissions.quotationsView)
        ? quoteShow.url({ reference: card.reference })
        : inquiryShow.url({ reference: card.reference });

    const label = [
        `inquiry ${card.reference}`,
        card.name,
        materials === null ? null : `Materials: ${materials}`,
        card.service === null ? null : `Service: ${card.service.name}`,
        statusLabel('inquiry', card.announcedStatus),
        card.announcedPaymentStatus === null
            ? null
            : statusLabel('payment', card.announcedPaymentStatus),
    ]
        .filter((part) => part !== null)
        .join(', ');

    const media =
        card.stones.length > 0 ? (
            <span
                className={`grid h-full ${HERO_COLUMNS[card.stones.length - 1]} gap-px`}
                data-testid="inquiry-card-stones"
            >
                {card.stones.map((stone, index) => (
                    <span key={stone.slug} className="relative min-w-0">
                        <img
                            src={stone.thumbnail}
                            alt=""
                            loading="lazy"
                            className="size-full object-cover"
                        />
                        {extraStones > 0 && index === card.stones.length - 1 ? (
                            <span
                                aria-hidden
                                className="absolute inset-0 flex items-center justify-center gap-1 bg-foreground/60 text-xs font-bold text-background"
                                data-testid="inquiry-card-extra-stones"
                            >
                                <Images className="size-3.5" />+{extraStones}
                            </span>
                        ) : null}
                    </span>
                ))}
            </span>
        ) : card.stoneAdviceRequested ? (
            <span
                className="flex h-full items-center justify-center p-3 text-center text-xs font-bold text-foreground"
                data-testid="inquiry-card-stone-advice"
            >
                Stone to be recommended
            </span>
        ) : card.service !== null ? (
            <span
                className="flex h-full flex-col items-center justify-center gap-1.5 p-3 text-center text-secondary-foreground"
                data-testid="inquiry-card-service-tile"
            >
                <Wrench className="size-6" aria-hidden />
                <span className="max-w-full truncate text-xs font-bold text-foreground">
                    {card.service.name}
                </span>
            </span>
        ) : null;

    return (
        <div className={CARD_COLUMN} data-testid="inquiry-card">
            <CardHeader headline={headline} occurredAt={card.occurredAt} />

            <Link
                href={detailsHref}
                aria-label={`${headline}: ${label}. View details`}
                className={`${CARD_SURFACE} ${CARD_HEIGHT} transition-opacity hover:opacity-85 ${CARD_FOCUS}`}
            >
                {media === null ? null : (
                    <span className={CARD_MEDIA}>{media}</span>
                )}

                <span className={CARD_DETAILS}>
                    <span className="flex min-w-0 items-center justify-between gap-2 text-[0.65rem] text-secondary-foreground">
                        <span className="shrink-0 font-bold tracking-wider uppercase">
                            Inquiry
                        </span>
                        <span
                            className="min-w-0 truncate"
                            data-testid="inquiry-card-reference"
                        >
                            {card.reference}
                        </span>
                    </span>

                    <span
                        className="block truncate text-sm font-bold text-foreground"
                        data-testid="inquiry-card-title"
                    >
                        {card.name}
                    </span>

                    {materials === null && card.service === null ? null : (
                        <span className="block divide-y border-y text-xs">
                            {materials === null ? null : (
                                <span
                                    className="flex min-w-0 items-center justify-between gap-3 py-1.5"
                                    data-testid="inquiry-card-materials"
                                >
                                    <span className="shrink-0 text-secondary-foreground">
                                        Materials
                                    </span>
                                    <span className="min-w-0 truncate text-right font-bold text-foreground">
                                        {materials}
                                    </span>
                                </span>
                            )}
                            {card.service === null ? null : (
                                <span
                                    className="flex min-w-0 items-center justify-between gap-3 py-1.5"
                                    data-testid="inquiry-card-service"
                                >
                                    <span className="shrink-0 text-secondary-foreground">
                                        Service
                                    </span>
                                    <span className="min-w-0 truncate text-right font-bold text-foreground">
                                        {card.service.name}
                                    </span>
                                </span>
                            )}
                        </span>
                    )}

                    <span className="mt-auto flex items-center justify-between gap-2 text-xs text-secondary-foreground">
                        <span
                            className="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap"
                            data-testid="inquiry-card-status"
                        >
                            <span className="shrink-0">
                                <StatusBadge
                                    group="inquiry"
                                    status={card.announcedStatus}
                                />
                            </span>
                            {/* Only once accounting's stage had started. */}
                            {card.announcedPaymentStatus === null ? null : (
                                <span className="min-w-0 truncate">
                                    <StatusBadge
                                        group="payment"
                                        status={card.announcedPaymentStatus}
                                    />
                                </span>
                            )}
                        </span>

                        <span className="inline-flex shrink-0 items-center gap-1">
                            View
                            <ArrowRight className="size-3" aria-hidden />
                        </span>
                    </span>
                </span>
            </Link>
        </div>
    );
};

export default InquiryCard;

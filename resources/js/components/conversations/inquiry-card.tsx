import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import CardHeader from '@/components/conversations/card-header';
import { StatusBadge } from '@/components/inquiry-status-badge';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { statusLabel } from '@/lib/inquiry-statuses';
import { inquiryHeadline } from '@/lib/thread-cards';
import { show as inquiryShow } from '@/routes/inquiry';
import { show as quoteShow } from '@/routes/quote';
import type { InquiryCard as InquiryCardData } from '@/types/message';

/**
 * Written out rather than interpolated: Tailwind only sees class names it can
 * find in the source.
 */
const HERO_COLUMNS = ['grid-cols-1', 'grid-cols-2'] as const;

/**
 * An inquiry as a card in a thread, built from the `inquiry_card` message.
 *
 * One size whatever it holds: `max-w-sm` wide at every breakpoint, a `h-32`
 * strip with or without pictures, and every line on one line — the heading,
 * the sub-line (the project the inquiry is) and the status row all truncate
 * rather than wrap — so a thread of cards reads as a column of identical tiles.
 *
 * The headline and the badges, the inquiry status and then the payment status
 * (once accounting's stage had started), are the snapshot the card was posted
 * for, not whatever the inquiry is now: a card is a record of a change.
 *
 * TODO(staff-inquiry-scope): the details page only finds inquiries the viewer
 * owns (InquiryDetailsService::forClient), so for staff this
 * link ends in a 404 until staff access to inquiry details is built.
 */
const InquiryCard = ({ card }: { card: InquiryCardData }) => {
    const { can } = usePermissions();
    const headline = inquiryHeadline(card.announcedStatus);
    const extraStones = card.stoneCount - card.stones.length;
    const detailsHref = can(permissions.quotationsView)
        ? quoteShow.url({ reference: card.reference })
        : inquiryShow.url({ reference: card.reference });

    return (
        <div
            className="mx-auto w-full max-w-sm space-y-1.5"
            data-testid="inquiry-card"
        >
            <CardHeader headline={headline} occurredAt={card.occurredAt} />

            <Link
                href={detailsHref}
                aria-label={`${headline}: inquiry ${card.reference}, ${card.name}, ${statusLabel('inquiry', card.announcedStatus)}${
                    card.announcedPaymentStatus === null
                        ? ''
                        : `, ${statusLabel('payment', card.announcedPaymentStatus)}`
                }. View details`}
                className="block w-full overflow-hidden rounded-md bg-secondary text-left transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="relative block h-32 bg-sidebar-border">
                    {card.stones.length > 0 ? (
                        <span
                            className={`grid h-full ${HERO_COLUMNS[card.stones.length - 1]} gap-px`}
                        >
                            {card.stones.map((stone, index) => (
                                <span
                                    key={stone.slug}
                                    className="relative min-w-0"
                                >
                                    <img
                                        src={stone.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="size-full object-cover"
                                    />
                                    {extraStones > 0 &&
                                    index === card.stones.length - 1 ? (
                                        <span
                                            aria-hidden
                                            className="absolute inset-0 flex items-start justify-center bg-foreground/60 pt-2 text-xs font-bold text-background"
                                        >
                                            +{extraStones}
                                        </span>
                                    ) : null}
                                </span>
                            ))}
                        </span>
                    ) : null}

                    <span className="absolute inset-x-0 bottom-0 block min-w-0 space-y-0.5 bg-foreground/60 p-3">
                        <span
                            className="block truncate text-sm font-bold text-background"
                            data-testid="inquiry-card-title"
                        >
                            {card.reference}
                        </span>
                        <span
                            className="block truncate text-xs text-background/80"
                            data-testid="inquiry-card-subline"
                        >
                            {card.name}
                        </span>
                    </span>
                </span>

                <span className="flex items-center justify-between gap-2 p-3 text-xs text-secondary-foreground">
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
            </Link>
        </div>
    );
};

export default InquiryCard;

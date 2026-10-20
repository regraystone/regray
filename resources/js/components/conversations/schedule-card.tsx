import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import CardHeader from '@/components/conversations/card-header';
import { StatusBadge } from '@/components/schedule-status-badge';
import { bookedByText, scheduleHeadline } from '@/lib/thread-cards';
import { schedule as profileSchedule } from '@/routes/profile';
import { index as scheduleIndex } from '@/routes/schedule';
import type { ScheduleCard as ScheduleCardData } from '@/types/message';

const monthFormatter = new Intl.DateTimeFormat(undefined, { month: 'short' });
const dayFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric' });
const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

/**
 * A visit as a card in a thread, built from the `schedule_card` message.
 *
 * The same size as the inquiry card: `max-w-sm` wide, and a fixed set of
 * one-line rows. A row with nothing to say (no linked inquiry, no cancel
 * reason) keeps its height, so every visit card is the same height too.
 */
const ScheduleCard = ({
    card,
    isStaff,
}: {
    card: ScheduleCardData;
    isStaff: boolean;
}) => {
    const headline = scheduleHeadline(card.announcedStatus, card.bookedByType);
    const arrival = new Date(card.arrivalAt);
    const href = isStaff ? scheduleIndex.url() : profileSchedule.url();
    const cancelled = card.announcedStatus === 'cancelled';

    return (
        <div
            className="mx-auto w-full max-w-sm space-y-1.5"
            data-testid="schedule-card"
        >
            <CardHeader headline={headline} occurredAt={card.occurredAt} />

            <Link
                href={href}
                aria-label={`${headline}: ${card.title}, ${dayFormatter.format(arrival)} ${monthFormatter.format(arrival)} at ${timeFormatter.format(arrival)}. View schedule`}
                className="flex h-32 w-full gap-px overflow-hidden rounded-md bg-sidebar-border text-left transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="flex w-16 shrink-0 flex-col items-center justify-center gap-1 bg-foreground px-1 text-background">
                    <span className="text-[0.6rem] leading-none tracking-wider uppercase">
                        {monthFormatter.format(arrival)}
                    </span>
                    <span
                        className={`text-2xl leading-none font-bold ${cancelled ? 'line-through' : ''}`}
                    >
                        {dayFormatter.format(arrival)}
                    </span>
                    <span className="text-[0.6rem] leading-none">
                        {timeFormatter.format(arrival)}
                    </span>
                </span>

                <span className="flex min-w-0 flex-1 flex-col justify-between bg-secondary p-3 text-xs">
                    <span
                        className="block truncate text-sm font-bold text-foreground"
                        data-testid="schedule-card-title"
                    >
                        {card.title}
                    </span>
                    <span
                        className="flex min-w-0 items-center gap-1.5 overflow-hidden whitespace-nowrap text-secondary-foreground"
                        data-testid="schedule-card-status"
                    >
                        <span className="shrink-0">
                            <StatusBadge status={card.announcedStatus} />
                        </span>
                        <span className="truncate font-bold">{headline}</span>
                    </span>
                    <span
                        className="block truncate text-secondary-foreground"
                        data-testid="schedule-card-booked-by"
                    >
                        {bookedByText(card.bookedByType, card.bookedByName)}
                    </span>
                    <span
                        className="block truncate text-secondary-foreground"
                        data-testid="schedule-card-inquiry"
                    >
                        {card.inquiryReference ? (
                            <>
                                Inquiry No:{' '}
                                <span className="font-bold">
                                    {card.inquiryReference}
                                </span>
                            </>
                        ) : (
                            <span aria-hidden>&nbsp;</span>
                        )}
                    </span>
                    <span
                        className="block truncate text-secondary-foreground"
                        data-testid="schedule-card-footer"
                    >
                        {cancelled && card.cancelReason ? (
                            <>Reason: {card.cancelReason}</>
                        ) : (
                            <span className="inline-flex items-center gap-1">
                                View schedule{' '}
                                <ArrowRight className="size-3" aria-hidden />
                            </span>
                        )}
                    </span>
                </span>
            </Link>
        </div>
    );
};

export default ScheduleCard;

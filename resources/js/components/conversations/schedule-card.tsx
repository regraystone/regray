import { Link } from '@inertiajs/react';
import { ArrowRight, Clock, User } from 'lucide-react';
import CardHeader from '@/components/conversations/card-header';
import { ScheduleStatusBadge } from '@/components/schedule/schedule-status-badge';
import { bookedByText, scheduleHeadline } from '@/lib/thread-cards';
import { schedule as profileSchedule } from '@/routes/profile';
import { index as scheduleIndex } from '@/routes/schedule';
import type { ScheduleCard as ScheduleCardData } from '@/types/message';

const monthFormatter = new Intl.DateTimeFormat(undefined, { month: 'short' });
const dayFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric' });
const weekdayFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
});
const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

/**
 * A visit as a card in a thread, built from the `schedule_card` message.
 *
 * A date block on the left, and the title with the status badge in its top
 * right corner. The time and who booked it sit under the title, and the last
 * row carries the linked inquiry or the cancel reason with "View" on its
 * right, the same as the inquiry card.
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
                className="flex w-full gap-px overflow-hidden rounded-md bg-sidebar-border text-left transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span className="flex w-14 shrink-0 flex-col items-center justify-center gap-0.5 bg-foreground px-1 py-2 text-background">
                    <span className="text-[0.6rem] leading-none tracking-wider uppercase">
                        {monthFormatter.format(arrival)}
                    </span>
                    <span
                        className={`text-lg leading-none font-bold ${cancelled ? 'line-through' : ''}`}
                    >
                        {dayFormatter.format(arrival)}
                    </span>
                    <span className="text-[0.6rem] leading-none">
                        {weekdayFormatter.format(arrival)}
                    </span>
                </span>

                <span className="flex min-w-0 flex-1 flex-col gap-1 bg-secondary p-2 text-xs">
                    <span className="flex items-center justify-between gap-2">
                        <span
                            className="block min-w-0 truncate font-bold text-foreground"
                            data-testid="schedule-card-title"
                        >
                            {card.title}
                        </span>
                        <span
                            className="shrink-0"
                            data-testid="schedule-card-status"
                        >
                            <ScheduleStatusBadge
                                status={card.announcedStatus}
                            />
                        </span>
                    </span>

                    <span className="flex min-w-0 items-center gap-2 text-secondary-foreground">
                        <Clock className="size-3 shrink-0" aria-hidden />
                        <span className="shrink-0">
                            {timeFormatter.format(arrival)}
                        </span>
                        <span aria-hidden>·</span>
                        <User className="size-3 shrink-0" aria-hidden />
                        <span
                            className="truncate"
                            data-testid="schedule-card-booked-by"
                        >
                            {bookedByText(card.bookedByType, card.bookedByName)}
                        </span>
                    </span>

                    <span className="flex min-w-0 items-center justify-between gap-2 text-secondary-foreground">
                        <span
                            className="truncate"
                            data-testid="schedule-card-footer"
                        >
                            {cancelled && card.cancelReason ? (
                                <>Reason: {card.cancelReason}</>
                            ) : card.inquiryReference ? (
                                <>
                                    Inquiry No:{' '}
                                    <span className="font-bold">
                                        {card.inquiryReference}
                                    </span>
                                </>
                            ) : null}
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

export default ScheduleCard;

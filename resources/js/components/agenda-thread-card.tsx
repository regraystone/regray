import { Link } from '@inertiajs/react';
import { Clock, User } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import ThreadCardHeader from '@/components/thread-card-header';
import { StatusBadge } from '@/components/agenda-status-badge';
import type { AgendaItem, AgendaStatus } from '@/data/agenda';
import { index as agendaIndex } from '@/routes/agenda';
import profileAgenda from '@/routes/profile/agenda';
import type { Auth } from '@/types';
import { cn } from '@/lib/utils';

const STATUS_HEADLINES: Record<
    AgendaStatus,
    Record<'client' | 'staff', string>
> = {
    requested: {
        client: 'Booking requested',
        staff: 'Booking requested',
    },
    confirmed: {
        client: 'Booking approved',
        staff: 'Visit booked',
    },
    completed: {
        client: 'Visit completed',
        staff: 'Visit completed',
    },
    cancelled: {
        client: 'Booking cancelled',
        staff: 'Booking cancelled',
    },
};

const dateBlockColorMap: Record<AgendaStatus, string> = {
    requested: 'bg-foreground',
    confirmed: 'bg-foreground',
    completed: 'bg-foreground',
    cancelled: 'bg-foreground',
};

/**
 * Format date and time for the date block.
 * Month: small uppercase (e.g., "Oct")
 * Day: large bold (e.g., "14")
 * Weekday: small (e.g., "Tue")
 */
const dateFormatter = new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    weekday: 'short',
});

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

type AgendaThreadCardProps = {
    agenda: AgendaItem;
    status: AgendaStatus;
    bookedBy: 'client' | 'staff';
    bookedByName?: string;
    occurredAt: string;
};

const AgendaThreadCard = ({
    agenda,
    status,
    bookedBy,
    bookedByName,
    occurredAt,
}: AgendaThreadCardProps) => {
    const { auth } = usePage<{ auth: Auth }>().props;
    const headline = STATUS_HEADLINES[status][bookedBy];

    const formatDateParts = (date: Date) => {
        const formatted = dateFormatter.formatToParts(date);
        const month = formatted.find((p) => p.type === 'month')?.value || '';
        const day = formatted.find((p) => p.type === 'day')?.value || '';
        const weekday =
            formatted.find((p) => p.type === 'weekday')?.value || '';
        return { month, day, weekday };
    };

    const { month, day, weekday } = formatDateParts(agenda.arrival);
    const time = timeFormatter.format(agenda.arrival);

    const accessibleLabel = `${headline}: ${agenda.title}, ${weekday} ${day} ${month.toLowerCase()} at ${time}, ${status}${bookedByName ? `, booked by ${bookedByName}` : ''}. View agenda`;

    const isStaff = auth.roles.includes('staff');
    const detailsHref = isStaff ? agendaIndex.url() : profileAgenda.book.url();

    const dateBlockBase = dateBlockColorMap[status];
    const dateBlockTextColor = 'text-background';
    const dayTextStrike = status === 'cancelled' ? 'line-through' : '';

    const cardContent = (
        <div className="flex gap-px overflow-hidden bg-sidebar-border">
            {/* Date block */}
            <div
                className={cn(
                    'flex w-14 shrink-0 flex-col items-center justify-center px-1 py-2',
                    dateBlockBase,
                )}
            >
                <span
                    className={cn(
                        'text-[0.6rem] leading-none tracking-wider uppercase',
                        dateBlockTextColor,
                    )}
                >
                    {month}
                </span>
                <span
                    className={cn(
                        'text-lg leading-none font-bold',
                        dayTextStrike,
                        dateBlockTextColor,
                    )}
                >
                    {day}
                </span>
                <span
                    className={cn(
                        'text-[0.6rem] leading-none',
                        dateBlockTextColor,
                    )}
                >
                    {weekday}
                </span>
            </div>

            {/* Details */}
            <div className="flex min-w-0 flex-1 flex-col justify-between gap-1 p-2">
                {/* Title and status badge */}
                <div className="flex items-start justify-between gap-1">
                    <h3 className="line-clamp-1 text-xs font-bold">
                        {agenda.title}
                    </h3>
                    <StatusBadge status={status} />
                </div>

                {/* Time and booked by */}
                <div className="flex items-center gap-2 text-xs text-secondary-foreground">
                    <Clock className="size-3 shrink-0" aria-hidden />
                    <span>{time}</span>
                    <span aria-hidden="true">·</span>
                    <User className="size-3 shrink-0" aria-hidden />
                    <span className="truncate">
                        {bookedByName
                            ? bookedByName
                            : bookedBy === 'client'
                              ? 'Client'
                              : 'Staff'}
                    </span>
                </div>

                {/* Notes - only show if present */}
                {agenda.notes ? (
                    <p className="line-clamp-1 text-xs text-secondary-foreground">
                        {agenda.notes}
                    </p>
                ) : null}
            </div>
        </div>
    );

    return (
        <>
            <ThreadCardHeader headline={headline} occurredAt={occurredAt} />

            <Link
                href={detailsHref}
                aria-label={accessibleLabel}
                className="mx-auto block w-full max-w-xs overflow-hidden bg-secondary transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:max-w-sm"
            >
                {cardContent}
            </Link>
        </>
    );
};

export default AgendaThreadCard;

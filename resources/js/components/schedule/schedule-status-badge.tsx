import type { LucideIcon } from 'lucide-react';
import { Check, CircleDashed, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { ScheduleStatus } from '@/lib/schedule';

/**
 * What each status is called on screen.
 *
 * Shared rather than repeated, because a status read two different ways in two
 * places on the same journey is a small thing that makes the two screens feel
 * like different systems.
 */
export const SCHEDULE_STATUS_LABELS: Record<ScheduleStatus, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

/**
 * How each status is drawn on the grid.
 *
 * Four weights of the same two greys rather than four colours: a solid dark
 * rule says it is agreed, a dashed one says it is not, a pale flat block says
 * it has been and a struck-through word says it will not be. None of it is a
 * hue, so the page stays monochrome, and each treatment is a shape as much as a
 * shade, which is what carries the status for a reader who cannot see the
 * difference between two greys.
 */
export const STATUS_BLOCK_STYLES: Record<ScheduleStatus, string> = {
    confirmed: 'border-l-2 border-l-foreground bg-muted',
    pending: 'border border-dashed border-sidebar-border bg-background',
    completed: 'border-l-2 border-l-border bg-muted/60 text-muted-foreground',
    cancelled:
        'border border-dashed border-sidebar-border bg-background text-muted-foreground',
};

/** Whether a block strikes its own title through, which only a cancellation does. */
export const isStruck = (status: ScheduleStatus): boolean =>
    status === 'cancelled';

/**
 * The icon that says a status without its word.
 *
 * Paired with the word rather than replacing it: the shape carries the status
 * for anyone who cannot separate the greys, and the label beside it carries it
 * for everyone else.
 */
const STATUS_ICONS: Record<ScheduleStatus, LucideIcon> = {
    pending: CircleDashed,
    confirmed: Check,
    completed: Check,
    cancelled: X,
};

/**
 * The status as a badge, sized for a card rather than a table.
 *
 * The same three treatments the inquiry badge uses, and for the same reasons:
 * the two settled states carry a solid badge because something has happened,
 * a request carries an outline because nothing has been agreed yet, and a
 * cancellation strikes its own label through so the word does not have to be
 * argued with.
 */
export const ScheduleStatusBadge = ({ status }: { status: ScheduleStatus }) => {
    const Icon = STATUS_ICONS[status];

    const variant =
        status === 'confirmed' || status === 'completed'
            ? 'default'
            : status === 'cancelled'
              ? 'secondary'
              : 'outline';

    return (
        <Badge
            variant={variant}
            className={`gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase ${
                status === 'pending' ? 'border-dashed' : ''
            }`}
        >
            <Icon className="size-3" aria-hidden />
            <span className={isStruck(status) ? 'line-through' : undefined}>
                {SCHEDULE_STATUS_LABELS[status]}
            </span>
        </Badge>
    );
};

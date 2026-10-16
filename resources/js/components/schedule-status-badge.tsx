import type { ScheduleStatus } from '@/data/schedule';
import { cn } from '@/lib/utils';

export const STATUS_LABELS: Record<ScheduleStatus, string> = {
    requested: 'Requested',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const STATUS_STYLES: Record<ScheduleStatus, { bg: string; text: string }> = {
    requested: {
        bg: 'bg-transparent border border-secondary-foreground',
        text: 'text-secondary-foreground',
    },
    confirmed: {
        bg: 'bg-primary',
        text: 'text-primary-foreground',
    },
    completed: {
        bg: 'bg-primary',
        text: 'text-primary-foreground',
    },
    cancelled: {
        bg: 'bg-transparent',
        text: 'text-secondary-foreground line-through',
    },
};

type StatusBadgeProps = {
    status: ScheduleStatus;
};

export const StatusBadge = ({ status }: StatusBadgeProps) => {
    const styles = STATUS_STYLES[status];

    return (
        <span
            className={cn(
                'inline-block rounded-md px-2 py-1 text-xs font-semibold',
                styles.bg,
                styles.text,
            )}
        >
            {STATUS_LABELS[status]}
        </span>
    );
};

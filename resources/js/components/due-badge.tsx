import { Badge } from '@/components/ui/badge';
import { TONE_CLASSES } from '@/components/inquiry-status-badge';
import { formatDate } from '@/lib/format-date';
import type { StatusTone } from '@/lib/inquiry-statuses';
import { cn } from '@/lib/utils';

/** How an open inquiry stands against its deadline, as `DueStatus` sends it. */
export type DueStatus = 'on_track' | 'due_soon' | 'overdue';

const DUE_LABELS: Record<DueStatus, string> = {
    on_track: 'On track',
    due_soon: 'Due soon',
    overdue: 'Overdue',
};

const DUE_TONES: Record<DueStatus, StatusTone> = {
    on_track: 'green',
    due_soon: 'amber',
    overdue: 'red',
};

/**
 * The Due column's badge, drawn like the status badges, with the deadline and,
 * once the project is done, the day it was completed in its tooltip. A null due
 * (no deadline, or the inquiry cancelled) renders a dash.
 */
export const DueBadge = ({
    due,
    deadline,
    completedAt,
}: {
    due: DueStatus | null;
    deadline: string | null;
    completedAt: string | null;
}) => {
    if (due === null) {
        return <span className="text-secondary-foreground">—</span>;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] whitespace-nowrap uppercase',
                TONE_CLASSES[DUE_TONES[due]],
            )}
            title={[
                deadline === null ? null : `Needed by ${formatDate(deadline)}`,
                completedAt === null
                    ? null
                    : `Completed ${formatDate(completedAt)}`,
            ]
                .filter((line) => line !== null)
                .join(' · ')}
            data-due={due}
        >
            {DUE_LABELS[due]}
        </Badge>
    );
};

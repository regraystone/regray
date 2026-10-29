import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    isEndedStatus,
    isFinishedStatus,
    STATUS_GROUP_NAMES,
    statusLabel,
    statusTone,
    type StatusTone,
} from '@/lib/inquiry-statuses';
import type { StatusGroup, StatusOf } from '@/lib/inquiry-statuses';
import { cn } from '@/lib/utils';

/** Each tone as a tinted badge: a light fill, its border, and dark text of the same hue. */
const TONE_CLASSES: Record<StatusTone, string> = {
    slate: 'border-slate-200 bg-slate-100 text-slate-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
    indigo: 'border-indigo-200 bg-indigo-50 text-indigo-700',
    violet: 'border-violet-200 bg-violet-50 text-violet-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-800',
    yellow: 'border-yellow-200 bg-yellow-50 text-yellow-800',
    red: 'border-red-200 bg-red-50 text-red-700',
    rose: 'border-rose-200 bg-rose-50 text-rose-700',
    sky: 'border-sky-200 bg-sky-50 text-sky-700',
    cyan: 'border-cyan-200 bg-cyan-50 text-cyan-800',
    teal: 'border-teal-200 bg-teal-50 text-teal-700',
    orange: 'border-orange-200 bg-orange-50 text-orange-700',
    lime: 'border-lime-200 bg-lime-50 text-lime-800',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    fuchsia: 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-700',
    purple: 'border-purple-200 bg-purple-50 text-purple-700',
    green: 'border-green-200 bg-green-50 text-green-700',
};

/**
 * One status as a badge, sized for a card or a table cell, read in its own
 * group: the inquiry (display) status, or sales', accounting's or logistics'
 * own.
 *
 * Each status has its own colour (`statusTone`), the same on every page and
 * for every role, so the stage reads at a glance. A finished one carries a
 * tick, and one that ended for good strikes its label through.
 *
 * A null status is a stage that has not started, and renders nothing at all.
 * The group is named in the tooltip rather than in a caption.
 */
export const StatusBadge = <G extends StatusGroup>({
    group,
    status,
}: {
    group: G;
    status: StatusOf<G> | null;
}) => {
    if (status === null) {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase',
                TONE_CLASSES[statusTone(status)],
            )}
            title={STATUS_GROUP_NAMES[group]}
            data-status-group={group}
            data-status={status}
        >
            {isFinishedStatus(status) ? (
                <Check className="size-3" aria-hidden />
            ) : null}
            <span
                className={isEndedStatus(status) ? 'line-through' : undefined}
            >
                {statusLabel(group, status)}
            </span>
        </Badge>
    );
};

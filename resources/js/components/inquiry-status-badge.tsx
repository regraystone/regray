import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    isEndedStatus,
    isFinishedStatus,
    STATUS_GROUP_NAMES,
    statusLabel,
    statusTreatment,
} from '@/lib/inquiry-statuses';
import type { StatusGroup, StatusOf } from '@/lib/inquiry-statuses';

const VARIANTS = {
    solid: 'default',
    outline: 'outline',
    secondary: 'secondary',
} as const;

/**
 * One status as a badge, sized for a card rather than a table, read in its own
 * group: the inquiry (display) status, or marketing's, sales', accounting's or
 * logistics' own.
 *
 * Three treatments rather than one per status (see `statusTreatment`): a
 * reached stage is solid, one still open is outlined, and one that stopped is
 * muted. A finished one carries a tick, and one that ended for good strikes
 * its label through so the word does not have to be argued with.
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
            variant={VARIANTS[statusTreatment(status)]}
            className="gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase"
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

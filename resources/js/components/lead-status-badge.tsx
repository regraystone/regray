import { Badge } from '@/components/ui/badge';
import { isEndedStatus, statusTreatment } from '@/lib/inquiry-statuses';
import { leadStatusLabel, leadTallyParts } from '@/lib/lead-statuses';
import type { LeadStatus, LeadTally } from '@/lib/lead-statuses';

const VARIANTS = {
    solid: 'default',
    outline: 'outline',
    secondary: 'secondary',
} as const;

/**
 * Marketing's decision as a badge, drawn like the inquiry badges: won is
 * solid, idle outlined, and lost muted and struck through. With `count`, it
 * reads "Won 2". Staff only.
 */
export const LeadStatusBadge = ({
    status,
    count = null,
}: {
    status: LeadStatus;
    count?: number | null;
}) => (
    <Badge
        variant={VARIANTS[statusTreatment(status)]}
        className="gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase"
        title="Marketing's decision"
        data-lead-status={status}
    >
        <span className={isEndedStatus(status) ? 'line-through' : undefined}>
            {leadStatusLabel(status)}
        </span>
        {count === null ? null : <span>{count}</span>}
    </Badge>
);

/**
 * A lead's status: the tally of its inquiries' decisions, one badge per
 * decision ("Won 2", "Lost 1"), or the one status for a lead with a single
 * inquiry.
 */
export const LeadTallyBadges = ({ tally }: { tally: LeadTally }) => (
    <span className="inline-flex flex-wrap items-center gap-1">
        {leadTallyParts(tally).map(({ status, count }) => (
            <LeadStatusBadge key={status} status={status} count={count} />
        ))}
    </span>
);

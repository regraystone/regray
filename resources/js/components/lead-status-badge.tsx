import { Badge } from '@/components/ui/badge';
import { isEndedStatus, statusTreatment } from '@/lib/inquiry-statuses';
import { leadStatusLabel } from '@/lib/lead-statuses';
import type { LeadStatus } from '@/lib/lead-statuses';

const VARIANTS = {
    solid: 'default',
    outline: 'outline',
    secondary: 'secondary',
} as const;

/**
 * A lead's status as a badge, drawn like the inquiry badges: won is solid,
 * idle outlined, and lost muted and struck through. Staff only.
 */
export const LeadStatusBadge = ({ status }: { status: LeadStatus }) => (
    <Badge
        variant={VARIANTS[statusTreatment(status)]}
        className="gap-1 rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase"
        title="Lead status"
        data-lead-status={status}
    >
        <span className={isEndedStatus(status) ? 'line-through' : undefined}>
            {leadStatusLabel(status)}
        </span>
    </Badge>
);

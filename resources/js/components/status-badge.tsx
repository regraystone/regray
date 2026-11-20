import { Badge } from '@/components/ui/badge';

/**
 * Whether something is on, as a badge drawn like the lead and inquiry ones:
 * active is solid, inactive outlined and muted.
 */
const StatusBadge = ({ active }: { active: boolean }) => (
    <Badge
        variant={active ? 'default' : 'outline'}
        className={`rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase ${
            active ? '' : 'text-secondary-foreground'
        }`}
        data-status={active ? 'active' : 'inactive'}
    >
        {active ? 'Active' : 'Inactive'}
    </Badge>
);

export default StatusBadge;

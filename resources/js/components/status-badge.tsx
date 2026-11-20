import { Badge } from '@/components/ui/badge';

/**
 * Whether something is on, as a badge drawn like the lead and inquiry ones:
 * on is solid, off outlined and muted. "Active" / "Inactive" unless the page
 * names the two states itself, as Brands' "Visible" / "Hidden".
 */
const StatusBadge = ({
    active,
    labels = ['Active', 'Inactive'],
}: {
    active: boolean;
    labels?: readonly [on: string, off: string];
}) => (
    <Badge
        variant={active ? 'default' : 'outline'}
        className={`rounded-md px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase ${
            active ? '' : 'text-secondary-foreground'
        }`}
        data-status={active ? 'active' : 'inactive'}
    >
        {active ? labels[0] : labels[1]}
    </Badge>
);

export default StatusBadge;

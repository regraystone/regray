import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { InquiryStatus } from '@/data/constant';

/**
 * What each status is called on screen.
 *
 * Shared rather than repeated, because a status read two different ways in two
 * places on the same journey is a small thing that makes the two screens feel
 * like different systems.
 */
export const STATUS_LABELS: Record<InquiryStatus, string> = {
    draft: 'Draft',
    pending: 'Pending',
    quoted: 'Quoted',
    paid: 'Paid',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

/**
 * The status as a badge, sized for a card rather than a table.
 *
 * Three treatments rather than one per status: the stages reached carry a solid
 * badge because something has happened, the two still open carry an outline
 * because nothing has been decided yet, and cancelled strikes its own label
 * through so the word does not have to be argued with.
 */
export const StatusBadge = ({ status }: { status: InquiryStatus }) => {
    const isSolid =
        status === 'quoted' || status === 'paid' || status === 'completed';
    const showsCheck = status === 'paid' || status === 'completed';

    const variant = isSolid
        ? 'default'
        : status === 'cancelled'
          ? 'secondary'
          : 'outline';

    return (
        <Badge
            variant={variant}
            className={`gap-1 rounded-none px-2 py-0.5 text-[0.65rem] font-bold tracking-[0.05em] uppercase ${
                status === 'draft' ? 'border-dashed' : ''
            }`}
        >
            {showsCheck ? <Check className="size-3" aria-hidden /> : null}
            <span
                className={status === 'cancelled' ? 'line-through' : undefined}
            >
                {STATUS_LABELS[status]}
            </span>
        </Badge>
    );
};

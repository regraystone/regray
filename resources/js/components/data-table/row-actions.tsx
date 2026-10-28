import {
    ArrowRightLeft,
    Check,
    Eye,
    FileUp,
    Pencil,
    Truck,
    Wallet,
    X,
    type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Every row action a table can offer. The server sends which ones a row has
 * (each from a `Gate::allows` on the permission its route checks), so the page
 * never decides by role.
 */
export type RowActionKey =
    | 'view'
    | 'edit'
    | 'status.sales'
    | 'status.payment'
    | 'status.process'
    | 'job_order'
    | 'won'
    | 'lost';

const ACTIONS: Record<RowActionKey, { icon: LucideIcon; label: string }> = {
    view: { icon: Eye, label: 'View' },
    edit: { icon: Pencil, label: 'Edit' },
    'status.sales': { icon: ArrowRightLeft, label: 'Sales status' },
    'status.payment': { icon: Wallet, label: 'Payment status' },
    'status.process': { icon: Truck, label: 'Logistics status' },
    job_order: { icon: FileUp, label: 'Upload job order' },
    won: { icon: Check, label: 'Mark won' },
    lost: { icon: X, label: 'Mark lost' },
};

/** The order the buttons sit in, whatever order the server sent them in. */
const ORDER: RowActionKey[] = [
    'view',
    'edit',
    'status.sales',
    'status.payment',
    'status.process',
    'job_order',
    'won',
    'lost',
];

/**
 * A row's actions: one icon button per task, each with its name as a tooltip
 * and an accessible label. No menus. A key the page has no handler for is not
 * drawn.
 */
const RowActions = ({
    actions,
    handlers,
    subject,
}: {
    actions: readonly string[];
    handlers: Partial<Record<RowActionKey, () => void>>;
    /** What the row is, for the labels: "View INQ-1049". */
    subject: string;
}) => (
    <div className="flex items-center justify-end gap-1">
        {ORDER.filter(
            (key) => actions.includes(key) && handlers[key] !== undefined,
        ).map((key) => {
            const { icon: Icon, label } = ACTIONS[key];

            return (
                <Tooltip key={key}>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-9 w-9"
                            aria-label={`${label} · ${subject}`}
                            data-row-action={key}
                            onClick={handlers[key]}
                        >
                            <Icon aria-hidden />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{label}</TooltipContent>
                </Tooltip>
            );
        })}
    </div>
);

export default RowActions;

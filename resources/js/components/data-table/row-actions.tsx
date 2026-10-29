import {
    ArrowRightLeft,
    Check,
    Eye,
    FileUp,
    MoreVertical,
    Pencil,
    Truck,
    Wallet,
    X,
    type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
 * A row's actions: shows the first action (view) inline, and converts to a
 * dropdown menu (three dots) when there are additional actions.
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
}) => {
    const availableActions = ORDER.filter(
        (key) => actions.includes(key) && handlers[key] !== undefined,
    );

    if (availableActions.length === 0) {
        return null;
    }

    // Show first action inline, rest in dropdown
    const inlineAction = availableActions[0];
    const dropdownActions = availableActions.slice(1);

    return (
        <div className="flex items-center justify-end gap-0.5">
            {inlineAction &&
                (() => {
                    const { icon: Icon, label } = ACTIONS[inlineAction];

                    return (
                        <Tooltip key={inlineAction}>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="h-9 w-9"
                                    aria-label={`${label} · ${subject}`}
                                    data-row-action={inlineAction}
                                    onClick={handlers[inlineAction]}
                                >
                                    <Icon aria-hidden className="size-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{label}</TooltipContent>
                        </Tooltip>
                    );
                })()}

            {dropdownActions.length > 0 && (
                <DropdownMenu>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="h-9 w-9"
                                    aria-label={`More actions for ${subject}`}
                                >
                                    <MoreVertical
                                        aria-hidden
                                        className="size-4"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                        </TooltipTrigger>
                        <TooltipContent>More</TooltipContent>
                    </Tooltip>
                    <DropdownMenuContent align="end">
                        {dropdownActions.map((key) => {
                            const { icon: Icon, label } = ACTIONS[key];

                            return (
                                <DropdownMenuItem
                                    key={key}
                                    onClick={handlers[key]}
                                    data-row-action={key}
                                >
                                    <Icon className="mr-2 size-4" aria-hidden />
                                    {label}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
};

export default RowActions;

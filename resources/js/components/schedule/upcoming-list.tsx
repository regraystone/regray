import { MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import type { ScheduleDialogAction } from '@/components/schedule/event-dialog';
import { ScheduleStatusBadge } from '@/components/schedule/schedule-status-badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatFullDate, formatTime, formatWeekday } from '@/lib/date';
import type { ScheduleItem } from '@/lib/schedule';

const formatMonth = new Intl.DateTimeFormat(undefined, { month: 'short' });

/**
 * The next visits, soonest first.
 *
 * Draws exactly the `upcoming` prop: the server has already picked the next
 * three pending or confirmed visits and left out served, cancelled and expired ones.
 * One flat list, each row carrying its own date, so there are no day headings.
 * Its menu offers the changes this reader may make to the visit now
 * (`item.can`), the same ones its dialog does.
 */
const UpcomingList = ({
    items,
    onSelect,
    onAction,
    emptyAction,
}: {
    items: ScheduleItem[];
    onSelect: (item: ScheduleItem) => void;
    onAction: (action: ScheduleDialogAction, item: ScheduleItem) => void;
    emptyAction: ReactNode;
}) => {
    if (items.length === 0) {
        return (
            <div className="rounded-md border border-sidebar-border bg-background px-5 py-10 text-center">
                <p className="text-sm font-bold">No upcoming visits</p>
                <p className="mt-1.5 text-sm text-secondary-foreground">
                    Nothing booked from today onwards. A visit you book will
                    appear here.
                </p>
                <div className="mt-4">{emptyAction}</div>
            </div>
        );
    }

    return (
        <ul className="overflow-hidden rounded-md border border-sidebar-border bg-background">
            {items.map((item) => {
                const when = `${formatFullDate.format(item.arrival)}, ${formatTime.format(item.arrival)}`;

                return (
                    <li
                        key={item.id}
                        className="flex items-center gap-3 border-b border-sidebar-border px-3 py-3 last:border-b-0"
                    >
                        {/*
                            The whole row's date at a glance: month over day,
                            so three visits read down the list as three days
                            without a heading for each.
                        */}
                        <span
                            aria-hidden
                            className="grid w-11 shrink-0 place-items-center rounded-md border border-sidebar-border py-1"
                        >
                            <span className="text-[0.6rem] font-bold tracking-[0.12em] text-secondary-foreground uppercase">
                                {formatMonth.format(item.arrival)}
                            </span>
                            <span className="text-base leading-tight font-bold tabular-nums">
                                {item.arrival.getDate()}
                            </span>
                        </span>

                        {/*
                            A button rather than a row of presentation: the
                            whole line opens the visit, which is what a pointer
                            reaching for it expects, and it is the same target a
                            keyboard needs to find.
                        */}
                        <button
                            type="button"
                            onClick={() => onSelect(item)}
                            aria-label={`${item.title}, ${when}`}
                            className="min-w-0 flex-1 rounded-md text-left transition-opacity hover:opacity-80 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span className="block truncate text-sm font-bold">
                                {item.title}
                            </span>
                            <span className="block truncate text-xs text-secondary-foreground tabular-nums">
                                {formatWeekday.format(item.arrival)} ·{' '}
                                {formatTime.format(item.arrival)}
                            </span>
                        </button>

                        <ScheduleStatusBadge status={item.status} />

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={`More actions for ${item.title} on ${when}`}
                                    className="grid size-8 shrink-0 place-items-center rounded-md text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <MoreHorizontal
                                        className="size-4"
                                        aria-hidden
                                    />
                                </button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem
                                    onSelect={() => onSelect(item)}
                                >
                                    View details
                                </DropdownMenuItem>
                                {item.can.confirm ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            onAction('confirm', item)
                                        }
                                    >
                                        Confirm
                                    </DropdownMenuItem>
                                ) : null}
                                {item.can.decline ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            onAction('decline', item)
                                        }
                                    >
                                        Decline
                                    </DropdownMenuItem>
                                ) : null}
                                {item.can.assign ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            onAction('assign', item)
                                        }
                                    >
                                        {item.status === 'awaiting_sales'
                                            ? 'Reassign'
                                            : 'Assign to sales'}
                                    </DropdownMenuItem>
                                ) : null}
                                {item.can.update ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            onAction('update', item)
                                        }
                                    >
                                        Update
                                    </DropdownMenuItem>
                                ) : null}
                                {item.can.cancel ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            onAction('cancel', item)
                                        }
                                    >
                                        Cancel appointment
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </li>
                );
            })}
        </ul>
    );
};

export default UpcomingList;

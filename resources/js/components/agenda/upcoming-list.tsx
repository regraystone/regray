import type { LucideIcon } from 'lucide-react';
import {
    CalendarClock,
    CalendarDays,
    ClipboardCheck,
    MoreHorizontal,
    Ruler,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AgendaStatusBadge } from '@/components/agenda/agenda-status-badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AgendaItem } from '@/data/agenda';
import { dayKey, formatTime, startOfDay } from '@/lib/date';

/**
 * The icon that stands for each kind of visit.
 *
 * Matched on the title rather than given a field of its own, because the title
 * is the only thing the mock has to key on today and a second field would be one
 * more thing the backend has to send before the list can draw itself.
 */
const iconsByTitle = new Map<string, LucideIcon>([
    ['Showroom visit', CalendarDays],
    ['Material selection', ClipboardCheck],
    ['Site ocular assessment', Ruler],
    ['Quotation review', CalendarClock],
]);

const iconFor = (title: string) => iconsByTitle.get(title) ?? CalendarDays;

/** How a day is named in a group heading, given how far away it is. */
const dayHeading = (day: Date, today: Date): string => {
    const days = Math.round(
        (startOfDay(day).getTime() - startOfDay(today).getTime()) / 86_400_000,
    );

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
    }).format(day);
};

type DayGroup = {
    key: string;
    heading: string;
    items: AgendaItem[];
};

/**
 * Group the visits by the day they are on.
 *
 * Grouped rather than listed flat because a client asking what is coming up
 * thinks in days, not in a queue of items: which day do I need to be
 * free for is the question, and a heading per day answers it before any of the
 * times are read.
 */
const groupByDay = (items: AgendaItem[], today: Date): DayGroup[] => {
    const groups = new Map<string, DayGroup>();

    for (const item of items) {
        const key = dayKey(item.arrival);
        const existing = groups.get(key);

        if (existing) {
            existing.items.push(item);

            continue;
        }

        groups.set(key, {
            key,
            heading: dayHeading(item.arrival, today),
            items: [item],
        });
    }

    return [...groups.values()];
};

/**
 * What is coming up, day by day.
 *
 * Everything from today onwards, in the order it happens. A visit that has been
 * and is not happening again is left out: this list answers "what have I got to
 * turn up for", and neither of those is a thing to turn up for.
 */
const UpcomingList = ({
    items,
    onSelect,
    onReschedule,
    onCancel,
    emptyAction,
}: {
    items: AgendaItem[];
    onSelect: (item: AgendaItem) => void;
    onReschedule: (item: AgendaItem) => void;
    onCancel: (item: AgendaItem) => void;
    emptyAction: ReactNode;
}) => {
    const upcoming = items
        .filter(
            (item) =>
                item.status === 'confirmed' || item.status === 'requested',
        )
        .sort((a, b) => a.arrival.getTime() - b.arrival.getTime())
        .slice(0, 4);

    const groups = groupByDay(upcoming, new Date());

    if (groups.length === 0) {
        return (
            <div className="border border-sidebar-border bg-background px-5 py-10 text-center">
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
        <div className="space-y-5">
            {groups.map((group) => (
                <section key={group.key}>
                    <h4 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                        {group.heading}
                    </h4>

                    <ul className="border border-sidebar-border bg-background">
                        {group.items.map((item) => {
                            const Icon = iconFor(item.title);

                            return (
                                <li
                                    key={item.id}
                                    className="flex items-center gap-3 border-b border-sidebar-border px-3 py-2.5 last:border-b-0"
                                >
                                    <span className="grid size-8 shrink-0 place-items-center border border-sidebar-border text-secondary-foreground">
                                        <Icon className="size-4" aria-hidden />
                                    </span>

                                    {/*
                                        A button rather than a row of
                                        presentation: the whole line opens the
                                        visit, which is what a pointer reaching
                                        for it expects, and it is the same target
                                        a keyboard needs to find.
                                    */}
                                    <button
                                        type="button"
                                        onClick={() => onSelect(item)}
                                        className="min-w-0 flex-1 text-left transition-opacity hover:opacity-80 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        <span className="block truncate text-sm font-bold">
                                            {item.title}
                                        </span>
                                        <span className="block truncate text-xs text-secondary-foreground">
                                            {formatTime.format(item.arrival)}
                                        </span>
                                    </button>

                                    <AgendaStatusBadge status={item.status} />

                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <button
                                                type="button"
                                                aria-label={`More actions for ${item.title} on ${formatTime.format(item.arrival)}`}
                                                className="grid size-8 shrink-0 place-items-center text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            >
                                                <MoreHorizontal
                                                    className="size-4"
                                                    aria-hidden
                                                />
                                            </button>
                                        </DropdownMenuTrigger>

                                        <DropdownMenuContent
                                            align="end"
                                            className="w-48 rounded-none"
                                        >
                                            <DropdownMenuItem
                                                onSelect={() => onSelect(item)}
                                            >
                                                View details
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    onReschedule(item)
                                                }
                                            >
                                                Reschedule
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onSelect={() => onCancel(item)}
                                            >
                                                Cancel visit
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            ))}
        </div>
    );
};

export default UpcomingList;

import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Link, router, usePage } from '@inertiajs/react';
import ConfirmDialog from '@/components/confirm-dialog';
import AssignScheduleDialog from '@/components/schedule/assign-schedule-dialog';
import CancelScheduleDialog from '@/components/schedule/cancel-schedule-dialog';
import EventDialog, {
    type ScheduleDialogAction,
} from '@/components/schedule/event-dialog';
import MiniCalendar from '@/components/schedule/mini-calendar';
import UpcomingList from '@/components/schedule/upcoming-list';
import WeekGrid from '@/components/schedule/week-grid';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { useBookRoute } from '@/hooks/use-book-route';
import { permissions } from '@/layouts/workspace-layout';
import { monthDays } from '@/lib/calendar';
import {
    addDays,
    dayKey,
    formatDayMonth,
    startOfDay,
    startOfWeek,
} from '@/lib/date';
import {
    fitsRange,
    rangeCovering,
    rangeQuery,
    toScheduleItems,
    type ScheduleData,
    type ScheduleItem,
} from '@/lib/schedule';
import { sendScheduleChange } from '@/lib/schedule-changes';
import { edit as profileScheduleEdit } from '@/routes/profile/schedule';
import {
    confirm as confirmSchedule,
    edit as scheduleEdit,
    noShow as noShowSchedule,
    serve as serveSchedule,
} from '@/routes/schedule';

/**
 * How many days the week grid shows.
 *
 * Seven on a wide screen, because a client planning a visit wants to see the
 * whole week around it and the grid scrolls inside its own box rather than
 * pushing the page sideways. Three on a narrow one, for the same reason in the
 * other direction: seven columns on a phone are too narrow to read a title in,
 * and a wider grid would mean the page itself scrolls sideways, which is the one
 * thing this layout is arranged to avoid.
 */
const DESKTOP_DAY_COUNT = 7;
const MOBILE_DAY_COUNT = 3;

/**
 * Where the week stops being three days.
 *
 * Tailwind's `md`, and matched to the day count rather than left to CSS so the
 * header and the columns always agree on how many days there are.
 */
const WIDE_VIEWPORT_QUERY = '(min-width: 768px)';

/**
 * The label over the week, written as the span it covers.
 *
 * Both months named rather than one, because a week that opens in September and
 * closes in October is the normal case rather than the exception and "Sep 28 -
 * 3" is not a range anybody can read.
 */
const rangeLabel = (days: Date[]): string => {
    const first = days[0];
    const last = days[days.length - 1];

    return `${formatDayMonth.format(first)} – ${formatDayMonth.format(last)}, ${last.getFullYear()}`;
};

/** The columns the week grid shows for an anchor day. */
const visibleDays = (anchor: Date, isWide: boolean): Date[] =>
    Array.from(
        { length: isWide ? DESKTOP_DAY_COUNT : MOBILE_DAY_COUNT },
        (_, index) => addDays(isWide ? startOfWeek(anchor) : anchor, index),
    );

type SchedulePageProps = {
    /** Every visit in the range asked for, any status, ordered by arrival. */
    schedules: ScheduleData[];
    /** The next three pending or confirmed visits, soonest first. */
    upcoming: ScheduleData[];
};

const Schedule = () => {
    const { can } = usePermissions();
    const { props } = usePage<SchedulePageProps>();
    const items = useMemo(
        () => toScheduleItems(props.schedules),
        [props.schedules],
    );
    const upcoming = useMemo(
        () => toScheduleItems(props.upcoming),
        [props.upcoming],
    );
    const [isLoading, setIsLoading] = useState(false);

    const [anchor, setAnchor] = useState(() => startOfDay(new Date()));
    const [month, setMonth] = useState(() => startOfDay(new Date()));
    const [selected, setSelected] = useState<ScheduleItem | null>(null);

    /*
     * Whether the wide or the narrow week is drawn.
     *
     * Read from the viewport rather than left to CSS, because the two views are
     * different numbers of days rather than the same seven days at a different
     * size: hiding four of them with a class would leave a grid whose columns do
     * not line up with the header above it. Starts true so a server render and
     * the first client render agree, which React would otherwise complain about.
     */
    const [isWide, setIsWide] = useState(true);

    useEffect(() => {
        const query = window.matchMedia(WIDE_VIEWPORT_QUERY);

        const sync = () => setIsWide(query.matches);

        sync();
        query.addEventListener('change', sync);

        return () => query.removeEventListener('change', sync);
    }, []);

    const dayCount = isWide ? DESKTOP_DAY_COUNT : MOBILE_DAY_COUNT;

    const days = useMemo(() => visibleDays(anchor, isWide), [anchor, isWide]);

    /*
     * Move the week grid and the mini calendar, and read the visits for both.
     *
     * One request covers the grid's days and the month's six weeks, so the
     * dots are right too. When the two have drifted too far apart to fit in
     * one request, the side that did not move follows the one that did.
     */
    const show = (
        next: { anchor: Date; month: Date },
        moved: 'anchor' | 'month',
    ) => {
        let { anchor: nextAnchor, month: nextMonth } = next;
        const covering = () =>
            rangeCovering(
                visibleDays(nextAnchor, isWide),
                monthDays(nextMonth),
            );

        if (!fitsRange(covering())) {
            if (moved === 'anchor') {
                nextMonth = nextAnchor;
            } else {
                nextAnchor = new Date(
                    nextMonth.getFullYear(),
                    nextMonth.getMonth(),
                    1,
                );
            }
        }

        setAnchor(nextAnchor);
        setMonth(nextMonth);

        router.get(window.location.pathname, rangeQuery(covering()), {
            only: ['schedules'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    };

    /*
     * `now` is held rather than read at draw time.
     *
     * The grid draws a line for the current time, and a fresh `new Date()` on
     * every render would move that line by however long the render took, which
     * reads as a flicker rather than as time passing. A minute's drift is well
     * inside what an item estimate is worth.
     */
    const [now] = useState(() => new Date());

    const step = (daysToMove: number) =>
        show({ anchor: addDays(anchor, daysToMove), month }, 'anchor');

    const goToToday = () => {
        const today = startOfDay(new Date());

        show({ anchor: today, month: today }, 'anchor');
    };

    const stepLabel = isWide ? 'week' : 'day';

    /*
     * The days the mini calendar puts a dot under.
     *
     * Keys rather than dates, because the dot only ever asks "is this day in
     * here" and a set of keys answers that without building a formatter call
     * for every cell of the month.
     */
    const bookedDays = useMemo(
        () => new Set(items.map((entry) => dayKey(entry.arrival))),
        [items],
    );

    const bookRoute = useBookRoute();

    /*
     * Whether this reader may book a visit at all.
     *
     * A permission of its own rather than a byproduct of being able to read the
     * schedule: a client books their own visit and marketing books one on a
     * client's behalf, while sales (which answers the visits assigned to it)
     * and the roles that only oversee the week are not offered a button that
     * leads nowhere.
     */
    const canBook = can(permissions.scheduleCreate);

    const bookButton = (variant: 'default' | 'outline' = 'default') => {
        if (!canBook || !bookRoute) {
            return null;
        }
        return (
            <Button asChild type="button" variant={variant}>
                <Link href={bookRoute.url()}>
                    <Plus aria-hidden />
                    Book an appointment
                </Link>
            </Button>
        );
    };

    /*
     * Changing a visit, from its dialog or the Upcoming menu. Update opens the
     * Book an appointment page filled in with it, from this reader's door;
     * Cancel and Decline ask for a reason here, Assign for the sales member;
     * Confirm, Mark as attended and Did not attend only ask to be sure. The server answers with the page's schedules
     * again, already changed.
     */
    const isClient = !can(permissions.scheduleView);
    const [cancelling, setCancelling] = useState<ScheduleItem | null>(null);
    const [declining, setDeclining] = useState<ScheduleItem | null>(null);
    const [assigning, setAssigning] = useState<ScheduleItem | null>(null);
    const [confirming, setConfirming] = useState<{
        action: 'confirm' | 'serve' | 'noShow';
        item: ScheduleItem;
    } | null>(null);
    const [isConfirming, setIsConfirming] = useState(false);

    const startAction = (action: ScheduleDialogAction, item: ScheduleItem) => {
        setSelected(null);

        if (action === 'update') {
            router.visit(
                isClient
                    ? profileScheduleEdit.url(item.id)
                    : scheduleEdit.url(item.id),
            );
        } else if (action === 'cancel') {
            setCancelling(item);
        } else if (action === 'decline') {
            setDeclining(item);
        } else if (action === 'assign') {
            setAssigning(item);
        } else {
            setConfirming({ action, item });
        }
    };

    const CONFIRMS = {
        confirm: {
            title: 'Confirm appointment',
            description: (item: ScheduleItem) =>
                `${item.title}${item.clientName ? ` with ${item.clientName}` : ''}. The client is told in their Regray chat that it is confirmed.`,
            label: 'Confirm',
            route: confirmSchedule,
            done: 'Appointment confirmed',
        },
        serve: {
            title: 'Mark as attended',
            description: (item: ScheduleItem) =>
                `${item.title}. The client came, so the appointment is served.`,
            label: 'Mark as attended',
            route: serveSchedule,
            done: 'Marked as served',
        },
        noShow: {
            title: 'Did not attend',
            description: (item: ScheduleItem) =>
                `${item.title}. The client did not come, so the appointment is cancelled with the reason "Did not attend".`,
            label: 'Did not attend',
            route: noShowSchedule,
            done: 'Marked as not attended',
        },
    } as const;

    const confirm = async () => {
        if (confirming === null) {
            return;
        }

        const step = CONFIRMS[confirming.action];

        setIsConfirming(true);
        const outcome = await sendScheduleChange(
            'post',
            step.route.url(confirming.item.id),
        );
        setIsConfirming(false);
        setConfirming(null);

        if (outcome.kind === 'saved') {
            toast.success(step.done);
        } else if (outcome.kind === 'invalid') {
            toast.error(
                outcome.errors.schedule ??
                    'This appointment could not be changed.',
            );
        } else {
            toast.error('This appointment could not be changed. Try again.');
        }
    };

    return (
        <div className="w-full min-w-0">
            <PageHeading
                title="Schedule"
                description="Your appointments"
                action={bookButton()}
            />

            {/*
                The week stretches to the panel's height beside it (grid items
                fill their row), so its bottom edge lines up with the panel's.

                One `minmax(0, 1fr)` column below `lg`, and `min-w-0` on every
                child: without them the column grows to the week's minimum
                width and pushes the page past the screen on a phone, when the
                week should scroll inside its own border instead.

                The order classes set the reading order. On a phone: upcoming
                events and the month first, then the range and its arrows right
                above the week they move. From `lg`: the range across the top,
                the week on the left and the panel on the right.
             */}
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="order-2 flex min-w-0 flex-col items-start justify-between gap-3 sm:flex-row sm:items-center sm:gap-4 lg:order-1 lg:col-span-2">
                    {/*
                        The label is the only thing that says where the arrows have
                        taken you, so it is announced when they move: stepping the
                        view is a change of place, and a screen reader user would
                        otherwise be told nothing happened.
                     */}
                    <div className="flex items-center gap-2">
                        <p
                            aria-live="polite"
                            className="text-sm font-bold tracking-[-0.02em]"
                        >
                            {rangeLabel(days)}
                        </p>

                        {isLoading ? (
                            <span
                                aria-hidden
                                className="text-xs text-secondary-foreground"
                            >
                                Loading…
                            </span>
                        ) : null}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={goToToday}
                        >
                            Today
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => step(-dayCount)}
                            aria-label={`Go to previous ${stepLabel}`}
                            className="h-11 w-11"
                        >
                            <ChevronLeft aria-hidden />
                        </Button>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={() => step(dayCount)}
                            aria-label={`Go to next ${stepLabel}`}
                            className="h-11 w-11"
                        >
                            <ChevronRight aria-hidden />
                        </Button>
                    </div>
                </div>

                <div
                    aria-busy={isLoading}
                    className={`order-3 min-w-0 transition-opacity lg:order-2 ${isLoading ? 'opacity-60' : ''}`}
                >
                    <WeekGrid
                        days={days}
                        items={items}
                        now={now}
                        onSelect={setSelected}
                    />
                </div>

                {/*
                    The panel. Beside the calendar only once there is room for
                    both, since seven days and a month side by side need the
                    width. Below `lg` it comes first instead, upcoming events
                    before the month, so a phone opens on what is coming next
                    and reaches the week after. The order classes put the panel
                    back on the right, month above the list, once there is room.
                 */}
                <aside className="order-1 flex min-w-0 flex-col gap-4 lg:order-3">
                    <div className="order-2 rounded-md border border-sidebar-border bg-background p-4 lg:order-1">
                        <MiniCalendar
                            month={month}
                            selectedDay={anchor}
                            visibleDays={days}
                            bookedDays={bookedDays}
                            onSelectDay={(day) =>
                                show(
                                    {
                                        anchor: startOfDay(day),
                                        month: startOfDay(day),
                                    },
                                    'anchor',
                                )
                            }
                            onChangeMonth={(nextMonth) =>
                                show({ anchor, month: nextMonth }, 'month')
                            }
                        />
                    </div>

                    <div className="order-1 lg:order-2">
                        <h3 className="mb-3 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                            Upcoming events
                        </h3>

                        <UpcomingList
                            items={upcoming}
                            onSelect={setSelected}
                            onAction={startAction}
                            emptyAction={bookButton('outline')}
                        />
                    </div>
                </aside>
            </div>

            <EventDialog
                item={selected}
                onClose={() => setSelected(null)}
                onAction={startAction}
            />

            <CancelScheduleDialog
                item={cancelling}
                onClose={() => setCancelling(null)}
                onSaved={() => {
                    setCancelling(null);
                    toast.success('Appointment cancelled');
                }}
            />

            <CancelScheduleDialog
                kind="decline"
                item={declining}
                onClose={() => setDeclining(null)}
                onSaved={() => {
                    setDeclining(null);
                    toast.success('Appointment declined');
                }}
            />

            <AssignScheduleDialog
                item={assigning}
                onClose={() => setAssigning(null)}
                onSaved={(sales) => {
                    setAssigning(null);
                    toast.success(`Assigned to ${sales.name}`);
                }}
            />

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => {
                    if (!open && !isConfirming) {
                        setConfirming(null);
                    }
                }}
                title={confirming ? CONFIRMS[confirming.action].title : ''}
                description={
                    confirming
                        ? CONFIRMS[confirming.action].description(
                              confirming.item,
                          )
                        : ''
                }
                confirmLabel={
                    confirming ? CONFIRMS[confirming.action].label : ''
                }
                cancelLabel="Not now"
                onConfirm={confirm}
                processing={isConfirming}
            />
        </div>
    );
};

export default Schedule;

import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Link, router, usePage } from '@inertiajs/react';
import EventDialog from '@/components/schedule/event-dialog';
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
     * schedule: a client books their own visit and the sales and marketing roles
     * book one on somebody's behalf, while the roles that only oversee the week
     * are not offered a button that leads nowhere.
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
                    Book a visit
                </Link>
            </Button>
        );
    };

    const reschedule = (item: ScheduleItem) =>
        toast.info(`Rescheduling ${item.title} is not available yet.`);

    const cancel = (item: ScheduleItem) =>
        toast.info(`Cancelling ${item.title} is not available yet.`);

    return (
        <div className="mx-auto w-full max-w-6xl">
            <PageHeading
                title="Schedule"
                description="Your showroom visits"
                action={bookButton()}
            />

            <div className="mb-3 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center sm:gap-4">
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

            {/*
                The week stretches to the panel's height beside it (grid items
                fill their row), so its bottom edge lines up with the panel's.
             */}
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div
                    aria-busy={isLoading}
                    className={`transition-opacity ${isLoading ? 'opacity-60' : ''}`}
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
                    width. Below `lg` it lands underneath instead, which is the
                    order a phone reads anyway: the week first, then what else
                    is coming.
                 */}
                <aside className="space-y-4">
                    <div className="rounded-md border border-sidebar-border bg-background p-4">
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

                    <div>
                        <h3 className="mb-3 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                            Upcoming events
                        </h3>

                        <UpcomingList
                            items={upcoming}
                            onSelect={setSelected}
                            onReschedule={reschedule}
                            onCancel={cancel}
                            emptyAction={bookButton('outline')}
                        />
                    </div>
                </aside>
            </div>

            <EventDialog item={selected} onClose={() => setSelected(null)} />
        </div>
    );
};

export default Schedule;

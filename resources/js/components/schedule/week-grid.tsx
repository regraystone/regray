import {
    SCHEDULE_STATUS_LABELS,
    isStruck,
    STATUS_BLOCK_STYLES,
} from '@/components/schedule/schedule-status-badge';
import type { ScheduleItem } from '@/data/schedule';
import {
    atHour,
    formatFullDate,
    formatHour,
    formatTime,
    formatWeekday,
    isSameDay,
    startOfDay,
} from '@/lib/date';
import { cn } from '@/lib/utils';

/**
 * The hours the showroom keeps.
 *
 * Ten until six: a visit is booked for a time the client can be there and the
 * studio can show them round, and a morning before ten is not either of those.
 */
const OPEN_HOUR = 10;
const CLOSE_HOUR = 18;

/** The least an hour row is ever drawn at, when it holds one event or none. */
const MIN_HOUR_HEIGHT = 56;

/** Every event is drawn at this height, however many share its hour. */
const EVENT_HEIGHT = 28;

/** Breathing room above and below an event inside its slot. */
const EVENT_GAP = 2;

const HOUR_COUNT = CLOSE_HOUR - OPEN_HOUR;

const HOURS = Array.from(
    { length: HOUR_COUNT },
    (_, index) => OPEN_HOUR + index,
);

/** Where an arrival sits, counted in hour slots from the top of the grid. */
const slotOf = (item: ScheduleItem): number =>
    item.arrival.getHours() - OPEN_HOUR;

/**
 * One block's place on the grid.
 *
 * `row` is its position in the stack within its hour: events are a fixed
 * height, so two visits in one hour are stacked one under the other and the
 * hour grows to hold them.
 */
type Placement = {
    item: ScheduleItem;
    slot: number;
    row: number;
};

/**
 * Where each of one day's blocks goes.
 *
 * Grouped by hour rather than by arrival, so visits expected at 10:00 and 10:45
 * are recognised as sharing a row. Within an hour they are ordered by arrival.
 */
const placeBlocks = (day: Date, items: ScheduleItem[]): Placement[] => {
    const byHour = new Map<number, ScheduleItem[]>();

    for (const item of items) {
        if (!isSameDay(item.arrival, day)) {
            continue;
        }

        const slot = slotOf(item);

        if (slot < 0 || slot >= HOUR_COUNT) {
            /*
             * Expected outside the hours on show.
             *
             * Left off the grid: there is no row to put it in, and
             * borrowing the nearest one would put a 07:00 arrival at the top of
             * the morning as though it were an 08:00 one. It is still listed and
             * still opens in the dialog, where the time is written out.
             */
            continue;
        }

        byHour.set(slot, [...(byHour.get(slot) ?? []), item]);
    }

    return [...byHour.entries()].flatMap(([slot, inHour]) =>
        [...inHour]
            .sort((a, b) => a.arrival.getTime() - b.arrival.getTime())
            .map((item, row) => ({ item, slot, row })),
    );
};

/**
 * One day over its column: the short name above the date.
 *
 * Today is marked three ways, because a week grid is scanned rather than read:
 * the filled circle is the strongest mark on the page, the heading names the day
 * out loud for anyone who cannot see it, and `aria-current` is what a screen
 * reader reads it back as.
 */
const DayHeading = ({ day, isToday }: { day: Date; isToday: boolean }) => (
    <div className="border-l border-sidebar-border px-2 py-3 text-center first:border-l-0">
        <p className="text-[0.65rem] font-bold tracking-[0.12em] text-secondary-foreground uppercase">
            {formatWeekday.format(day)}
        </p>

        {isToday ? (
            <>
                <span
                    aria-hidden
                    className="mx-auto mt-1 grid size-7 place-items-center rounded-md bg-primary text-sm font-bold text-primary-foreground tabular-nums"
                >
                    {day.getDate()}
                </span>
                <span className="sr-only">Today</span>
            </>
        ) : (
            <p className="mt-1 text-lg font-bold tabular-nums">
                {day.getDate()}
            </p>
        )}
    </div>
);

const ItemBlock = ({
    placement,
    rowTops,
    onSelect,
}: {
    placement: Placement;
    rowTops: number[];
    onSelect: (item: ScheduleItem) => void;
}) => {
    const { item, slot, row } = placement;
    const status = SCHEDULE_STATUS_LABELS[item.status].toLowerCase();

    /*
     * The status is in the accessible name rather than in the block, which has
     * room for a title and nothing else.
     *
     * Every status here is a weight or a tint rather than a hue, so a block is
     * already told apart by shape; spelling the word out is what keeps it told
     * apart for a screen reader and for anyone who cannot separate two greys.
     */
    const label = `${item.title}, ${formatTime.format(item.arrival)}, ${status}`;

    return (
        <button
            type="button"
            onClick={() => onSelect(item)}
            aria-label={label}
            title={label}
            style={{
                top: `${rowTops[slot] + row * EVENT_HEIGHT + EVENT_GAP / 2}px`,
                height: `${EVENT_HEIGHT - EVENT_GAP}px`,
                left: '2px',
                right: '2px',
            }}
            className={cn(
                'absolute z-10 flex items-center overflow-hidden px-2.5 text-left transition-opacity hover:opacity-85 focus-visible:z-20 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                STATUS_BLOCK_STYLES[item.status],
            )}
        >
            <span
                className={cn(
                    'block truncate text-[0.65rem] leading-tight font-bold sm:text-xs',
                    isStruck(item.status) && 'line-through',
                )}
            >
                {item.title}
            </span>
        </button>
    );
};

/**
 * The week, as a grid of hours.
 *
 * Hours down the left and days across the top. Events are a fixed height and
 * the hour rows give way to them: an hour is as tall as the busiest day's stack
 * in it (never less than `MIN_HOUR_HEIGHT`), so every column shares the same
 * row lines and nothing overlaps. The whole grid scrolls inside its own
 * container rather than the page.
 */
const WeekGrid = ({
    days,
    items,
    now,
    onSelect,
}: {
    days: Date[];
    items: ScheduleItem[];
    now: Date;
    onSelect: (item: ScheduleItem) => void;
}) => {
    /*
     * Placements are worked out once per day, up front, because the row heights
     * depend on every day at once: an hour is as tall as its fullest column.
     */
    const placementsByDay = days.map((day) => placeBlocks(day, items));

    const rowHeights = HOURS.map((_, slot) => {
        const busiest = Math.max(
            0,
            ...placementsByDay.map(
                (placements) =>
                    placements.filter((p) => p.slot === slot).length,
            ),
        );

        return Math.max(MIN_HOUR_HEIGHT, busiest * EVENT_HEIGHT);
    });

    /** The top edge of each hour row, in pixels from the top of the grid. */
    const rowTops = rowHeights.map((_, slot) =>
        rowHeights.slice(0, slot).reduce((sum, height) => sum + height, 0),
    );

    const gridHeight = rowHeights.reduce((sum, height) => sum + height, 0);

    /*
     * The line for right now.
     *
     * Only while the showroom is open and only on a week that has today in it.
     * It is placed inside whichever hour row it falls in, by how far through the
     * hour it is, so it stays right when that row has grown.
     */

    /*
     * A floor under the width, so a column is never narrower than a title can be
     * read in and the grid scrolls inside its own border rather than pushing the
     * page sideways. Worked out from the day count because the wide week and the
     * narrow one are not one layout at two sizes.
     */
    const minimumWidth = days.length > 5 ? '38rem' : '18rem';

    return (
        <div className="overflow-auto border border-sidebar-border bg-background">
            <div
                className="grid"
                style={{
                    gridTemplateColumns: `4rem repeat(${days.length}, minmax(0, 1fr))`,
                    minWidth: minimumWidth,
                }}
            >
                {/* Header row */}
                <div aria-hidden className="border-b border-sidebar-border" />
                {days.map((day) => (
                    <DayHeading
                        key={day.toISOString()}
                        day={day}
                        isToday={isSameDay(day, now)}
                    />
                ))}

                {/* Hour column */}
                <div className="relative" style={{ height: gridHeight }}>
                    {HOURS.map((hour, index) => (
                        <div
                            key={hour}
                            className="absolute right-2 pt-1 text-[0.65rem] font-bold text-secondary-foreground tabular-nums"
                            style={{ top: `${rowTops[index]}px` }}
                        >
                            {formatHour.format(atHour(startOfDay(now), hour))}
                        </div>
                    ))}
                </div>

                {/* Day columns */}
                {days.map((day, dayIndex) => {
                    const placements = placementsByDay[dayIndex];
                    const isToday = isSameDay(day, now);

                    return (
                        <div
                            key={day.toISOString()}
                            role="group"
                            aria-label={formatFullDate.format(day)}
                            className={cn(
                                'relative border-l border-sidebar-border',
                                isToday && 'bg-muted/40',
                            )}
                            style={{ height: gridHeight }}
                        >
                            {/* Hour lines, one per row, since the rows are no
                                longer all the same height. */}
                            {rowTops.map((top, slot) => (
                                <div
                                    key={slot}
                                    aria-hidden
                                    className="absolute inset-x-0 border-t border-border"
                                    style={{
                                        top: `${top}px`,
                                        height: `${rowHeights[slot]}px`,
                                    }}
                                />
                            ))}

                            {placements.map((placement) => (
                                <ItemBlock
                                    key={placement.item.id}
                                    placement={placement}
                                    rowTops={rowTops}
                                    onSelect={onSelect}
                                />
                            ))}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default WeekGrid;

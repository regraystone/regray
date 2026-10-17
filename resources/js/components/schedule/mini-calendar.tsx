import {
    dayKey,
    formatFullDate,
    formatWeekday,
    isSameDay,
    startOfDay,
} from '@/lib/date';
import { cn } from '@/lib/utils';
import { CalendarHeader } from './calendar-header';
import { DAYS_IN_WEEK, monthDays } from '@/lib/calendar';

/**
 * The month, one screen at a time.
 *
 * The dot under a day is the whole reason this is here: it answers "is there
 * anything on that day" without leaving the page, which is the question a
 * client has before they pick one. Today is the filled circle and the day the
 * week view is showing carries a ring, so the two are never confused for each
 * other.
 */
const MiniCalendar = ({
    month,
    selectedDay,
    visibleDays,
    bookedDays,
    onSelectDay,
    onChangeMonth,
}: {
    /** The month on show, which is not the month being looked at. */
    month: Date;
    /** The day the week grid is centred on, and so the one carrying a ring. */
    selectedDay: Date;
    /** Days currently showing in the big calendar (week) */
    visibleDays?: Date[];
    bookedDays: Set<string>;
    onSelectDay: (day: Date) => void;
    onChangeMonth: (month: Date) => void;
}) => {
    const today = startOfDay(new Date());
    const days = monthDays(month);

    /*
     * Stepping by month off the first, rather than off the day being shown.
     *
     * Adding a month to the 31st lands on the 2nd or 3rd of the next one, so
     * paging from a month viewed at its end would silently skip a month. From
     * the first there is no such day to fall foul of.
     */
    const changeMonth = (delta: number) =>
        onChangeMonth(
            new Date(month.getFullYear(), month.getMonth() + delta, 1),
        );

    return (
        <div>
            <CalendarHeader
                month={month}
                onPrevMonth={() => changeMonth(-1)}
                onNextMonth={() => changeMonth(1)}
            />

            {/*
                The weekday row, read off the first seven days so the columns
                are Monday to Sunday whatever the reader's locale would order
                them in.

                Two letters rather than one: a single letter gives M T W T F S S,
                which asks the reader to work out which T is Tuesday and which
                S is Saturday, in the one place on the page whose whole job is
                telling them which day is which.
             */}
            <div aria-hidden className="mb-1 grid grid-cols-7 gap-1">
                {days.slice(0, DAYS_IN_WEEK).map((day) => (
                    <span
                        key={day.toISOString()}
                        className="text-center text-[0.62rem] font-bold tracking-[0.04em] text-secondary-foreground uppercase"
                    >
                        {formatWeekday.format(day).slice(0, 2)}
                    </span>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-1">
                {days.map((day) => {
                    const isInMonth = day.getMonth() === month.getMonth();
                    const isToday = isSameDay(day, today);
                    const isSelected = isSameDay(day, selectedDay);
                    const hasVisit = bookedDays.has(dayKey(day));

                    return (
                        <button
                            key={day.toISOString()}
                            type="button"
                            onClick={() => onSelectDay(day)}
                            aria-label={`${formatFullDate.format(day)}${
                                hasVisit ? ', has visits' : ''
                            }`}
                            aria-current={isToday ? 'date' : undefined}
                            aria-pressed={isSelected}
                            className={cn(
                                'relative grid aspect-square h-full w-full place-items-center rounded-md text-xs tabular-nums transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                isToday
                                    ? 'bg-primary font-bold text-primary-foreground'
                                    : visibleDays?.some((d) =>
                                            isSameDay(d, day),
                                        )
                                      ? 'bg-muted font-bold'
                                      : isSelected
                                        ? 'font-bold ring-1 ring-ring'
                                        : isInMonth
                                          ? 'hover:bg-muted'
                                          : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {day.getDate()}

                            {/*
                             * The dot, placed below the number rather than
                             * beside it so a day that has something booked still
                             * reads as the same size as one that does not.
                             */}
                            {hasVisit ? (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute bottom-1 size-1 rounded-full',
                                        isToday
                                            ? 'bg-primary-foreground'
                                            : 'bg-foreground',
                                    )}
                                />
                            ) : null}
                        </button>
                    );
                })}
            </div>
        </div>
    );
};

export default MiniCalendar;

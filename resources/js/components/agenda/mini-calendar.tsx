import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    addDays,
    dayKey,
    formatFullDate,
    formatMonthYear,
    formatWeekday,
    isSameDay,
    startOfDay,
    startOfWeek,
} from '@/lib/date';
import { cn } from '@/lib/utils';

/** Six weeks, so every month fills the same box and it does not jump about. */
const WEEK_COUNT = 6;

const DAYS_IN_WEEK = 7;

/**
 * The days of `month`, padded out to whole weeks.
 *
 * Starts on the Monday on or before the first of the month, so the columns are
 * the same seven days the week grid uses and a date never changes its weekday
 * between the two views.
 */
const monthDays = (month: Date): Date[] => {
    const first = new Date(month.getFullYear(), month.getMonth(), 1);

    return Array.from({ length: WEEK_COUNT * DAYS_IN_WEEK }, (_, index) =>
        addDays(startOfWeek(first), index),
    );
};

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
            <div className="mb-3 flex items-center justify-between gap-2">
                <p className="text-sm font-bold tracking-[-0.02em]">
                    {formatMonthYear.format(month)}
                </p>

                <div className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => changeMonth(-1)}
                        aria-label="Previous month"
                        className="size-8 px-0"
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => changeMonth(1)}
                        aria-label="Next month"
                        className="size-8 px-0"
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>

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
                                'relative grid aspect-square place-items-center rounded-md text-xs tabular-nums transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none w-full h-full',
                                isToday
                                    ? 'bg-primary font-bold text-primary-foreground'
                                    : visibleDays?.some((d) => isSameDay(d, day))
                                      ? 'font-bold bg-muted'
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

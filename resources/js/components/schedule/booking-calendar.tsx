import {
    formatFullDate,
    formatWeekday,
    isSameDay,
    startOfDay,
} from '@/lib/date';
import { cn } from '@/lib/utils';
import { CalendarHeader } from './calendar-header';
import { DAYS_IN_WEEK, monthDays } from '@/lib/calendar';

const BookingCalendar = ({
    month,
    onChangeMonth,
    value,
    onChange,
    isDisabled,
    error,
    describedBy,
}: {
    month: Date;
    onChangeMonth: (month: Date) => void;
    value: Date | null;
    onChange: (day: Date) => void;
    isDisabled: (day: Date) => boolean;
    error?: string;
    describedBy?: string;
}) => {
    const today = startOfDay(new Date());
    const days = monthDays(month);

    const changeMonth = (delta: number) =>
        onChangeMonth(
            new Date(month.getFullYear(), month.getMonth() + delta, 1),
        );

    const prevMonth = new Date(month.getFullYear(), month.getMonth() - 1, 1);
    const lastOfPrev = new Date(
        prevMonth.getFullYear(),
        prevMonth.getMonth() + 1,
        0,
    );
    const disablePrevious = lastOfPrev < today;

    return (
        <div
            className="rounded-md border border-sidebar-border bg-background p-4 sm:p-6"
            aria-describedby={error ? describedBy : undefined}
        >
            <CalendarHeader
                month={month}
                onPrevMonth={() => changeMonth(-1)}
                onNextMonth={() => changeMonth(1)}
                disablePrevious={disablePrevious}
            />

            <div aria-hidden className="mb-1 grid grid-cols-7 gap-1">
                {days.slice(0, DAYS_IN_WEEK).map((day) => (
                    <span
                        key={day.toISOString()}
                        className="text-center text-[0.62rem] font-bold tracking-[0.04em] text-secondary-foreground uppercase sm:text-xs"
                    >
                        {formatWeekday.format(day).slice(0, 2)}
                    </span>
                ))}
            </div>

            <div className="grid grid-cols-7 gap-1">
                {days.map((day) => {
                    const isInMonth = day.getMonth() === month.getMonth();
                    const isToday = isSameDay(day, today);
                    const isSelected = value !== null && isSameDay(day, value);
                    const disabled = isDisabled(day);

                    if (!isInMonth) {
                        return (
                            <div
                                key={day.toISOString()}
                                aria-hidden
                                className="aspect-square h-full w-full"
                            />
                        );
                    }

                    return (
                        <button
                            key={day.toISOString()}
                            type="button"
                            onClick={() => onChange(day)}
                            disabled={disabled}
                            aria-label={formatFullDate.format(day)}
                            aria-pressed={isSelected}
                            className={cn(
                                'relative grid aspect-square h-full w-full place-items-center rounded-md text-sm tabular-nums transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:text-base',
                                disabled
                                    ? 'cursor-not-allowed text-muted-foreground opacity-60'
                                    : isToday && isSelected
                                      ? 'bg-primary font-bold text-primary-foreground ring-2 ring-ring'
                                      : isToday
                                        ? 'bg-primary/10 font-bold ring-2 ring-primary'
                                        : isSelected
                                          ? 'bg-primary font-bold text-primary-foreground'
                                          : 'hover:bg-muted',
                            )}
                        >
                            {day.getDate()}
                        </button>
                    );
                })}
            </div>
            {error ? (
                <p id={describedBy} className="mt-2 text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
};

export default BookingCalendar;

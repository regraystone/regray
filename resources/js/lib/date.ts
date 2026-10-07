/*
 * The date arithmetic the agenda page needs, on native Date.
 *
 * No calendar library is installed and none is wanted: a week grid and a month
 * grid are a handful of additions and a comparison, all of which say what they
 * do. Every helper works in the reader's own timezone, so a visit booked for
 * 10:00 is drawn at 10:00 wherever they open the page.
 */

/** Midnight on the day `date` falls on. */
export const startOfDay = (date: Date): Date =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate());

/** The same clock time, `days` later. */
export const addDays = (date: Date, days: number): Date =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

/** `day` itself, at `hour` past midnight. */
export const atHour = (day: Date, hour: number): Date =>
    new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour);

/**
 * Midnight on the Monday of `date`'s week.
 *
 * Monday rather than whatever the reader's locale opens a week on: the grid,
 * the mini calendar and the week label are all read Monday to Sunday, so the
 * week is pinned in one place here instead of being asked of Intl three times
 * in three places that would then have to agree.
 */
export const startOfWeek = (date: Date): Date =>
    addDays(startOfDay(date), -weekdayIndex(date));

/**
 * How far `date` sits past the Monday of its own week, Monday being 0.
 *
 * `getDay()` counts from Sunday, which is a different question from this one.
 */
export const weekdayIndex = (date: Date): number => (date.getDay() + 6) % 7;

export const isSameDay = (a: Date, b: Date): boolean =>
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate();

/** A day as `YYYY-MM-DD`, so calendar days can be compared as plain strings. */
export const dayKey = (date: Date): string =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');

const formatter = (options: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat(undefined, options);

/** `Mon`, `Tue`, and so on. */
export const formatWeekday = formatter({ weekday: 'short' });

/** `8 AM`, the label beside an hour on the grid. */
export const formatHour = formatter({ hour: 'numeric' });

/** `10:00 AM`, the time an arrival is written with. */
export const formatTime = formatter({ hour: 'numeric', minute: '2-digit' });

/** `September 2026`, above the mini calendar. */
export const formatMonthYear = formatter({ month: 'long', year: 'numeric' });

/** `Tuesday, September 29`, read out and written in full. */
export const formatFullDate = formatter({
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
});

/** `Sep 29`, one end of the week label. */
export const formatDayMonth = formatter({ month: 'short', day: 'numeric' });

/** `day` at the given hour and minute. */
export const atTime = (day: Date, hour: number, minute: number): Date =>
    new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute);

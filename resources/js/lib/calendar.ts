/** Six weeks, so every month fills the same box and it does not jump about. */
export const WEEK_COUNT = 6;

export const DAYS_IN_WEEK = 7;

/**
 * The days of `month`, padded out to whole weeks.
 *
 * Starts on the Monday on or before the first of the month, so the columns are
 * the same seven days the week grid uses and a date never changes its weekday
 * between the two views.
 */
export const monthDays = (month: Date): Date[] => {
    const first = new Date(month.getFullYear(), month.getMonth(), 1);

    return Array.from({ length: WEEK_COUNT * DAYS_IN_WEEK }, (_, index) =>
        addDays(startOfWeek(first), index),
    );
};

import { addDays, startOfWeek } from '@/lib/date';

/*
 * The Schedule page's data: what the server sends, the items the page draws,
 * the range it asks for, and the Upcoming panel's day groups. Pure, with no
 * imports, for tests/js.
 */

/** Where a visit has got to. */
export type ScheduleStatus =
    | 'pending'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

/** One visit as ScheduleResource sends it. */
export type ScheduleData = {
    id: number;
    title: string;
    /** ISO 8601, with an offset. */
    arrivalAt: string;
    status: ScheduleStatus;
    notes: string | null;
    /** Set when the visit was created by sending an inquiry. */
    inquiryReference: string | null;
};

/** One visit, as the grid, the list and the dialog read it. */
export type ScheduleItem = {
    id: number;
    title: string;
    /** The time the client needs to be there. */
    arrival: Date;
    status: ScheduleStatus;
    notes: string | null;
    inquiryReference: string | null;
};

/** The most days one request may cover. Mirrors `schedule.max_range_days`. */
export const MAX_RANGE_DAYS = 62;

const DAY_MS = 86_400_000;

export const toScheduleItem = (data: ScheduleData): ScheduleItem => ({
    id: data.id,
    title: data.title,
    arrival: new Date(data.arrivalAt),
    status: data.status,
    notes: data.notes,
    inquiryReference: data.inquiryReference,
});

export const toScheduleItems = (list: ScheduleData[]): ScheduleItem[] =>
    list.map(toScheduleItem);

/** Midnight on or before `from` up to, not including, midnight after `to`. */
export type ScheduleRange = { from: Date; to: Date };

const midnight = (date: Date): Date =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate());

const nextMidnight = (date: Date): Date =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate() + 1);

/**
 * The whole days that hold every given day: the week grid's columns and the
 * mini calendar's six weeks, so one request fills both.
 */
export const rangeCovering = (...dayLists: Date[][]): ScheduleRange => {
    const days = dayLists.flat();

    if (days.length === 0) {
        throw new Error('rangeCovering needs at least one day.');
    }

    const times = days.map((day) => day.getTime());

    return {
        from: midnight(new Date(Math.min(...times))),
        to: nextMidnight(new Date(Math.max(...times))),
    };
};

/** How many days a range covers, counting a partial day as a whole one. */
export const rangeDays = ({ from, to }: ScheduleRange): number =>
    Math.ceil((to.getTime() - from.getTime()) / DAY_MS);

export const fitsRange = (range: ScheduleRange): boolean =>
    rangeDays(range) <= MAX_RANGE_DAYS;

/** The `from` and `to` query values, as ISO 8601 instants. */
export const rangeQuery = ({
    from,
    to,
}: ScheduleRange): { from: string; to: string } => ({
    from: from.toISOString(),
    to: to.toISOString(),
});

/**
 * The hours the showroom keeps: ten until six. The week grid always shows at
 * least these. Same as BOOKING_OPEN_HOUR and BOOKING_CLOSE_HOUR in
 * lib/booking-slots.ts (not imported, to keep this file self-contained): a
 * booking always falls inside them, while an inquiry project may
 * be set for any time of day.
 */
export const SHOWROOM_OPEN_HOUR = 10;
export const SHOWROOM_CLOSE_HOUR = 18;

/** A day as `YYYY-MM-DD` in the reader's zone. */
const dayKey = (date: Date): string =>
    [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');

/**
 * The hour rows the week grid draws for these days.
 *
 * The showroom's hours, widened to take in any visit on show that falls
 * outside them, such as a site visit at 08:00. A visit must never be left off
 * the grid just because of its hour.
 */
export const gridHours = (items: ScheduleItem[], days: Date[]): number[] => {
    const shown = new Set(days.map(dayKey));
    let first = SHOWROOM_OPEN_HOUR;
    let last = SHOWROOM_CLOSE_HOUR;

    for (const item of items) {
        if (!shown.has(dayKey(item.arrival))) {
            continue;
        }

        const hour = item.arrival.getHours();
        first = Math.min(first, hour);
        last = Math.max(last, hour + 1);
    }

    return Array.from({ length: last - first }, (_, index) => first + index);
};

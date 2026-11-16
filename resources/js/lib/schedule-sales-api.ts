import { clash as clashRoute, sales as salesRoute } from '@/routes/schedule';

/**
 * The appointment of a sales member's that one at the asked time would
 * overlap (closer than `schedule.visit_minutes`). `time` is `HH:mm` in the
 * studio's zone. A warning: they may still be chosen.
 */
export type SalesClash = {
    arrivalAt: string;
    time: string;
    title: string;
};

/**
 * A sales member an appointment may be assigned to, as SalesPickerController
 * sends them. `openAppointmentCount` is how many appointments are still to
 * happen with them (awaiting them or confirmed): informational only. `clash`
 * is set only when a time was asked about.
 */
export type AssignableSales = {
    id: number;
    name: string;
    openAppointmentCount: number;
    clash: SalesClash | null;
};

/**
 * What the picker warns about: the time a new booking is for (`date`
 * `YYYY-MM-DD` and `time` `HH:mm`, the studio's), or the appointment being
 * assigned (`scheduleId`), whose own time the server reads and which never
 * clashes with itself.
 */
export type SalesPickerTime =
    | { date: string; time: string }
    | { scheduleId: number };

/** The sales members matching a search, by name. Marketing (and the admin) only. */
export const searchAssignableSales = async (
    search: string,
    at: SalesPickerTime | null = null,
): Promise<{ sales: AssignableSales[] }> => {
    const query: Record<string, string> = {};

    if (search.trim() !== '') {
        query.search = search;
    }

    if (at !== null && 'scheduleId' in at) {
        query.scheduleId = String(at.scheduleId);
    } else if (at !== null) {
        query.date = at.date;
        query.time = at.time;
    }

    const response = await fetch(salesRoute.url({ query }), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(
            `The sales members could not be loaded (${response.status}).`,
        );
    }

    return (await response.json()) as { sales: AssignableSales[] };
};

/**
 * For the sales member about to confirm an appointment: theirs it would
 * overlap, or null. A warning only.
 */
export const fetchOwnClash = async (
    scheduleId: number,
): Promise<SalesClash | null> => {
    const response = await fetch(clashRoute.url(scheduleId), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        return null;
    }

    return ((await response.json()) as { clash: SalesClash | null }).clash;
};

/** "Already has “Showroom visit” at 10:30 AM": the warning for a clash. */
export const clashWarning = (
    clash: SalesClash,
    label: (time: string) => string,
): string => `Already has “${clash.title}” at ${label(clash.time)}`;

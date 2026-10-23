/*
 * The times an appointment can be booked for, and which of them are still
 * ahead. Import-free, so tests/js can load it.
 *
 * Same hours and step as StoreBookingRequest::slots(), which refuses anything
 * else.
 */

export const BOOKING_OPEN_HOUR = 10;
export const BOOKING_CLOSE_HOUR = 18;
export const SLOT_MINUTES = 30;

/** The studio's date (`YYYY-MM-DD`) and time (`HH:mm`) now, as the server sends them. */
export type StudioNow = { date: string; time: string };

/** Every bookable arrival as `HH:mm`, from opening to closing inclusive. */
export const bookingSlots = (): string[] => {
    const slots: string[] = [];

    for (
        let minutes = BOOKING_OPEN_HOUR * 60;
        minutes <= BOOKING_CLOSE_HOUR * 60;
        minutes += SLOT_MINUTES
    ) {
        const hour = String(Math.floor(minutes / 60)).padStart(2, '0');
        const minute = String(minutes % 60).padStart(2, '0');
        slots.push(`${hour}:${minute}`);
    }

    return slots;
};

/** "13:30" reads as "1:30 PM". */
export const slotLabel = (slot: string): string => {
    const [hour, minute] = slot.split(':').map(Number);
    const twelveHour = hour % 12 === 0 ? 12 : hour % 12;

    return `${twelveHour}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
};

/**
 * The slots still ahead on `day` (`YYYY-MM-DD`) by the studio's clock: every
 * slot on a later day, none on an earlier one, and on today only those after
 * the current time.
 */
export const openSlotsOn = (
    day: string,
    now: StudioNow,
    slots: readonly string[] = bookingSlots(),
): string[] => {
    if (day > now.date) {
        return [...slots];
    }

    if (day < now.date) {
        return [];
    }

    return slots.filter((slot) => slot > now.time);
};

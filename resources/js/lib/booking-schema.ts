import { z } from 'zod';

export const BOOKING_OPEN_HOUR = 10;
export const BOOKING_CLOSE_HOUR = 18;
export const SLOT_MINUTES = 30;

export const bookingSlots = (): string[] => {
    const slots: string[] = [];
    for (let hour = BOOKING_OPEN_HOUR; hour <= BOOKING_CLOSE_HOUR; hour++) {
        for (let minute = 0; minute < 60; minute += SLOT_MINUTES) {
            if (hour === BOOKING_CLOSE_HOUR && minute > 0) {
                continue;
            }
            const h = hour % 12 === 0 ? 12 : hour % 12;
            const period = hour < 12 ? 'AM' : 'PM';
            const m = String(minute).padStart(2, '0');
            slots.push(`${h}:${m} ${period}`);
        }
    }
    return slots;
};

export const bookingSchema = z.object({
    date: z
        .date({ message: 'Choose a day' })
        .nullable()
        .refine((v) => v !== null, { message: 'Choose a day' }),
    arrivalTime: z.string().min(1, 'Choose a time'),
    purpose: z
        .string()
        .trim()
        .min(1, 'Purpose is required')
        .max(80, 'Purpose is too long'),
    notes: z
        .string()
        .trim()
        .max(500, 'Notes are too long')
        .optional()
        .or(z.literal('')),
});

// TODO: A real endpoint must repeat these rules in a FormRequest, because the
// time window cannot be trusted from the browser.

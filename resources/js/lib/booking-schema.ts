import { z } from 'zod';

/*
 * The Book an appointment form's rules. StoreBookingRequest repeats them on
 * the server, along with the time window (lib/booking-slots.ts), which the
 * browser cannot be trusted with.
 */
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

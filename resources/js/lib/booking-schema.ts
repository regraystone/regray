import { z } from 'zod';

/*
 * The appointment forms' rules, built from the same pieces: booking (a client
 * for themselves, or staff for a client they choose), updating and
 * cancelling. The server repeats them (BookingFieldRules, ScheduleReasonRules),
 * along with the time window (lib/booking-slots.ts), which the browser cannot be
 * trusted with.
 */

const arrivalTimeSchema = z.string().min(1, 'Choose a time');

const purposeSchema = z
    .string()
    .trim()
    .min(1, 'Purpose is required')
    .max(80, 'Purpose is too long');

const notesSchema = z
    .string()
    .trim()
    .max(500, 'Notes are too long')
    .optional()
    .or(z.literal(''));

/** Same as ScheduleReasonRules::REASON_MIN and REASON_MAX. */
export const REASON_MIN = 3;
export const REASON_MAX = 255;

/** Why an appointment is adjusted or cancelled: required, 3 to 255 characters. */
export const reasonSchema = (required: string) =>
    z
        .string()
        .trim()
        .min(1, required)
        .min(REASON_MIN, `Give a reason of at least ${REASON_MIN} characters`)
        .max(
            REASON_MAX,
            `Keep the reason to ${REASON_MAX} characters or fewer`,
        );

/** The day, time, purpose and notes, as the Book an appointment page asks them. */
export const bookingFieldsSchema = z.object({
    date: z
        .date({ message: 'Choose a day' })
        .nullable()
        .refine((v) => v !== null, { message: 'Choose a day' }),
    arrivalTime: arrivalTimeSchema,
    purpose: purposeSchema,
    notes: notesSchema,
});

/** A client booking for themselves. */
export const bookingSchema = bookingFieldsSchema;

/** Staff booking for a client they choose (StoreBookingRequest's `clientId`). */
export const staffBookingSchema = bookingFieldsSchema.extend({
    clientId: z
        .number()
        .nullable()
        .refine((v) => v !== null, {
            message: 'Choose the client this appointment is for',
        }),
});

/**
 * Update an appointment from the Book an appointment page: the same fields as
 * booking it, and the reason for the change.
 */
export const updateScheduleSchema = bookingFieldsSchema.extend({
    reason: reasonSchema('Say why the appointment is being updated'),
});

/** Cancel an appointment: why. */
export const cancelScheduleSchema = z.object({
    reason: reasonSchema('Say why the appointment is being cancelled'),
});

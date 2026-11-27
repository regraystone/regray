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

/** Same as StoreBookingRequest::ORDER_MESSAGE. */
export const ORDER_REQUIRED = 'Choose one of your accepted orders';

/**
 * A client booking from the Schedule page: a showroom visit asks its purpose,
 * an installation or a delivery the order it is for instead, as the order says
 * what it is (StoreBookingRequest's `kind` and `orderReference`).
 */
export const clientBookingSchema = bookingFieldsSchema
    .extend({
        kind: z.enum(['showroom_visit', 'installation', 'delivery']),
        orderReference: z.string(),
        purpose: z.string(),
    })
    .superRefine((values, context) => {
        if (values.kind === 'showroom_visit') {
            const purpose = purposeSchema.safeParse(values.purpose);

            if (!purpose.success) {
                context.addIssue({
                    code: 'custom',
                    path: ['purpose'],
                    message: purpose.error.issues[0].message,
                });
            }

            return;
        }

        if (values.orderReference.trim() === '') {
            context.addIssue({
                code: 'custom',
                path: ['orderReference'],
                message: ORDER_REQUIRED,
            });
        }
    });

/** Same as AssignScheduleRequest::SALES_MESSAGE. */
export const SALES_REQUIRED =
    'Choose a sales member to confirm this appointment';

/**
 * Marketing booking for a client it chooses, assigned to a sales member who
 * confirms it (StoreBookingRequest's `clientId` and `salesId`).
 */
export const staffBookingSchema = bookingFieldsSchema.extend({
    clientId: z
        .number()
        .nullable()
        .refine((v) => v !== null, {
            message: 'Choose the client this appointment is for',
        }),
    salesId: z
        .number()
        .nullable()
        .refine((v) => v !== null, { message: SALES_REQUIRED }),
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

/** Decline an appointment assigned to you: why (DeclineScheduleRequest). */
export const declineScheduleSchema = z.object({
    reason: reasonSchema('Say why you are declining the appointment'),
});

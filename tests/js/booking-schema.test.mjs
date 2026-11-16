/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    cancelScheduleSchema,
    staffBookingSchema,
    updateScheduleSchema,
} from '../../resources/js/lib/booking-schema.ts';

/** The first message the schema gives for `value`, or null when it passes. */
const firstError = (schema, value) => {
    const parsed = schema.safeParse(value);

    return parsed.success ? null : parsed.error.issues[0].message;
};

const update = (overrides = {}) => ({
    date: new Date(2026, 9, 17),
    arrivalTime: '14:00',
    purpose: 'Showroom visit',
    notes: '',
    reason: 'The client asked for later',
    ...overrides,
});

describe('the reason for a change', () => {
    it('is required, 3 to 255 characters once trimmed, as ScheduleReasonRules says', () => {
        assert.equal(
            firstError(cancelScheduleSchema, { reason: '   ' }),
            'Say why the appointment is being cancelled',
        );
        assert.equal(
            firstError(cancelScheduleSchema, { reason: 'no' }),
            'Give a reason of at least 3 characters',
        );
        assert.equal(
            firstError(cancelScheduleSchema, { reason: 'a'.repeat(256) }),
            'Keep the reason to 255 characters or fewer',
        );
        assert.equal(
            firstError(cancelScheduleSchema, { reason: 'a'.repeat(255) }),
            null,
        );
    });

    it("is asked when updating, with the booking page's fields", () => {
        assert.equal(firstError(updateScheduleSchema, update()), null);
        assert.equal(
            firstError(updateScheduleSchema, update({ reason: '' })),
            'Say why the appointment is being updated',
        );
        assert.equal(
            firstError(updateScheduleSchema, update({ arrivalTime: '' })),
            'Choose a time',
        );
        assert.equal(
            firstError(updateScheduleSchema, update({ date: null })),
            'Choose a day',
        );
    });
});

describe('a staff booking', () => {
    it('needs the client it is for', () => {
        const booking = {
            date: new Date(2026, 9, 17),
            arrivalTime: '14:00',
            purpose: 'Showroom visit',
            notes: '',
        };

        assert.equal(
            firstError(staffBookingSchema, {
                ...booking,
                clientId: null,
                salesId: 4,
            }),
            'Choose the client this appointment is for',
        );
        assert.equal(
            firstError(staffBookingSchema, {
                ...booking,
                clientId: 12,
                salesId: 4,
            }),
            null,
        );
    });

    it('needs the sales member who confirms it', () => {
        const booking = {
            date: new Date(2026, 9, 17),
            arrivalTime: '14:00',
            purpose: 'Showroom visit',
            notes: '',
            clientId: 12,
        };

        assert.equal(
            firstError(staffBookingSchema, { ...booking, salesId: null }),
            'Choose a sales member to confirm this appointment',
        );
    });
});

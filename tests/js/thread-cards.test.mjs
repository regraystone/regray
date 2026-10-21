/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    bookedByText,
    inquiryHeadline,
    inquirySubline,
    scheduleHeadline,
    serviceCountText,
} from '../../resources/js/lib/thread-cards.ts';

describe('inquiry card', () => {
    it('announces the status it was posted for', () => {
        assert.equal(inquiryHeadline('quoted'), 'Inquiry quoted');
        assert.equal(inquiryHeadline('cancelled'), 'Inquiry cancelled');
    });

    it('calls the card a newly sent inquiry posts "New Inquiry"', () => {
        assert.equal(inquiryHeadline('pending'), 'New Inquiry');
    });

    it('counts projects and services in the singular and the plural', () => {
        assert.equal(
            inquirySubline(1, 'INQ-1048'),
            '1 project · Inquiry No: INQ-1048',
        );
        assert.equal(
            inquirySubline(2, 'INQ-1048'),
            '2 projects · Inquiry No: INQ-1048',
        );
        assert.equal(serviceCountText(0), '0 services');
        assert.equal(serviceCountText(1), '1 service');
    });
});

describe('schedule card', () => {
    it('says a confirmed client booking was approved, and a staff one was booked', () => {
        assert.equal(
            scheduleHeadline('confirmed', 'client'),
            'Booking approved',
        );
        assert.equal(scheduleHeadline('confirmed', 'staff'), 'Visit booked');
    });

    it('says the same for every other status whoever booked it', () => {
        for (const [status, headline] of [
            ['requested', 'Booking requested'],
            ['completed', 'Visit completed'],
            ['cancelled', 'Booking cancelled'],
        ]) {
            assert.equal(scheduleHeadline(status, 'client'), headline);
            assert.equal(scheduleHeadline(status, 'staff'), headline);
        }
    });

    it('names the staff member who booked, and only the role for a client', () => {
        assert.equal(bookedByText('staff', 'Sam'), 'Booked by Sam');
        assert.equal(bookedByText('staff', null), 'Booked by staff');
        assert.equal(bookedByText('client', 'Sam'), 'Booked by client');
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    INQUIRY_HEADLINES,
    bookedByText,
    inquiryHeadline,
    scheduleHeadline,
} from '../../resources/js/lib/thread-cards.ts';
import { INQUIRY_STATUS_LABELS } from '../../resources/js/lib/inquiry-statuses.ts';

describe('inquiry card', () => {
    it('announces the display status it was posted for', () => {
        assert.equal(inquiryHeadline('quoted'), 'Quotation sent');
        assert.equal(inquiryHeadline('won'), 'Quotation approved');
        assert.equal(inquiryHeadline('production'), 'In production');
        assert.equal(inquiryHeadline('closed'), 'Inquiry closed');
        assert.equal(inquiryHeadline('cancelled'), 'Inquiry cancelled');
    });

    it('calls the card a newly sent inquiry posts "New Inquiry"', () => {
        assert.equal(inquiryHeadline('pending'), 'New Inquiry');
    });

    // Plus refunded, which cards posted before a full refund cancelled the inquiry announce.
    it('has a headline for every display status and nothing else', () => {
        assert.deepEqual(
            Object.keys(INQUIRY_HEADLINES).sort(),
            [...Object.keys(INQUIRY_STATUS_LABELS), 'refunded'].sort(),
        );
    });
});

describe('schedule card', () => {
    it('says a confirmed client booking was approved, and a staff one was booked', () => {
        assert.equal(
            scheduleHeadline('confirmed', 'client'),
            'Booking approved',
        );
        assert.equal(
            scheduleHeadline('confirmed', 'staff'),
            'Appointment booked',
        );
    });

    it('says the same for every other status whoever booked it', () => {
        for (const [status, headline] of [
            ['pending', 'Booking pending'],
            ['completed', 'Appointment completed'],
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

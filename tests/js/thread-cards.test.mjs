/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    INQUIRY_HEADLINES,
    bookedByText,
    extraStoneCount,
    inquiryHeadline,
    materialItemsLabel,
    quotationSubline,
    scheduleHeadline,
} from '../../resources/js/lib/thread-cards.ts';
import { formatFileSize } from '../../resources/js/lib/message-attachments.ts';
import { INQUIRY_STATUS_LABELS } from '../../resources/js/lib/inquiry-statuses.ts';

describe('inquiry card', () => {
    it('announces the display status it was posted for', () => {
        assert.equal(inquiryHeadline('quoted'), 'Quotation sent');
        assert.equal(inquiryHeadline('won'), 'Quotation approved');
        assert.equal(inquiryHeadline('production'), 'In production');
        assert.equal(inquiryHeadline('closed'), 'Inquiry closed');
        assert.equal(inquiryHeadline('cancelled'), 'Inquiry cancelled');
    });

    it('announces the approval when sales won shows the inquiry as quoted', () => {
        assert.equal(inquiryHeadline('quoted', 'won'), 'Quotation approved');
        assert.equal(inquiryHeadline('quoted', 'quoted'), 'Quotation sent');
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
            ['rescheduled', 'Appointment rescheduled'],
            ['served', 'Appointment served'],
            ['cancelled', 'Booking cancelled'],
            ['expired', 'Booking expired'],
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

describe('inquiry card details', () => {
    it('counts the Materials row in items, singular for one', () => {
        assert.equal(materialItemsLabel(1), '1 item');
        assert.equal(materialItemsLabel(3), '3 items');
        assert.equal(materialItemsLabel(12), '12 items');
    });

    it('counts the stones past the pictures it draws as "+N", never below 0', () => {
        assert.equal(extraStoneCount(5, 3), 2);
        assert.equal(extraStoneCount(4, 3), 1);
        assert.equal(extraStoneCount(3, 3), 0);
        assert.equal(extraStoneCount(2, 2), 0);
        assert.equal(extraStoneCount(0, 0), 0);
        // A stale count cannot read as a negative "+N".
        assert.equal(extraStoneCount(1, 3), 0);
    });
});

describe('quotation card', () => {
    it('leads the sub-line with the material count, singular for one', () => {
        assert.equal(
            quotationSubline(3, 'Kitchen renovation'),
            '3 materials · Kitchen renovation',
        );
        assert.equal(
            quotationSubline(1, 'Kitchen renovation'),
            '1 material · Kitchen renovation',
        );
    });

    it('shows only the inquiry name for a service-only inquiry', () => {
        assert.equal(
            quotationSubline(0, 'Countertop repair'),
            'Countertop repair',
        );
    });

    it('gives the file size in KB or MB', () => {
        assert.equal(formatFileSize(640 * 1024), '640 KB');
        assert.equal(formatFileSize(Math.round(2.8 * 1024 * 1024)), '2.8 MB');
        assert.equal(formatFileSize(1024 * 1024), '1.0 MB');
        assert.equal(formatFileSize(900), '900 B');
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    bookingSlots,
    openSlotsOn,
    slotLabel,
} from '../../resources/js/lib/booking-slots.ts';

describe('bookingSlots', () => {
    it('runs from 10:00 to 18:00 inclusive, every 30 minutes', () => {
        const slots = bookingSlots();

        assert.equal(slots[0], '10:00');
        assert.equal(slots[1], '10:30');
        assert.equal(slots.at(-1), '18:00');
        assert.equal(slots.length, 17);
    });
});

describe('slotLabel', () => {
    it('reads a 24-hour slot as a 12-hour time', () => {
        assert.equal(slotLabel('10:00'), '10:00 AM');
        assert.equal(slotLabel('12:30'), '12:30 PM');
        assert.equal(slotLabel('18:00'), '6:00 PM');
    });
});

describe('openSlotsOn', () => {
    const now = { date: '2026-10-14', time: '13:05' };

    it('offers every slot on a later day and none on an earlier one', () => {
        assert.equal(openSlotsOn('2026-10-15', now).length, 17);
        assert.deepEqual(openSlotsOn('2026-10-13', now), []);
    });

    it('offers only the slots after the studio time today', () => {
        assert.equal(openSlotsOn('2026-10-14', now)[0], '13:30');
    });

    it('offers nothing today once the last slot has gone', () => {
        assert.deepEqual(
            openSlotsOn('2026-10-14', { date: '2026-10-14', time: '18:00' }),
            [],
        );
    });

    it('offers nothing before the earliest bookable day', () => {
        assert.deepEqual(openSlotsOn('2026-10-14', now, '2026-10-15'), []);
        assert.deepEqual(openSlotsOn('2026-10-13', now, '2026-10-15'), []);
    });

    it('offers every slot from the earliest bookable day on', () => {
        assert.equal(openSlotsOn('2026-10-15', now, '2026-10-15').length, 17);
        assert.equal(openSlotsOn('2026-10-16', now, '2026-10-15').length, 17);
    });

    it('still offers only the slots ahead today when today is the earliest day', () => {
        assert.equal(openSlotsOn('2026-10-14', now, '2026-10-14')[0], '13:30');
        assert.deepEqual(openSlotsOn('2026-10-13', now, '2026-10-14'), []);
    });
});

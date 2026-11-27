/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    MAX_RANGE_DAYS,
    fitsRange,
    gridHours,
    rangeCovering,
    rangeDays,
    rangeQuery,
    toScheduleItem,
} from '../../resources/js/lib/schedule.ts';

const at = (year, month, day, hour = 0, minute = 0) =>
    new Date(year, month - 1, day, hour, minute);

const item = (id, arrival) => ({
    id,
    title: `Visit ${id}`,
    arrival,
    status: 'pending',
    notes: null,
    inquiryReference: null,
});

const can = {
    update: true,
    cancel: true,
    assign: true,
    confirm: false,
    decline: false,
    serve: false,
    noShow: false,
};
const rest = {
    kind: 'delivery',
    orderReference: 'INQ-2',
    bookedByType: 'staff',
    bookedByName: 'Sam',
    clientName: 'Rafa',
    walkIn: {
        contacts: [{ kind: 'phone', value: '+639171234567' }],
    },
    assignedSalesName: 'Sales Officer',
    declinedByName: null,
    declineReason: null,
    cancelReason: null,
    changeReason: 'Client asked for later',
    can,
};

describe('toScheduleItem', () => {
    it('turns arrivalAt into a Date and keeps the rest', () => {
        const mapped = toScheduleItem({
            id: 7,
            title: 'Kitchen',
            arrivalAt: '2026-10-15T02:00:00+00:00',
            status: 'confirmed',
            notes: 'Inquiry INQ-1 · Kitchen · Makati',
            inquiryReference: 'INQ-1',
            ...rest,
        });

        assert.equal(mapped.arrival.getTime(), Date.UTC(2026, 9, 15, 2, 0));
        assert.deepEqual(
            { ...mapped, arrival: undefined },
            {
                id: 7,
                title: 'Kitchen',
                arrival: undefined,
                status: 'confirmed',
                notes: 'Inquiry INQ-1 · Kitchen · Makati',
                inquiryReference: 'INQ-1',
                ...rest,
            },
        );
    });
});

describe('rangeCovering', () => {
    it('runs from midnight of the earliest day to midnight after the latest', () => {
        const range = rangeCovering(
            [at(2026, 10, 12, 9), at(2026, 10, 18, 17)],
            [at(2026, 9, 28), at(2026, 11, 8)],
        );

        assert.deepEqual(range, {
            from: at(2026, 9, 28),
            to: at(2026, 11, 9),
        });
        assert.equal(rangeDays(range), 42);
    });

    it('refuses no days at all', () => {
        assert.throws(() => rangeCovering([], []));
    });
});

describe('fitsRange', () => {
    it(`allows ${MAX_RANGE_DAYS} days and no more`, () => {
        const from = at(2026, 10, 1);

        assert.equal(
            fitsRange({ from, to: at(2026, 10, 1 + MAX_RANGE_DAYS) }),
            true,
        );
        assert.equal(
            fitsRange({ from, to: at(2026, 10, 2 + MAX_RANGE_DAYS) }),
            false,
        );
    });
});

describe('rangeQuery', () => {
    it('sends both ends as ISO instants', () => {
        const from = at(2026, 10, 1);
        const to = at(2026, 10, 8);

        assert.deepEqual(rangeQuery({ from, to }), {
            from: from.toISOString(),
            to: to.toISOString(),
        });
    });
});

describe('gridHours', () => {
    const week = [at(2026, 10, 26), at(2026, 10, 27), at(2026, 10, 28)];

    it('is the showroom hours, ten until six, when every visit falls inside them', () => {
        assert.deepEqual(
            gridHours([item(1, at(2026, 10, 27, 14))], week),
            [10, 11, 12, 13, 14, 15, 16, 17],
        );
    });

    it('widens to take in a visit outside the showroom hours, so none is left off', () => {
        const hours = gridHours(
            [item(1, at(2026, 10, 27, 9)), item(2, at(2026, 10, 28, 19, 30))],
            week,
        );

        assert.equal(hours[0], 9);
        assert.equal(hours[hours.length - 1], 19);
        assert.equal(hours.length, 11);
    });

    it('ignores visits on days that are not on show', () => {
        assert.equal(gridHours([item(1, at(2026, 11, 2, 7))], week)[0], 10);
    });
});

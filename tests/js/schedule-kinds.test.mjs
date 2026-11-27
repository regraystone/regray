/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { clientBookingSchema } from '../../resources/js/lib/booking-schema.ts';
import {
    isForOrder,
    kindLine,
    ordersFor,
} from '../../resources/js/lib/schedule-kinds.ts';

/** Every message the schema gives for `value`, keyed by field. */
const errors = (value) => {
    const parsed = clientBookingSchema.safeParse(value);

    return parsed.success
        ? {}
        : Object.fromEntries(
              parsed.error.issues.map(({ path, message }) => [
                  path.join('.'),
                  message,
              ]),
          );
};

const booking = (overrides = {}) => ({
    kind: 'showroom_visit',
    orderReference: '',
    date: new Date(2026, 9, 15),
    arrivalTime: '10:00',
    purpose: '',
    notes: '',
    ...overrides,
});

const orders = [
    {
        reference: 'INQ-1',
        name: 'Kitchen',
        service: null,
        kinds: ['installation'],
    },
    { reference: 'INQ-2', name: 'Slabs', service: null, kinds: ['delivery'] },
    {
        reference: 'INQ-3',
        name: 'Both',
        service: null,
        kinds: ['installation', 'delivery'],
    },
];

describe('a client booking', () => {
    it('asks a showroom visit its purpose, and not an order', () => {
        assert.deepEqual(errors(booking()), { purpose: 'Purpose is required' });
        assert.deepEqual(errors(booking({ purpose: 'See the marble' })), {});
    });

    it('asks an installation or a delivery its order, and not a purpose', () => {
        assert.deepEqual(errors(booking({ kind: 'installation' })), {
            orderReference: 'Choose one of your accepted orders',
        });
        assert.deepEqual(
            errors(booking({ kind: 'delivery', orderReference: 'INQ-2' })),
            {},
        );
    });
});

describe('the orders a kind is booked for', () => {
    it('lists those that may still take it, and none for a showroom visit', () => {
        assert.deepEqual(
            ordersFor(orders, 'installation').map(({ reference }) => reference),
            ['INQ-1', 'INQ-3'],
        );
        assert.deepEqual(
            ordersFor(orders, 'delivery').map(({ reference }) => reference),
            ['INQ-2', 'INQ-3'],
        );
        assert.deepEqual(ordersFor(orders, 'showroom_visit'), []);
    });

    it('names a kind with its order', () => {
        assert.equal(isForOrder('installation'), true);
        assert.equal(isForOrder('showroom_visit'), false);
        assert.equal(isForOrder(null), false);
        assert.equal(kindLine('delivery', 'INQ-2'), 'Delivery for INQ-2');
        assert.equal(kindLine('showroom_visit', null), 'Showroom visit');
        assert.equal(kindLine(null, 'INQ-2'), null);
    });
});

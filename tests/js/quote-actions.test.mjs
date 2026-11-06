/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    CARD_BUTTON_ORDER,
    buildCardActions,
} from '../../resources/js/lib/quote-actions.ts';

/*
 * `actions` exactly as `ClientInquiryResource` sends them for each status
 * (see App\Policies\InquiryActions). A hidden action is `visible: false`; a shown
 * and locked one carries its reason.
 */
const hidden = { visible: false, enabled: false };
const on = { visible: true, enabled: true };
const QUOTED_REASON =
    'Locked once you approved the quotation. Message the team to request changes.';
const CANCELLED_REASON = 'This inquiry was cancelled.';

const fromServer = {
    pending: { view: on, update: on, cancel: on, invoice: hidden },
    quoted: {
        view: on,
        update: { visible: true, enabled: false, reason: QUOTED_REASON },
        cancel: on,
        invoice: hidden,
    },
    paid: { view: on, update: hidden, cancel: hidden, invoice: on },
    completed: { view: on, update: hidden, cancel: hidden, invoice: on },
    cancelled: {
        view: on,
        update: { visible: true, enabled: false, reason: CANCELLED_REASON },
        cancel: hidden,
        invoice: hidden,
    },
};

/** The product's table, written out button by button. */
const table = {
    pending: [
        {
            key: 'view',
            label: 'View details',
            variant: 'default',
        },
        { key: 'update', label: 'Update', variant: 'outline' },
        { key: 'cancel', label: 'Cancel', variant: 'outline' },
    ],
    quoted: [
        {
            key: 'view',
            label: 'View details',
            variant: 'default',
        },
        { key: 'cancel', label: 'Cancel', variant: 'outline' },
    ],
    paid: [
        {
            key: 'view',
            label: 'View details',
            variant: 'default',
        },
        {
            key: 'invoice',
            label: 'Get invoice',
            variant: 'outline',
        },
    ],
    completed: [
        {
            key: 'view',
            label: 'View details',
            variant: 'default',
        },
        {
            key: 'invoice',
            label: 'Get invoice',
            variant: 'outline',
        },
    ],
    cancelled: [
        {
            key: 'view',
            label: 'View details',
            variant: 'default',
        },
    ],
};

describe('buildCardActions', () => {
    for (const status of Object.keys(table)) {
        it(`gives a ${status} card exactly its buttons, in order`, () => {
            assert.deepEqual(
                buildCardActions(fromServer[status]),
                table[status],
            );
        });
    }

    it('draws View details, Update, Cancel, Get invoice in that order', () => {
        assert.deepEqual(
            [...CARD_BUTTON_ORDER],
            ['view', 'update', 'cancel', 'invoice'],
        );
    });

    it('does not render an action the server did not show', () => {
        const keys = (status) =>
            buildCardActions(fromServer[status]).map((button) => button.key);

        assert.deepEqual(keys('paid'), ['view', 'invoice']);
        assert.equal(keys('paid').includes('update'), false);
        assert.equal(keys('paid').includes('cancel'), false);
        assert.equal(keys('cancelled').includes('cancel'), false);
        assert.equal(keys('cancelled').includes('invoice'), false);
        assert.equal(keys('pending').includes('invoice'), false);
    });

    it('does not render an action that is shown but locked', () => {
        const keys = (status) =>
            buildCardActions(fromServer[status]).map((button) => button.key);

        assert.equal(keys('quoted').includes('update'), false);
        assert.equal(keys('cancelled').includes('update'), false);
        assert.deepEqual(
            buildCardActions({
                ...fromServer.pending,
                cancel: {
                    visible: true,
                    enabled: false,
                    reason: 'Ask the team.',
                },
            }).map((button) => button.key),
            ['view', 'update'],
        );
    });

    it('trusts the server over the status when they disagree', () => {
        // A cancelled inquiry the server says can still be cancelled: shown.
        const buttons = buildCardActions({
            ...fromServer.cancelled,
            cancel: on,
        });

        assert.deepEqual(
            buttons.map((b) => b.key),
            ['view', 'cancel'],
        );
    });
});

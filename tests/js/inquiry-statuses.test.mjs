/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    activityLabel,
    documentGroupLabel,
    startedStatuses,
    statusLabel,
    statusTone,
    statusTreatment,
} from '../../resources/js/lib/inquiry-statuses.ts';
import {
    isLeadOpenForDecision,
    LEAD_STATUSES,
    leadStatusLabel,
} from '../../resources/js/lib/lead-statuses.ts';

describe('statusTreatment', () => {
    it('draws a positive or reached state solid', () => {
        for (const status of [
            'won',
            'quoted',
            'closed',
            'fully_paid',
            'measure',
            'production',
            'installation',
            'completed',
        ]) {
            assert.equal(statusTreatment(status), 'solid', status);
        }
    });

    it('outlines a state that is still open', () => {
        for (const status of [
            'pending',
            'negotiation',
            'order_received',
            'awaiting',
        ]) {
            assert.equal(statusTreatment(status), 'outline', status);
        }
    });

    it('mutes a state that stopped', () => {
        for (const status of [
            'cancelled',
            'rejected',
            'lost',
            'on_hold',
            'delayed',
        ]) {
            assert.equal(statusTreatment(status), 'secondary', status);
        }
    });
});

describe('statusLabel', () => {
    it('reads a value in its own group', () => {
        assert.equal(statusLabel('inquiry', 'quality_check'), 'Quality check');
        assert.equal(statusLabel('sales', 'on_hold'), 'On hold');
        assert.equal(statusLabel('payment', 'fully_paid'), 'Fully paid');
        assert.equal(statusLabel('process', 'completed'), 'Completed');
    });
});

describe('startedStatuses', () => {
    it('leaves out a null payment, sales or logistics status, so it renders no badge', () => {
        assert.deepEqual(
            startedStatuses([
                { group: 'inquiry', status: 'pending' },
                { group: 'sales', status: null },
                { group: 'process', status: null },
                { group: 'payment', status: null },
            ]),
            [{ group: 'inquiry', status: 'pending' }],
        );
    });

    it('keeps every status once its stage has started, in the order given', () => {
        const entries = [
            { group: 'inquiry', status: 'production' },
            { group: 'sales', status: 'quoted' },
            { group: 'process', status: 'production' },
            { group: 'payment', status: 'partial' },
        ];

        assert.deepEqual(startedStatuses(entries), entries);
    });

    it('never puts a placeholder such as pending in place of a stage not started', () => {
        const shown = startedStatuses([
            { group: 'sales', status: null },
            { group: 'payment', status: null },
        ]);

        assert.equal(shown.length, 0);
    });
});

describe('activityLabel', () => {
    it("says what happened in the client's words, without naming a team", () => {
        assert.equal(
            activityLabel({ track: 'sales', status: 'negotiation' }),
            'In discussion',
        );
        assert.equal(
            activityLabel({ track: 'sales', status: 'quoted' }),
            'Quotation sent',
        );
        assert.equal(
            activityLabel({ track: 'sales', status: 'won' }),
            'Quote approved',
        );
        assert.equal(
            activityLabel({ track: 'payment', status: 'partial' }),
            'Partially paid',
        );
        assert.equal(
            activityLabel({ track: 'process', status: 'production' }),
            'In production',
        );
        assert.equal(
            activityLabel({ track: 'sales', status: 'rejected' }),
            'Cancelled',
        );
        // An inquiry cancelled with its lost lead: the client hears only that.
        assert.equal(
            activityLabel({ track: 'inquiry', status: 'cancelled' }),
            'Cancelled',
        );
    });
});

describe('documentGroupLabel', () => {
    it('names a quotation by its revision and a receipt as a payment receipt', () => {
        assert.equal(documentGroupLabel('quoted', 2), 'Quotation · Rev 2');
        assert.equal(documentGroupLabel('awaiting', null), 'Billing statement');
        assert.equal(documentGroupLabel('partial', null), 'Partial payment');
        assert.equal(documentGroupLabel('fully_paid', null), 'Full payment');
        assert.equal(documentGroupLabel(null, null), 'Documents');
    });
});

describe('lead statuses', () => {
    it('labels each lead status and lists the Leads tabs in order', () => {
        assert.deepEqual(LEAD_STATUSES, ['idle', 'won', 'lost']);
        assert.equal(leadStatusLabel('idle'), 'Idle');
        assert.equal(leadStatusLabel('won'), 'Won');
        assert.equal(leadStatusLabel('lost'), 'Lost');
    });

    it('draws a lead badge like the inquiry badges: won solid, idle outlined, lost stopped', () => {
        assert.equal(statusTreatment('won'), 'solid');
        assert.equal(statusTreatment('idle'), 'outline');
        assert.equal(statusTreatment('lost'), 'secondary');
    });

    it('lets marketing decide an idle lead only', () => {
        assert.equal(isLeadOpenForDecision('idle'), true);
        assert.equal(isLeadOpenForDecision('won'), false);
        assert.equal(isLeadOpenForDecision('lost'), false);
    });
});

describe('statusTone', () => {
    it('gives every inquiry and payment status a colour of its own kind', () => {
        assert.equal(statusTone('pending'), 'slate');
        assert.equal(statusTone('quoted'), 'violet');
        assert.equal(statusTone('order_received'), 'sky');
        assert.equal(statusTone('cancelled'), 'red');
        assert.equal(statusTone('closed'), 'green');
        assert.equal(statusTone('awaiting'), 'amber');
        assert.equal(statusTone('fully_paid'), 'green');
    });

    it('labels the payment badge "Awaiting", not "Awaiting payment"', () => {
        assert.equal(statusLabel('payment', 'awaiting'), 'Awaiting');
    });
});

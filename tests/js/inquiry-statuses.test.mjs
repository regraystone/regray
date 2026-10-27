/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    activityLabel,
    documentLabel,
    startedStatuses,
    statusLabel,
    statusTreatment,
} from '../../resources/js/lib/inquiry-statuses.ts';

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
            'received',
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
        assert.equal(statusLabel('marketing', 'won'), 'Won');
        assert.equal(statusLabel('process', 'completed'), 'Completed');
    });
});

describe('startedStatuses', () => {
    it('leaves out a null payment, sales or logistics status, so it renders no badge', () => {
        assert.deepEqual(
            startedStatuses([
                { group: 'inquiry', status: 'pending' },
                { group: 'marketing', status: 'pending' },
                { group: 'sales', status: null },
                { group: 'process', status: null },
                { group: 'payment', status: null },
            ]),
            [
                { group: 'inquiry', status: 'pending' },
                { group: 'marketing', status: 'pending' },
            ],
        );
    });

    it('keeps every status once its stage has started, in the order given', () => {
        const entries = [
            { group: 'inquiry', status: 'production' },
            { group: 'marketing', status: 'won' },
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
        assert.equal(
            activityLabel({ track: 'marketing', status: 'lost' }),
            'Cancelled',
        );
    });
});

describe('documentLabel', () => {
    it('names a quotation by its revision and a receipt as a payment receipt', () => {
        assert.equal(documentLabel('quotation', 2), 'Quotation · Rev 2');
        assert.equal(documentLabel('quotation', null), 'Quotation');
        assert.equal(documentLabel('receipt', null), 'Payment receipt');
    });
});

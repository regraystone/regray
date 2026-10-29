/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    clampPage,
    DEFAULT_PAGE_SIZE,
    pageNumbers,
    rangeText,
} from '../../resources/js/lib/data-table.ts';
import { formatDate } from '../../resources/js/lib/format-date.ts';
import {
    allowedTargets,
    extraField,
    requiredFileGroups,
} from '../../resources/js/lib/status-rules.ts';

describe('formatDate', () => {
    it('writes a date as "June 22, 2020", in Manila', () => {
        assert.equal(formatDate('2020-06-22T03:00:00Z'), 'June 22, 2020');
        // 20:00 UTC is already the next day in Manila.
        assert.equal(formatDate('2020-06-22T20:00:00Z'), 'June 23, 2020');
    });
});

describe('status options', () => {
    it('never offer the current status', () => {
        assert.ok(
            !allowedTargets('sales', 'negotiation').includes('negotiation'),
        );
        assert.deepEqual(allowedTargets('sales', 'quoted'), [
            'negotiation',
            'site_visited',
            'on_hold',
            'rejected',
        ]);
        assert.ok(
            !allowedTargets('process', 'production').includes('production'),
        );
    });

    it('offer nothing from a final status, and payment only forward', () => {
        assert.deepEqual(allowedTargets('sales', 'rejected'), []);
        assert.deepEqual(allowedTargets('process', 'completed'), []);
        assert.deepEqual(allowedTargets('payment', null), ['awaiting']);
        assert.deepEqual(allowedTargets('payment', 'awaiting'), [
            'partial',
            'fully_paid',
        ]);
        assert.deepEqual(allowedTargets('payment', 'partial'), ['fully_paid']);
        assert.deepEqual(allowedTargets('payment', 'fully_paid'), []);
    });
});

describe('required file groups', () => {
    const slots = (track, to) =>
        requiredFileGroups(track, to).map((group) => [
            group.label,
            group.slots.map((slot) => slot.type),
        ]);

    it("ask for each target's files, and none where none are needed", () => {
        assert.deepEqual(slots('sales', 'quoted'), [
            ['Quotation', ['quotation', 'job_order']],
        ]);
        assert.deepEqual(slots('payment', 'awaiting'), [
            ['Billing statement', ['billing_statement']],
        ]);
        assert.deepEqual(slots('payment', 'partial'), [
            ['Collection of receipts', ['receipt', 'invoice']],
        ]);
        assert.deepEqual(slots('payment', 'fully_paid'), [
            ['Collection of receipts', ['receipt', 'invoice']],
        ]);
        assert.deepEqual(slots('sales', 'negotiation'), []);
        assert.deepEqual(slots('process', 'production'), []);
    });

    it('keep the job order from the client and ask for the right amount', () => {
        const [quotation, jobOrder] = requiredFileGroups('sales', 'quoted')[0]
            .slots;

        assert.equal(quotation.clientVisible, true);
        assert.equal(jobOrder.clientVisible, false);
        // The job order may come later, on its own.
        assert.equal(quotation.required, true);
        assert.equal(jobOrder.required, false);
        assert.equal(extraField('sales', 'quoted'), 'quotedTotal');
        assert.equal(extraField('payment', 'partial'), 'amountReceived');
        assert.equal(extraField('payment', 'awaiting'), null);
    });
});

describe('page sizes', () => {
    it('use one fixed page size of 20', () => {
        assert.equal(DEFAULT_PAGE_SIZE, 20);
    });

    it('keep the page between the first and the last', () => {
        assert.equal(clampPage(0, 5), 1);
        assert.equal(clampPage('9', 5), 5);
        assert.equal(clampPage('abc', 5), 1);
        assert.equal(clampPage(3, 0), 1);
    });
});

describe('pagination', () => {
    it('says which rows are showing', () => {
        assert.equal(
            rangeText({
                currentPage: 2,
                lastPage: 5,
                perPage: 10,
                total: 42,
                from: 11,
                to: 20,
            }),
            'Showing 11–20 of 42',
        );
        assert.equal(
            rangeText({
                currentPage: 1,
                lastPage: 1,
                perPage: 10,
                total: 0,
                from: null,
                to: null,
            }),
            'Showing 0 of 0',
        );
    });

    it('draws the first, last and nearby pages, with a gap for the rest', () => {
        assert.deepEqual(pageNumbers(1, 3), [1, 2, 3]);
        assert.deepEqual(pageNumbers(5, 10), [1, null, 4, 5, 6, null, 10]);
        assert.deepEqual(pageNumbers(1, 10), [1, 2, null, 10]);
    });
});

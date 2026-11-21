/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    clampPage,
    DEFAULT_PAGE_SIZE,
    nextQuery,
    pageNumbers,
    rangeText,
    splitRowActions,
} from '../../resources/js/lib/data-table.ts';
import { formatDate } from '../../resources/js/lib/format-date.ts';
import { formatPeso } from '../../resources/js/lib/format-money.ts';
import {
    allowedTargets,
    canRequestRefund,
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

describe('formatPeso', () => {
    it('writes an amount as pesos with two decimals', () => {
        assert.equal(formatPeso('1250.5'), '₱1,250.50');
        assert.equal(formatPeso(0), '₱0.00');
    });
});

describe('status options', () => {
    it('never offer the current status', () => {
        assert.ok(
            !allowedTargets('sales', 'negotiation').includes('negotiation'),
        );
        assert.deepEqual(allowedTargets('sales', 'quoted', true), [
            'negotiation',
            'site_visited',
            'on_hold',
            'revision',
            'won',
            'rejected',
        ]);
        assert.ok(
            !allowedTargets('process', 'production').includes('production'),
        );
    });

    it('offer revision and won only once a quotation has been sent', () => {
        for (const current of ['negotiation', 'site_visited', 'on_hold']) {
            assert.ok(!allowedTargets('sales', current).includes('revision'));
            assert.ok(!allowedTargets('sales', current).includes('won'));
            assert.ok(
                allowedTargets('sales', current, true).includes('revision'),
            );
            assert.ok(allowedTargets('sales', current, true).includes('won'));
        }
    });

    it('offer a revision after a revision, and only for refund after won', () => {
        assert.ok(
            allowedTargets('sales', 'revision', true).includes('revision'),
        );
        assert.ok(!allowedTargets('sales', 'quoted', true).includes('quoted'));
        assert.deepEqual(allowedTargets('sales', 'won', true), ['for_refund']);
        // For refund is only from won, and rejected only before it.
        assert.ok(
            !allowedTargets('sales', 'quoted', true).includes('for_refund'),
        );
        assert.ok(allowedTargets('sales', 'quoted', true).includes('rejected'));
    });

    it('offer nothing from a final status, and payment only forward', () => {
        assert.deepEqual(allowedTargets('sales', 'rejected'), []);
        assert.deepEqual(allowedTargets('sales', 'for_refund'), []);
        assert.deepEqual(allowedTargets('process', 'completed'), []);
        assert.deepEqual(allowedTargets('payment', null), ['awaiting']);
        assert.deepEqual(allowedTargets('payment', 'awaiting'), [
            'partial',
            'fully_paid',
        ]);
        assert.deepEqual(allowedTargets('payment', 'partial'), ['fully_paid']);
        assert.deepEqual(allowedTargets('payment', 'fully_paid'), []);
        // No refund until sales asks for one.
        assert.ok(!allowedTargets('payment', 'partial').includes('refunded'));
    });

    it('offer for refund only with a payment in and the project not done', () => {
        assert.deepEqual(allowedTargets('sales', 'won', true, null, false), []);
        assert.equal(canRequestRefund('partial', 'production'), true);
        assert.equal(canRequestRefund('fully_paid', null), true);
        assert.equal(canRequestRefund(null, null), false);
        assert.equal(canRequestRefund('awaiting', null), false);
        assert.equal(canRequestRefund('fully_paid', 'completed'), false);
    });

    it('turn payment to refunds once sales is for refund', () => {
        const refund = (current) =>
            allowedTargets('payment', current, true, 'for_refund');

        assert.deepEqual(refund(null), ['partially_refunded', 'refunded']);
        assert.deepEqual(refund('fully_paid'), [
            'partially_refunded',
            'refunded',
        ]);
        assert.deepEqual(refund('partially_refunded'), ['refunded']);
        assert.deepEqual(refund('refunded'), []);
        assert.equal(
            extraField('payment', 'partially_refunded'),
            'amountRefunded',
        );
        assert.equal(extraField('payment', 'refunded'), 'amountRefunded');
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
            ['Quotation', ['quotation']],
        ]);
        assert.deepEqual(slots('sales', 'revision'), [
            ['Quotation', ['quotation']],
        ]);
        assert.deepEqual(slots('sales', 'won'), [['Job order', ['job_order']]]);
        assert.deepEqual(slots('payment', 'awaiting'), [
            ['Billing statement', ['billing_statement']],
        ]);
        assert.deepEqual(slots('payment', 'partial'), [
            ['Collection of receipts', ['receipt', 'invoice']],
        ]);
        assert.deepEqual(slots('payment', 'fully_paid'), [
            ['Collection of receipts', ['receipt', 'invoice']],
        ]);
        assert.deepEqual(slots('sales', 'for_refund'), [
            ['Refund request', ['refund_request']],
        ]);
        assert.deepEqual(slots('payment', 'partially_refunded'), [
            ['Refund settled', ['refund_settlement']],
        ]);
        assert.deepEqual(slots('payment', 'refunded'), [
            ['Refund settled', ['refund_settlement']],
        ]);
        assert.deepEqual(slots('sales', 'negotiation'), []);
        assert.deepEqual(slots('process', 'production'), []);
    });

    it('keep the job order from the client and ask for the right amount', () => {
        const [quotation] = requiredFileGroups('sales', 'quoted')[0].slots;
        const [jobOrder] = requiredFileGroups('sales', 'won')[0].slots;

        assert.equal(quotation.clientVisible, true);
        assert.equal(jobOrder.clientVisible, false);
        assert.equal(quotation.required, true);
        assert.equal(jobOrder.required, true);
        assert.equal(extraField('sales', 'quoted'), 'quotedTotal');
        assert.equal(extraField('sales', 'revision'), 'quotedTotal');
        assert.equal(extraField('sales', 'won'), null);
        assert.equal(extraField('payment', 'partial'), 'amountReceived');
        assert.equal(extraField('payment', 'awaiting'), 'contractTotal');
    });
});

describe('page sizes', () => {
    it('use one fixed page size of 10', () => {
        assert.equal(DEFAULT_PAGE_SIZE, 10);
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

    it('draws every page up to seven', () => {
        assert.deepEqual(pageNumbers(1, 1), []);
        assert.deepEqual(pageNumbers(1, 3), [1, 2, 3]);
        assert.deepEqual(pageNumbers(4, 7), [1, 2, 3, 4, 5, 6, 7]);
    });

    it('draws the first two, the last two and the current page with its neighbours', () => {
        const draw = (current, last) =>
            pageNumbers(current, last)
                .map((page) => page ?? '…')
                .join(' ');

        assert.equal(draw(1, 10), '1 2 3 … 9 10');
        assert.equal(draw(3, 10), '1 2 3 4 … 9 10');
        assert.equal(draw(6, 10), '1 2 … 5 6 7 8 9 10');
        assert.equal(draw(8, 10), '1 2 … 7 8 9 10');
        assert.equal(draw(10, 10), '1 2 … 8 9 10');
        assert.equal(draw(10, 20), '1 2 … 9 10 11 … 19 20');
    });

    it('shows a single skipped page rather than a gap', () => {
        // 1 2 _ 4 5 6 7 8: the 3 is shown, not hidden behind an ellipsis.
        assert.deepEqual(pageNumbers(5, 8), [1, 2, 3, 4, 5, 6, 7, 8]);
        // Two or more skipped pages are a gap.
        assert.deepEqual(pageNumbers(3, 8), [1, 2, 3, 4, null, 7, 8]);
    });
});

describe('row actions', () => {
    it('draws up to two as buttons, and three or more as view beside a menu', () => {
        assert.deepEqual(splitRowActions(['view']), {
            inline: ['view'],
            menu: [],
        });
        assert.deepEqual(splitRowActions(['view', 'edit']), {
            inline: ['view', 'edit'],
            menu: [],
        });
        assert.deepEqual(splitRowActions(['view', 'edit', 'won', 'lost']), {
            inline: ['view'],
            menu: ['edit', 'won', 'lost'],
        });
    });

    it('takes a threshold: three inline when the page allows three', () => {
        assert.deepEqual(splitRowActions(['view', 'edit', 'archive'], 3), {
            inline: ['view', 'edit', 'archive'],
            menu: [],
        });
        assert.deepEqual(
            splitRowActions(['view', 'edit', 'archive', 'lost'], 3),
            { inline: ['view'], menu: ['edit', 'archive', 'lost'] },
        );
    });
});

describe('list query', () => {
    it('keeps the other filters and starts again from page 1', () => {
        assert.deepEqual(
            nextQuery(
                { search: 'ana', status: 'active', page: '4' },
                {
                    search: 'ben',
                },
            ),
            { search: 'ben', status: 'active', page: '1' },
        );
    });

    it('keeps the page it is told to go to', () => {
        assert.deepEqual(nextQuery({ status: 'active' }, { page: 3 }), {
            status: 'active',
            page: '3',
        });
    });

    it('drops an empty search and the all filter', () => {
        assert.deepEqual(
            nextQuery(
                { search: 'ana', status: 'inactive' },
                {
                    search: '  ',
                    status: 'all',
                },
            ),
            { page: '1' },
        );
    });
});

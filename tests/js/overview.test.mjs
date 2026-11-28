/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    bucketLabel,
    changeFrom,
    clampRange,
    comparedTo,
    formatFigure,
    funnelShares,
    labelEvery,
    rangeLabel,
} from '../../resources/js/lib/overview.ts';

describe('formatFigure', () => {
    it('reads each format, and shows nothing to show as a dash', () => {
        assert.equal(formatFigure(1234, 'count'), '1,234');
        assert.equal(formatFigure('170000.00', 'money'), '₱170,000');
        assert.equal(formatFigure(75, 'percent'), '75%');
        assert.equal(formatFigure(2.5, 'days'), '2.5 days');
        assert.equal(formatFigure(24, 'hours'), '24.0 h');
        assert.equal(formatFigure(72, 'hours'), '3.0 days');
        assert.equal(formatFigure(null, 'percent'), '—');
    });
});

describe('changeFrom', () => {
    it('says which way a figure moved, and by how much', () => {
        assert.deepEqual(changeFrom(3, 1, 'count'), {
            direction: 'up',
            text: '▲ 2',
        });
        assert.deepEqual(changeFrom('30000.00', '170000.00', 'money'), {
            direction: 'down',
            text: '▼ ₱140,000',
        });
        assert.deepEqual(changeFrom(75, 100, 'percent'), {
            direction: 'down',
            text: '▼ 25 pts',
        });
        assert.deepEqual(changeFrom(4, 4, 'count'), {
            direction: 'same',
            text: 'No change',
        });
    });

    it('compares nothing when one side has nothing to show or is as of now', () => {
        assert.equal(changeFrom(75, null, 'percent'), null);
        assert.equal(changeFrom(null, 50, 'percent'), null);
    });

    it('says nothing when it was zero then and is zero now', () => {
        assert.equal(changeFrom(0, 0, 'count'), null);
        assert.equal(changeFrom('0.00', '0.00', 'money'), null);
    });

    it('names the days it compares with', () => {
        assert.equal(comparedTo(1), 'vs the day before');
        assert.equal(comparedTo(23), 'vs the 23 days before');
    });
});

describe('the dates', () => {
    it('keeps To at today at the latest, and From at To', () => {
        const today = '2026-11-23';

        assert.deepEqual(clampRange('2026-11-01', '2026-12-05', today, 'to'), {
            from: '2026-11-01',
            to: today,
        });
        assert.deepEqual(
            clampRange('2026-11-20', '2026-11-10', today, 'from'),
            { from: '2026-11-20', to: '2026-11-20' },
        );
        assert.deepEqual(clampRange('2026-11-20', '2026-11-10', today, 'to'), {
            from: '2026-11-10',
            to: '2026-11-10',
        });
    });

    it('labels a span and a chart bucket', () => {
        assert.equal(
            rangeLabel('2026-11-01', '2026-11-23'),
            'Nov 1 – Nov 23, 2026',
        );
        assert.equal(rangeLabel('2026-11-23', '2026-11-23'), 'Nov 23, 2026');
        assert.equal(bucketLabel('2026-11-05', 'day'), 'Nov 5');
        assert.equal(bucketLabel('2026-11-02', 'week'), 'Wk of Nov 2');
        assert.equal(bucketLabel('2026-11-01', 'month'), 'Nov 2026');
    });

    it('prints every label on a short chart, and about eight on a long one', () => {
        assert.equal(labelEvery(12), 1);
        assert.equal(labelEvery(23), 3);
        assert.equal(labelEvery(31), 4);
    });
});

describe('the insights', () => {
    it('reads a decimal and a signed percentage', () => {
        assert.equal(formatFigure(2, 'decimal'), '2.0');
        assert.equal(formatFigure(-5, 'signedPercent'), '-5.0%');
        assert.equal(formatFigure(3.25, 'signedPercent'), '+3.3%');
        assert.deepEqual(changeFrom(-5, -2.5, 'signedPercent'), {
            direction: 'down',
            text: '▼ 2.5 pts',
        });
    });

    it('gives each funnel step its share of the first and of the step before', () => {
        assert.deepEqual(funnelShares([4, 2, 1, 0]), [
            { ofFirst: 100, ofPrevious: null },
            { ofFirst: 50, ofPrevious: 50 },
            { ofFirst: 25, ofPrevious: 50 },
            { ofFirst: 0, ofPrevious: 0 },
        ]);
        assert.deepEqual(funnelShares([0, 0]), [
            { ofFirst: null, ofPrevious: null },
            { ofFirst: null, ofPrevious: null },
        ]);
    });
});

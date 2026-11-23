/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    compactPeso,
    monthLabel,
    niceTicks,
    plural,
    share,
    winRateText,
} from '../../resources/js/lib/sales-report.ts';

describe('niceTicks', () => {
    it('steps by 1, 2, 2.5 or 5 times a power of ten up to at least the largest value', () => {
        assert.deepEqual(niceTicks(1156050), [0, 500000, 1000000, 1500000]);
        assert.deepEqual(niceTicks(30000), [0, 10000, 20000, 30000]);
        assert.deepEqual(niceTicks(95), [0, 25, 50, 75, 100]);
    });

    it('still draws an axis when there is nothing to show', () => {
        assert.deepEqual(niceTicks(0), [0, 1]);
    });
});

describe('labels', () => {
    it('shortens pesos for an axis', () => {
        assert.equal(compactPeso(0), '₱0');
        assert.equal(compactPeso(500000), '₱500K');
        assert.equal(compactPeso(1500000), '₱1.5M');
    });

    it('names a month, with its year when asked', () => {
        assert.equal(monthLabel('2026-10'), 'Oct');
        assert.equal(monthLabel('2027-01', true), 'Jan 2027');
    });

    it('counts in words, singular for one', () => {
        assert.equal(plural(1, 'inquiry', 'inquiries'), '1 inquiry');
        assert.equal(plural(17, 'inquiry', 'inquiries'), '17 inquiries');
    });

    it('shows a win rate, or a dash when nothing was decided', () => {
        assert.equal(winRateText(89), '89%');
        assert.equal(winRateText(null), '—');
    });
});

describe('share', () => {
    it('is how much of the largest a value is, kept within 0 and 100', () => {
        assert.equal(share(50, 200), 25);
        assert.equal(share(300, 200), 100);
        assert.equal(share(5, 0), 0);
    });
});

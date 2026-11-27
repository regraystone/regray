/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    QUOTE_DEFAULT_FILTERS,
    buildQuoteQuery,
    tally,
} from '../../resources/js/lib/quote-filters.ts';

describe('buildQuoteQuery', () => {
    it('is empty for the default view, so /quote stays bare', () => {
        assert.deepEqual(buildQuoteQuery(QUOTE_DEFAULT_FILTERS), {});
    });

    it('carries a search, trimmed', () => {
        assert.deepEqual(
            buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, search: '  kitchen ' }),
            { search: 'kitchen' },
        );
    });

    it('drops a search that is only spaces', () => {
        assert.deepEqual(
            buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, search: '   ' }),
            {},
        );
    });

    it('never carries a status, which the list no longer filters by', () => {
        assert.equal('status' in QUOTE_DEFAULT_FILTERS, false);
        assert.equal(
            'status' in
                buildQuoteQuery({
                    ...QUOTE_DEFAULT_FILTERS,
                    status: 'closed',
                }),
            false,
        );
    });

    it('carries a tab other than All', () => {
        assert.deepEqual(
            buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, tab: 'quoted' }),
            { tab: 'quoted' },
        );
        assert.deepEqual(
            buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, tab: 'all' }),
            {},
        );
    });

    it('carries a sort other than newest', () => {
        assert.deepEqual(
            buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, sort: 'oldest' }),
            { sort: 'oldest' },
        );
    });

    it('carries a search and a sort together', () => {
        assert.deepEqual(
            buildQuoteQuery({
                search: 'INQ-1',
                sort: 'oldest',
            }),
            { search: 'INQ-1', sort: 'oldest' },
        );
    });

    it('does not use the old `q` key', () => {
        assert.equal(
            'q' in buildQuoteQuery({ ...QUOTE_DEFAULT_FILTERS, search: 'x' }),
            false,
        );
    });
});

describe('tally', () => {
    it('shows everything when it fits', () => {
        assert.deepEqual(tally(['a', 'b'], 3), {
            shown: ['a', 'b'],
            hidden: 0,
        });
    });

    it('shows everything at exactly the limit', () => {
        assert.deepEqual(tally(['a', 'b', 'c'], 3), {
            shown: ['a', 'b', 'c'],
            hidden: 0,
        });
    });

    it('cuts at the limit and counts the rest', () => {
        assert.deepEqual(tally(['a', 'b', 'c', 'd', 'e'], 3), {
            shown: ['a', 'b', 'c'],
            hidden: 2,
        });
    });

    it('handles an empty row', () => {
        assert.deepEqual(tally([], 3), { shown: [], hidden: 0 });
    });

    it('keeps the order it was given', () => {
        assert.deepEqual(tally([3, 1, 2], 2).shown, [3, 1]);
    });
});

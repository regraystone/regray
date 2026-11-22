/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    archiveDescription,
    showcaseCount,
    showcaseSubtitle,
} from '../../resources/js/lib/showcase-admin.ts';
import { initials } from '../../resources/js/lib/initials.ts';

describe('showcaseSubtitle', () => {
    it('reads "location · year"', () => {
        assert.equal(
            showcaseSubtitle('Makati City', 2026),
            'Makati City · 2026',
        );
    });

    it('leaves out what is missing, so the card shows its placeholder', () => {
        assert.equal(showcaseSubtitle('  ', 2026), '2026');
        assert.equal(showcaseSubtitle('Makati City', null), 'Makati City');
        assert.equal(showcaseSubtitle('', null), '');
    });
});

describe('showcaseCount', () => {
    it('counts the stones and photos', () => {
        assert.equal(showcaseCount(2, 3), '2 stones · 3 photos');
        assert.equal(showcaseCount(1, 1), '1 stone · 1 photo');
    });

    it('leaves out an empty gallery', () => {
        assert.equal(showcaseCount(1, 0), '1 stone');
    });

    it('says when there are no stones yet', () => {
        assert.equal(showcaseCount(0, 4), 'No stones yet');
    });

    it('groups large numbers', () => {
        assert.equal(showcaseCount(1200, 0), '1,200 stones');
    });
});

describe('archiveDescription', () => {
    it('says what archiving does', () => {
        assert.equal(
            archiveDescription(),
            'It leaves this list and its page goes offline. Saved showcases keep it.',
        );
    });
});

describe('initials', () => {
    it('is the cover a showcase without a thumbnail shows', () => {
        assert.equal(initials('Ginza One'), 'GO');
    });
});

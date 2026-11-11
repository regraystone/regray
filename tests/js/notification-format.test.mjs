/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    badgeLabel,
    relativeTime,
} from '../../resources/js/lib/notification-format.ts';

describe('badgeLabel', () => {
    it('hides the badge at none', () => {
        assert.equal(badgeLabel(0), null);
    });

    it('counts up to nine, then shows 9+', () => {
        assert.equal(badgeLabel(1), '1');
        assert.equal(badgeLabel(9), '9');
        assert.equal(badgeLabel(10), '9+');
        assert.equal(badgeLabel(250), '9+');
    });
});

describe('relativeTime', () => {
    const now = Date.parse('2026-11-11T12:00:00Z');
    const ago = (ms) => new Date(now - ms).toISOString();

    it('reads the time since, in the largest unit that fits', () => {
        assert.equal(relativeTime(ago(20_000), now), 'Just now');
        assert.equal(relativeTime(ago(5 * 60_000), now), '5 min ago');
        assert.equal(relativeTime(ago(3 * 3_600_000), now), '3 h ago');
        assert.equal(relativeTime(ago(86_400_000), now), '1 day ago');
        assert.equal(relativeTime(ago(2 * 86_400_000), now), '2 days ago');
    });

    it('gives the date after a week', () => {
        assert.equal(relativeTime(ago(10 * 86_400_000), now), 'Nov 1');
    });
});

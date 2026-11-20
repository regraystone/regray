/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    archiveDescription,
    projectCount,
    projectSubtitle,
} from '../../resources/js/lib/project-admin.ts';
import { initials } from '../../resources/js/lib/initials.ts';

describe('projectSubtitle', () => {
    it('reads "location · year"', () => {
        assert.equal(
            projectSubtitle('Makati City', 2026),
            'Makati City · 2026',
        );
    });

    it('leaves out what is missing, so the card shows its placeholder', () => {
        assert.equal(projectSubtitle('  ', 2026), '2026');
        assert.equal(projectSubtitle('Makati City', null), 'Makati City');
        assert.equal(projectSubtitle('', null), '');
    });
});

describe('projectCount', () => {
    it('counts the stones and photos', () => {
        assert.equal(projectCount(2, 3), '2 stones · 3 photos');
        assert.equal(projectCount(1, 1), '1 stone · 1 photo');
    });

    it('leaves out an empty gallery', () => {
        assert.equal(projectCount(1, 0), '1 stone');
    });

    it('says when there are no stones yet', () => {
        assert.equal(projectCount(0, 4), 'No stones yet');
    });

    it('groups large numbers', () => {
        assert.equal(projectCount(1200, 0), '1,200 stones');
    });
});

describe('archiveDescription', () => {
    it('says what archiving does', () => {
        assert.equal(
            archiveDescription(),
            'It leaves this list and its page goes offline. Saved projects keep it.',
        );
    });
});

describe('initials', () => {
    it('is the cover a project without a thumbnail shows', () => {
        assert.equal(initials('Ginza One'), 'GO');
    });
});

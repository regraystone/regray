/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    CONTACT_KIND_LABELS,
    CONTACT_KINDS,
    STAFF_INQUIRE_OPTIONS,
    staffInquireKindFor,
    staffInquireKindFrom,
} from '../../resources/js/lib/staff-inquire.ts';

describe('the staff Inquire modal', () => {
    it('offers exactly two options: Platform and Direct', () => {
        assert.deepEqual(
            STAFF_INQUIRE_OPTIONS.map((option) => [option.kind, option.label]),
            [
                ['platform', 'Platform'],
                ['direct', 'Direct'],
            ],
        );
        assert.equal(
            STAFF_INQUIRE_OPTIONS[0].description,
            'The client has a Regray account.',
        );
        assert.equal(
            STAFF_INQUIRE_OPTIONS[1].description,
            'The client has no account.',
        );
    });

    it('reads a branch from the URL, and nothing else', () => {
        assert.equal(staffInquireKindFrom('platform'), 'platform');
        assert.equal(staffInquireKindFrom('direct'), 'direct');
        assert.equal(staffInquireKindFrom('walk_in'), null);
        assert.equal(staffInquireKindFrom(null), null);
        assert.equal(staffInquireKindFrom(undefined), null);
    });

    it('keeps the branch asked for when Platform is allowed', () => {
        assert.equal(staffInquireKindFor('platform', true), 'platform');
        assert.equal(staffInquireKindFor('direct', true), 'direct');
        assert.equal(staffInquireKindFor(null, true), null);
    });

    it('always lands sales, who may not use Platform, on Direct', () => {
        assert.equal(staffInquireKindFor('platform', false), 'direct');
        assert.equal(staffInquireKindFor(null, false), 'direct');
    });
});

describe('direct client contacts', () => {
    it('takes a phone and an email only', () => {
        assert.deepEqual(CONTACT_KINDS, ['phone', 'email']);
        assert.equal(CONTACT_KIND_LABELS.email, 'Email');
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    CONTACT_KIND_LABELS,
    CONTACT_KINDS,
    STAFF_INQUIRE_OPTIONS,
    staffInquireKindFrom,
} from '../../resources/js/lib/staff-inquire.ts';

describe('the staff Inquire modal', () => {
    it('offers exactly two options: Platform and Direct (walk-in)', () => {
        assert.deepEqual(
            STAFF_INQUIRE_OPTIONS.map((option) => [option.kind, option.label]),
            [
                ['platform', 'Platform'],
                ['direct', 'Direct (walk-in)'],
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
});

describe('direct client contacts', () => {
    it('takes a phone, Viber, WhatsApp or email, and never a walk-in kind', () => {
        assert.deepEqual(CONTACT_KINDS, [
            'phone',
            'viber',
            'whatsapp',
            'email',
        ]);
        assert.equal(CONTACT_KIND_LABELS.whatsapp, 'WhatsApp');
    });
});

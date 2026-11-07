/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    COMMON_QUESTIONS,
    showsCommonQuestions,
} from '../../resources/js/lib/common-questions.ts';

const shows = (overrides = {}) =>
    showsCommonQuestions({
        isClient: true,
        composerMode: 'enabled',
        isEditing: false,
        unseen: 0,
        ...overrides,
    });

describe('the common questions', () => {
    it('are these three', () => {
        assert.deepEqual(COMMON_QUESTIONS, [
            'How long does an order take?',
            'Do you offer nationwide installation?',
            'How do I request a quotation?',
        ]);
    });

    it('are offered to a client who can write', () => {
        assert.equal(shows(), true);
    });

    it('are never offered to staff', () => {
        assert.equal(shows({ isClient: false }), false);
    });

    it('are not offered while the composer is locked', () => {
        assert.equal(shows({ composerMode: 'locked' }), false);
    });

    it('are not offered while editing a message', () => {
        assert.equal(shows({ isEditing: true }), false);
    });

    it('give way to the new-messages pill', () => {
        assert.equal(shows({ unseen: 2 }), false);
    });
});

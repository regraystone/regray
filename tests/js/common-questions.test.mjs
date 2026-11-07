/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    COMMON_QUESTIONS,
    wheelPull,
} from '../../resources/js/lib/common-questions.ts';

const list = (overrides = {}) => ({
    scrollTop: 0,
    clientHeight: 100,
    scrollHeight: 100,
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
});

describe('scrolling down to hide them', () => {
    it('adds up what is scrolled down', () => {
        assert.equal(wheelPull(10, 25, list()), 35);
    });

    it('starts again when scrolled up', () => {
        assert.equal(wheelPull(30, -5, list()), 0);
    });

    it('lets a list that overflows scroll to its end first', () => {
        const tall = { clientHeight: 100, scrollHeight: 300 };

        assert.equal(wheelPull(30, 50, list(tall)), 0);
        assert.equal(wheelPull(0, 50, list({ ...tall, scrollTop: 200 })), 50);
    });
});

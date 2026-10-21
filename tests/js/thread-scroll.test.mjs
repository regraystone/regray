/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    anchorAfterPrepend,
    isNearBottom,
    isNearTop,
    shouldAutoScroll,
} from '../../resources/js/lib/thread-scroll.ts';

describe('anchorAfterPrepend', () => {
    it('moves the view down by exactly the height of the older messages', () => {
        assert.equal(
            anchorAfterPrepend({
                previousScrollHeight: 1000,
                previousScrollTop: 40,
                nextScrollHeight: 1600,
            }),
            640,
        );
    });

    it('keeps the view where it was when nothing was added', () => {
        assert.equal(
            anchorAfterPrepend({
                previousScrollHeight: 1000,
                previousScrollTop: 0,
                nextScrollHeight: 1000,
            }),
            0,
        );
    });

    it('never scrolls above the top', () => {
        assert.equal(
            anchorAfterPrepend({
                previousScrollHeight: 1000,
                previousScrollTop: 10,
                nextScrollHeight: 900,
            }),
            0,
        );
    });
});

describe('isNearBottom', () => {
    it('counts the last 120 pixels as the bottom', () => {
        assert.equal(
            isNearBottom({
                scrollTop: 380,
                clientHeight: 500,
                scrollHeight: 1000,
            }),
            true,
        );
        assert.equal(
            isNearBottom({
                scrollTop: 379,
                clientHeight: 500,
                scrollHeight: 1000,
            }),
            false,
        );
    });
});

describe('isNearTop', () => {
    it('asks for older messages within 80 pixels of the top', () => {
        assert.equal(isNearTop(80), true);
        assert.equal(isNearTop(81), false);
    });
});

describe('shouldAutoScroll', () => {
    it("always follows the reader's own message", () => {
        assert.equal(
            shouldAutoScroll({ nearBottom: false, isOwn: true }),
            true,
        );
    });

    it("follows someone else's message only from the bottom", () => {
        assert.equal(
            shouldAutoScroll({ nearBottom: true, isOwn: false }),
            true,
        );
        assert.equal(
            shouldAutoScroll({ nearBottom: false, isOwn: false }),
            false,
        );
    });
});

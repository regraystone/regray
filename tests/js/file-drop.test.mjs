/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { isFileDrag, nextDragDepth } from '../../resources/js/lib/file-drop.ts';

describe('isFileDrag', () => {
    it('is true only when the drag carries files', () => {
        assert.equal(isFileDrag(['Files']), true);
        assert.equal(isFileDrag(['text/plain', 'Files']), true);
        assert.equal(isFileDrag(['text/plain', 'text/uri-list']), false);
        assert.equal(isFileDrag([]), false);
    });
});

describe('nextDragDepth', () => {
    it('stays above 0 while the pointer moves between children', () => {
        let depth = 0;

        // Enter the thread, then a bubble inside it, then leave the thread
        // for the bubble: the thread's leave comes after the bubble's enter.
        for (const event of ['enter', 'enter', 'leave']) {
            depth = nextDragDepth(depth, event);
        }

        assert.equal(depth, 1);
    });

    it('reaches 0 when the pointer leaves the thread', () => {
        const depth = ['enter', 'enter', 'leave', 'leave'].reduce(
            nextDragDepth,
            0,
        );

        assert.equal(depth, 0);
    });

    it('never goes below 0, and a drop always ends the drag', () => {
        assert.equal(nextDragDepth(0, 'leave'), 0);
        assert.equal(nextDragDepth(3, 'drop'), 0);
    });
});

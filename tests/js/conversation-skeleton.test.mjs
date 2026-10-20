/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    SKELETON_ROW_COUNT,
    conversationListSkeleton,
    threadLoadingState,
} from '../../resources/js/lib/conversation-skeleton.ts';

const NOTHING = { rows: false, footer: false, dim: false };

describe('conversationListSkeleton', () => {
    it('shows nothing while nothing is being fetched, in every mode', () => {
        for (const mode of ['initial', 'filter', 'loadMore']) {
            for (const hasRows of [true, false]) {
                assert.deepEqual(
                    conversationListSkeleton({
                        isFetching: false,
                        hasRows,
                        mode,
                    }),
                    NOTHING,
                    `${mode} ${hasRows}`,
                );
            }
        }
    });

    it('fills the list with placeholder rows before the first page arrives', () => {
        assert.deepEqual(
            conversationListSkeleton({
                isFetching: true,
                hasRows: false,
                mode: 'initial',
            }),
            { rows: true, footer: false, dim: false },
        );
    });

    it('does not replace rows that are already there on the first load', () => {
        assert.deepEqual(
            conversationListSkeleton({
                isFetching: true,
                hasRows: true,
                mode: 'initial',
            }),
            NOTHING,
        );
    });

    it('dims the rows while a search or filter reloads them', () => {
        assert.deepEqual(
            conversationListSkeleton({
                isFetching: true,
                hasRows: true,
                mode: 'filter',
            }),
            { rows: false, footer: false, dim: true },
        );
    });

    it('shows placeholder rows when a filter reloads an empty list', () => {
        assert.deepEqual(
            conversationListSkeleton({
                isFetching: true,
                hasRows: false,
                mode: 'filter',
            }),
            { rows: true, footer: false, dim: false },
        );
    });

    it('adds placeholder rows under the list while the next page loads', () => {
        assert.deepEqual(
            conversationListSkeleton({
                isFetching: true,
                hasRows: true,
                mode: 'loadMore',
            }),
            { rows: false, footer: true, dim: false },
        );
    });

    it('draws six placeholder rows', () => {
        assert.equal(SKELETON_ROW_COUNT, 6);
    });
});

describe('threadLoadingState', () => {
    it('is a skeleton until the first page arrives', () => {
        assert.equal(
            threadLoadingState({
                hasLoaded: false,
                isLoadingOlder: false,
                hasMore: true,
            }),
            'skeleton',
        );
    });

    it('shows the top spinner while older messages load', () => {
        assert.equal(
            threadLoadingState({
                hasLoaded: true,
                isLoadingOlder: true,
                hasMore: true,
            }),
            'olderSpinner',
        );
    });

    it('shows nothing once the oldest message is in', () => {
        assert.equal(
            threadLoadingState({
                hasLoaded: true,
                isLoadingOlder: true,
                hasMore: false,
            }),
            'none',
        );
        assert.equal(
            threadLoadingState({
                hasLoaded: true,
                isLoadingOlder: false,
                hasMore: true,
            }),
            'none',
        );
    });
});

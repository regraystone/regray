/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    SKELETON_CARD_COUNT,
    skeletonState,
} from '../../resources/js/lib/quote-skeleton.ts';

const NOTHING = { cards: false, footer: false, dim: false };

describe('skeletonState', () => {
    it('shows nothing while nothing is being fetched, in every mode', () => {
        for (const mode of ['initial', 'filter', 'loadMore']) {
            for (const hasCards of [true, false]) {
                assert.deepEqual(
                    skeletonState({ isFetching: false, hasCards, mode }),
                    NOTHING,
                    `${mode} ${hasCards}`,
                );
            }
        }
    });

    it('fills the page with cards before the first data arrives', () => {
        assert.deepEqual(
            skeletonState({
                isFetching: true,
                hasCards: false,
                mode: 'initial',
            }),
            { cards: true, footer: false, dim: false },
        );
    });

    it('does not replace cards that are already there on the first load', () => {
        assert.deepEqual(
            skeletonState({
                isFetching: true,
                hasCards: true,
                mode: 'initial',
            }),
            NOTHING,
        );
    });

    it('dims the list while a filter, search or sort reloads it', () => {
        assert.deepEqual(
            skeletonState({ isFetching: true, hasCards: true, mode: 'filter' }),
            { cards: false, footer: false, dim: true },
        );
    });

    it('shows card skeletons for a filter when there is no list to dim', () => {
        assert.deepEqual(
            skeletonState({
                isFetching: true,
                hasCards: false,
                mode: 'filter',
            }),
            { cards: true, footer: false, dim: false },
        );
    });

    it('adds a footer of skeletons while loading more, leaving the list alone', () => {
        for (const hasCards of [true, false]) {
            assert.deepEqual(
                skeletonState({ isFetching: true, hasCards, mode: 'loadMore' }),
                { cards: false, footer: true, dim: false },
            );
        }
    });

    it('hides the footer when the request finishes', () => {
        assert.deepEqual(
            skeletonState({
                isFetching: false,
                hasCards: true,
                mode: 'loadMore',
            }),
            NOTHING,
        );
    });

    it('never asks for both the full skeleton and the footer', () => {
        for (const mode of ['initial', 'filter', 'loadMore']) {
            for (const hasCards of [true, false]) {
                const state = skeletonState({
                    isFetching: true,
                    hasCards,
                    mode,
                });

                assert.equal(
                    state.cards && state.footer,
                    false,
                    `${mode} ${hasCards}`,
                );
            }
        }
    });

    it('draws three placeholder cards', () => {
        assert.equal(SKELETON_CARD_COUNT, 3);
    });
});

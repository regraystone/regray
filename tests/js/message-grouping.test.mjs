/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildThreadItems,
    dateSeparatorLabel,
} from '../../resources/js/lib/message-grouping.ts';

// Local times, so the labels do not depend on the machine's timezone.
const NOW = new Date(2026, 9, 20, 15, 0); // Tue 20 Oct 2026, 15:00
const at = (day, hour, minute = 0) =>
    new Date(2026, 9, day, hour, minute).toISOString();

const mara = { id: 1, name: 'Mara' };
const client = { id: 2, name: 'Client' };
const text = (id, sender, createdAt) => ({
    id,
    type: 'text',
    sender,
    createdAt,
});

describe('dateSeparatorLabel', () => {
    it('says Today and Yesterday', () => {
        assert.equal(dateSeparatorLabel(at(20, 0, 1), NOW, 'en-GB'), 'Today');
        assert.equal(
            dateSeparatorLabel(at(19, 23, 59), NOW, 'en-GB'),
            'Yesterday',
        );
    });

    it('names the weekday within the last week', () => {
        assert.equal(dateSeparatorLabel(at(16, 9), NOW, 'en-GB'), 'Friday');
    });

    it('gives the full date beyond a week', () => {
        assert.equal(
            dateSeparatorLabel(at(13, 9), NOW, 'en-GB'),
            'Tue, 13 October 2026',
        );
    });
});

describe('buildThreadItems', () => {
    it('puts a separator wherever the day changes', () => {
        const items = buildThreadItems(
            [
                text(1, mara, at(19, 10)),
                text(2, mara, at(20, 10)),
                text(3, client, at(20, 11)),
            ],
            NOW,
            'en-GB',
        );

        assert.deepEqual(
            items.map((item) =>
                item.kind === 'separator' ? item.label : item.message.id,
            ),
            ['Yesterday', 1, 'Today', 2, 3],
        );
    });

    it('runs consecutive messages from one sender within five minutes', () => {
        const items = buildThreadItems(
            [
                text(1, mara, at(20, 10, 0)),
                text(2, mara, at(20, 10, 5)),
                text(3, mara, at(20, 10, 11)),
            ],
            NOW,
        ).filter((item) => item.kind === 'message');

        assert.deepEqual(
            items.map((item) => [item.isRunStart, item.isRunEnd]),
            [
                [true, false],
                [false, true],
                [true, true],
            ],
        );
    });

    it('breaks a run when the sender changes', () => {
        const items = buildThreadItems(
            [text(1, mara, at(20, 10)), text(2, client, at(20, 10, 1))],
            NOW,
        ).filter((item) => item.kind === 'message');

        assert.deepEqual(
            items.map((item) => [item.isRunStart, item.isRunEnd]),
            [
                [true, true],
                [true, true],
            ],
        );
    });

    it('keeps cards and system notices out of runs', () => {
        const items = buildThreadItems(
            [
                text(1, mara, at(20, 10)),
                {
                    id: 2,
                    type: 'system',
                    sender: null,
                    createdAt: at(20, 10, 1),
                },
                text(3, mara, at(20, 10, 2)),
            ],
            NOW,
        ).filter((item) => item.kind === 'message');

        assert.deepEqual(
            items.map((item) => [item.isRunStart, item.isRunEnd]),
            [
                [true, true],
                [true, true],
                [true, true],
            ],
        );
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    latestOwnMessageId,
    receiptFor,
} from '../../resources/js/lib/receipts.ts';

const ME = 1;
const pointer = (userId, name, read, delivered) => ({
    userId,
    name,
    lastReadMessageId: read,
    lastDeliveredMessageId: delivered,
});

describe('latestOwnMessageId', () => {
    it("finds the viewer's latest message that is not removed", () => {
        const messages = [
            { id: 1, sender: { id: ME }, isRemoved: false },
            { id: 2, sender: { id: 2 }, isRemoved: false },
            { id: 3, sender: { id: ME }, isRemoved: true },
            { id: 4, sender: null, isRemoved: false },
        ];

        assert.equal(latestOwnMessageId(messages, ME), 1);
    });

    it('is null when the viewer has written nothing', () => {
        assert.equal(latestOwnMessageId([], ME), null);
    });
});

describe('receiptFor in a direct chat', () => {
    const receipt = (other) =>
        receiptFor({
            messageId: 10,
            pointers: [pointer(ME, 'Me', 10, 10), other],
            currentUserId: ME,
            isDirect: true,
        });

    it('says Sent until the other person has it', () => {
        assert.deepEqual(receipt(pointer(2, 'Ana', 9, 9)), {
            kind: 'status',
            text: 'Sent',
        });
    });

    it('says Delivered once it reached them', () => {
        assert.deepEqual(receipt(pointer(2, 'Ana', 9, 10)), {
            kind: 'status',
            text: 'Delivered',
        });
    });

    it('says Seen once they read it or anything after it', () => {
        assert.deepEqual(receipt(pointer(2, 'Ana', 12, null)), {
            kind: 'status',
            text: 'Seen',
        });
    });
});

describe('receiptFor in a group or Regray Team thread', () => {
    it('lists who has read up to the message, never the viewer', () => {
        assert.deepEqual(
            receiptFor({
                messageId: 10,
                pointers: [
                    pointer(ME, 'Me', 10, 10),
                    pointer(2, 'Ana', 10, 10),
                    pointer(3, 'Ben', 9, 10),
                ],
                currentUserId: ME,
                isDirect: false,
            }),
            { kind: 'readers', readers: [{ userId: 2, name: 'Ana' }] },
        );
    });

    it('says Sent while nobody has read it', () => {
        assert.deepEqual(
            receiptFor({
                messageId: 10,
                pointers: [pointer(2, 'Ana', null, 10)],
                currentUserId: ME,
                isDirect: false,
            }),
            { kind: 'status', text: 'Sent' },
        );
    });

    it('shows nothing when the viewer has no message', () => {
        assert.deepEqual(
            receiptFor({
                messageId: null,
                pointers: [],
                currentUserId: ME,
                isDirect: false,
            }),
            { kind: 'none' },
        );
    });
});

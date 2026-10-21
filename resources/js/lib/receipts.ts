/*
 * The receipt under the viewer's latest message. Pure, with no imports, for
 * tests/js.
 *
 * - A direct chat says "Sent", "Delivered" or "Seen" about the other person.
 * - A group or a Regray Team thread shows who has read up to that message.
 */

export type ReceiptPointer = {
    userId: number;
    name: string;
    lastReadMessageId: number | null;
    lastDeliveredMessageId: number | null;
};

export type Receipt =
    | { kind: 'none' }
    | { kind: 'status'; text: 'Sent' | 'Delivered' | 'Seen' }
    | { kind: 'readers'; readers: Array<{ userId: number; name: string }> };

/** The id of the viewer's latest message that the server has confirmed. */
export const latestOwnMessageId = (
    messages: ReadonlyArray<{
        id: number;
        sender: { id: number } | null;
        isRemoved: boolean;
    }>,
    currentUserId: number,
): number | null => {
    for (let index = messages.length - 1; index >= 0; index -= 1) {
        const message = messages[index];

        if (message.sender?.id === currentUserId && !message.isRemoved) {
            return message.id;
        }
    }

    return null;
};

export const receiptFor = ({
    messageId,
    pointers,
    currentUserId,
    isDirect,
}: {
    messageId: number | null;
    pointers: readonly ReceiptPointer[];
    currentUserId: number;
    isDirect: boolean;
}): Receipt => {
    if (messageId === null) {
        return { kind: 'none' };
    }

    const others = pointers.filter(
        (pointer) => pointer.userId !== currentUserId,
    );
    const hasRead = (pointer: ReceiptPointer): boolean =>
        pointer.lastReadMessageId !== null &&
        pointer.lastReadMessageId >= messageId;
    const hasDelivered = (pointer: ReceiptPointer): boolean =>
        hasRead(pointer) ||
        (pointer.lastDeliveredMessageId !== null &&
            pointer.lastDeliveredMessageId >= messageId);

    if (isDirect) {
        if (others.length > 0 && others.every(hasRead)) {
            return { kind: 'status', text: 'Seen' };
        }

        if (others.length > 0 && others.every(hasDelivered)) {
            return { kind: 'status', text: 'Delivered' };
        }

        return { kind: 'status', text: 'Sent' };
    }

    const readers = others
        .filter(hasRead)
        .map((pointer) => ({ userId: pointer.userId, name: pointer.name }));

    return readers.length > 0
        ? { kind: 'readers', readers }
        : { kind: 'status', text: 'Sent' };
};

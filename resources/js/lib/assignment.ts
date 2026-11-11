/*
 * What a thread shows a viewer, from who they are, whether a Regray Team
 * thread is assigned, and whether they may write in it at all. Pure, with no
 * imports, for tests/js.
 *
 * - Before anyone claims a Regray Team thread, every pool member sees the
 *   assignment card, which has the only "Assign to me" button, and a disabled
 *   composer.
 * - After a claim, only the assignee may write; the client always may. A claim
 *   is for good: it is never released or reassigned.
 * - The client never sees the card.
 * - Groups and direct chats have no assignment at all.
 * - A reader who may not write here (`canSend` false: the owner, or staff
 *   reading an inquiry's group they are not in) sees the composer disabled,
 *   whatever the rest says.
 */

export type AssignmentInput = {
    canClaim: boolean;
    assignee: { id: number; name: string } | null;
    isMine: boolean;
};

export type ComposerState =
    | { mode: 'enabled' }
    | { mode: 'locked'; note: string };

export type AssignmentView = {
    /** The staff-only card pinned under the thread header. */
    showCard: boolean;
    composer: ComposerState;
    /** "Handled by {name}" in the header, once somebody handles it. */
    subtitle: string | null;
    /** Whether staff names may label messages in this thread. */
    showStaffNames: boolean;
};

export const ASSIGN_TO_REPLY = 'Assign to yourself to reply';

export const READ_ONLY = 'You can read this conversation but not reply.';

const assignmentOnly = ({
    isSupport,
    isClient,
    assignment,
    assignee,
}: {
    isSupport: boolean;
    isClient: boolean;
    assignment: AssignmentInput | null;
    assignee: { id: number; name: string } | null;
}): AssignmentView => {
    if (!isSupport) {
        return {
            showCard: false,
            composer: { mode: 'enabled' },
            subtitle: null,
            showStaffNames: true,
        };
    }

    const handledBy = assignee ?? assignment?.assignee ?? null;
    const subtitle = handledBy === null ? null : `Handled by ${handledBy.name}`;

    if (isClient || assignment === null) {
        return {
            showCard: false,
            composer: { mode: 'enabled' },
            subtitle,
            showStaffNames: handledBy !== null,
        };
    }

    if (handledBy === null) {
        return {
            showCard: assignment.canClaim,
            composer: {
                mode: 'locked',
                note: assignment.canClaim
                    ? ASSIGN_TO_REPLY
                    : 'Nobody is handling this conversation yet.',
            },
            subtitle: null,
            showStaffNames: false,
        };
    }

    return {
        showCard: false,
        composer: assignment.isMine
            ? { mode: 'enabled' }
            : {
                  mode: 'locked',
                  note: `${handledBy.name} is handling this conversation.`,
              },
        subtitle,
        showStaffNames: true,
    };
};

export const assignmentView = ({
    canSend,
    ...input
}: {
    isSupport: boolean;
    isClient: boolean;
    /** The staff-only payload; always null for a client. */
    assignment: AssignmentInput | null;
    /** Who handles the thread, as the conversation summary says (any viewer). */
    assignee: { id: number; name: string } | null;
    /** Whether the reader may write here at all; unknown until the thread loads. */
    canSend?: boolean;
}): AssignmentView => {
    const view = assignmentOnly(input);

    return canSend === false
        ? { ...view, composer: { mode: 'locked', note: READ_ONLY } }
        : view;
};

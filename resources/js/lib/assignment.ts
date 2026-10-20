/*
 * What a Regray Team thread shows a viewer, from who they are and whether the
 * thread is assigned. Pure, with no imports, for tests/js.
 *
 * - Before anyone claims it, every pool member sees the assignment card and a
 *   composer that cannot send, with the same "Assign to me" button.
 * - After a claim, only the assignee may write; the client always may.
 * - The client never sees the card.
 * - Groups and direct chats have no assignment at all.
 */

export type AssignmentInput = {
    canClaim: boolean;
    assignee: { id: number; name: string } | null;
    isMine: boolean;
    canRelease: boolean;
    canReassign: boolean;
};

export type ComposerState =
    | { mode: 'enabled' }
    | { mode: 'claim'; note: string }
    | { mode: 'locked'; note: string };

export type AssignmentView = {
    /** The staff-only card at the top of the thread. */
    showCard: boolean;
    composer: ComposerState;
    /** "Handled by {name}" in the header, once somebody handles it. */
    subtitle: string | null;
    /** Whether staff names may label messages in this thread. */
    showStaffNames: boolean;
    canRelease: boolean;
    canReassign: boolean;
};

export const ASSIGN_TO_REPLY = 'Assign to me to reply';

export const assignmentView = ({
    isSupport,
    isClient,
    assignment,
    assignee,
}: {
    isSupport: boolean;
    isClient: boolean;
    /** The staff-only payload; always null for a client. */
    assignment: AssignmentInput | null;
    /** Who handles the thread, as the conversation summary says (any viewer). */
    assignee: { id: number; name: string } | null;
}): AssignmentView => {
    if (!isSupport) {
        return {
            showCard: false,
            composer: { mode: 'enabled' },
            subtitle: null,
            showStaffNames: true,
            canRelease: false,
            canReassign: false,
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
            canRelease: false,
            canReassign: false,
        };
    }

    if (handledBy === null) {
        return {
            showCard: assignment.canClaim,
            composer: assignment.canClaim
                ? { mode: 'claim', note: ASSIGN_TO_REPLY }
                : {
                      mode: 'locked',
                      note: 'Nobody is handling this conversation yet.',
                  },
            subtitle: null,
            showStaffNames: false,
            canRelease: false,
            canReassign: assignment.canReassign,
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
        canRelease: assignment.canRelease,
        canReassign: assignment.canReassign,
    };
};

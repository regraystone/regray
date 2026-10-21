/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    ASSIGN_TO_REPLY,
    assignmentView,
} from '../../resources/js/lib/assignment.ts';

const MARA = { id: 5, name: 'Mara' };

/** The staff-only payload the server sends, as ConversationService builds it. */
const staff = ({
    assignee = null,
    isMine = false,
    canClaim,
    canRelease = false,
    canReassign = false,
}) => ({
    canClaim: canClaim ?? assignee === null,
    assignee,
    isMine,
    canRelease,
    canReassign,
});

describe('assignmentView', () => {
    it('leaves groups and direct chats alone', () => {
        const view = assignmentView({
            isSupport: false,
            isClient: false,
            assignment: null,
            assignee: null,
        });

        assert.equal(view.showCard, false);
        assert.deepEqual(view.composer, { mode: 'enabled' });
        assert.equal(view.subtitle, null);
    });

    it('shows the pool the card and a composer that asks them to claim first', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({}),
            assignee: null,
        });

        assert.equal(view.showCard, true);
        assert.deepEqual(view.composer, {
            mode: 'claim',
            note: ASSIGN_TO_REPLY,
        });
        assert.equal(view.showStaffNames, false);
    });

    it('locks the composer for staff who cannot claim an unassigned thread', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({ canClaim: false }),
            assignee: null,
        });

        assert.equal(view.showCard, false);
        assert.equal(view.composer.mode, 'locked');
    });

    it('lets the assignee write and release', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({
                assignee: MARA,
                isMine: true,
                canRelease: true,
            }),
            assignee: MARA,
        });

        assert.equal(view.showCard, false);
        assert.deepEqual(view.composer, { mode: 'enabled' });
        assert.equal(view.subtitle, 'Handled by Mara');
        assert.equal(view.canRelease, true);
        assert.equal(view.canReassign, false);
    });

    it('tells the admin who handles a thread they do not, and offers reassign', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({
                assignee: MARA,
                canRelease: true,
                canReassign: true,
            }),
            assignee: MARA,
        });

        assert.deepEqual(view.composer, {
            mode: 'locked',
            note: 'Mara is handling this conversation.',
        });
        assert.equal(view.canRelease, true);
        assert.equal(view.canReassign, true);
    });

    it('never shows the client the card, and lets them always write', () => {
        for (const assignee of [null, MARA]) {
            const view = assignmentView({
                isSupport: true,
                isClient: true,
                assignment: null,
                assignee,
            });

            assert.equal(view.showCard, false);
            assert.deepEqual(view.composer, { mode: 'enabled' });
            assert.equal(view.canRelease, false);
            assert.equal(view.canReassign, false);
        }
    });

    it('shows the client who handles the thread, and staff names only then', () => {
        const unassigned = assignmentView({
            isSupport: true,
            isClient: true,
            assignment: null,
            assignee: null,
        });
        const assigned = assignmentView({
            isSupport: true,
            isClient: true,
            assignment: null,
            assignee: MARA,
        });

        assert.equal(unassigned.subtitle, null);
        assert.equal(unassigned.showStaffNames, false);
        assert.equal(assigned.subtitle, 'Handled by Mara');
        assert.equal(assigned.showStaffNames, true);
    });
});

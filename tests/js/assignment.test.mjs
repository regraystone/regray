/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    ASSIGN_TO_REPLY,
    READ_ONLY,
    assignmentView,
} from '../../resources/js/lib/assignment.ts';

const MARA = { id: 5, name: 'Mara' };

/** The staff-only payload the server sends, as ConversationService builds it. */
const staff = ({ assignee = null, isMine = false, canClaim }) => ({
    canClaim: canClaim ?? assignee === null,
    assignee,
    isMine,
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

    it('shows the pool the card and a disabled composer that says to claim first', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({}),
            assignee: null,
        });

        assert.equal(view.showCard, true);
        assert.deepEqual(view.composer, {
            mode: 'locked',
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

    it('lets the assignee write', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({ assignee: MARA, isMine: true }),
            assignee: MARA,
        });

        assert.equal(view.showCard, false);
        assert.deepEqual(view.composer, { mode: 'enabled' });
        assert.equal(view.subtitle, 'Handled by Mara');
    });

    it('tells other staff who handles the thread', () => {
        const view = assignmentView({
            isSupport: true,
            isClient: false,
            assignment: staff({ assignee: MARA }),
            assignee: MARA,
        });

        assert.deepEqual(view.composer, {
            mode: 'locked',
            note: 'Mara is handling this conversation.',
        });
    });

    it('disables the composer for a reader who may not write, in any conversation', () => {
        for (const isSupport of [false, true]) {
            const view = assignmentView({
                isSupport,
                isClient: false,
                assignment: isSupport
                    ? staff({ assignee: MARA, isMine: true })
                    : null,
                assignee: isSupport ? MARA : null,
                canSend: false,
            });

            assert.deepEqual(view.composer, {
                mode: 'locked',
                note: READ_ONLY,
            });
        }
    });

    it('leaves the composer to the assignment until the thread says whether the reader may write', () => {
        const view = assignmentView({
            isSupport: false,
            isClient: false,
            assignment: null,
            assignee: null,
            canSend: undefined,
        });

        assert.deepEqual(view.composer, { mode: 'enabled' });
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

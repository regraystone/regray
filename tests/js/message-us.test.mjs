/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { messageUsPlacement } from '../../resources/js/lib/message-us.ts';

const client = { roles: ['client'], canMessage: true };

describe('the floating Message us button on the platform', () => {
    it('shows for a client on any page, at the bottom of the screen', () => {
        for (const component of ['home', 'profile/index', 'brand-profile']) {
            assert.equal(
                messageUsPlacement({ ...client, component }),
                'bottom-[max(1rem,env(safe-area-inset-bottom,0px))]',
            );
        }
    });

    it('is not shown on Inbox', () => {
        assert.equal(
            messageUsPlacement({ ...client, component: 'inbox' }),
            null,
        );
    });

    it('is not shown to staff, nor to a client who may not message', () => {
        assert.equal(
            messageUsPlacement({
                roles: ['sales'],
                canMessage: true,
                component: 'home',
            }),
            null,
        );
        assert.equal(
            messageUsPlacement({
                roles: ['client'],
                canMessage: false,
                component: 'home',
            }),
            null,
        );
    });

    it('sits higher on a page that pins its own bar to the bottom', () => {
        for (const component of [
            'saved',
            'inquire',
            'quote',
            'stone-profile',
            'furniture-profile',
        ]) {
            assert.notEqual(
                messageUsPlacement({ ...client, component }),
                'bottom-[max(1rem,env(safe-area-inset-bottom,0px))]',
                component,
            );
        }
    });
});

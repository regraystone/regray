/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { forgetBrowserStorage } from '../../resources/js/lib/browser-storage.ts';

/** A store holding a few keys, as `localStorage` or `sessionStorage` would. */
const storeWith = (entries) => {
    const values = new Map(Object.entries(entries));

    return {
        values,
        clear: () => values.clear(),
    };
};

describe('forgetting the browser storage on logout', () => {
    it('empties every store, the inquiry draft included', () => {
        const local = storeWith({
            'regray.inquiry-draft.v8:7': '{"version":8}',
            other: 'x',
        });
        const session = storeWith({ 'quote-prompt-dismissed': '1' });

        forgetBrowserStorage([local, session]);

        assert.equal(local.values.size, 0);
        assert.equal(session.values.size, 0);
    });

    it('still empties the next store when one refuses', () => {
        const refusing = {
            clear: () => {
                throw new Error('SecurityError');
            },
        };
        const session = storeWith({ 'quote-prompt-dismissed': '1' });

        assert.doesNotThrow(() => forgetBrowserStorage([refusing, session]));
        assert.equal(session.values.size, 0);
    });

    it('does nothing outside a browser', () => {
        assert.doesNotThrow(() => forgetBrowserStorage());
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    CONTINUE_REQUEST_LABEL,
    QUOTE_PROMPT_DISMISSED_KEY,
    REQUEST_QUOTE_LABEL,
    dismissQuotePrompt,
    isQuotePromptDismissed,
    quotePromptView,
} from '../../resources/js/lib/quote-prompt.ts';

/** One browser tab's `sessionStorage`. A new tab is a new one of these. */
const tabStorage = () => {
    const values = new Map();

    return {
        getItem: (key) => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
    };
};

const viewIn = (storage, { canRequestQuote = true, hasDraft = false } = {}) =>
    quotePromptView({
        canRequestQuote,
        hasDraft,
        isDismissed: isQuotePromptDismissed(storage),
    });

describe('the quote prompt', () => {
    it('shows only when the server says the client can request a quote', () => {
        assert.equal(viewIn(tabStorage()).isVisible, true);
        assert.equal(
            viewIn(tabStorage(), { canRequestQuote: false }).isVisible,
            false,
        );
    });

    it('hides after the × and writes the dismissal to sessionStorage', () => {
        const storage = tabStorage();

        dismissQuotePrompt(storage);

        assert.notEqual(storage.getItem(QUOTE_PROMPT_DISMISSED_KEY), null);
        assert.equal(viewIn(storage).isVisible, false);
    });

    it('shows again in a new session', () => {
        dismissQuotePrompt(tabStorage());

        assert.equal(viewIn(tabStorage()).isVisible, true);
    });

    it('switches the label when a draft exists', () => {
        assert.equal(viewIn(tabStorage()).label, REQUEST_QUOTE_LABEL);
        assert.equal(
            viewIn(tabStorage(), { hasDraft: true }).label,
            CONTINUE_REQUEST_LABEL,
        );
        assert.equal(REQUEST_QUOTE_LABEL, 'Request a quotation');
        assert.equal(CONTINUE_REQUEST_LABEL, 'Continue your request');
    });

    it('treats storage the browser blocks as not dismissed, without throwing', () => {
        const blocked = {
            getItem: () => {
                throw new Error('blocked');
            },
            setItem: () => {
                throw new Error('blocked');
            },
        };

        assert.doesNotThrow(() => dismissQuotePrompt(blocked));
        assert.equal(isQuotePromptDismissed(blocked), false);
        assert.equal(isQuotePromptDismissed(null), false);
    });
});

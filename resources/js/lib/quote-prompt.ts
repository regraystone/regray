/**
 * The "Request a quotation" pill on a client's Regray Team thread.
 *
 * Whether the client may be offered it is the server's answer
 * (`canRequestQuote`). What is decided here is the browser's half: the × hides
 * it for this tab only, which is what `sessionStorage` lasts for, and the label
 * follows a draft kept in this browser.
 */
export const QUOTE_PROMPT_DISMISSED_KEY = 'quote-prompt-dismissed';

export const REQUEST_QUOTE_LABEL = 'Request a quotation';

export const CONTINUE_REQUEST_LABEL = 'Continue your request';

type TabStore = Pick<Storage, 'getItem' | 'setItem'>;

/** This tab's storage, or null where the browser refuses to hand it over. */
const tabStore = (): TabStore | null => {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
};

/** Whether the × was pressed in this tab. Blocked storage counts as no. */
export const isQuotePromptDismissed = (
    store: TabStore | null = tabStore(),
): boolean => {
    try {
        return (store?.getItem(QUOTE_PROMPT_DISMISSED_KEY) ?? null) !== null;
    } catch {
        return false;
    }
};

/** Remember the × for this tab. Never throws. */
export const dismissQuotePrompt = (
    store: TabStore | null = tabStore(),
): void => {
    try {
        store?.setItem(QUOTE_PROMPT_DISMISSED_KEY, '1');
    } catch {
        // The pill still goes for this page; it just returns on a reload.
    }
};

export const quotePromptView = ({
    canRequestQuote,
    isDismissed,
    hasDraft,
}: {
    canRequestQuote: boolean;
    isDismissed: boolean;
    hasDraft: boolean;
}): { isVisible: boolean; label: string } => ({
    isVisible: canRequestQuote && !isDismissed,
    label: hasDraft ? CONTINUE_REQUEST_LABEL : REQUEST_QUOTE_LABEL,
});

type ClearableStore = Pick<Storage, 'clear'>;

/** This browser's stores, leaving out any the browser refuses to hand over. */
const browserStores = (): ClearableStore[] => {
    if (typeof window === 'undefined') {
        return [];
    }

    const stores: ClearableStore[] = [];

    for (const read of [
        () => window.localStorage,
        () => window.sessionStorage,
    ]) {
        try {
            stores.push(read());
        } catch {
            // Blocked storage holds nothing to forget.
        }
    }

    return stores;
};

/**
 * Forget everything this account left in the browser: the inquiry draft, the
 * dismissed quote prompt and anything else kept in `localStorage` or
 * `sessionStorage`, so the next person on this browser starts clean.
 *
 * Called once the logout has gone through, after the page swap: the inquiry
 * stepper writes its draft as it unmounts, and wiping before then would let it
 * put the draft straight back. Never throws.
 */
export const forgetBrowserStorage = (
    stores: ClearableStore[] = browserStores(),
): void => {
    for (const store of stores) {
        try {
            store.clear();
        } catch {
            // Nothing to be done about storage that will not take a write.
        }
    }
};

import { router } from '@inertiajs/react';

/**
 * A form left part-way to add what it needs on another admin page, and
 * brought back with it picked: a question's form goes to the Choices page for
 * a new choice, a service's form to the Questions page for a new question.
 *
 * Leaving keeps the form's values in this tab (sessionStorage) and opens the
 * other page's Add drawer (`?add=1`) with where to come back to (`back`).
 * Saving there brings the admin back (`?reopen=1&picked=<new id>`), and the
 * form opens again as it was, with the new item picked. Closing the drawer
 * there brings them back without one. Storage that is blocked only loses the
 * half-filled values: the form opens empty.
 */

const REOPEN = 'reopen';
const PICKED = 'picked';
const ADD = 'add';
const BACK = 'back';

const storageKey = (key: string) => `regray:handoff:${key}`;

/** A form's state to bring back: whether it was adding or editing, and its values. */
export type Handoff<TValues, TItem = unknown> = {
    /** The item being edited, or null while adding. */
    item: TItem | null;
    values: TValues;
};

/** Where the admin is, without what a handoff added to the address. */
const here = (): string => {
    const url = new URL(window.location.href);

    for (const param of [REOPEN, PICKED, ADD]) {
        url.searchParams.delete(param);
    }

    return `${url.pathname}${url.search}`;
};

/** Only an address on the dashboard is gone back to. */
export const isSafeBack = (back: string | null): back is string =>
    back !== null &&
    back.startsWith('/dashboard/') &&
    !back.startsWith('//') &&
    !back.includes('\\');

/**
 * Keep the form under `key` and open `addUrl`'s Add drawer, to come back
 * here with what is added there picked.
 */
export const leaveToAdd = <TValues, TItem>(
    key: string,
    handoff: Handoff<TValues, TItem>,
    addUrl: string,
): void => {
    try {
        window.sessionStorage.setItem(storageKey(key), JSON.stringify(handoff));
    } catch {
        // Blocked storage: the form comes back empty.
    }

    const back = new URL(here(), window.location.origin);
    back.searchParams.set(REOPEN, '1');
    const target = new URL(addUrl, window.location.origin);
    target.searchParams.set(ADD, '1');
    target.searchParams.set(BACK, `${back.pathname}${back.search}`);

    router.visit(`${target.pathname}${target.search}`);
};

/**
 * The form kept under `key`, when the admin has just come back to it, with
 * the id of what they added (null when they added nothing). Read once: the
 * address and the storage are cleared.
 */
export const takeHandoff = <TValues, TItem>(
    key: string,
): { handoff: Handoff<TValues, TItem>; picked: number | null } | null => {
    const params = new URLSearchParams(window.location.search);

    if (params.get(REOPEN) !== '1') {
        return null;
    }

    const picked = Number(params.get(PICKED));
    let handoff: Handoff<TValues, TItem> | null = null;

    try {
        const kept = window.sessionStorage.getItem(storageKey(key));
        window.sessionStorage.removeItem(storageKey(key));
        handoff = kept ? (JSON.parse(kept) as Handoff<TValues, TItem>) : null;
    } catch {
        handoff = null;
    }

    window.history.replaceState(window.history.state, '', here());

    return handoff === null
        ? null
        : {
              handoff,
              picked: Number.isInteger(picked) && picked > 0 ? picked : null,
          };
};

/**
 * Whether this page was opened to add something for a form on another page:
 * where to bring the admin back to, and whether to open the Add drawer now
 * (not when they are coming back to a form of this page's own).
 */
export const addRequest = (): { back: string; open: boolean } | null => {
    const params = new URLSearchParams(window.location.search);
    const back = params.get(BACK);

    return isSafeBack(back)
        ? { back, open: params.get(ADD) === '1' && params.get(REOPEN) !== '1' }
        : null;
};

/** Go back to the form that sent the admin here, adding nothing. */
export const returnWithout = (back: string): void => {
    router.visit(back);
};

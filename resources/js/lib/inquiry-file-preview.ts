/*
 * Opening an inquiry's file from its details page. Pure, with no imports, for
 * tests/js: the browser's window and fetch are handed in.
 *
 * A preview opens a blank tab inside the click, before anything is awaited,
 * because a pop-up blocker only lets through a tab opened by the click itself.
 * The file is then checked with HEAD, and only once it answers is the tab sent
 * to it, so a file that is gone shows its error on the page instead of in an
 * empty tab. The server decides what may be previewed (`previewable`); the
 * page keeps no list of its own.
 */

export const OPENING = 'Opening…';

export const POPUP_BLOCKED = 'Allow pop-ups for this site to preview files.';

export const FILE_GONE = 'This file is no longer available.';

export const NOT_PREVIEWABLE =
    "This format can't be previewed in the browser. You can download it.";

export const OPEN_FAILED = "Couldn't open the file. Try again.";

/** What a failed check says, by its status; null when the request never got an answer. */
export const previewErrorMessage = (status: number | null): string => {
    if (status === 404) {
        return FILE_GONE;
    }

    if (status === 415) {
        return NOT_PREVIEWABLE;
    }

    return OPEN_FAILED;
};

/** The same file, saved rather than shown. */
export const downloadUrl = (url: string): string =>
    `${url}${url.includes('?') ? '&' : '?'}download=1`;

/** The server's format label ("PDF", "JPG"), with a word for a file that has none. */
export const formatLabel = (format: string): string =>
    format.trim().toUpperCase() || 'FILE';

export type PreviewTab = {
    opener: unknown;
    location: { href: string };
    close: () => void;
};

export type PreviewCheck = (
    url: string,
) => Promise<{ ok: boolean; status: number }>;

/**
 * Open `url` in a new tab. Resolves to null once the tab is on the file, or
 * to the sentence the row shows when it could not be. The tab is opened
 * before the first await, so call this straight from the click.
 */
export const openPreview = async (
    url: string,
    openTab: () => PreviewTab | null,
    check: PreviewCheck,
): Promise<string | null> => {
    const tab = openTab();

    if (tab === null) {
        return POPUP_BLOCKED;
    }

    let status: number | null = null;

    try {
        const response = await check(url);

        if (response.ok) {
            // The file's tab cannot reach back into this page.
            tab.opener = null;
            tab.location.href = url;

            return null;
        }

        status = response.status;
    } catch {
        status = null;
    }

    tab.close();

    return previewErrorMessage(status);
};

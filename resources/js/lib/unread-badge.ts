/**
 * A word between the page and the layout that the unread count on the Message
 * link may have changed here, in this tab: messages were just read. Reading
 * moves the reader's pointer without telling their own inbox channel, so the
 * layout is told directly instead.
 */
const UNREAD_CHANGED = 'regray:unread-changed';

export const announceUnreadChanged = (): void => {
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event(UNREAD_CHANGED));
    }
};

export const onUnreadChanged = (callback: () => void): (() => void) => {
    window.addEventListener(UNREAD_CHANGED, callback);

    return () => window.removeEventListener(UNREAD_CHANGED, callback);
};

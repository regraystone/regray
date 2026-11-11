/*
 * How the bell writes things. Pure, with no imports, for tests/js.
 */

/** The badge: hidden (null) at none, "9+" above nine. */
export const badgeLabel = (count: number): string | null =>
    count <= 0 ? null : count > 9 ? '9+' : String(count);

const MINUTE = 60_000;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

/** "Just now", "5 min ago", "3 h ago", "2 days ago", then the date. */
export const relativeTime = (iso: string, now: number = Date.now()): string => {
    const at = new Date(iso).getTime();
    const elapsed = Math.max(0, now - at);

    if (elapsed < MINUTE) {
        return 'Just now';
    }

    if (elapsed < HOUR) {
        return `${Math.floor(elapsed / MINUTE)} min ago`;
    }

    if (elapsed < DAY) {
        return `${Math.floor(elapsed / HOUR)} h ago`;
    }

    const days = Math.floor(elapsed / DAY);

    if (days < 7) {
        return days === 1 ? '1 day ago' : `${days} days ago`;
    }

    return new Date(at).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
};

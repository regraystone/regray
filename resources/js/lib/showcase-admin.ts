/**
 * The admin Showcase page's own rules, kept apart from the page so they can
 * be tested: how a card's line and count read, and what archiving says.
 */

const plural = (count: number, one: string, many: string) =>
    `${count.toLocaleString('en-PH')} ${count === 1 ? one : many}`;

/** "Makati City · 2026", or the year alone when there is no location. */
export const showcaseSubtitle = (
    location: string,
    year: number | null,
): string =>
    [location.trim(), year === null ? '' : String(year)]
        .filter((part) => part !== '')
        .join(' · ');

/** "2 stones · 3 photos", "1 stone", or "No stones yet". */
export const showcaseCount = (stones: number, photos: number): string => {
    if (stones === 0) {
        return 'No stones yet';
    }

    return [
        plural(stones, 'stone', 'stones'),
        photos > 0 ? plural(photos, 'photo', 'photos') : null,
    ]
        .filter((part): part is string => part !== null)
        .join(' · ');
};

/** What archiving a showcase does, in words. */
export const archiveDescription = (): string =>
    'It leaves this list and its page goes offline. Saved showcases keep it.';

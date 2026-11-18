import type { Crumb } from '@/lib/catalogue-breadcrumbs';

export type ProfileUrls = {
    profile: string;
    settings: string;
    accountSecurity: string;
    security: string;
    schedule: string;
    scheduleBook: string;
    clientInformation: string;
};

/**
 * The workspace header's trail on the Profile hub and every page under it:
 * Regray / Dashboard / Profile, then the page's own place. Null on any other
 * page.
 *
 * Built by name rather than by URL segment: Account & Security keeps its old
 * URL beside /settings but is reached from Settings, so its trail reads
 * Profile / Settings / Account & Security, the way the reader walked, and
 * Security sits under it. Each ancestor carries its link; the page itself is
 * last and has none. A page under the Profile with no trail of its own reads
 * Regray / Dashboard / Profile.
 */
export const profileCrumbs = (
    url: string,
    urls: ProfileUrls,
): Crumb[] | null => {
    if (url !== urls.profile && !url.startsWith(`${urls.profile}/`)) {
        return null;
    }

    const root: Crumb[] = [{ label: 'Regray' }, { label: 'Dashboard' }];

    if (url === urls.profile) {
        return [...root, { label: 'Profile' }];
    }

    const profile: Crumb = { label: 'Profile', href: urls.profile };
    const settings: Crumb = { label: 'Settings', href: urls.settings };
    const trail = (...rest: Crumb[]): Crumb[] => [...root, profile, ...rest];

    if (url === urls.settings) {
        return trail({ label: 'Settings' });
    }

    if (url === urls.accountSecurity) {
        return trail(settings, { label: 'Account & Security' });
    }

    if (url === urls.security || url.startsWith(`${urls.security}/`)) {
        return trail(
            settings,
            { label: 'Account & Security', href: urls.accountSecurity },
            { label: 'Security' },
        );
    }

    if (url === urls.schedule) {
        return trail({ label: 'Schedule' });
    }

    if (url === urls.clientInformation) {
        return trail({ label: 'Client Information' });
    }

    if (url === urls.scheduleBook) {
        return trail(
            { label: 'Schedule', href: urls.schedule },
            { label: 'Book' },
        );
    }

    return trail();
};

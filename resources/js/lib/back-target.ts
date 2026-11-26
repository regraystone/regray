import type { Crumb } from '@/lib/catalogue-breadcrumbs';

/**
 * Pages that draw their own way back, so the workspace adds none: a
 * showcase's Return, an inquiry's Back to inquiries, and Inbox, whose open
 * conversation has its own back arrow on a phone.
 */
export const OWN_BACK_COMPONENTS = [
    'inbox',
    'showcase-profile',
    'inquiry-details',
];

/**
 * Where the Back button on a phone or tablet goes: the page one level up,
 * which is the nearest earlier crumb with a link (Saved / Stone goes to
 * Saved, Home / Brand to Home), or the landing page when the trail has none
 * (Saved goes Home). Null where there is no Back: on a landing page, and on a
 * page with its own. It goes up the trail rather than back through the
 * browser's history, which may lead out of Regray when the page was opened
 * from a link.
 */
export const backTarget = ({
    component,
    url,
    crumbs,
    landingUrls,
    landing,
}: {
    component: string;
    url: string;
    crumbs: Crumb[];
    landingUrls: string[];
    landing: string;
}): string | null => {
    const path = url.split('?')[0];

    if (OWN_BACK_COMPONENTS.includes(component) || landingUrls.includes(path)) {
        return null;
    }

    const parent = crumbs
        .slice(0, -1)
        .reverse()
        .find((crumb) => crumb.href && crumb.href.split('?')[0] !== path);

    return parent?.href ?? landing;
};

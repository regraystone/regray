import type { SocialPlatform } from '@/types/home';

/**
 * The admin Brands page's own rules, kept apart from the page so they can be
 * tested: the tags the "What it makes" input takes, how a brand's catalogue
 * is counted in words, and which sites a social link may be on. The slug
 * rules are shared with Projects (lib/slug.ts).
 */

/**
 * The tags typed so far, plus those in `input`: split at commas, trimmed,
 * empty ones and repeats (whatever their case) left out, and no more than
 * `max` in all.
 */
export const addTags = (
    current: string[],
    input: string,
    max: number,
): string[] => {
    const next = [...current];

    for (const part of input.split(',')) {
        const tag = part.trim();

        if (
            tag === '' ||
            next.length >= max ||
            next.some(
                (existing) => existing.toLowerCase() === tag.toLowerCase(),
            )
        ) {
            continue;
        }

        next.push(tag);
    }

    return next;
};

const plural = (count: number, one: string, many: string) =>
    `${count.toLocaleString('en-PH')} ${count === 1 ? one : many}`;

/** "75 stones · 6 furniture", "1 stone", or "No products yet". */
export const catalogueCount = (stones: number, furniture: number): string => {
    const parts = [
        stones > 0 ? plural(stones, 'stone', 'stones') : null,
        furniture > 0 ? plural(furniture, 'furniture', 'furniture') : null,
    ].filter((part): part is string => part !== null);

    return parts.length > 0 ? parts.join(' · ') : 'No products yet';
};

/**
 * What archiving a brand does, in words, from its counts: its page goes, and
 * its stones and furniture are hidden with it.
 */
export const archiveDescription = (
    stones: number,
    furniture: number,
): string => {
    const products = [
        stones > 0 ? plural(stones, 'stone', 'stones') : null,
        furniture > 0
            ? plural(furniture, 'piece of furniture', 'pieces of furniture')
            : null,
    ].filter((part): part is string => part !== null);

    const hidden =
        products.length === 0
            ? ''
            : ` Its ${products.join(' and ')} ${stones + furniture === 1 ? 'is' : 'are'} hidden from the catalogue too.`;

    return `It leaves this list and its page goes offline.${hidden} Saved items and past inquiries keep them.`;
};

/** A website as its domain: "https://www.cucinelube.it/" is "cucinelube.it". */
export const websiteDomain = (url: string): string => {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
};

/** The sites each network's links are on (SocialPlatform::hosts() on the server). */
export const SOCIAL_HOSTS: Record<SocialPlatform, string[]> = {
    facebook: ['facebook.com', 'fb.com'],
    twitter: ['twitter.com', 'x.com'],
    x: ['x.com', 'twitter.com'],
    instagram: ['instagram.com'],
    tiktok: ['tiktok.com'],
};

/**
 * Whether a link, with or without its https://, is on the network's own site
 * (subdomains such as www. included). Anything that is not a web address is
 * not.
 */
export const isSocialLinkFor = (platform: SocialPlatform, value: string) => {
    try {
        const url = new URL(
            /^[a-z][a-z0-9+.-]*:\/\//i.test(value) ? value : `https://${value}`,
        );
        const host = url.hostname.toLowerCase();

        return (
            ['http:', 'https:'].includes(url.protocol) &&
            SOCIAL_HOSTS[platform].some(
                (site) => host === site || host.endsWith(`.${site}`),
            )
        );
    } catch {
        return false;
    }
};

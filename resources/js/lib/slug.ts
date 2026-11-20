/**
 * Slugs, as the admin's Brands and Projects pages make them: the server's
 * own pattern (BrandValidationRules, ProjectValidationRules).
 */

/** A slug: lower-case letters and numbers, in words joined by single hyphens. */
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

/** The slug a name makes: "Cucine Lube" is "cucine-lube", "Façades & Co." is "facades-co". */
export const slugify = (name: string): string =>
    name
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/&/g, ' ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100)
        .replace(/-+$/g, '');

/**
 * The cities and municipalities a project can be in.
 *
 * Metro Manila is the National Capital Region's seventeen local government
 * units: sixteen cities, plus Pateros, which is the region's one municipality.
 * The spellings are the official ones, diacritics included, because these end up
 * on a delivery docket. Alphabetical, so the unfiltered list reads as a list.
 */
export const LOCATIONS: readonly string[] = [
    'Caloocan',
    'Las Piñas',
    'Makati',
    'Malabon',
    'Mandaluyong',
    'Manila',
    'Marikina',
    'Muntinlupa',
    'Navotas',
    'Parañaque',
    'Pasay',
    'Pasig',
    'Pateros',
    'Quezon City',
    'San Juan',
    'Taguig',
    'Valenzuela',
];

/**
 * Folds a name down to what a keyboard actually produces.
 *
 * Every accented name here is one somebody will type without its accent: Las
 * Pinas, Paranaque. NFD splits the accent into its own combining character, so
 * dropping the U+0300 to U+036F range leaves the plain letters underneath and an
 * unaccented search can find an accented name.
 */
const foldForSearch = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();

/**
 * The locations matching what has been typed.
 *
 * An empty query returns the whole list rather than nothing, so the field can be
 * opened and browsed as well as searched. Prefix matches come first and
 * substring matches after, which is what puts Makati at the top for "mak" while
 * still finding it for "ati".
 */
export const searchLocations = (query: string): string[] => {
    const needle = foldForSearch(query.trim());

    if (needle === '') {
        return [...LOCATIONS];
    }

    const fromStart: string[] = [];
    const fromInside: string[] = [];

    for (const location of LOCATIONS) {
        const haystack = foldForSearch(location);

        if (haystack.startsWith(needle)) {
            fromStart.push(location);
        } else if (haystack.includes(needle)) {
            fromInside.push(location);
        }
    }

    return [...fromStart, ...fromInside];
};

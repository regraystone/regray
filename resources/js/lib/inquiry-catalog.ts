import type { InquiryCatalog, InquirePageProps } from '@/types/inquiry';

/**
 * The answer a stone picked as extra information is given for its edge or finish.
 *
 * The "advise me" option when the database has one, which StoneSeeder and
 * EdgeProfileSeeder both write. Without one, the stones picked for a visit
 * were left with a blank answer, which the draft check refused on a form that
 * has no field to show it on — the visit could not be added. So the first
 * option still stands in when a list has no advisory entry.
 */
const placeholderAnswer = (
    options: Array<{ name: string; isAdvisory: boolean }>,
): string | null =>
    (options.find((option) => option.isAdvisory) ?? options[0])?.name ?? null;

/** Turn the page's props into what the flow reads. */
export const createInquiryCatalog = (
    props: InquirePageProps,
): InquiryCatalog => ({
    // A service no longer offered still reads back on the inquiry that has it.
    services: [
        ...props.services,
        ...(props.projects ?? []).flatMap((project) =>
            project.heldServices.filter(
                (held) =>
                    !props.services.some((service) => service.id === held.id),
            ),
        ),
    ],
    furnitureTypes: props.furnitureTypes,
    stones: props.stones,
    edgeProfiles: props.edgeProfiles,
    finishes: props.finishes,
    locations: props.locations,
    advisoryEdge: placeholderAnswer(props.edgeProfiles),
    advisoryFinish: placeholderAnswer(props.finishes),
});

/**
 * Folds a name down to what a keyboard actually produces.
 *
 * Every accented name in the locations is one somebody will type without its
 * accent: Las Pinas, Paranaque. NFD splits the accent into its own combining
 * character, so dropping the U+0300 to U+036F range leaves the plain letters
 * underneath and an unaccented search can find an accented name.
 */
const foldForSearch = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();

/**
 * The locations matching what has been typed.
 *
 * An empty query returns the whole list rather than nothing, so the field can be
 * opened and browsed as well as searched. Prefix matches come first and
 * substring matches after, which is what puts Makati at the top for "mak" while
 * still finding it for "ati".
 */
export const searchLocations = (
    locations: readonly string[],
    query: string,
): string[] => {
    const needle = foldForSearch(query.trim());

    if (needle === '') {
        return [...locations];
    }

    const fromStart: string[] = [];
    const fromInside: string[] = [];

    for (const location of locations) {
        const haystack = foldForSearch(location);

        if (haystack.startsWith(needle)) {
            fromStart.push(location);
        } else if (haystack.includes(needle)) {
            fromInside.push(location);
        }
    }

    return [...fromStart, ...fromInside];
};

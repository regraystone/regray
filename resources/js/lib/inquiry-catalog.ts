import { stonesNotOffered } from '@/lib/inquiry-flow';
import type { ServiceTypeOption } from '@/lib/inquiry-flow';
import type {
    InquiryCatalog,
    InquirePageProps,
    InquiryStoneOption,
} from '@/types/inquiry';

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

/**
 * Turn the page's props into what the flow reads. `stones` are the ones the page
 * has been sent so far, which is what lets a stone picked under one filter still
 * be drawn under another.
 */
export const createInquiryCatalog = (
    props: InquirePageProps,
    stones: InquiryStoneOption[],
): InquiryCatalog => {
    const serviceTypes = withHeldServices(props);
    const services = serviceTypes.flatMap((type) => type.services);

    return {
        serviceTypes,
        stones,
        /*
         * One rule for a restored draft's `pickedStones`, an edit's `heldStones`
         * and a change of service: the stones are those the page knows, and a
         * service the page does not offer fits none.
         */
        stonesNotOfferedFor: (stoneIds, serviceId) =>
            stonesNotOffered(
                stoneIds,
                stones,
                services.find(({ id }) => id === serviceId),
            ),
        edgeProfiles: props.edgeProfiles,
        finishes: props.finishes,
        locations: props.locations,
        advisoryEdge: placeholderAnswer(props.edgeProfiles),
        advisoryFinish: placeholderAnswer(props.finishes),
    };
};

/** The offered service types, with any service an inquiry being edited already has. */
const withHeldServices = (props: InquirePageProps): ServiceTypeOption[] =>
    // A service no longer offered still reads back on the inquiry that has it.
    (props.projects ?? []).reduce((types, { heldServiceType: held }) => {
        if (held === null) {
            return types;
        }

        const known = types.find((type) => type.id === held.id);

        if (known === undefined) {
            return [...types, held];
        }

        return types.map((type) =>
            type === known
                ? {
                      ...type,
                      services: [
                          ...type.services,
                          ...held.services.filter(
                              (service) =>
                                  !type.services.some(
                                      ({ id }) => id === service.id,
                                  ),
                          ),
                      ],
                  }
                : type,
        );
    }, props.serviceTypes);

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

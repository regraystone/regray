/** A brand page's collections, as the `collection` query param names them. */
export type BrandCollectionKind = 'all' | 'stones' | 'furniture';

export type BrandFilterOption = {
    value: string;
    label: string;
    bestSeller?: boolean;
};

/** The first select, All Categories first as the default. */
export const COLLECTION_OPTIONS: Array<{
    value: BrandCollectionKind;
    label: string;
}> = [
    { value: 'all', label: 'All Categories' },
    { value: 'stones', label: 'Natural Stones' },
    { value: 'furniture', label: 'Furniture' },
];

/** The second select's default, the same in both collections that have it. */
export const SECOND_FILTER_DEFAULT_LABEL = 'All Product Type';

export const toCollection = (
    value: string | null | undefined,
): BrandCollectionKind =>
    value === 'stones' || value === 'furniture' ? value : 'all';

/**
 * The second select for a collection, or null when it is hidden: All Categories
 * spans both kinds, so there is no one list of options to narrow by.
 */
export const secondFilter = (
    collection: BrandCollectionKind,
    options: {
        types?: BrandFilterOption[];
        furnitureTypes?: BrandFilterOption[];
    },
): {
    param: 'type' | 'furniture_type';
    ariaLabel: string;
    defaultLabel: string;
    options: BrandFilterOption[];
} | null => {
    if (collection === 'all') {
        return null;
    }

    return collection === 'furniture'
        ? {
              param: 'furniture_type',
              ariaLabel: 'Product type',
              defaultLabel: SECOND_FILTER_DEFAULT_LABEL,
              options: options.furnitureTypes ?? [],
          }
        : {
              param: 'type',
              ariaLabel: 'Stone type',
              defaultLabel: SECOND_FILTER_DEFAULT_LABEL,
              options: options.types ?? [],
          };
};

/** The query for a brand page. The second param only goes with its own collection. */
export const collectionQuery = ({
    collection,
    search,
    filter,
}: {
    collection: BrandCollectionKind;
    search: string;
    filter: string;
}): Record<string, string> => {
    const query: Record<string, string> = {};
    const trimmedSearch = search.trim();

    if (collection !== 'all') {
        query.collection = collection;
    }

    if (trimmedSearch !== '') {
        query.search = trimmedSearch;
    }

    const second = secondFilter(collection, {});

    if (second !== null && filter !== '') {
        query[second.param] = filter;
    }

    return query;
};

/** What the search box says it looks through. */
export const searchLabel = (collection: BrandCollectionKind): string =>
    ({
        all: 'Search products',
        stones: 'Search stones',
        furniture: 'Search furniture',
    })[collection];

/** The heading when a search or filter finds nothing. */
export const noResultsTitle = (collection: BrandCollectionKind): string =>
    ({
        all: 'No products found',
        stones: 'No stones found',
        furniture: 'No furniture found',
    })[collection];

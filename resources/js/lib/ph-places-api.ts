import {
    barangays as barangaysRoute,
    cities as citiesRoute,
    postalCodes as postalCodesRoute,
    provinces as provincesRoute,
    regions as regionsRoute,
    resolve as resolveRoute,
} from '@/routes/ph';
import type { AddressLevel, PhPlace, PhRegionOption } from '@/lib/ph-address';

/**
 * The address picker's searches (PhPlaceController), one level at a time
 * under its parent, a page at a time. `nextPage` is null on the last page.
 */
export type PlacePage<T extends PhPlace = PhPlace> = {
    data: T[];
    nextPage: number | null;
};

export type PhCityOption = PhPlace & { isCity: boolean };

const get = async <T>(url: string, signal?: AbortSignal): Promise<T> => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal,
    });

    if (!response.ok) {
        throw new Error(
            response.status === 429
                ? 'Too many searches at once. Wait a moment and try again.'
                : "We couldn't load the places. Try again.",
        );
    }

    return (await response.json()) as T;
};

const query = (
    q: string,
    page: number,
    parent: Record<string, string> = {},
): Record<string, string> => ({
    ...parent,
    ...(q.trim() === '' ? {} : { q: q.trim() }),
    ...(page > 1 ? { page: String(page) } : {}),
});

export const searchRegions = (
    q: string,
    page = 1,
    signal?: AbortSignal,
): Promise<PlacePage<PhRegionOption>> =>
    get(regionsRoute.url({ query: query(q, page) }), signal);

export const searchProvinces = (
    region: string,
    q: string,
    page = 1,
    signal?: AbortSignal,
): Promise<PlacePage> =>
    get(provincesRoute.url({ query: query(q, page, { region }) }), signal);

/** A province's cities, or with `province` null, the region's cities in no province. */
export const searchCities = (
    parent: { region: string; province: string | null },
    q: string,
    page = 1,
    signal?: AbortSignal,
): Promise<PlacePage<PhCityOption>> =>
    get(
        citiesRoute.url({
            query: query(
                q,
                page,
                parent.province === null
                    ? { region: parent.region }
                    : { province: parent.province },
            ),
        }),
        signal,
    );

export const searchBarangays = (
    city: string,
    q: string,
    page = 1,
    signal?: AbortSignal,
): Promise<PlacePage> =>
    get(barangaysRoute.url({ query: query(q, page, { city }) }), signal);

/** Every postal code of the city: one page, `code` and `name` both the code. */
export const postalCodesFor = (
    city: string,
    signal?: AbortSignal,
): Promise<PlacePage> => get(postalCodesRoute.url({ query: { city } }), signal);

/**
 * The address a point on the map stands for, worked out on the server from
 * Mapbox's names for it (lowest first) and its postal code: Mapbox names no
 * PSGC region, so the city is found first and the region is the city's.
 * `missed` names the levels nothing matched, for the reader to pick.
 */
export type ResolvedMapPlace = {
    region: PhPlace | null;
    province: PhPlace | null;
    noProvince: boolean;
    city: PhPlace | null;
    barangay: PhPlace | null;
    postalCode: string;
    needsPostalCode: boolean;
    missed: Exclude<AddressLevel, 'postalCode'>[];
};

export const resolveMapPlace = (
    names: string[],
    postalCode: string | null,
    signal?: AbortSignal,
): Promise<ResolvedMapPlace> =>
    get(
        resolveRoute.url({
            query: {
                names: names.slice(0, 8),
                ...(postalCode !== null && /^[0-9]{4}$/.test(postalCode)
                    ? { postalCode }
                    : {}),
            },
        }),
        signal,
    );

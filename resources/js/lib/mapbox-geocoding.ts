import { PH_BOUNDS } from '@/lib/ph-address';

/*
 * Mapbox's Geocoding API (v6), kept to the Philippines: the search box's
 * suggestions, the place under a dropped pin, and where a picked place is.
 * The public token is VITE_MAPBOX_PUBLIC_TOKEN; without one the picker has
 * no map and these are never called.
 */

const ENDPOINT = 'https://api.mapbox.com/search/geocode/v6';

export const mapboxToken = (): string =>
    (import.meta.env.VITE_MAPBOX_PUBLIC_TOKEN as string | undefined)?.trim() ??
    '';

/**
 * One result, with the names of every place it sits in, lowest first. Which
 * of Mapbox's levels is a PSGC province or city differs across the country,
 * so the picker tries every name at every level (ph-address.ts matchAnyName).
 */
export type GeocodeResult = {
    id: string;
    /** "Bel-Air, Makati, Metro Manila". */
    label: string;
    lat: number;
    lng: number;
    names: string[];
    postalCode: string | null;
};

const CONTEXT_LEVELS = [
    'neighborhood',
    'locality',
    'place',
    'district',
    'region',
] as const;

type Feature = {
    id: string;
    geometry: { coordinates: [number, number] };
    properties: {
        full_address?: string;
        name?: string;
        place_formatted?: string;
        context?: Record<string, { name?: string } | undefined>;
    };
};

const toResult = (feature: Feature): GeocodeResult => {
    const context = feature.properties.context ?? {};
    const [lng, lat] = feature.geometry.coordinates;

    return {
        id: feature.id,
        label:
            feature.properties.full_address ??
            [feature.properties.name, feature.properties.place_formatted]
                .filter(Boolean)
                .join(', '),
        lat,
        lng,
        names: [
            ...new Set(
                [
                    feature.properties.name,
                    ...CONTEXT_LEVELS.map((level) => context[level]?.name),
                ].filter(
                    (name): name is string =>
                        typeof name === 'string' && name.trim() !== '',
                ),
            ),
        ],
        postalCode: context.postcode?.name ?? null,
    };
};

const geocode = async (
    path: 'forward' | 'reverse',
    params: Record<string, string>,
    signal?: AbortSignal,
): Promise<GeocodeResult[]> => {
    const url = new URL(`${ENDPOINT}/${path}`);

    for (const [key, value] of Object.entries({
        ...params,
        country: 'PH',
        language: 'en',
        access_token: mapboxToken(),
    })) {
        url.searchParams.set(key, value);
    }

    const response = await fetch(url, { signal });

    if (!response.ok) {
        throw new Error("We couldn't search the map. Try again.");
    }

    const body = (await response.json()) as { features?: Feature[] };

    return (body.features ?? []).map(toResult);
};

/** Suggestions for what is typed in the map's search box. */
export const searchMap = (
    text: string,
    signal?: AbortSignal,
): Promise<GeocodeResult[]> =>
    geocode(
        'forward',
        {
            q: text,
            autocomplete: 'true',
            limit: '5',
            bbox: PH_BOUNDS.join(','),
        },
        signal,
    );

/** Where a picked place is, from its names, lowest first: "Bel-Air, City of Makati, …". */
export const locatePlace = async (
    names: string[],
    signal?: AbortSignal,
): Promise<GeocodeResult | null> =>
    (
        await geocode(
            'forward',
            {
                q: names.join(', '),
                limit: '1',
                bbox: PH_BOUNDS.join(','),
            },
            signal,
        )
    )[0] ?? null;

/** The places under a dropped pin. */
export const placeAt = async (
    lat: number,
    lng: number,
    signal?: AbortSignal,
): Promise<GeocodeResult | null> =>
    (
        await geocode(
            'reverse',
            { latitude: String(lat), longitude: String(lng), limit: '1' },
            signal,
        )
    )[0] ?? null;

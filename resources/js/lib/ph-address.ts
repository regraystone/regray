import { z } from 'zod';

/*
 * An address in the Philippines as every page holds one: each level a PSGC
 * code with its name, the postal code and the map pin. No street. The address
 * picker (components/address-picker.tsx) fills one; the address book and the
 * inquiry keep a copy (App\Data\PhAddress, whose payload is
 * `PhAddressPayload`).
 *
 * Only zod is imported, so the tests under tests/js run this file straight
 * under Node.
 */

/** One place of one level, as the /api/ph searches send it. */
export type PhPlace = { code: string; name: string };

/** A region, with what the picker asks under it. */
export type PhRegionOption = PhPlace & {
    designation: string;
    /** False for the National Capital Region, which has no provinces. */
    hasProvinces: boolean;
    /** True where some cities sit in no province (Isabela City, Cotabato City). */
    hasCitiesWithoutProvince: boolean;
};

/** An address as App\Data\PhAddress::toPayload() sends it. */
export type PhAddressPayload = {
    regionCode: string;
    regionName: string;
    provinceCode: string | null;
    provinceName: string | null;
    cityCode: string;
    cityName: string;
    barangayCode: string;
    barangayName: string;
    postalCode: string | null;
    lat: number | null;
    lng: number | null;
    line: string;
};

/**
 * The address picker's value: a level not yet picked is null. `noProvince` is
 * the "Not in a province" choice, for a region with no provinces or a city in
 * none; the province is then null on purpose rather than unanswered.
 */
export type AddressValue = {
    region: PhPlace | null;
    province: PhPlace | null;
    noProvince: boolean;
    city: PhPlace | null;
    barangay: PhPlace | null;
    postalCode: string;
    lat: number | null;
    lng: number | null;
    /**
     * Whether the city has postal codes to choose from, once the picker has
     * asked; unknown is taken as yes. A few cities have none listed.
     */
    needsPostalCode?: boolean;
};

/** The levels, top down, as the picker asks them. */
export const ADDRESS_LEVELS = [
    'region',
    'province',
    'city',
    'barangay',
    'postalCode',
] as const;

export type AddressLevel = (typeof ADDRESS_LEVELS)[number];

export const ADDRESS_LEVEL_LABELS: Record<AddressLevel, string> = {
    region: 'Region',
    province: 'Province',
    city: 'City or municipality',
    barangay: 'Barangay',
    postalCode: 'Postal code',
};

/** The Philippines, for the map's bounds and the geocoder's bbox: [west, south, east, north]. */
export const PH_BOUNDS: [number, number, number, number] = [
    116.0, 4.2, 127.0, 21.3,
];

export const emptyAddress = (): AddressValue => ({
    region: null,
    province: null,
    noProvince: false,
    city: null,
    barangay: null,
    postalCode: '',
    lat: null,
    lng: null,
});

/**
 * Pick `place` at `level` and clear every level under it, since a barangay
 * of the old city is no barangay of the new one. Picking the same place again
 * keeps what is below. The pin is kept: it is where the reader put it.
 */
export const withLevel = (
    value: AddressValue,
    level: Exclude<AddressLevel, 'postalCode'>,
    place: PhPlace | null,
    options: { noProvince?: boolean } = {},
): AddressValue => {
    const same =
        level === 'province'
            ? value.province?.code === place?.code &&
              value.noProvince === (options.noProvince ?? false)
            : value[level]?.code === place?.code;

    if (same) {
        return value;
    }

    const cleared = clearBelow(value, level);

    return level === 'province'
        ? {
              ...cleared,
              province: place,
              noProvince: options.noProvince ?? false,
          }
        : { ...cleared, [level]: place };
};

/** Every level under `level` emptied; `level` itself and those above are kept. */
export const clearBelow = (
    value: AddressValue,
    level: AddressLevel,
): AddressValue => {
    const index = ADDRESS_LEVELS.indexOf(level);
    const next = { ...value };

    for (const below of ADDRESS_LEVELS.slice(index + 1)) {
        if (below === 'postalCode') {
            next.postalCode = '';
            delete next.needsPostalCode;
        } else if (below === 'province') {
            next.province = null;
            next.noProvince = false;
        } else {
            next[below] = null;
        }
    }

    return next;
};

/**
 * A place name folded for comparing: lowercase, no accents, without the
 * words a name is written with or without ("City of", "Barangay", "Pob."),
 * punctuation as spaces. The same as App\Support\PhPlaceName::fold(), so
 * "City of Las Piñas" and "Las Pinas" are the same place.
 */
export const foldPlaceName = (name: string): string =>
    name
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\(.*?\)/g, ' ')
        .replace(
            /\b(city of|province of|municipality of|barangay|brgy\.?|pob\.?|poblacion)(?=\W|$)/g,
            ' ',
        )
        .replace(/\bcity\b/g, ' ')
        .replace(/\bsta\.?(?=\s|$)/g, 'santa')
        .replace(/\bsto\.?(?=\s|$)/g, 'santo')
        .replace(/[^a-z0-9]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

export const placeNamesMatch = (a: string, b: string): boolean => {
    const left = foldPlaceName(a);

    return left !== '' && left === foldPlaceName(b);
};

/**
 * The option a geocoder's name stands for: the one whose folded name is the
 * same, else the only one that contains it or is contained by it. Null when
 * none, or more than one, could be meant: the reader then picks it.
 */
export const matchPlace = <T extends PhPlace>(
    options: readonly T[],
    name: string | null | undefined,
): T | null => {
    const wanted = foldPlaceName(name ?? '');

    if (wanted === '') {
        return null;
    }

    const exact = options.filter(
        (option) => foldPlaceName(option.name) === wanted,
    );

    if (exact.length === 1) {
        return exact[0];
    }

    if (exact.length > 1) {
        return null;
    }

    const partial = options.filter((option) => {
        const folded = foldPlaceName(option.name);

        return (
            folded !== '' &&
            (folded.includes(wanted) || wanted.includes(folded))
        );
    });

    return partial.length === 1 ? partial[0] : null;
};

/** What maps and people call a region, where the PSGC calls it otherwise. */
const REGION_ALIASES: Record<string, string> = {
    'metro manila': 'National Capital Region',
    'metro mla': 'National Capital Region',
    ncr: 'National Capital Region',
    'cordillera administrative region': 'CAR',
    cordillera: 'CAR',
    bangsamoro: 'BARMM',
    'autonomous region in muslim mindanao': 'BARMM',
    armm: 'BARMM',
};

/**
 * The region a map's name stands for, by its name, an alias ("Metro Manila"),
 * or its designation ("Region IV-A").
 */
export const matchRegion = <T extends PhRegionOption>(
    regions: readonly T[],
    name: string | null | undefined,
): T | null => {
    const alias = REGION_ALIASES[foldPlaceName(name ?? '')];

    return (
        matchPlace(regions, alias ?? name) ??
        matchPlace(
            regions.map((region) => ({ ...region, name: region.designation })),
            name,
        )
    );
};

/**
 * The first of a map's names (lowest level first) that stands for one of
 * `options`, or null when none does.
 */
export const matchAnyName = <T extends PhPlace>(
    options: readonly T[],
    names: readonly string[],
): T | null => {
    for (const name of names) {
        const match = matchPlace(options, name);

        if (match !== null) {
            return match;
        }
    }

    return null;
};

/**
 * "Barangay, City, Province, Region, Postal code", leaving out a level the
 * address has none of. The same as App\Data\PhAddress::line().
 */
export const formatAddressLine = (
    address: Pick<
        PhAddressPayload,
        | 'barangayName'
        | 'cityName'
        | 'provinceName'
        | 'regionName'
        | 'postalCode'
    >,
): string =>
    [
        address.barangayName,
        address.cityName,
        address.provinceName,
        address.regionName,
        address.postalCode,
    ]
        .filter(
            (part): part is string =>
                typeof part === 'string' && part.trim() !== '',
        )
        .join(', ');

/** The picker's value read as a line, for Review before it is sent. */
export const addressValueLine = (value: AddressValue): string =>
    formatAddressLine({
        barangayName: value.barangay?.name ?? '',
        cityName: value.city?.name ?? '',
        provinceName: value.province?.name ?? null,
        regionName: value.region?.name ?? '',
        postalCode: value.postalCode,
    });

/**
 * How the work reaches the client, as every page words it, the same as
 * App\Models\Inquiry::fulfillmentSummary(): "Pickup", "Delivery to …", or
 * "Installation at …" for a service with no method to choose (null method).
 */
export const fulfillmentSummary = (
    deliveryMethod: string | null,
    addressLine: string | null,
): string => {
    if (deliveryMethod === 'pickup') {
        return 'Pickup';
    }

    const line =
        addressLine === null || addressLine === ''
            ? 'an address not given'
            : addressLine;

    return deliveryMethod === 'delivery'
        ? `Delivery to ${line}`
        : `Installation at ${line}`;
};

/** A stored address as the picker holds it, to edit or start from. */
export const addressFromPayload = (
    payload: PhAddressPayload,
): AddressValue => ({
    region: { code: payload.regionCode, name: payload.regionName },
    province:
        payload.provinceCode === null
            ? null
            : { code: payload.provinceCode, name: payload.provinceName ?? '' },
    noProvince: payload.provinceCode === null,
    city: { code: payload.cityCode, name: payload.cityName },
    barangay: { code: payload.barangayCode, name: payload.barangayName },
    postalCode: payload.postalCode ?? '',
    lat: payload.lat,
    lng: payload.lng,
});

/** The fields the server reads (PhAddressService::FIELDS), as PSGC codes. */
export type AddressRequest = {
    regionCode: string;
    provinceCode: string;
    cityCode: string;
    barangayCode: string;
    postalCode: string;
    lat: string;
    lng: string;
};

/** The picker's value in the server's field names; a level not picked is ''. */
export const addressToRequest = (value: AddressValue): AddressRequest => ({
    regionCode: value.region?.code ?? '',
    provinceCode: value.noProvince ? '' : (value.province?.code ?? ''),
    cityCode: value.city?.code ?? '',
    barangayCode: value.barangay?.code ?? '',
    postalCode: value.postalCode,
    lat: value.lat === null ? '' : value.lat.toFixed(6),
    lng: value.lng === null ? '' : value.lng.toFixed(6),
});

/** Whether the pin is inside the Philippines (PhAddressService::BOUNDS). */
export const pinInPhilippines = (lat: number, lng: number): boolean =>
    lat >= PH_BOUNDS[1] &&
    lat <= PH_BOUNDS[3] &&
    lng >= PH_BOUNDS[0] &&
    lng <= PH_BOUNDS[2];

/**
 * The picker's rules, as PhAddressService::check() words them: every level
 * down to the barangay, the province unless "Not in a province", the postal
 * code when the city has any (`needsPostalCode`, or `postalRequired` to
 * say so here) and a pin inside the Philippines when there is one.
 */
export const addressSchema = (options: { postalRequired?: boolean } = {}) =>
    z
        .object({
            region: z.object({ code: z.string(), name: z.string() }).nullable(),
            province: z
                .object({ code: z.string(), name: z.string() })
                .nullable(),
            noProvince: z.boolean(),
            city: z.object({ code: z.string(), name: z.string() }).nullable(),
            barangay: z
                .object({ code: z.string(), name: z.string() })
                .nullable(),
            postalCode: z.string(),
            lat: z.number().nullable(),
            lng: z.number().nullable(),
            needsPostalCode: z.boolean().optional(),
        })
        .superRefine((value, ctx) => {
            const issue = (path: AddressLevel | 'pin', message: string) =>
                ctx.addIssue({ code: 'custom', path: [path], message });

            if (value.region === null) {
                issue('region', 'Choose the region.');

                return;
            }

            if (value.province === null && !value.noProvince) {
                issue('province', 'Choose the province.');
            }

            if (value.city === null) {
                issue('city', 'Choose the city or municipality.');
            } else if (value.barangay === null) {
                issue('barangay', 'Choose the barangay.');
            }

            if (value.city !== null && value.postalCode === '') {
                if (
                    (options.postalRequired ?? value.needsPostalCode) !== false
                ) {
                    issue('postalCode', 'Choose the postal code.');
                }
            } else if (
                value.postalCode !== '' &&
                !/^[0-9]{4}$/.test(value.postalCode)
            ) {
                issue('postalCode', 'Choose a postal code from the list.');
            }

            if ((value.lat === null) !== (value.lng === null)) {
                issue('pin', 'Put the pin on the map again.');
            } else if (
                value.lat !== null &&
                value.lng !== null &&
                !pinInPhilippines(value.lat, value.lng)
            ) {
                issue('pin', 'Put the pin somewhere in the Philippines.');
            }
        });

/** Whether every level is picked, so the address could be sent. */
export const isAddressComplete = (value: AddressValue | null): boolean =>
    value !== null && addressSchema().safeParse(value).success;

/* ------------------------------------------------------------------ */
/* The address book                                                     */
/* ------------------------------------------------------------------ */

/*
 * The address book's rules (Profile → Addresses), as SaveClientAddressRequest
 * words them, and the server's complaints put back on the picker's fields.
 */

export const LABEL_MAX = 50;

type PickerField =
    | 'region'
    | 'province'
    | 'city'
    | 'barangay'
    | 'postalCode'
    | 'pin';

/** The server's `address.*` fields, as the picker's fields. */
const SERVER_FIELDS: Record<string, PickerField> = {
    regionCode: 'region',
    provinceCode: 'province',
    cityCode: 'city',
    barangayCode: 'barangay',
    postalCode: 'postalCode',
    lat: 'pin',
    lng: 'pin',
};

/**
 * What is wrong with the address before it is sent: the label, and each level
 * of the address the picker has not got (ph-address.ts addressSchema).
 */
export const savedAddressProblems = (entry: {
    label: string;
    address: AddressValue | null;
}): {
    label: string | undefined;
    address: Partial<Record<PickerField, string>>;
} => {
    const label = entry.label.trim();
    const address: Partial<Record<PickerField, string>> = {};

    if (entry.address === null) {
        address.region = 'Choose the region.';
    } else {
        const parsed = addressSchema().safeParse(entry.address);

        if (!parsed.success) {
            for (const issue of parsed.error.issues) {
                const field = issue.path[0] as PickerField;

                address[field] ??= issue.message;
            }
        }
    }

    return {
        label:
            label === ''
                ? 'Give the address a label.'
                : label.length > LABEL_MAX
                  ? `Keep the label to ${LABEL_MAX} characters or fewer.`
                  : undefined,
        address,
    };
};

/**
 * The server's complaints about `{prefix}.regionCode` and the rest, on the
 * picker's fields. `projects.0.address` is a project's address.
 */
export const pickerErrors = (
    errors: Record<string, string>,
    prefix = 'address',
): Partial<Record<PickerField, string>> => {
    const mapped: Partial<Record<PickerField, string>> = {};

    for (const [key, message] of Object.entries(errors)) {
        if (!key.startsWith(`${prefix}.`)) {
            continue;
        }

        const field = SERVER_FIELDS[key.slice(prefix.length + 1)];

        if (field !== undefined) {
            mapped[field] ??= message;
        }
    }

    return mapped;
};

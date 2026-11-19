import {
    Component,
    lazy,
    Suspense,
    useCallback,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import PlaceCombobox from '@/components/address/place-combobox';
import FormField from '@/components/form-field';
import { Skeleton } from '@/components/ui/skeleton';
import {
    locatePlace,
    mapboxToken,
    placeAt,
    type GeocodeResult,
} from '@/lib/mapbox-geocoding';
import {
    ADDRESS_LEVEL_LABELS,
    emptyAddress,
    withLevel,
    type AddressLevel,
    type AddressValue,
    type PhPlace,
    type PhRegionOption,
} from '@/lib/ph-address';
import {
    postalCodesFor,
    resolveMapPlace,
    searchBarangays,
    searchCities,
    searchProvinces,
    searchRegions,
} from '@/lib/ph-places-api';

const AddressMap = lazy(() => import('@/components/address/address-map'));

/** "Not in a province": a region's city that sits in none. */
const NO_PROVINCE = 'none';

/** The 17 regions, asked once per page load: every picker reads the same. */
let regionsRequest: Promise<PhRegionOption[]> | null = null;

const loadRegions = (): Promise<PhRegionOption[]> => {
    regionsRequest ??= searchRegions('', 1)
        .then((page) => page.data)
        .catch((error: unknown) => {
            regionsRequest = null;
            throw error;
        });

    return regionsRequest;
};

/** How close the map comes for the lowest level picked. */
const ZOOM: Record<'region' | 'province' | 'city' | 'barangay', number> = {
    region: 7,
    province: 9,
    city: 12,
    barangay: 15,
};

export type AddressPickerErrors = Partial<Record<AddressLevel | 'pin', string>>;

type AddressPickerProps = {
    /** Prefixes every control's id, so two pickers on a page never share one. */
    idPrefix: string;
    value: AddressValue | null;
    onChange: (value: AddressValue) => void;
    errors?: AddressPickerErrors;
    /** False leaves the map out: the fields alone. */
    showMap?: boolean;
};

/** Shows the fields alone when the map cannot load (an old browser, no network). */
class MapBoundary extends Component<
    { onError: () => void; children: ReactNode },
    { failed: boolean }
> {
    state = { failed: false };

    static getDerivedStateFromError() {
        return { failed: true };
    }

    componentDidCatch() {
        this.props.onError();
    }

    render() {
        return this.state.failed ? null : this.props.children;
    }
}

/**
 * An address in the Philippines picked from the PSGC, never typed: Region,
 * Province (skipped where the region has none), City or municipality,
 * Barangay, then Postal code (picked for the reader when the city has one, or
 * when it is the map's). Changing a level clears the levels under it.
 *
 * Before the fields, a map with a draggable pin: on their left from `lg`,
 * above them below it. Moving the pin reads the place under it and picks each level whose
 * name matches; a level that does not is highlighted for the reader to pick.
 * Picking a level flies the map there and puts the pin on it. Without a
 * Mapbox token, or a browser that can draw the map, the fields are enough.
 */
const AddressPicker = ({
    idPrefix,
    value: given,
    onChange,
    errors = {},
    showMap = true,
}: AddressPickerProps) => {
    const value = given ?? emptyAddress();
    const valueRef = useRef(value);
    const [regions, setRegions] = useState<PhRegionOption[]>([]);
    const [postalCodes, setPostalCodes] = useState<PhPlace[] | null>(null);
    const [mismatch, setMismatch] = useState<
        Partial<Record<AddressLevel, string>>
    >({});
    const [mapUnavailable, setMapUnavailable] = useState(false);
    const [flyTo, setFlyTo] = useState<{
        lat: number;
        lng: number;
        zoom: number;
    } | null>(null);
    const [reading, setReading] = useState(false);
    const readSequence = useRef(0);

    useEffect(() => {
        valueRef.current = value;
    });

    const hasMap = showMap && mapboxToken() !== '' && !mapUnavailable;

    useEffect(() => {
        let live = true;

        loadRegions()
            .then((loaded) => live && setRegions(loaded))
            .catch(() => undefined);

        return () => {
            live = false;
        };
    }, []);

    const region = regions.find(({ code }) => code === value.region?.code);
    const regionHasProvinces = region?.hasProvinces ?? !value.noProvince;
    const cityCode = value.city?.code ?? null;

    const set = useCallback(
        (next: AddressValue) => {
            valueRef.current = next;
            onChange(next);
        },
        [onChange],
    );

    // The city's postal codes: the only one is picked; none means none is asked.
    useEffect(() => {
        if (cityCode === null) {
            setPostalCodes(null);

            return;
        }

        const controller = new AbortController();

        postalCodesFor(cityCode, controller.signal)
            .then(({ data }) => {
                setPostalCodes(data);
                const current = valueRef.current;

                if (current.city?.code !== cityCode) {
                    return;
                }

                const needsPostalCode = data.length > 0;
                const postalCode =
                    data.length === 1
                        ? data[0].code
                        : data.some(({ code }) => code === current.postalCode)
                          ? current.postalCode
                          : '';

                if (
                    current.needsPostalCode !== needsPostalCode ||
                    current.postalCode !== postalCode
                ) {
                    set({ ...current, needsPostalCode, postalCode });
                }
            })
            .catch(() => setPostalCodes(null));

        return () => controller.abort();
    }, [cityCode, set]);

    /** Fly the map to the lowest level picked, and put the pin there. */
    const locate = (next: AddressValue) => {
        if (!hasMap) {
            return;
        }

        const lowest = next.barangay
            ? 'barangay'
            : next.city
              ? 'city'
              : next.province
                ? 'province'
                : next.region
                  ? 'region'
                  : null;

        if (lowest === null) {
            return;
        }

        const names = [
            next.barangay?.name,
            next.city?.name,
            next.province?.name,
            next.region?.name,
        ].filter((name): name is string => Boolean(name));
        const sequence = ++readSequence.current;

        locatePlace(names)
            .then((found) => {
                if (found === null || sequence !== readSequence.current) {
                    return;
                }

                setFlyTo({
                    lat: found.lat,
                    lng: found.lng,
                    zoom: ZOOM[lowest],
                });

                if (lowest === 'barangay' || lowest === 'city') {
                    set({
                        ...valueRef.current,
                        lat: found.lat,
                        lng: found.lng,
                    });
                }
            })
            .catch(() => undefined);
    };

    const pick = (
        level: 'region' | 'province' | 'city' | 'barangay',
        place: PhPlace | null,
        options: { noProvince?: boolean } = {},
    ) => {
        let next = withLevel(value, level, place, options);

        if (level === 'region' && place !== null) {
            const shape = regions.find(({ code }) => code === place.code);

            if (shape !== undefined && !shape.hasProvinces) {
                next = { ...next, noProvince: true };
            }
        }

        setMismatch((current) => {
            const kept = { ...current };

            delete kept[level];

            return kept;
        });
        set(next);
        locate(next);
    };

    /** The pin moved: pick the places under it (ResolveMapPlaceAction on the server). */
    const readPin = async (
        lat: number,
        lng: number,
        result: GeocodeResult | null,
    ) => {
        const sequence = ++readSequence.current;
        const pinned = { ...valueRef.current, lat, lng };

        set(pinned);
        setReading(true);

        try {
            const place = result ?? (await placeAt(lat, lng));
            const notListed = (level: AddressLevel) =>
                `The map's place isn't on the list of ${ADDRESS_LEVEL_LABELS[level].toLowerCase()}s here. Choose it from the list.`;

            if (sequence !== readSequence.current) {
                return;
            }

            if (place === null || place.names.length === 0) {
                setMismatch({ region: notListed('region') });

                return;
            }

            const found = await resolveMapPlace(place.names, place.postalCode);

            if (sequence !== readSequence.current) {
                return;
            }

            setMismatch(
                Object.fromEntries(
                    found.missed.map((level) => [level, notListed(level)]),
                ),
            );
            set({
                ...emptyAddress(),
                lat,
                lng,
                region: found.region,
                province: found.province,
                noProvince: found.noProvince,
                city: found.city,
                barangay: found.barangay,
                postalCode: found.postalCode,
                needsPostalCode: found.needsPostalCode,
            });
        } catch {
            // Never silently: the reader is told to pick the places by hand.
            if (sequence === readSequence.current) {
                setMismatch({
                    region: "We couldn't read the place on the map. Choose it from the list.",
                });
            }
        } finally {
            if (sequence === readSequence.current) {
                setReading(false);
            }
        }
    };

    const id = (level: string) => `${idPrefix}-${level}`;
    const message = (level: AddressLevel) => errors[level] ?? mismatch[level];

    return (
        <div
            className={
                hasMap
                    ? 'grid min-w-0 gap-6 lg:grid-cols-2'
                    : 'grid min-w-0 gap-6'
            }
        >
            {/* The map first: finding the place on it fills the fields below (beside it from lg). */}
            {hasMap ? (
                <MapBoundary onError={() => setMapUnavailable(true)}>
                    <Suspense
                        fallback={
                            <Skeleton className="h-64 w-full lg:h-full lg:min-h-80" />
                        }
                    >
                        <AddressMap
                            lat={value.lat}
                            lng={value.lng}
                            flyTo={flyTo}
                            onPinMove={(lat, lng, _source, result) => {
                                void readPin(lat, lng, result);
                            }}
                            onUnavailable={() => setMapUnavailable(true)}
                        />
                    </Suspense>
                </MapBoundary>
            ) : null}
            <div className="grid min-w-0 content-start gap-5">
                <FormField
                    id={id('region')}
                    label="Region"
                    required
                    error={message('region')}
                >
                    {(accessibility) => (
                        <PlaceCombobox
                            id={id('region')}
                            value={value.region}
                            load={(q, page, signal) =>
                                searchRegions(q, page, signal)
                            }
                            loadKey="regions"
                            listLabel="Regions"
                            placeholder="Search a region"
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={
                                accessibility.invalid ||
                                mismatch.region !== undefined
                            }
                            onChange={(place) => pick('region', place)}
                        />
                    )}
                </FormField>

                {value.region !== null && !regionHasProvinces ? null : (
                    <FormField
                        id={id('province')}
                        label="Province"
                        required
                        error={message('province')}
                    >
                        {(accessibility) => (
                            <PlaceCombobox
                                id={id('province')}
                                value={value.province}
                                extraValue={
                                    value.noProvince ? NO_PROVINCE : null
                                }
                                extraOptions={
                                    region?.hasCitiesWithoutProvince
                                        ? [
                                              {
                                                  key: NO_PROVINCE,
                                                  label: 'Not in a province',
                                              },
                                          ]
                                        : []
                                }
                                load={(q, page, signal) =>
                                    searchProvinces(
                                        value.region?.code ?? '',
                                        q,
                                        page,
                                        signal,
                                    )
                                }
                                loadKey={value.region?.code ?? ''}
                                disabled={value.region === null}
                                listLabel="Provinces"
                                placeholder={
                                    value.region === null
                                        ? 'Choose the region first'
                                        : 'Search a province'
                                }
                                aria-describedby={accessibility.describedBy}
                                aria-invalid={
                                    accessibility.invalid ||
                                    mismatch.province !== undefined
                                }
                                onChange={(place) => pick('province', place)}
                                onExtra={() =>
                                    pick('province', null, { noProvince: true })
                                }
                            />
                        )}
                    </FormField>
                )}

                <FormField
                    id={id('city')}
                    label="City or municipality"
                    required
                    error={message('city')}
                >
                    {(accessibility) => (
                        <PlaceCombobox
                            id={id('city')}
                            value={value.city}
                            load={(q, page, signal) =>
                                searchCities(
                                    {
                                        region: value.region?.code ?? '',
                                        province: value.noProvince
                                            ? null
                                            : (value.province?.code ?? null),
                                    },
                                    q,
                                    page,
                                    signal,
                                )
                            }
                            loadKey={`${value.region?.code}:${value.noProvince ? '-' : value.province?.code}`}
                            disabled={
                                value.province === null && !value.noProvince
                            }
                            listLabel="Cities and municipalities"
                            placeholder={
                                value.province === null && !value.noProvince
                                    ? value.region === null
                                        ? 'Choose the region first'
                                        : 'Choose the province first'
                                    : 'Search a city or municipality'
                            }
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={
                                accessibility.invalid ||
                                mismatch.city !== undefined
                            }
                            onChange={(place) =>
                                pick('city', {
                                    code: place.code,
                                    name: place.name,
                                })
                            }
                        />
                    )}
                </FormField>

                <FormField
                    id={id('barangay')}
                    label="Barangay"
                    required
                    error={message('barangay')}
                >
                    {(accessibility) => (
                        <PlaceCombobox
                            id={id('barangay')}
                            value={value.barangay}
                            load={(q, page, signal) =>
                                searchBarangays(
                                    value.city?.code ?? '',
                                    q,
                                    page,
                                    signal,
                                )
                            }
                            loadKey={value.city?.code ?? ''}
                            disabled={value.city === null}
                            listLabel="Barangays"
                            placeholder={
                                value.city === null
                                    ? 'Choose the city or municipality first'
                                    : 'Search a barangay'
                            }
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={
                                accessibility.invalid ||
                                mismatch.barangay !== undefined
                            }
                            onChange={(place) => pick('barangay', place)}
                        />
                    )}
                </FormField>

                <FormField
                    id={id('postal-code')}
                    label="Postal code"
                    required={postalCodes === null || postalCodes.length > 0}
                    hint={
                        postalCodes !== null && postalCodes.length === 0
                            ? 'No postal code is listed for this city or municipality, so none is needed.'
                            : undefined
                    }
                    error={message('postalCode')}
                >
                    {(accessibility) => (
                        <PlaceCombobox
                            id={id('postal-code')}
                            value={
                                value.postalCode === ''
                                    ? null
                                    : {
                                          code: value.postalCode,
                                          name: value.postalCode,
                                      }
                            }
                            load={(q) =>
                                Promise.resolve({
                                    data: (postalCodes ?? []).filter(
                                        ({ code }) => code.startsWith(q.trim()),
                                    ),
                                    nextPage: null,
                                })
                            }
                            loadKey={`${value.city?.code}:${postalCodes?.length ?? 'x'}`}
                            disabled={
                                value.city === null || postalCodes?.length === 0
                            }
                            listLabel="Postal codes"
                            placeholder={
                                value.city === null
                                    ? 'Choose the city or municipality first'
                                    : 'Choose a postal code'
                            }
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            onChange={(place) =>
                                set({ ...value, postalCode: place.code })
                            }
                        />
                    )}
                </FormField>

                {reading ? (
                    <p
                        role="status"
                        className="text-sm text-secondary-foreground"
                    >
                        Reading the place under the pin…
                    </p>
                ) : null}

                {errors.pin !== undefined ? (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.pin}
                    </p>
                ) : null}
            </div>
        </div>
    );
};

export default AddressPicker;

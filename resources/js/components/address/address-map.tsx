import 'mapbox-gl/dist/mapbox-gl.css';
import mapboxgl from 'mapbox-gl';
import { LocateFixed, Search } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    mapboxToken,
    searchMap,
    type GeocodeResult,
} from '@/lib/mapbox-geocoding';
import { PH_BOUNDS, pinInPhilippines } from '@/lib/ph-address';
import { cn } from '@/lib/utils';

/** Manila, where the map opens when there is no pin yet. */
const START: [number, number] = [121.0244, 14.5547];

/** Why the pin moved: the reader dragged or dropped it, searched, or used their location. */
export type PinSource = 'drag' | 'search' | 'location';

export type AddressMapProps = {
    /** The pin, or null for none yet. */
    lat: number | null;
    lng: number | null;
    /** Where to fly, when the fields pick a place; a new object each time. */
    flyTo: { lat: number; lng: number; zoom: number } | null;
    /** The reader moved the pin; `result` is the search result it came from, if any. */
    onPinMove: (
        lat: number,
        lng: number,
        source: PinSource,
        result: GeocodeResult | null,
    ) => void;
    /** The map could not start (no WebGL): the fields are enough on their own. */
    onUnavailable: () => void;
};

/**
 * The address picker's map (Mapbox GL), loaded only when the picker shows,
 * kept to the Philippines: a draggable pin, a click to move it, a search box
 * with suggestions as the reader types, and "Use my current location".
 */
const AddressMap = ({
    lat,
    lng,
    flyTo,
    onPinMove,
    onUnavailable,
}: AddressMapProps) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<mapboxgl.Map | null>(null);
    const markerRef = useRef<mapboxgl.Marker | null>(null);
    const onPinMoveRef = useRef(onPinMove);
    const [locating, setLocating] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    useEffect(() => {
        onPinMoveRef.current = onPinMove;
    }, [onPinMove]);

    // The map is made once; the pin and the view follow the props below.
    useEffect(() => {
        if (containerRef.current === null) {
            return;
        }

        if (!mapboxgl.supported()) {
            onUnavailable();

            return;
        }

        mapboxgl.accessToken = mapboxToken();

        let map: mapboxgl.Map;

        try {
            map = new mapboxgl.Map({
                container: containerRef.current,
                style: 'mapbox://styles/mapbox/streets-v12',
                center: lng !== null && lat !== null ? [lng, lat] : START,
                zoom: lat !== null ? 15 : 10,
                maxBounds: [
                    [PH_BOUNDS[0] - 1, PH_BOUNDS[1] - 1],
                    [PH_BOUNDS[2] + 1, PH_BOUNDS[3] + 1],
                ],
                cooperativeGestures: true,
            });
        } catch {
            onUnavailable();

            return;
        }

        map.addControl(
            new mapboxgl.NavigationControl({ showCompass: false }),
            'top-right',
        );

        const marker = new mapboxgl.Marker({ draggable: true, color: '#111' });

        marker.on('dragend', () => {
            const { lat: newLat, lng: newLng } = marker.getLngLat();

            onPinMoveRef.current(newLat, newLng, 'drag', null);
        });

        map.on('click', (event) => {
            marker.setLngLat(event.lngLat).addTo(map);
            onPinMoveRef.current(
                event.lngLat.lat,
                event.lngLat.lng,
                'drag',
                null,
            );
        });

        mapRef.current = map;
        markerRef.current = marker;

        return () => {
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
        };
        // Made once: the pin and view are kept up to date below.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const map = mapRef.current;
        const marker = markerRef.current;

        if (map === null || marker === null) {
            return;
        }

        if (lat === null || lng === null) {
            marker.remove();
        } else {
            marker.setLngLat([lng, lat]).addTo(map);
        }
    }, [lat, lng]);

    useEffect(() => {
        if (flyTo !== null) {
            mapRef.current?.flyTo({
                center: [flyTo.lng, flyTo.lat],
                zoom: flyTo.zoom,
                essential: true,
            });
        }
    }, [flyTo]);

    const moveTo = (
        newLat: number,
        newLng: number,
        source: PinSource,
        result: GeocodeResult | null,
    ) => {
        mapRef.current?.flyTo({
            center: [newLng, newLat],
            zoom: 16,
            essential: true,
        });
        onPinMoveRef.current(newLat, newLng, source, result);
    };

    const useMyLocation = () => {
        setNotice(null);

        if (!('geolocation' in navigator)) {
            setNotice(
                "This browser can't share your location. Search for the place or drag the pin instead.",
            );

            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                const { latitude, longitude } = position.coords;

                if (!pinInPhilippines(latitude, longitude)) {
                    setNotice(
                        'You seem to be outside the Philippines. Search for the place or drag the pin instead.',
                    );

                    return;
                }

                moveTo(latitude, longitude, 'location', null);
            },
            (error) => {
                setLocating(false);
                setNotice(
                    error.code === error.PERMISSION_DENIED
                        ? 'Location access is turned off for this site. Search for the place or drag the pin instead.'
                        : "We couldn't find your location. Search for the place or drag the pin instead.",
                );
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    return (
        <div className="flex flex-col gap-2">
            <MapSearch
                onPick={(result) =>
                    moveTo(result.lat, result.lng, 'search', result)
                }
            />

            <div
                ref={containerRef}
                className="h-64 w-full overflow-hidden border border-sidebar-border lg:h-full lg:min-h-80"
                role="region"
                aria-label="Map. Drag the pin, or click the map, to where the address is."
                data-testid="address-map"
            />

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="rounded-none"
                    disabled={locating}
                    onClick={useMyLocation}
                >
                    <LocateFixed aria-hidden className="size-4" />
                    {locating ? 'Finding you…' : 'Use my current location'}
                </Button>
                <p className="text-xs text-secondary-foreground">
                    Drag the pin to the exact spot.
                </p>
            </div>

            {notice !== null ? (
                <p role="status" className="text-sm text-secondary-foreground">
                    {notice}
                </p>
            ) : null}
        </div>
    );
};

/** The map's search box: Mapbox's suggestions as the reader types. */
const MapSearch = ({ onPick }: { onPick: (result: GeocodeResult) => void }) => {
    const listboxId = `map-search-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    const [text, setText] = useState('');
    const [results, setResults] = useState<GeocodeResult[]>([]);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const [failed, setFailed] = useState<string | null>(null);

    useEffect(() => {
        if (text.trim().length < 3) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            searchMap(text.trim(), controller.signal)
                .then((found) => {
                    setResults(found);
                    setFailed(null);
                    setActiveIndex(0);
                })
                .catch((error: unknown) => {
                    if (
                        !(error instanceof DOMException) ||
                        error.name !== 'AbortError'
                    ) {
                        setFailed("We couldn't search the map. Try again.");
                    }
                });
        }, 300);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [text]);

    const shown = text.trim().length < 3 ? [] : results;

    const pick = (result: GeocodeResult) => {
        setText(result.label);
        setOpen(false);
        onPick(result);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);

            if (shown.length > 0) {
                setActiveIndex(
                    (current) =>
                        (current +
                            (event.key === 'ArrowDown' ? 1 : -1) +
                            shown.length) %
                        shown.length,
                );
            }
        } else if (event.key === 'Enter' && open && shown[activeIndex]) {
            event.preventDefault();
            pick(shown[activeIndex]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div className="relative">
            <Search
                aria-hidden
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 opacity-50"
            />
            <Input
                type="search"
                role="combobox"
                aria-label="Search the map"
                aria-expanded={open && shown.length > 0}
                aria-controls={listboxId}
                aria-autocomplete="list"
                aria-activedescendant={
                    open && shown[activeIndex]
                        ? `${listboxId}-${activeIndex}`
                        : undefined
                }
                autoComplete="off"
                placeholder="Search a place, street or landmark"
                value={text}
                onChange={(event) => {
                    setText(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={onKeyDown}
                className="rounded-none pl-9"
            />
            {open && shown.length > 0 ? (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label="Places on the map"
                    className="absolute top-[calc(100%+4px)] right-0 left-0 z-30 max-h-60 overflow-y-auto rounded-md border border-sidebar-border bg-background"
                >
                    {shown.map((result, index) => (
                        <li
                            key={result.id}
                            id={`${listboxId}-${index}`}
                            role="option"
                            aria-selected={index === activeIndex}
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={() => pick(result)}
                            className={cn(
                                'cursor-pointer px-3 py-2 text-sm break-words',
                                index === activeIndex && 'bg-muted',
                            )}
                        >
                            {result.label}
                        </li>
                    ))}
                </ul>
            ) : null}
            {failed !== null ? (
                <p role="status" className="mt-1 text-sm text-destructive">
                    {failed}
                </p>
            ) : null}
        </div>
    );
};

export default AddressMap;

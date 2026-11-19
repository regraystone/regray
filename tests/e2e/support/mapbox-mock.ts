import type { BrowserContext, Route } from '@playwright/test';

/*
 * Mapbox, answered in the browser so the suite never calls it: a style with
 * nothing but a background (so the map asks for no tiles, sprites or glyphs),
 * Geocoding v6 answering every search and every dropped pin with Bel-Air,
 * Makati, and telemetry swallowed. Every context the suite opens gets it, so
 * a page with the address picker needs no network and no token of its own.
 */

/** Bel-Air, City of Makati: the place every mocked search finds. */
export const MAPBOX_PLACE = {
    lat: 14.5618,
    lng: 121.0296,
    label: 'Bel-Air, Makati, Metro Manila 1209, Philippines',
} as const;

export const MAPBOX_HOSTS = ['api.mapbox.com', 'events.mapbox.com'];

const STYLE = {
    version: 8,
    name: 'e2e',
    sources: {},
    layers: [
        {
            id: 'background',
            type: 'background',
            paint: { 'background-color': '#e9e6df' },
        },
    ],
};

const FEATURE = {
    type: 'Feature',
    id: 'dXJuOm1ieHBsYzpBYmVsYWly',
    geometry: {
        type: 'Point',
        coordinates: [MAPBOX_PLACE.lng, MAPBOX_PLACE.lat],
    },
    properties: {
        name: 'Bel-Air',
        full_address: MAPBOX_PLACE.label,
        place_formatted: 'Makati, Metro Manila 1209, Philippines',
        // As Mapbox answers for the Philippines: the barangay as the
        // locality, and the city again as the "region", never the PSGC's.
        context: {
            locality: { name: 'Bel-Air' },
            place: { name: 'Makati' },
            region: { name: 'Makati' },
            postcode: { name: '1209' },
            country: { name: 'Philippines' },
        },
    },
};

const json = (route: Route, body: unknown) =>
    route.fulfill({
        status: 200,
        contentType: 'application/json',
        headers: { 'access-control-allow-origin': '*' },
        body: JSON.stringify(body),
    });

/** Answer every Mapbox request made in `context`. */
export const mockMapbox = async (context: BrowserContext): Promise<void> => {
    await context.route(/^https:\/\/(api|events)\.mapbox\.com\//, (route) => {
        const url = new URL(route.request().url());

        if (url.host === 'events.mapbox.com') {
            return route.fulfill({ status: 204, body: '' });
        }

        if (url.pathname.startsWith('/styles/')) {
            return json(route, STYLE);
        }

        if (url.pathname.startsWith('/search/geocode/v6/')) {
            return json(route, {
                type: 'FeatureCollection',
                features: [FEATURE],
            });
        }

        return route.fulfill({ status: 204, body: '' });
    });
};

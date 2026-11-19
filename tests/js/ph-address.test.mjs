/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    addressFromPayload,
    addressToRequest,
    emptyAddress,
    foldPlaceName,
    formatAddressLine,
    fulfillmentSummary,
    isAddressComplete,
    matchPlace,
    matchRegion,
    pickerErrors,
    pinInPhilippines,
    placeNamesMatch,
    savedAddressProblems,
    withLevel,
} from '../../resources/js/lib/ph-address.ts';

const ncr = { code: '1300000000', name: 'National Capital Region' };
const makati = { code: '1380300000', name: 'City of Makati' };
const belAir = { code: '1380300001', name: 'Bel-Air' };

/** Makati's Bel-Air, every level picked: NCR has no provinces. */
const complete = () => ({
    ...emptyAddress(),
    region: ncr,
    noProvince: true,
    city: makati,
    barangay: belAir,
    postalCode: '1209',
    needsPostalCode: true,
    lat: 14.56,
    lng: 121.03,
});

describe('folding a place name', () => {
    it('drops accents, case and the words a name is written with or without', () => {
        assert.equal(foldPlaceName('City of Las Piñas'), 'las pinas');
        assert.equal(foldPlaceName('Barangay Bel-Air'), 'bel air');
        assert.equal(foldPlaceName('Sta. Mesa'), 'santa mesa');
        assert.equal(foldPlaceName('Quezon City'), 'quezon');
    });

    it('matches two ways of writing the same place', () => {
        assert.equal(placeNamesMatch('Makati City', 'City of Makati'), true);
        assert.equal(placeNamesMatch('Makati', 'Pasig'), false);
        assert.equal(placeNamesMatch('', ''), false);
    });
});

describe('matching a map name to a PSGC place', () => {
    const cities = [
        makati,
        { code: '1', name: 'City of San Juan' },
        { code: '2', name: 'San Juan' },
        { code: '3', name: 'City of Pasig' },
    ];

    it('takes the exact folded name, else the one partial match', () => {
        assert.equal(matchPlace(cities, 'Makati City')?.code, makati.code);
        assert.equal(matchPlace(cities, 'Pasig City Proper')?.code, '3');
    });

    it('picks nothing when more than one could be meant', () => {
        assert.equal(matchPlace(cities, 'San Juan'), null);
        assert.equal(matchPlace(cities, ''), null);
    });

    it('reads Metro Manila, and a designation, as their regions', () => {
        const regions = [
            { ...ncr, designation: 'NCR' },
            {
                code: '0400000000',
                name: 'CALABARZON',
                designation: 'Region IV-A',
            },
        ];

        assert.equal(matchRegion(regions, 'Metro Manila')?.code, ncr.code);
        assert.equal(matchRegion(regions, 'Region IV-A')?.code, '0400000000');
    });
});

describe('picking a level', () => {
    it('clears every level below the one changed', () => {
        const next = withLevel(complete(), 'city', {
            code: '1381200000',
            name: 'City of Pasig',
        });

        assert.equal(next.city.name, 'City of Pasig');
        assert.equal(next.region.code, ncr.code);
        assert.equal(next.barangay, null);
        assert.equal(next.postalCode, '');
        assert.equal('needsPostalCode' in next, false);
    });

    it('clears the province, the city and below when the region changes', () => {
        const next = withLevel(complete(), 'region', {
            code: '0400000000',
            name: 'CALABARZON',
        });

        assert.equal(next.province, null);
        assert.equal(next.noProvince, false);
        assert.equal(next.city, null);
    });

    it('keeps everything when the same place is picked again', () => {
        const value = complete();

        assert.equal(withLevel(value, 'city', makati), value);
    });
});

describe('a complete address', () => {
    it('needs every level, the postal code when the city has any', () => {
        assert.equal(isAddressComplete(complete()), true);
        assert.equal(
            isAddressComplete({ ...complete(), barangay: null }),
            false,
        );
        assert.equal(
            isAddressComplete({ ...complete(), postalCode: '' }),
            false,
        );
        assert.equal(
            isAddressComplete({
                ...complete(),
                postalCode: '',
                needsPostalCode: false,
            }),
            true,
        );
        assert.equal(isAddressComplete(null), false);
    });

    it('needs the province, unless the region has none', () => {
        assert.equal(
            isAddressComplete({ ...complete(), noProvince: false }),
            false,
        );
    });

    it('keeps the pin in the Philippines', () => {
        assert.equal(pinInPhilippines(14.56, 121.03), true);
        assert.equal(pinInPhilippines(35.68, 139.69), false);
        assert.equal(
            isAddressComplete({ ...complete(), lat: 35.68, lng: 139.69 }),
            false,
        );
    });
});

describe('the address book', () => {
    it('asks for a label and the address, worded per field', () => {
        const problems = savedAddressProblems({
            label: ' ',
            address: { ...complete(), barangay: null },
        });

        assert.equal(typeof problems.label, 'string');
        assert.deepEqual(problems.address, {
            barangay: 'Choose the barangay.',
        });
        assert.deepEqual(
            savedAddressProblems({ label: 'Home', address: complete() }),
            { label: undefined, address: {} },
        );
    });

    it("puts the server's address errors on the picker's fields", () => {
        assert.deepEqual(
            pickerErrors({
                'address.cityCode': 'Choose the city.',
                'address.lat': 'Pin outside.',
                label: 'Name it.',
            }),
            { city: 'Choose the city.', pin: 'Pin outside.' },
        );
        assert.deepEqual(
            pickerErrors(
                { 'projects.0.address.barangayCode': 'Choose the barangay.' },
                'projects.0.address',
            ),
            { barangay: 'Choose the barangay.' },
        );
    });
});

describe('formatting', () => {
    const payload = {
        regionCode: ncr.code,
        regionName: ncr.name,
        provinceCode: null,
        provinceName: null,
        cityCode: makati.code,
        cityName: makati.name,
        barangayCode: belAir.code,
        barangayName: belAir.name,
        postalCode: '1209',
        lat: 14.56,
        lng: 121.03,
        line: '',
    };

    it('reads an address lowest level first, leaving out what it has not', () => {
        assert.equal(
            formatAddressLine(payload),
            'Bel-Air, City of Makati, National Capital Region, 1209',
        );
    });

    it('says how the work is got', () => {
        assert.equal(fulfillmentSummary('pickup', null), 'Pickup');
        assert.equal(
            fulfillmentSummary('delivery', 'Bel-Air, Makati'),
            'Delivery to Bel-Air, Makati',
        );
        assert.equal(
            fulfillmentSummary(null, 'Bel-Air, Makati'),
            'Installation at Bel-Air, Makati',
        );
    });

    it('reads a stored address into the picker and back by its codes', () => {
        const value = addressFromPayload(payload);

        assert.equal(value.noProvince, true);
        assert.deepEqual(addressToRequest(value), {
            regionCode: ncr.code,
            provinceCode: '',
            cityCode: makati.code,
            barangayCode: belAir.code,
            postalCode: '1209',
            lat: '14.560000',
            lng: '121.030000',
        });
    });
});

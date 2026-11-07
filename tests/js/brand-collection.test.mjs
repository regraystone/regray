/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    COLLECTION_OPTIONS,
    collectionQuery,
    secondFilter,
    toCollection,
} from '../../resources/js/lib/brand-collection.ts';
import { catalogueCrumbs } from '../../resources/js/lib/catalogue-breadcrumbs.ts';

const OPTIONS = {
    types: [{ value: 'granite', label: 'Granite' }],
    furnitureTypes: [{ value: 'Tables', label: 'Tables' }],
};

describe('the first select', () => {
    it('offers All Categories first, then Natural Stones, then Furniture', () => {
        assert.deepEqual(COLLECTION_OPTIONS, [
            { value: 'all', label: 'All Categories' },
            { value: 'stones', label: 'Natural Stones' },
            { value: 'furniture', label: 'Furniture' },
        ]);
    });

    it('falls back to All Categories for anything it does not know', () => {
        assert.equal(toCollection(undefined), 'all');
        assert.equal(toCollection('projects'), 'all');
        assert.equal(toCollection('furniture'), 'furniture');
    });
});

describe('the second select', () => {
    it('is hidden under All Categories', () => {
        assert.equal(secondFilter('all', OPTIONS), null);
    });

    it('lists the stone types under Natural Stones, by the type param', () => {
        const second = secondFilter('stones', OPTIONS);

        assert.equal(second.param, 'type');
        assert.equal(second.defaultLabel, 'All Product Type');
        assert.deepEqual(second.options, OPTIONS.types);
    });

    it('lists the furniture types under Furniture, by the furniture_type param', () => {
        const second = secondFilter('furniture', OPTIONS);

        assert.equal(second.param, 'furniture_type');
        assert.equal(second.defaultLabel, 'All Product Type');
        assert.deepEqual(second.options, OPTIONS.furnitureTypes);
    });
});

describe('the query', () => {
    it('leaves the default collection and an empty search out of the URL', () => {
        assert.deepEqual(
            collectionQuery({
                collection: 'all',
                search: '  ',
                filter: '',
            }),
            {},
        );
    });

    it('ignores the second filter under All Categories', () => {
        assert.deepEqual(
            collectionQuery({
                collection: 'all',
                search: 'oak',
                filter: 'granite',
            }),
            { search: 'oak' },
        );
    });

    it('sends the second filter by its own collection param', () => {
        assert.deepEqual(
            collectionQuery({
                collection: 'stones',
                search: '',
                filter: 'granite',
            }),
            { collection: 'stones', type: 'granite' },
        );
        assert.deepEqual(
            collectionQuery({
                collection: 'furniture',
                search: '',
                filter: 'Tables',
            }),
            { collection: 'furniture', furniture_type: 'Tables' },
        );
    });
});

describe('the breadcrumbs', () => {
    const urls = {
        homeHref: '/',
        brandHref: (slug) => `/brands/${slug}`,
    };
    const brand = { slug: 'regray-stone', name: 'Regray Stone' };
    const home = { label: 'Home', href: '/' };
    const brandCrumb = { label: 'Regray Stone', href: '/brands/regray-stone' };

    it('reads Regray / Home / Brand on a brand page', () => {
        assert.deepEqual(
            catalogueCrumbs({
                ...urls,
                component: 'brand-profile',
                props: { brand: { data: brand } },
            }),
            [{ label: 'Regray' }, home, brandCrumb],
        );
    });

    it('reads Regray / Home / Brand / Stone on a stone page, by name', () => {
        assert.deepEqual(
            catalogueCrumbs({
                ...urls,
                component: 'stone-profile',
                props: { stone: { data: { name: 'Arctic Pink', brand } } },
            }),
            [{ label: 'Regray' }, home, brandCrumb, { label: 'Arctic Pink' }],
        );
    });

    it('reads Regray / Home / Brand / Furniture on a furniture page, by name', () => {
        assert.deepEqual(
            catalogueCrumbs({
                ...urls,
                component: 'furniture-profile',
                props: { furniture: { data: { name: 'Alora', brand } } },
            }),
            [{ label: 'Regray' }, home, brandCrumb, { label: 'Alora' }],
        );
    });

    it('has nothing to say on any other page', () => {
        assert.equal(
            catalogueCrumbs({ ...urls, component: 'landing', props: {} }),
            null,
        );
        assert.equal(
            catalogueCrumbs({
                ...urls,
                component: 'project-profile',
                props: { project: { data: { name: 'Ginza One' } } },
            }),
            null,
        );
    });
});

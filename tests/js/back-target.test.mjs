/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { backTarget } from '../../resources/js/lib/back-target.ts';

const LANDINGS = ['/dashboard/home', '/dashboard/overview'];

const target = (component, url, crumbs) =>
    backTarget({
        component,
        url,
        crumbs,
        landingUrls: LANDINGS,
        landing: '/dashboard',
    });

describe('the Back button on a phone or tablet', () => {
    it('goes to the landing page from a page with no link above it, such as Saved', () => {
        assert.equal(
            target('saved', '/dashboard/saved', [
                { label: 'Regray' },
                { label: 'Dashboard' },
                { label: 'Saved', href: '/dashboard/saved' },
            ]),
            '/dashboard',
        );
    });

    it('goes one level up the trail: a stone to its brand, a brand to Home', () => {
        const home = { label: 'Home', href: '/dashboard/home' };
        const brand = {
            label: 'Regray Stone',
            href: '/dashboard/brands/regray-stone',
        };

        assert.equal(
            target(
                'stone-profile',
                '/dashboard/brands/regray-stone/stones/arctic-pink',
                [{ label: 'Regray' }, home, brand, { label: 'Arctic Pink' }],
            ),
            brand.href,
        );
        assert.equal(
            target(
                'brand-profile',
                '/dashboard/brands/regray-stone?collection=stones',
                [{ label: 'Regray' }, home, brand],
            ),
            home.href,
        );
    });

    it('is not shown on a landing page', () => {
        assert.equal(target('home', '/dashboard/home', []), null);
        assert.equal(
            target('overview', '/dashboard/overview?range=30', []),
            null,
        );
    });

    it('is not shown on a page with its own way back', () => {
        for (const component of [
            'inbox',
            'showcase-profile',
            'inquiry-details',
        ]) {
            assert.equal(
                target(component, '/dashboard/somewhere', [
                    { label: 'Home', href: '/dashboard/home' },
                    { label: 'Here' },
                ]),
                null,
            );
        }
    });
});

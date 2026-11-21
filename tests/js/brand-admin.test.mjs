/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    addTags,
    archiveDescription,
    catalogueCount,
    isSocialLinkFor,
    websiteDomain,
} from '../../resources/js/lib/brand-admin.ts';
import { initials } from '../../resources/js/lib/initials.ts';
import { SLUG_PATTERN, slugify } from '../../resources/js/lib/slug.ts';

describe('slugify', () => {
    it('makes a lower-case, hyphenated slug from a name', () => {
        assert.equal(slugify('Cucine Lube'), 'cucine-lube');
        assert.equal(slugify('  Res  Italia  '), 'res-italia');
        assert.equal(slugify('Façades & Co.'), 'facades-co');
        assert.equal(slugify('ReForm 2026!'), 'reform-2026');
        assert.equal(slugify(''), '');
    });

    it('always makes a slug the pattern accepts, at most 100 long', () => {
        for (const name of [
            'Ascale',
            'Faer — Ambienti',
            'a'.repeat(140),
            '--x--',
        ]) {
            const slug = slugify(name);

            assert.ok(SLUG_PATTERN.test(slug), slug);
            assert.ok(slug.length <= 100);
        }
    });
});

describe('SLUG_PATTERN', () => {
    it('takes words joined by single hyphens, and nothing else', () => {
        assert.ok(SLUG_PATTERN.test('regray-stone'));
        assert.ok(!SLUG_PATTERN.test('Regray-Stone'));
        assert.ok(!SLUG_PATTERN.test('regray--stone'));
        assert.ok(!SLUG_PATTERN.test('-regray'));
        assert.ok(!SLUG_PATTERN.test('regray stone'));
    });
});

describe('addTags', () => {
    it('adds what is typed, split at commas and trimmed', () => {
        assert.deepEqual(addTags([], ' Kitchens ', 8), ['Kitchens']);
        assert.deepEqual(addTags(['Kitchens'], 'Floors, Walls,', 8), [
            'Kitchens',
            'Floors',
            'Walls',
        ]);
    });

    it('leaves out empty tags and repeats, whatever their case', () => {
        assert.deepEqual(addTags(['Kitchens'], 'kitchens, , KITCHENS', 8), [
            'Kitchens',
        ]);
    });

    it('stops at the limit', () => {
        assert.deepEqual(addTags(['a', 'b'], 'c, d, e', 3), ['a', 'b', 'c']);
    });
});

describe('catalogueCount', () => {
    it('counts the stones and the furniture, or says there are none', () => {
        assert.equal(catalogueCount(75, 6), '75 stones · 6 furniture');
        assert.equal(catalogueCount(1, 0), '1 stone');
        assert.equal(catalogueCount(0, 2), '2 furniture');
        assert.equal(catalogueCount(1200, 0), '1,200 stones');
        assert.equal(catalogueCount(0, 0), 'No products yet');
    });
});

describe('archiveDescription', () => {
    it('says what goes with the brand, from its counts', () => {
        assert.equal(
            archiveDescription(75, 6),
            'It leaves this list and its page goes offline. Its 75 stones and 6 pieces of furniture are hidden from the catalogue too. Saved items and past inquiries keep them.',
        );
        assert.equal(
            archiveDescription(0, 1),
            'It leaves this list and its page goes offline. Its 1 piece of furniture is hidden from the catalogue too. Saved items and past inquiries keep them.',
        );
        assert.equal(
            archiveDescription(0, 0),
            'It leaves this list and its page goes offline. Saved items and past inquiries keep them.',
        );
    });
});

describe('websiteDomain and initials', () => {
    it('shows a website as its domain', () => {
        assert.equal(
            websiteDomain('https://www.cucinelube.it/'),
            'cucinelube.it',
        );
        assert.equal(websiteDomain('https://ascale.es/en'), 'ascale.es');
        assert.equal(websiteDomain('not a url'), 'not a url');
    });

    it('takes the first letters of the first two words', () => {
        assert.equal(initials('Cucine Lube'), 'CL');
        assert.equal(initials('reform'), 'R');
        assert.equal(initials('Res Italia Doors'), 'RI');
    });
});

describe('isSocialLinkFor', () => {
    it("takes a link on the network's own site, with or without https:// and www.", () => {
        assert.ok(
            isSocialLinkFor(
                'instagram',
                'https://www.instagram.com/regraystoneph/',
            ),
        );
        assert.ok(isSocialLinkFor('instagram', 'instagram.com/regraystoneph'));
        assert.ok(
            isSocialLinkFor('facebook', 'https://m.facebook.com/Regraystone'),
        );
        assert.ok(isSocialLinkFor('facebook', 'fb.com/Regraystone'));
        assert.ok(
            isSocialLinkFor('tiktok', 'https://www.tiktok.com/@regraystone'),
        );
    });

    it('takes twitter.com and x.com for either Twitter or X', () => {
        assert.ok(isSocialLinkFor('twitter', 'https://twitter.com/regray'));
        assert.ok(isSocialLinkFor('twitter', 'https://x.com/regray'));
        assert.ok(isSocialLinkFor('x', 'x.com/regray'));
        assert.ok(isSocialLinkFor('x', 'https://twitter.com/regray'));
    });

    it('refuses another site, a look-alike, or something that is no address', () => {
        assert.ok(
            !isSocialLinkFor(
                'instagram',
                'https://www.facebook.com/Regraystone',
            ),
        );
        assert.ok(!isSocialLinkFor('facebook', 'https://notfacebook.com/x'));
        assert.ok(
            !isSocialLinkFor('facebook', 'https://facebook.com.evil.test/x'),
        );
        assert.ok(!isSocialLinkFor('x', 'javascript:alert(1)'));
        assert.ok(!isSocialLinkFor('tiktok', 'not a link'));
    });
});

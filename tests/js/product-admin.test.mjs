/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    photoCount,
    productDetail,
    productUrl,
    sizeCount,
} from '../../resources/js/lib/product-admin.ts';

describe('the Products page counts', () => {
    it('counts sizes and photos in words', () => {
        assert.equal(sizeCount(0), 'No sizes yet');
        assert.equal(sizeCount(1), '1 size');
        assert.equal(sizeCount(4), '4 sizes');
        assert.equal(photoCount(0), 'Thumbnail only');
        assert.equal(photoCount(1), '1 photo');
        assert.equal(photoCount(3), '3 photos');
    });

    it('shows a stone its sizes and a piece of furniture its photos', () => {
        const sizes = [{ sizeId: 1, thicknessId: 2 }];

        assert.equal(productDetail('stone', sizes, 5), '1 size');
        assert.equal(productDetail('furniture', [], 2), '2 photos');
    });
});

describe('productUrl', () => {
    const template = 'https://regray.test/brands/__brand__/stones/__slug__';

    it('fills in the brand and slug', () => {
        assert.equal(
            productUrl(template, 'cucine-lube', 'calacatta'),
            'https://regray.test/brands/cucine-lube/stones/calacatta',
        );
    });

    it('shows placeholders while they are empty', () => {
        assert.equal(
            productUrl(template, undefined, ''),
            'https://regray.test/brands/brand/stones/your-product',
        );
    });
});

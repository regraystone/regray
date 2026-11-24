/**
 * What the admin's Products page says about a product: helpers, kept here so
 * the node tests can check them.
 */
import type { ProductKind, ProductSize } from '@/types/product-admin';

export const KIND_LABELS: Record<ProductKind, string> = {
    stone: 'Stone',
    furniture: 'Furniture',
};

/** "2 sizes", "1 size", "No sizes yet". */
export const sizeCount = (count: number): string =>
    count === 0 ? 'No sizes yet' : `${count} ${count === 1 ? 'size' : 'sizes'}`;

/** "3 photos", "1 photo", "Thumbnail only": the photos besides the thumbnail. */
export const photoCount = (count: number): string =>
    count === 0
        ? 'Thumbnail only'
        : `${count} ${count === 1 ? 'photo' : 'photos'}`;

/** What a row says in its Details column: a stone's sizes, a piece's photos. */
export const productDetail = (
    kind: ProductKind,
    sizes: ProductSize[],
    photos: number,
): string => (kind === 'stone' ? sizeCount(sizes.length) : photoCount(photos));

/** The public link a slug makes, with a placeholder while it is empty. */
export const productUrl = (
    template: string,
    brandSlug: string | undefined,
    slug: string,
): string =>
    template
        .replace('__brand__', brandSlug || 'brand')
        .replace('__slug__', slug || 'your-product');

export const archiveDescription = (kind: ProductKind): string =>
    kind === 'stone'
        ? 'It leaves the Products page and its page goes offline everywhere on Regray. Saved lists drop it; past inquiries keep it.'
        : 'It leaves the Products page and its page goes offline everywhere on Regray. Saved lists drop it.';

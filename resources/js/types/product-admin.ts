import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';

export type ProductKind = 'stone' | 'furniture';

/** One size a stone comes in and one thickness it is cut in at that size (one variant); a `sizeId` of null is "no set size". */
export type ProductSize = { sizeId: number | null; thicknessId: number };

/** One row on the admin's Products page, a stone or a piece of furniture, as `AdminProductResource` sends it. */
export type ProductRow = {
    /** Unique across both kinds: `stone-12`, `furniture-3`. */
    key: string;
    kind: ProductKind;
    id: number;
    name: string;
    slug: string;
    thumbnail: string | null;
    brand: { id: number; slug: string; name: string };
    /** The stone's type, or the piece's furniture type. */
    type: { id: number; name: string };
    isActive: boolean;
    /** The photos besides the thumbnail, in order. */
    gallery: { id: number; url: string }[];
    /** A stone's; null for a piece of furniture. */
    origin: string | null;
    sizeNote: string | null;
    /** The other types a stone is listed under. */
    types: { id: number; name: string }[];
    sizes: ProductSize[];
    /** A piece of furniture's; null for a stone. */
    description: string | null;
    /** A table's own shape and base design, which its Inquire button picks; null for none or a stone. */
    tableShape: ProductOption | null;
    tableBaseDesign: ProductOption | null;
    updatedAt: string;
    /** Its page while it is shown, or null. */
    publicUrl: string | null;
    actions: string[];
    canToggle: boolean;
};

export type ProductOption = { id: number; name: string };

/** The furniture columns a table's own shape and base design are kept in. */
export type TableChoiceColumn =
    | 'table_shape_option_id'
    | 'table_base_design_option_id';

export type ProductLimits = {
    thumbnailMaxKb: number;
    photoMaxKb: number;
    galleryMax: number;
    sizesMax: number;
    descriptionMax: number;
};

export type ProductFilters = DataTableFilters & {
    kind: 'all' | ProductKind;
    /** A brand's slug, or `all`. */
    brand: string;
    status: 'all' | 'active' | 'inactive';
};

export type ProductsPageProps = {
    products: { data: ProductRow[]; meta: TableMeta };
    filters: ProductFilters;
    canCreate: boolean;
    limits: ProductLimits;
    options: {
        brands: { id: number; slug: string; name: string }[];
        stoneTypes: ProductOption[];
        furnitureTypes: ProductOption[];
        sizes: { id: number; label: string }[];
        thicknesses: { id: number; label: string }[];
        /**
         * The shapes and base designs a table of each furniture type may be
         * given, by type id; a type that asks neither is absent.
         */
        tableChoices: Record<
            string,
            Partial<Record<TableChoiceColumn, ProductOption[]>>
        >;
    };
    /** The names Regray keeps for its own pages; no product may take one. */
    reservedSlugs: string[];
    /** A product's public page, with `__brand__` and `__slug__` to fill in. */
    publicUrls: Record<ProductKind, string>;
};

import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';

/** An uploaded image's own name and size, to show; null for a committed one. */
export type ShowcaseImageFile = {
    originalName: string;
    sizeBytes: number | null;
};

/** One photo of a showcase's gallery, in its order. */
export type ShowcasePhoto = {
    id: number;
    url: string;
    /** Its file's own name without the extension, its alt text; null for a committed one. */
    name: string | null;
    file: ShowcaseImageFile | null;
};

/** A stone or category as the form offers it and a showcase lists it. */
export type ShowcaseOption = { id: number; name: string };

/** One showcase as a card on the admin's Showcase page, as `AdminShowcaseResource` sends it. */
export type ShowcaseRow = {
    id: number;
    slug: string;
    name: string;
    location: string;
    completedYear: number;
    overview: string;
    thumbnail: string;
    thumbnailFile: ShowcaseImageFile | null;
    stones: ShowcaseOption[];
    categories: ShowcaseOption[];
    images: ShowcasePhoto[];
    /** Published on Regray; a draft has no page. */
    isPublished: boolean;
    /** When it was first published, kept when it goes back to a draft. */
    publishedAt: string | null;
    createdAt: string;
    updatedAt: string;
    /** The showcase's public page, or null while it is a draft. */
    publicUrl: string | null;
    /** The actions this reader has on this showcase, decided by the server. */
    actions: string[];
};

/** The limits the form checks, from config/showcases.php. */
export type ShowcaseLimits = {
    thumbnailMaxKb: number;
    photoMaxKb: number;
    galleryMax: number;
    overviewMax: number;
};

export type ShowcasesPageProps = {
    showcases: { data: ShowcaseRow[]; meta: TableMeta };
    filters: DataTableFilters;
    canCreate: boolean;
    limits: ShowcaseLimits;
    /** The names Regray keeps for its own pages; no showcase may take one. */
    reservedSlugs: string[];
    /** A showcase's public page, with `__slug__` where its slug goes. */
    publicShowcaseUrl: string;
    /** The active stones, each with its brand's name to tell alike names apart. */
    stoneOptions: (ShowcaseOption & { brand: string })[];
    categoryOptions: ShowcaseOption[];
};

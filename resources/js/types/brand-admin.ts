import type { DataTableFilters } from '@/components/data-table/data-table';
import type { TableMeta } from '@/lib/data-table';
import type { BrandFact, BrandSocial, SocialPlatform } from '@/types/home';

/** An uploaded image's own name and size, to show; null for a committed one. */
export type BrandImageFile = { originalName: string; sizeBytes: number | null };

/** One brand as a card on the admin's Brands page, as `AdminBrandResource` sends it. */
export type BrandRow = {
    id: number;
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    websiteUrl: string | null;
    logo: string;
    thumbnail: string | null;
    /** Visible on Regray; a hidden brand keeps its products but has no page. */
    isActive: boolean;
    sortOrder: number;
    stonesCount: number;
    furnitureCount: number;
    projectsCount: number;
    facts: BrandFact[];
    makes: string[];
    socials: BrandSocial[];
    logoFile: BrandImageFile | null;
    thumbnailFile: BrandImageFile | null;
    createdAt: string;
    updatedAt: string;
    /** The brand's public page, or null while it is hidden. */
    publicUrl: string | null;
    /** The actions this reader has on this brand, decided by the server. */
    actions: string[];
};

/** The limits the form checks, from config/brands.php. */
export type BrandLimits = {
    logoMaxKb: number;
    thumbnailMaxKb: number;
    factsMax: number;
    factLabelMax: number;
    factValueMax: number;
    makesMax: number;
    makeMax: number;
};

export type BrandsPageProps = {
    brands: { data: BrandRow[]; meta: TableMeta };
    filters: DataTableFilters;
    canCreate: boolean;
    limits: BrandLimits;
    /** The names Regray keeps for its own pages; no brand may take one. */
    reservedSlugs: string[];
    /** A brand's public page, with `__slug__` where its slug goes. */
    publicBrandUrl: string;
    /** The networks a brand may link to, in the order the form offers them. */
    socialPlatforms: { value: SocialPlatform; label: string }[];
};

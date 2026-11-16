import type { BrandCollectionKind } from '@/lib/brand-collection';
import type { StoneCardData } from '@/types/stone';

export type ProjectProfileCategory = {
    slug: string;
    name: string;
};

export type ProjectProfileStone = {
    slug: string;
    name: string;
    thumbnail: string;
    type: { slug: string; name: string };
};

export type ProjectProfileImage = {
    src: string;
};

export type ProjectProfile = {
    slug: string;
    name: string;
    completedYear: number;
    location: string;
    overview: string;
    thumbnail: string;
    categories: ProjectProfileCategory[];
    stones: ProjectProfileStone[];
    gallery: ProjectProfileImage[];
};

export type ProjectCardData = {
    slug: string;
    name: string;
    thumbnail: string;
};

export type BrandCardData = {
    slug: string;
    name: string;
    tagline: string | null;
    logo: string;
    thumbnail: string | null;
};

/** The brand a product belongs to, enough to build its URL and breadcrumb. */
export type ProductBrand = {
    slug: string;
    name: string;
};

export type BrandProfile = BrandCardData & {
    description: string | null;
    websiteUrl: string | null;
    thumbnail: string | null;
    projects: ProjectCardData[];
};

export type FurnitureCardData = {
    slug: string;
    name: string;
    thumbnail: string | null;
    brand: ProductBrand;
    furnitureType: { slug: string; name: string };
};

export type FurnitureProfile = FurnitureCardData & {
    description: string | null;
};

export type FilterOption = {
    value: string;
    label: string;
    bestSeller?: boolean;
};

export type FilterOptions = {
    types: FilterOption[];
    sizes?: FilterOption[];
    thicknesses?: FilterOption[];
    furnitureTypes?: FilterOption[];
};

export type BrandFilters = {
    collection: BrandCollectionKind;
    search: string | null;
    type: string | null;
    size: string | null;
    thickness: string | null;
    furnitureType: string | null;
    perPage: number;
};

/** One card in a brand's collection; `kind` says which link and save list it uses. */
export type BrandProduct =
    | (StoneCardData & { kind: 'stone' })
    | (FurnitureCardData & { kind: 'furniture' });

export type BrandPageProps = {
    brand: { data: BrandProfile };
    products?: {
        data: BrandProduct[];
    };
    hasProducts: boolean;
    filters: BrandFilters;
    filterOptions: FilterOptions;
};

export type HomePageProps = {
    brands: BrandCardData[];
};

/** A finished project on the landing page (LandingProjectResource). */
export type LandingProject = {
    slug: string;
    name: string;
    location: string;
    completedYear: number;
    thumbnail: string;
};

/** A group of services on the landing page (LandingServiceTypeResource). */
export type LandingServiceType = {
    slug: string;
    name: string;
    services: { slug: string; name: string; description: string }[];
};

export type LandingPageProps = HomePageProps & {
    projects: LandingProject[];
    serviceTypes: LandingServiceType[];
};

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

/** One fact about a brand, such as "Made in" / "Treia, Italy". */
export type BrandFact = { label: string; value: string };

/** The social networks a brand can link to (SocialPlatform on the server). */
export type SocialPlatform =
    | 'facebook'
    | 'twitter'
    | 'x'
    | 'instagram'
    | 'tiktok';

/** One of a brand's social links, in the order its page shows them. */
export type BrandSocial = { platform: SocialPlatform; url: string };

export type BrandProfile = BrandCardData & {
    description: string | null;
    websiteUrl: string | null;
    thumbnail: string | null;
    /** Set on the admin's Brands page, in the order they show. */
    facts: BrandFact[];
    /** What the brand makes, such as "Kitchens". */
    makes: string[];
    /** Its social links, one per network, each shown with that network's icon. */
    socials: BrandSocial[];
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

/**
 * What a client may choose for a piece of furniture, from the Inquire page's
 * service for its type (FurnitureCustomizationService).
 */
export type FurnitureCustomization = {
    /** The stone types the piece can be made in. */
    stones: string[];
    /** Each question's answers: "Table base": Wood, Steel, Same as stone. */
    options: { label: string; values: string[] }[];
};

/** The piece before or after this one in its brand's catalog. */
export type FurnitureStep = { slug: string; name: string } | null;

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

/** A stone or furniture type a brand's products come in, with how many. */
export type BrandOffering = {
    collection: 'stones' | 'furniture';
    /** What the collection's type filter takes for it. */
    value: string;
    name: string;
    count: number;
    thumbnail: string | null;
};

/** What a brand offers, counted from its active products. */
export type BrandOffer = {
    stones: number;
    furniture: number;
    types: BrandOffering[];
};

export type BrandPageProps = {
    brand: { data: BrandProfile };
    products?: {
        data: BrandProduct[];
    };
    hasProducts: boolean;
    filters: BrandFilters;
    filterOptions: FilterOptions;
    offer: BrandOffer;
    /** Its published projects, or those made with its stones, latest first. */
    projects: LandingProject[];
    /** The group's other brands, in their home page order. */
    otherBrands: LandingBrand[];
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

/** A brand as the landing page introduces it (LandingBrandResource). */
export type LandingBrand = {
    slug: string;
    name: string;
    description: string | null;
    /** The brand's own website, when it has one. */
    websiteUrl: string | null;
    logo: string;
    thumbnail: string | null;
};

export type LandingPageProps = {
    brands: LandingBrand[];
    projects: LandingProject[];
};

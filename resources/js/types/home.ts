import type { StoneCardData } from '@/types/stone';

export type ProjectProfileCategory = {
    slug: string;
    name: string;
};

export type ProjectProfileStone = {
    slug: string;
    name: string;
    thumbnail: string;
    bestSeller: boolean;
    category: { slug: string; name: string };
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
    description: string | null;
    logo: string;
};

export type BrandProfile = BrandCardData & {
    tagline: string | null;
    websiteUrl: string | null;
    thumbnail: string | null;
    projects: ProjectCardData[];
};

export type FurnitureCardData = {
    slug: string;
    name: string;
    thumbnail: string | null;
    projectType: { slug: string; name: string };
};

export type FurnitureProfile = FurnitureCardData & {
    description: string | null;
};

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterOptions = {
    categories: FilterOption[];
    sizes?: FilterOption[];
    thicknesses?: FilterOption[];
    /** The project types, which the Furniture collection filters by. */
    projectTypes?: FilterOption[];
};

/** The home page's collections, as the `collection` query param names them. */
export type HomeCollection = 'stones' | 'furniture';

export type Filters = {
    search: string | null;
    category: string | null;
    size: string | null;
    thickness: string | null;
    projectCategory: string | null;
    collection?: HomeCollection;
    perPage: number;
};

export type HomePageProps = {
    stones?: {
        data: StoneCardData[];
    };
    furniture?: {
        data: FurnitureCardData[];
    };
    brands: BrandCardData[];
    filters: Filters;
    filterOptions: FilterOptions;
};

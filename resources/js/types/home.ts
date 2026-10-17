import type { StoneCardData } from '@/types/stone';

export type ProjectProfileCategory = {
    slug: string;
    name: string;
};

export type ProjectProfileStone = {
    slug: string;
    name: string;
    thumbnail: string;
    recommended: boolean;
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
    stone: { slug: string; name: string };
    categories: ProjectProfileCategory[];
    stones: ProjectProfileStone[];
    gallery: ProjectProfileImage[];
};

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterOptions = {
    categories: FilterOption[];
    sizes: FilterOption[];
    thicknesses: FilterOption[];
};

export type Filters = {
    search: string | null;
    category: string | null;
    size: string | null;
    thickness: string | null;
};

export type HomePageProps = {
    stones: {
        data: StoneCardData[];
    };
    filters: Filters;
    filterOptions: FilterOptions;
};

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

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterOptions = {
    categories: FilterOption[];
    sizes?: FilterOption[];
    thicknesses?: FilterOption[];
    projectCategories?: FilterOption[];
};

export type Filters = {
    search: string | null;
    category: string | null;
    size: string | null;
    thickness: string | null;
    projectCategory: string | null;
    collection?: 'stones' | 'projects';
    perPage: number;
};

export type HomePageProps = {
    stones?: {
        data: StoneCardData[];
    };
    projects?: {
        data: ProjectCardData[];
    };
    filters: Filters;
    filterOptions: FilterOptions;
};

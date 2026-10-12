export type ProjectCardProps = {
    slug: string;
    name: string;
    thumbnail: string;
};

export type ProjectProfileCategory = {
    slug: string;
    name: string;
};

export type ProjectProfileStone = {
    slug: string;
    name: string;
    thumbnail: string;
    recommended: boolean;
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

export type FilterOption = {
    value: string;
    label: string;
};

export type FilterOptions = {
    stones: FilterOption[];
    categories: FilterOption[];
};

export type Filters = {
    search: string | null;
    stone: string | null;
    category: string | null;
};

export type HomePageProps = {
    projects: {
        data: ProjectCardProps[];
    };
    filters: Filters;
    filterOptions: FilterOptions;
};

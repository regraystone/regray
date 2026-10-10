export type ProjectCardProps = {
    slug: string;
    name: string;
    thumbnail: string;
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

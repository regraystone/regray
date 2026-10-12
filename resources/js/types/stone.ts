export type StoneProfileImage = {
    src: string;
    name: string;
};

export type StoneProfileProject = {
    slug: string;
    name: string;
    thumbnail: string;
};

export type StoneCardData = {
    id?: string;
    slug: string;
    name: string;
    thumbnail: string;
    recommended: boolean;
};

export type StoneProfile = {
    slug: string;
    name: string;
    thumbnail: string;
    recommended: boolean;
    description: string;
    specifications: {
        stoneType: string;
        origin: string;
        thicknessMm: number;
    };
    finishes: string[];
    applications: string[];
    care: string[];
    gallery: StoneProfileImage[];
    projects: StoneProfileProject[];
};

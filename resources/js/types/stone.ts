export type StoneProfileImage = {
    src: string;
    name: string;
};

export type StoneCardData = {
    id?: string;
    slug: string;
    name: string;
    thumbnail: string;
    brand: { slug: string; name: string };
    category: { slug: string; name: string };
};

export type StoneProfile = {
    slug: string;
    name: string;
    thumbnail: string;
    bestSeller: boolean;
    brand: { slug: string; name: string };
    category: { slug: string; name: string };
    description: string;
    specifications: {
        stoneType: string;
        origin: string;
        size: string;
        thicknessMm: number;
    };
    finishes: string[];
    applications: string[];
    care: string[];
    gallery: StoneProfileImage[];
};

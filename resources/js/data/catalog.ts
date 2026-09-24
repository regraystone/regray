export type FeedItem = {
    id: string;
    name: string;
    image: string;
    aspect: string;
    order?: number;
    recommended?: boolean;
};

export type CatalogItem = FeedItem & {
    material: string;
    price: string;
    category: string;
};

export const products: FeedItem[] = [
    {
        id: 'monolith-island',
        name: 'Monolith Island',
        image: '/images/project-monolith.jpg',
        aspect: 'aspect-[3/4]',
        order: 3,
        recommended: true,
    },
    {
        id: 'travertine-table',
        name: 'Travertine Table',
        image: '/images/project-travertine.jpg',
        aspect: 'aspect-[4/3]',
    },
    {
        id: 'onyx-backdrop',
        name: 'Onyx Backdrop',
        image: '/images/project-onyx.jpg',
        aspect: 'aspect-[4/5]',
        order: 2,
    },
    {
        id: 'limestone-stair',
        name: 'Limestone Stair',
        image: '/images/project-stair.jpg',
        aspect: 'aspect-[4/3]',
        order: 4,
    },
    {
        id: 'noir-console',
        name: 'Noir Console',
        image: '/images/project-table.jpg',
        aspect: 'aspect-[3/4]',
        recommended: true,
    },
    {
        id: 'verde-retreat',
        name: 'Verde Retreat',
        image: '/images/project-bath.jpg',
        aspect: 'aspect-[4/3]',
        order: 1,
    },
];

export const catalog: CatalogItem[] = [
    {
        id: 'monolith-island',
        name: 'Monolith Island',
        image: '/images/project-monolith.jpg',
        aspect: 'aspect-[3/4]',
        material: 'Calacatta stone',
        price: '$42,000',
        category: 'Kitchen',
        recommended: true,
    },
    {
        id: 'travertine-table',
        name: 'Travertine Table',
        image: '/images/project-travertine.jpg',
        aspect: 'aspect-[4/3]',
        material: 'Roman travertine',
        price: '$18,500',
        category: 'Furniture',
    },
    {
        id: 'onyx-backdrop',
        name: 'Onyx Backdrop',
        image: '/images/project-onyx.jpg',
        aspect: 'aspect-[4/5]',
        material: 'Backlit onyx',
        price: '$25,000',
        category: 'Feature wall',
    },
    {
        id: 'limestone-stair',
        name: 'Limestone Stair',
        image: '/images/project-stair.jpg',
        aspect: 'aspect-[4/3]',
        material: 'Limestone treads',
        price: '$31,200',
        category: 'Architecture',
    },
    {
        id: 'noir-console',
        name: 'Noir Console',
        image: '/images/project-table.jpg',
        aspect: 'aspect-[3/4]',
        material: 'Polished noir marble',
        price: '$9,800',
        category: 'Furniture',
        recommended: true,
    },
    {
        id: 'verde-retreat',
        name: 'Verde Retreat',
        image: '/images/project-bath.jpg',
        aspect: 'aspect-[4/3]',
        material: 'Verde alpi marble',
        price: '$27,400',
        category: 'Bathroom',
    },
];

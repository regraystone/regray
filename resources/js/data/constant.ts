export type FeedItem = {
    id: string;
    name: string;
    image: string;
    recommended?: boolean;
};

export type CatalogItem = FeedItem & {
    material: string;
    price: string;
    category: string;
};

export const products: FeedItem[] = [
    {
        id: 'granite',
        name: 'Granite',
        image: '/images/products/granite.webp',
    },
    {
        id: 'marble',
        name: 'Marble',
        image: '/images/products/marble.webp',
    },
    {
        id: 'monumental',
        name: 'Monumental',
        image: '/images/products/monumental.webp',
    },
    {
        id: 'onyx',
        name: 'Onyx',
        image: '/images/products/onyx.webp',
    },
    {
        id: 'patagonia-quartzite',
        name: 'Patagonia Quartzite',
        image: '/images/products/patagonia_quartzite.webp',
    },
    {
        id: 'quartzite',
        name: 'Quartzite',
        image: '/images/products/quartzite.webp',
    },
    {
        id: 'sintered-stone',
        name: 'Sintered Stone',
        image: '/images/products/sintered_stone.webp',
    },
    {
        id: 'travertine',
        name: 'Travertine',
        image: '/images/products/travertine.webp',
    },
];

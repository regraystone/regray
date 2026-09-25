export type FeedItem = {
    id: string;
    name: string;
    image: string;
    recommended: boolean;
};

export type CatalogItem = FeedItem & {
    material: string;
    price: string;
    category: string;
};

export type StoneProfile = FeedItem & {
    description: string;
    gallery: string[];
    specifications: {
        stoneType: string;
        origin: string;
        thickness: string;
    };
    applications: string[];
    finishes: string[];
    care: string[];
    projects: Array<{ id: string; name: string; image: string }>;
};

export const products: FeedItem[] = [
    {
        id: 'granite',
        name: 'Granite',
        image: '/images/products/granite.webp',
        recommended: true,
    },
    {
        id: 'marble',
        name: 'Marble',
        image: '/images/products/marble.webp',
        recommended: false,
    },
    {
        id: 'monumental',
        name: 'Monumental',
        image: '/images/products/monumental.webp',
        recommended: false,
    },
    {
        id: 'onyx',
        name: 'Onyx',
        image: '/images/products/onyx.webp',
        recommended: true,
    },
    {
        id: 'patagonia-quartzite',
        name: 'Patagonia Quartzite',
        image: '/images/products/patagonia_quartzite.webp',
        recommended: true,
    },
    {
        id: 'quartzite',
        name: 'Quartzite',
        image: '/images/products/quartzite.webp',
        recommended: false,
    },
    {
        id: 'sintered-stone',
        name: 'Sintered Stone',
        image: '/images/products/sintered_stone.webp',
        recommended: true,
    },
    {
        id: 'travertine',
        name: 'Travertine',
        image: '/images/products/travertine.webp',
        recommended: false,
    },
];

const hero = '/images/regray-hero.jpg';

export const stoneProfiles: Record<string, StoneProfile> = {
    granite: {
        id: 'granite',
        name: 'Granite',
        image: '/images/products/granite.webp',
        recommended: true,
        description:
            'The workhorse of natural stone, granite is a dense igneous rock prized for its exceptional hardness, heat resistance, and stone-deep durability. A broad palette of speckles, veins, and tones makes it a safe, lasting choice for busy kitchens.',
        gallery: ['/images/products/granite.webp', hero],
        specifications: {
            stoneType: 'Granite',
            origin: 'Brazil, India',
            thickness: '20 mm per slab',
        },
        applications: [
            'Kitchen countertops',
            'Kitchen islands',
            'Bathroom vanities',
            'Backsplashes',
            'Outdoor kitchens',
            'Flooring',
        ],
        finishes: ['Polished', 'Honed', 'Flamed'],
        care: [
            'Seal periodically',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid abrasive pads',
            'Always use trivets for hot pans',
        ],
        projects: [
            {
                id: 'granite-island',
                name: 'Cloud Kitchen Island',
                image: '/images/products/granite.webp',
            },
            {
                id: 'granite-vanity',
                name: 'Rue Est Vanity',
                image: hero,
            },
        ],
    },
    marble: {
        id: 'marble',
        name: 'Marble',
        image: '/images/products/marble.webp',
        recommended: false,
        description:
            'A classic metamorphic stone celebrated for its luminous surface and elegant, dramatic veining. Each slab is unique, bringing quiet luxury to vanities, counters, and walls.',
        gallery: ['/images/products/marble.webp', hero],
        specifications: {
            stoneType: 'Marble',
            origin: 'Italy, Turkey',
            thickness: '18 mm per slab',
        },
        applications: [
            'Luxury countertops',
            'Bathroom vanities',
            'Wall cladding',
            'Fireplace surrounds',
            'Flooring',
            'Sculptural furniture',
        ],
        finishes: ['Polished', 'Honed', 'Brushed'],
        care: [
            'Seal frequently',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid acidic cleaners like lemon and vinegar',
            'Protect from staining liquids such as wine and coffee',
        ],
        projects: [
            {
                id: 'marble-bath',
                name: 'Palazzo Bath Suite',
                image: '/images/products/marble.webp',
            },
            {
                id: 'marble-wall',
                name: 'Gallery Wall Cladding',
                image: hero,
            },
        ],
    },
    monumental: {
        id: 'monumental',
        name: 'Monumental',
        image: '/images/products/monumental.webp',
        recommended: false,
        description:
            'The densest and hardest natural stone available, cut for a glass-like, mirror-polished finish. Its extreme durability and scratch resistance make it ideal for high-traffic surfaces that must stay beautiful.',
        gallery: ['/images/products/monumental.webp', hero],
        specifications: {
            stoneType: 'Monumental',
            origin: 'South Africa',
            thickness: '20 mm per slab',
        },
        applications: [
            'Kitchen countertops',
            'Kitchen islands',
            'Bathroom vanities',
            'High-traffic flooring',
            'Outdoor surfaces',
            'Statement furniture',
        ],
        finishes: ['Polished', 'Leathered', 'Brushed'],
        care: [
            'Seal periodically',
            'Clean with pH-neutral cleaner',
            'Wipe spills promptly',
            'Avoid harsh chemical cleaners',
            'Use cutting mats',
            'Protect from heavy impact',
        ],
        projects: [
            {
                id: 'monumental-island',
                name: 'Foundry Kitchen Island',
                image: '/images/products/monumental.webp',
            },
            {
                id: 'monumental-terrace',
                name: 'Avenue Ref Landscape',
                image: hero,
            },
        ],
    },
    onyx: {
        id: 'onyx',
        name: 'Onyx',
        image: '/images/products/onyx.webp',
        recommended: true,
        description:
            'A translucent, banded stone that literally glows when lit from behind. Prized for statement backlit features, onyx turns any surface into a piece of art.',
        gallery: ['/images/products/onyx.webp', hero],
        specifications: {
            stoneType: 'Onyx',
            origin: 'Iran, Turkey',
            thickness: '18 mm per slab',
        },
        applications: [
            'Backlit feature walls',
            'Bar and counter fronts',
            'Decorative vanities',
            'Coffee tables',
            'Wall cladding',
            'Sculptural pieces',
        ],
        finishes: ['Polished', 'Honed', 'Matte'],
        care: [
            'Seal periodically',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid acidic and abrasive cleaners',
            'Use soft microfiber cloths',
            'Avoid direct heavy impact',
        ],
        projects: [
            {
                id: 'onyx-bar',
                name: 'Amber Bar Front',
                image: '/images/products/onyx.webp',
            },
            {
                id: 'onyx-wall',
                name: 'Nocturne Feature Wall',
                image: hero,
            },
        ],
    },
    'patagonia-quartzite': {
        id: 'patagonia-quartzite',
        name: 'Patagonia Quartzite',
        image: '/images/products/patagonia_quartzite.webp',
        recommended: true,
        description:
            'A show-stopping quartzite defined by sweeping cream, grey, and copper veining on a luminous white base. It pairs the beauty of marble with granite-grade toughness, performing beautifully in kitchens and on clad features.',
        gallery: ['/images/products/patagonia_quartzite.webp', hero],
        specifications: {
            stoneType: 'Patagonia Quartzite',
            origin: 'Brazil',
            thickness: '18 mm per slab',
        },
        applications: [
            'Luxury kitchen countertops',
            'Kitchen islands',
            'Bathroom vanities',
            'Dining and coffee tables',
            'Wall cladding and feature walls',
            'Fireplace surrounds',
        ],
        finishes: ['Polished', 'Honed', 'Leathered'],
        care: [
            'Seal periodically',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid acidic cleaners',
            'Use soft microfiber cloths',
            'Protect from heavy impact',
        ],
        projects: [
            {
                id: 'patagonia-island',
                name: "L'Atelier Kitchen Island",
                image: '/images/products/patagonia_quartzite.webp',
            },
            {
                id: 'patagonia-cladding',
                name: 'Soho Penthouse Cladding',
                image: hero,
            },
        ],
    },
    quartzite: {
        id: 'quartzite',
        name: 'Quartzite',
        image: '/images/products/quartzite.webp',
        recommended: false,
        description:
            'A naturally metamorphosed, quartz-rich stone that pairs marble-like movement with granite-grade strength. Its low porosity and heat resistance make it a favorite for high-traffic kitchen counters.',
        gallery: ['/images/products/quartzite.webp', hero],
        specifications: {
            stoneType: 'Quartzite',
            origin: 'Brazil, Norway',
            thickness: '18 mm per slab',
        },
        applications: [
            'Kitchen countertops',
            'Kitchen islands',
            'Bathroom vanities',
            'Backsplashes',
            'Wall cladding',
            'Outdoor bars',
        ],
        finishes: ['Polished', 'Honed', 'Leathered'],
        care: [
            'Seal periodically',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid acidic cleaners',
            'Use soft microfiber cloths',
            'Protect from heavy impact',
        ],
        projects: [
            {
                id: 'quartzite-counter',
                name: 'Brew House Counter',
                image: '/images/products/quartzite.webp',
            },
            {
                id: 'quartzite-terrace',
                name: 'Solstice Terrace Bar',
                image: hero,
            },
        ],
    },
    'sintered-stone': {
        id: 'sintered-stone',
        name: 'Sintered Stone',
        image: '/images/products/sintered_stone.webp',
        recommended: true,
        description:
            'A high-tech porcelain surface formed at extreme temperature, delivering a stone look with unmatched consistency. Ultra-thin, non-porous, and stain-proof, it needs no sealing and shrugs off heat and scratches.',
        gallery: ['/images/products/sintered_stone.webp', hero],
        specifications: {
            stoneType: 'Sintered Stone',
            origin: 'Spain',
            thickness: '12 mm per slab',
        },
        applications: [
            'Kitchen countertops',
            'Facade cladding',
            'Bathroom panels',
            'Outdoor kitchens',
            'Dining and coffee tables',
            'Flooring',
        ],
        finishes: ['Silk Satin', 'Polished', 'Grain Structured'],
        care: [
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'No sealing required',
            'Avoid abrasive scouring pads',
            'Use soft microfiber cloths',
            'Resistant to heat, but use trivets',
        ],
        projects: [
            {
                id: 'sintered-facade',
                name: 'Studio Facade',
                image: '/images/products/sintered_stone.webp',
            },
            {
                id: 'sintered-outdoor',
                name: 'Kona Outdoor Kitchen',
                image: hero,
            },
        ],
    },
    travertine: {
        id: 'travertine',
        name: 'Travertine',
        image: '/images/products/travertine.webp',
        recommended: false,
        description:
            'A warm, porous sedimentary stone with soft, earthy tones and a naturally pitted texture. Its time-honored character brings old-world warmth to bathrooms, patios, and feature walls.',
        gallery: ['/images/products/travertine.webp', hero],
        specifications: {
            stoneType: 'Travertine',
            origin: 'Turkey, Italy',
            thickness: '20 mm per slab',
        },
        applications: [
            'Bathroom walls and floors',
            'Facade cladding',
            'Fireplace surrounds',
            'Outdoor patios',
            'Pool decks',
            'Feature walls',
        ],
        finishes: ['Honed', 'Tumbled', 'Brushed'],
        care: [
            'Seal frequently',
            'Clean with pH-neutral cleaner',
            'Wipe spills quickly',
            'Avoid acidic cleaners',
            'Re-seal after resurfacing',
            'Use soft microfiber cloths',
        ],
        projects: [
            {
                id: 'travertine-deck',
                name: 'Terrace Pool Deck',
                image: '/images/products/travertine.webp',
            },
            {
                id: 'travertine-bath',
                name: 'Bloom Bath Feature',
                image: hero,
            },
        ],
    },
};

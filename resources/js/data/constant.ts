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

export const currentUserId = 'me';

export type Person = {
    id: string;
    name: string;
    role: string;
    avatar?: string;
};

export type ChatMessage = {
    id: string;
    senderId: string;
    text: string;
    sentAt: string;
};

export type Conversation = {
    id: string;
    type: 'direct' | 'group';
    name: string;
    avatar?: string;
    unread: boolean;
    memberIds: string[];
    messages: ChatMessage[];
};

export const people: Person[] = [
    { id: 'maria', name: 'Maria Alvarez', role: 'Stone Consultant' },
    { id: 'daniel', name: 'Daniel Okafor', role: 'Project Manager' },
    { id: 'sofia', name: 'Sofia Bianchi', role: 'Interior Designer' },
    { id: 'kenji', name: 'Kenji Watanabe', role: 'Procurement Lead' },
    { id: 'priya', name: 'Priya Raman', role: 'Structural Engineer' },
    { id: 'lucas', name: 'Lucas Moreau', role: 'Kitchen Studio Owner' },
    { id: 'amara', name: 'Amara Nwosu', role: 'Sales Director' },
    { id: 'tomas', name: 'Tomas Lindqvist', role: 'Logistics Coordinator' },
    {
        id: 'beatriz',
        name: 'Beatriz Vasconcelos Cummings',
        role: 'Architectural Stone Specialist',
    },
    {
        id: 'hassan',
        name: 'Hassan Al-Farsi',
        role: 'Facade & Cladding Advisor',
    },
];

export const conversations: Conversation[] = [
    {
        id: 'chat-maria',
        type: 'direct',
        name: 'Maria Alvarez',
        unread: false,
        memberIds: ['maria'],
        messages: [
            {
                id: 'm-1',
                senderId: 'maria',
                text: 'The Patagonia quartzite samples you approved are ready to collect.',
                sentAt: '2026-09-28T09:12:00.000Z',
            },
            {
                id: 'm-2',
                senderId: 'me',
                text: 'Great — I will pick them up Thursday afternoon.',
                sentAt: '2026-09-28T09:14:00.000Z',
            },
            {
                id: 'm-3',
                senderId: 'maria',
                text: 'Perfect. I will have them wrapped and labelled for the kitchen.',
                sentAt: '2026-09-28T09:15:00.000Z',
            },
            {
                id: 'm-4',
                senderId: 'me',
                text: 'Thank you Maria!',
                sentAt: '2026-09-28T09:16:00.000Z',
            },
        ],
    },
    {
        id: 'chat-supply',
        type: 'group',
        name: 'Supply Chain & Global Freight Coordination',
        unread: true,
        memberIds: ['daniel', 'kenji', 'tomas'],
        messages: [
            {
                id: 'm-5',
                senderId: 'daniel',
                text: 'Booking the vessel slot for the Italian marble this week.',
                sentAt: '2026-09-27T15:40:00.000Z',
            },
            {
                id: 'm-6',
                senderId: 'tomas',
                text: 'Port congestion is still high, expect four extra days on arrival.',
                sentAt: '2026-09-27T15:52:00.000Z',
            },
            {
                id: 'm-7',
                senderId: 'kenji',
                text: 'Then we shift the showroom install to the following Monday.',
                sentAt: '2026-09-27T16:05:00.000Z',
            },
        ],
    },
    {
        id: 'chat-daniel',
        type: 'direct',
        name: 'Daniel Okafor',
        unread: true,
        memberIds: ['daniel'],
        messages: [
            {
                id: 'm-8',
                senderId: 'daniel',
                text: 'Quote for the tower lobby is attached for your review.',
                sentAt: '2026-09-27T11:20:00.000Z',
            },
            {
                id: 'm-9',
                senderId: 'me',
                text: 'Got it, reviewing the slab quantities now.',
                sentAt: '2026-09-27T11:31:00.000Z',
            },
            {
                id: 'm-10',
                senderId: 'daniel',
                text: 'Anything unclear, send it over.',
                sentAt: '2026-09-27T11:33:00.000Z',
            },
        ],
    },
    {
        id: 'chat-kitchen',
        type: 'group',
        name: 'Granite Kitchen Project',
        avatar: hero,
        unread: false,
        memberIds: ['sofia', 'lucas', 'maria'],
        messages: [
            {
                id: 'm-11',
                senderId: 'sofia',
                text: 'Uploaded the revised kitchen elevation with the waterfall island.',
                sentAt: '2026-09-26T08:45:00.000Z',
            },
            {
                id: 'm-12',
                senderId: 'lucas',
                text: 'Looking good. Can we thicken the edge profile to 40 mm?',
                sentAt: '2026-09-26T09:10:00.000Z',
            },
            {
                id: 'm-13',
                senderId: 'me',
                text: 'Yes, that is within our standard fabrication range.',
                sentAt: '2026-09-26T09:18:00.000Z',
            },
            {
                id: 'm-14',
                senderId: 'maria',
                text: 'I will update the quote to reflect the thicker edge.',
                sentAt: '2026-09-26T09:24:00.000Z',
            },
            {
                id: 'm-15',
                senderId: 'sofia',
                text: 'Thanks Maria. Lucas, we can confirm on our end.',
                sentAt: '2026-09-26T09:30:00.000Z',
            },
        ],
    },
    {
        id: 'chat-sofia',
        type: 'direct',
        name: 'Sofia Bianchi',
        unread: false,
        memberIds: ['sofia'],
        messages: [
            {
                id: 'm-16',
                senderId: 'me',
                text: 'Do you have the honed finish samples for the bathroom?',
                sentAt: '2026-09-25T14:02:00.000Z',
            },
            {
                id: 'm-17',
                senderId: 'sofia',
                text: 'Yes, sending a courier over tomorrow morning.',
                sentAt: '2026-09-25T14:09:00.000Z',
            },
        ],
    },
    {
        id: 'chat-facade',
        type: 'group',
        name: 'Facade Cladding Coordination, Tower B Phase Two, Harbourfront Development',
        unread: true,
        memberIds: ['hassan', 'beatriz', 'priya', 'daniel'],
        messages: [],
    },
    {
        id: 'chat-kenji',
        type: 'direct',
        name: 'Kenji Watanabe',
        unread: false,
        memberIds: ['kenji'],
        messages: [
            {
                id: 'm-18',
                senderId: 'kenji',
                text: 'Can you hold the onyx allocation until Friday?',
                sentAt: '2026-09-24T10:15:00.000Z',
            },
            {
                id: 'm-19',
                senderId: 'me',
                text: 'Held. I will confirm the balance once the deposit clears.',
                sentAt: '2026-09-24T10:22:00.000Z',
            },
        ],
    },
    {
        id: 'chat-priya',
        type: 'direct',
        name: 'Priya Raman',
        unread: true,
        memberIds: ['priya'],
        messages: [
            {
                id: 'm-20',
                senderId: 'priya',
                text: 'Structural report for the cantilevered counter is ready for sign-off.',
                sentAt: '2026-09-23T16:48:00.000Z',
            },
        ],
    },
];

export type InquiryStatus =
    | 'draft'
    | 'pending'
    | 'quoted'
    | 'paid'
    | 'completed'
    | 'cancelled';

export type Inquiry = {
    id: string;
    reference: string;
    status: InquiryStatus;
    createdAt: string;
    stoneIds: string[];
    services: string[];
    projectName: string;
};

export const inquiries: Inquiry[] = [
    {
        id: 'inq-1050',
        reference: 'INQ-1050',
        status: 'draft',
        createdAt: '2026-09-29T01:20:00.000Z',
        stoneIds: ['patagonia-quartzite', 'marble'],
        services: ['Site Measurement', 'Fabrication'],
        projectName: 'Riverside Residence — Kitchen',
    },
    {
        id: 'inq-1048',
        reference: 'INQ-1048',
        status: 'pending',
        createdAt: '2026-09-27T03:40:00.000Z',
        stoneIds: ['granite', 'quartzite', 'sintered-stone'],
        services: [
            'Site Measurement',
            'Fabrication',
            'Delivery & Installation',
        ],
        projectName: 'Northgate Cafe — Counters',
    },
    {
        id: 'inq-1047',
        reference: 'INQ-1047',
        status: 'pending',
        createdAt: '2026-09-25T07:15:00.000Z',
        stoneIds: ['marble', 'travertine', 'monumental', 'granite'],
        services: ['Fabrication', 'Edge Profiling'],
        projectName: 'Beacon House — Reception Desk',
    },
    {
        id: 'inq-1046',
        reference: 'INQ-1046',
        status: 'quoted',
        createdAt: '2026-09-22T02:30:00.000Z',
        stoneIds: ['sintered-stone', 'onyx'],
        services: [
            'Site Measurement',
            'Fabrication',
            'Delivery & Installation',
            'Sealing & Protection',
        ],
        projectName: 'Aurora Tower — Lobby Cladding',
    },
    {
        id: 'inq-1045',
        reference: 'INQ-1045',
        status: 'quoted',
        createdAt: '2026-09-18T09:05:00.000Z',
        stoneIds: ['quartzite'],
        services: ['Fabrication'],
        projectName: 'Lakeside Villa — Staircase',
    },
    {
        id: 'inq-1044',
        reference: 'INQ-1044',
        status: 'quoted',
        createdAt: '2026-09-15T04:50:00.000Z',
        stoneIds: ['travertine', 'marble'],
        services: ['Site Measurement', 'Sealing & Protection'],
        projectName: 'Garden Court — Vanity Tops',
    },
    {
        id: 'inq-1043',
        reference: 'INQ-1043',
        status: 'paid',
        createdAt: '2026-09-11T08:25:00.000Z',
        stoneIds: ['granite', 'monumental'],
        services: [
            'Fabrication',
            'Sink & Hob Cut-outs',
            'Delivery & Installation',
        ],
        projectName: 'Hillcrest Showroom — Display Units',
    },
    {
        id: 'inq-1042',
        reference: 'INQ-1042',
        status: 'paid',
        createdAt: '2026-09-04T01:10:00.000Z',
        stoneIds: ['marble'],
        services: ['Fabrication'],
        projectName: 'Marlow Street Bathroom',
    },
    {
        id: 'inq-1041',
        reference: 'INQ-1041',
        status: 'completed',
        createdAt: '2026-08-28T05:45:00.000Z',
        stoneIds: ['quartzite', 'granite', 'marble'],
        services: [
            'Site Measurement',
            'Fabrication',
            'Delivery & Installation',
        ],
        projectName: 'The Grove — Outdoor Kitchen',
    },
    {
        id: 'inq-1040',
        reference: 'INQ-1040',
        status: 'completed',
        createdAt: '2026-08-19T03:20:00.000Z',
        stoneIds: ['travertine'],
        services: ['Fabrication', 'Edge Profiling'],
        projectName: 'Sunset Ridge — Pool Surround',
    },
    {
        id: 'inq-1039',
        reference: 'INQ-1039',
        status: 'cancelled',
        createdAt: '2026-08-12T06:55:00.000Z',
        stoneIds: ['onyx', 'sintered-stone'],
        services: ['Fabrication'],
        projectName: 'Maple Lane — Laundry',
    },
];

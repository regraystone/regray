export type StoneImage = {
    id: string;
    stoneId: string;
    name: string;
    src: string;
    created_at: string;
};

export type Stone = {
    id: string;
    name: string;
    thumbnail: string;
    recommended: boolean;
};

export type StoneProfile = Stone & {
    description: string;
    gallery: StoneImage[];
    specifications: {
        stoneType: string;
        origin: string;
        thickness: string;
    };
    applications: string[];
    finishes: string[];
    care: string[];
};

export const stones: Stone[] = [
    {
        id: 'cobblestone',
        name: 'Cobblestone',
        thumbnail: '/storage/stones/ef03fcfd4ff0e6063df004f6b897fe7a.png',
        recommended: false,
    },
    {
        id: 'crazy-cut',
        name: 'Crazy Cut',
        thumbnail: '/storage/stones/1715fd73a1ddbc8f8ae32158bd35ae4a.png',
        recommended: true,
    },
    {
        id: 'granite',
        name: 'Granite',
        thumbnail: '/storage/stones/8f7804fd9673f34737ecd0c1dfca9723.png',
        recommended: true,
    },
    {
        id: 'marble',
        name: 'Marble',
        thumbnail: '/storage/stones/a2a72c93b7369e681b72f0e05380df17.png',
        recommended: false,
    },
    {
        id: 'monumental',
        name: 'Monumental',
        thumbnail: '/storage/stones/20845a043f5abd823694192363b27458.png',
        recommended: false,
    },
    {
        id: 'onyx',
        name: 'Onyx',
        thumbnail: '/storage/stones/4b683a754fea6cc2e9c52669a4c2ca34.png',
        recommended: true,
    },
    {
        id: 'patagonia-quartzite',
        name: 'Patagonia Quartzite',
        thumbnail: '/storage/stones/32429db4b70ad914e80ab79f031e4263.png',
        recommended: true,
    },
    {
        id: 'quartzite',
        name: 'Quartzite',
        thumbnail: '/storage/stones/5b7cb3dae550009c37ad0cb518f4d09f.png',
        recommended: false,
    },
    {
        id: 'travertine',
        name: 'Travertine',
        thumbnail: '/storage/stones/bac6a5d8d41046a9fe8cb35205cbd34c.png',
        recommended: false,
    },
];

/**
 * One row per stone photograph, in the order the catalogue lists them.
 *
 * The gallery is this list filtered by stone rather than a second hand-kept
 * array, so a card image and the gallery of the same stone can never disagree
 * about which file is which.
 */
const stoneImages: StoneImage[] = [
    {
        id: 'ef03fcfd4ff0e6063df004f6b897fe7a',
        stoneId: 'cobblestone',
        name: 'Flamed + Brushed - Black',
        src: '/storage/stones/ef03fcfd4ff0e6063df004f6b897fe7a.png',
        created_at: '2026-09-30T00:00:00.000Z',
    },
    {
        id: '303335ce8c8f17b45f25f5d6cb8ed7ac',
        stoneId: 'cobblestone',
        name: 'Flamed - Gray',
        src: '/storage/stones/303335ce8c8f17b45f25f5d6cb8ed7ac.png',
        created_at: '2026-09-30T00:01:00.000Z',
    },
    {
        id: 'e077cee8b1f24629145a7074fdea5451',
        stoneId: 'cobblestone',
        name: 'Warm Beige Granite',
        src: '/storage/stones/e077cee8b1f24629145a7074fdea5451.png',
        created_at: '2026-09-30T00:02:00.000Z',
    },
    {
        id: '1715fd73a1ddbc8f8ae32158bd35ae4a',
        stoneId: 'crazy-cut',
        name: 'Bronze Sand Granite',
        src: '/storage/stones/1715fd73a1ddbc8f8ae32158bd35ae4a.png',
        created_at: '2026-09-30T00:03:00.000Z',
    },
    {
        id: '1f5c3b456d0f69aa6f854310f81348ed',
        stoneId: 'crazy-cut',
        name: 'Crystal Beige Marble',
        src: '/storage/stones/1f5c3b456d0f69aa6f854310f81348ed.png',
        created_at: '2026-09-30T00:04:00.000Z',
    },
    {
        id: 'f0dc7d8531ffaba69e5d4da2866728eb',
        stoneId: 'crazy-cut',
        name: 'Crystal Gray Marble',
        src: '/storage/stones/f0dc7d8531ffaba69e5d4da2866728eb.png',
        created_at: '2026-09-30T00:05:00.000Z',
    },
    {
        id: '8a5822b577d606de90e2dd3130365e4e',
        stoneId: 'crazy-cut',
        name: 'Crystal White Marble',
        src: '/storage/stones/8a5822b577d606de90e2dd3130365e4e.png',
        created_at: '2026-09-30T00:06:00.000Z',
    },
    {
        id: 'c9bcb7e62823662558eb9edd381dd471',
        stoneId: 'crazy-cut',
        name: 'Ivory Limestone',
        src: '/storage/stones/c9bcb7e62823662558eb9edd381dd471.png',
        created_at: '2026-09-30T00:07:00.000Z',
    },
    {
        id: '6731817278576f313caa9d31e09a516b',
        stoneId: 'crazy-cut',
        name: 'Nimbus Granite',
        src: '/storage/stones/6731817278576f313caa9d31e09a516b.png',
        created_at: '2026-09-30T00:08:00.000Z',
    },
    {
        id: '61042c492124b58138ec1774ff7e1a4f',
        stoneId: 'crazy-cut',
        name: 'Shutter Bronze Granite',
        src: '/storage/stones/61042c492124b58138ec1774ff7e1a4f.png',
        created_at: '2026-09-30T00:09:00.000Z',
    },
    {
        id: 'da50847b533363f3d4f4b7bb33abcd8c',
        stoneId: 'crazy-cut',
        name: 'Shutter Grey Granite',
        src: '/storage/stones/da50847b533363f3d4f4b7bb33abcd8c.png',
        created_at: '2026-09-30T00:10:00.000Z',
    },
    {
        id: '8f7804fd9673f34737ecd0c1dfca9723',
        stoneId: 'granite',
        name: 'Cosmic Black',
        src: '/storage/stones/8f7804fd9673f34737ecd0c1dfca9723.png',
        created_at: '2026-09-30T00:11:00.000Z',
    },
    {
        id: '1615cbbdf1ed29f40766804cc35991dc',
        stoneId: 'granite',
        name: 'Golden Fusion',
        src: '/storage/stones/1615cbbdf1ed29f40766804cc35991dc.png',
        created_at: '2026-09-30T00:12:00.000Z',
    },
    {
        id: 'a2a72c93b7369e681b72f0e05380df17',
        stoneId: 'marble',
        name: 'Arabescato White',
        src: '/storage/stones/a2a72c93b7369e681b72f0e05380df17.png',
        created_at: '2026-09-30T00:13:00.000Z',
    },
    {
        id: '473e456b584629db88130d3b78d2d1fc',
        stoneId: 'marble',
        name: 'Burberry Pink',
        src: '/storage/stones/473e456b584629db88130d3b78d2d1fc.png',
        created_at: '2026-09-30T00:14:00.000Z',
    },
    {
        id: 'f6d2208c50cbbdc0f14c7406c60db39e',
        stoneId: 'marble',
        name: 'Calacatta Viola I',
        src: '/storage/stones/f6d2208c50cbbdc0f14c7406c60db39e.png',
        created_at: '2026-09-30T00:15:00.000Z',
    },
    {
        id: '27898ee4c63efe8d4005c8e9153d85d4',
        stoneId: 'marble',
        name: 'Calacatta Viola II',
        src: '/storage/stones/27898ee4c63efe8d4005c8e9153d85d4.png',
        created_at: '2026-09-30T00:16:00.000Z',
    },
    {
        id: 'bd3d111d2dd7905d9641475abecc9596',
        stoneId: 'marble',
        name: 'Calacatta',
        src: '/storage/stones/bd3d111d2dd7905d9641475abecc9596.png',
        created_at: '2026-09-30T00:17:00.000Z',
    },
    {
        id: 'ff2534f167572ee010073cecb0eaac95',
        stoneId: 'marble',
        name: 'Carara White',
        src: '/storage/stones/ff2534f167572ee010073cecb0eaac95.png',
        created_at: '2026-09-30T00:18:00.000Z',
    },
    {
        id: '3296217e5c4e67912a97116405d727a8',
        stoneId: 'marble',
        name: 'Cippolino Ondualto II',
        src: '/storage/stones/3296217e5c4e67912a97116405d727a8.png',
        created_at: '2026-09-30T00:19:00.000Z',
    },
    {
        id: '8cdbde2f54d5b6bb802b7b34cb5ac407',
        stoneId: 'marble',
        name: 'Crema Marfil',
        src: '/storage/stones/8cdbde2f54d5b6bb802b7b34cb5ac407.png',
        created_at: '2026-09-30T00:20:00.000Z',
    },
    {
        id: '228a7cf8b1c31f44826566f237a87bd0',
        stoneId: 'marble',
        name: 'Emperador',
        src: '/storage/stones/228a7cf8b1c31f44826566f237a87bd0.png',
        created_at: '2026-09-30T00:21:00.000Z',
    },
    {
        id: '402c91800f583d2c9c6243f0a54b910a',
        stoneId: 'marble',
        name: 'Fendi Blu Gray',
        src: '/storage/stones/402c91800f583d2c9c6243f0a54b910a.png',
        created_at: '2026-09-30T00:22:00.000Z',
    },
    {
        id: '7a6913c46247c116b2d4e2256c7d2bd6',
        stoneId: 'marble',
        name: 'Golden Black Marquina',
        src: '/storage/stones/7a6913c46247c116b2d4e2256c7d2bd6.png',
        created_at: '2026-09-30T00:23:00.000Z',
    },
    {
        id: '9d257b787fad628a1b5e1abe89b85821',
        stoneId: 'marble',
        name: 'Laurent Black',
        src: '/storage/stones/9d257b787fad628a1b5e1abe89b85821.png',
        created_at: '2026-09-30T00:24:00.000Z',
    },
    {
        id: '597fb4392dc232b239d1264c0d36eb12',
        stoneId: 'marble',
        name: 'Nero Marquina',
        src: '/storage/stones/597fb4392dc232b239d1264c0d36eb12.png',
        created_at: '2026-09-30T00:25:00.000Z',
    },
    {
        id: '4702e130229372e2ade94736c70ce380',
        stoneId: 'marble',
        name: 'Panda White',
        src: '/storage/stones/4702e130229372e2ade94736c70ce380.png',
        created_at: '2026-09-30T00:26:00.000Z',
    },
    {
        id: '0e9ec3617fab7e1cab37d30b35437337',
        stoneId: 'marble',
        name: 'Rosso Levanto',
        src: '/storage/stones/0e9ec3617fab7e1cab37d30b35437337.png',
        created_at: '2026-09-30T00:27:00.000Z',
    },
    {
        id: '278970ab37851866d2ee11f705c2b032',
        stoneId: 'marble',
        name: 'Shangrila Pearl White',
        src: '/storage/stones/278970ab37851866d2ee11f705c2b032.png',
        created_at: '2026-09-30T00:28:00.000Z',
    },
    {
        id: 'e89704d00e5335c7dadc7fb262032bc8',
        stoneId: 'marble',
        name: 'Star White',
        src: '/storage/stones/e89704d00e5335c7dadc7fb262032bc8.png',
        created_at: '2026-09-30T00:29:00.000Z',
    },
    {
        id: 'ed029bc5ca18196f3bd479cde9d8e2fa',
        stoneId: 'marble',
        name: 'Verdi Alpi',
        src: '/storage/stones/ed029bc5ca18196f3bd479cde9d8e2fa.png',
        created_at: '2026-09-30T00:30:00.000Z',
    },
    {
        id: '20845a043f5abd823694192363b27458',
        stoneId: 'monumental',
        name: 'Flamed + Brushed - Black',
        src: '/storage/stones/20845a043f5abd823694192363b27458.png',
        created_at: '2026-09-30T00:31:00.000Z',
    },
    {
        id: 'c94dee4e4049681982c90ce1295c3a09',
        stoneId: 'monumental',
        name: 'Flamed - Gray',
        src: '/storage/stones/c94dee4e4049681982c90ce1295c3a09.png',
        created_at: '2026-09-30T00:32:00.000Z',
    },
    {
        id: 'dbd53cae91072a0db6eaa32211c9638d',
        stoneId: 'monumental',
        name: 'Gold Vein Limetone',
        src: '/storage/stones/dbd53cae91072a0db6eaa32211c9638d.png',
        created_at: '2026-09-30T00:33:00.000Z',
    },
    {
        id: 'c8c3655638b1929a14fc5cb97319270f',
        stoneId: 'monumental',
        name: 'Gray',
        src: '/storage/stones/c8c3655638b1929a14fc5cb97319270f.png',
        created_at: '2026-09-30T00:34:00.000Z',
    },
    {
        id: '069a8259b7fdadc758f4fe79a2f1212c',
        stoneId: 'monumental',
        name: 'Limestone II',
        src: '/storage/stones/069a8259b7fdadc758f4fe79a2f1212c.png',
        created_at: '2026-09-30T00:35:00.000Z',
    },
    {
        id: '66a3a2879457342deb4feb85a1ff0bcf',
        stoneId: 'monumental',
        name: 'Misty Beige',
        src: '/storage/stones/66a3a2879457342deb4feb85a1ff0bcf.png',
        created_at: '2026-09-30T00:36:00.000Z',
    },
    {
        id: '4b683a754fea6cc2e9c52669a4c2ca34',
        stoneId: 'onyx',
        name: 'Arancia',
        src: '/storage/stones/4b683a754fea6cc2e9c52669a4c2ca34.png',
        created_at: '2026-09-30T00:37:00.000Z',
    },
    {
        id: 'eefc96706013c0c8bf929d7143e1f56a',
        stoneId: 'onyx',
        name: 'Baltico Blue III',
        src: '/storage/stones/eefc96706013c0c8bf929d7143e1f56a.png',
        created_at: '2026-09-30T00:38:00.000Z',
    },
    {
        id: 'af3d57fe518624824aa1ee1e9ffadc75',
        stoneId: 'onyx',
        name: 'Classical Green',
        src: '/storage/stones/af3d57fe518624824aa1ee1e9ffadc75.png',
        created_at: '2026-09-30T00:39:00.000Z',
    },
    {
        id: '4ba1a205f3e99bf2249f4d8e3e5f1a1e',
        stoneId: 'onyx',
        name: 'Golden Pearl Onyx',
        src: '/storage/stones/4ba1a205f3e99bf2249f4d8e3e5f1a1e.png',
        created_at: '2026-09-30T00:40:00.000Z',
    },
    {
        id: '14b016f12102bde64dda5038253dc291',
        stoneId: 'onyx',
        name: 'Ikarus White',
        src: '/storage/stones/14b016f12102bde64dda5038253dc291.png',
        created_at: '2026-09-30T00:41:00.000Z',
    },
    {
        id: '1fa0f95ce528c8a7b130448fb68db6be',
        stoneId: 'onyx',
        name: 'Pardo Brown I',
        src: '/storage/stones/1fa0f95ce528c8a7b130448fb68db6be.png',
        created_at: '2026-09-30T00:42:00.000Z',
    },
    {
        id: 'fbb57689f0a43650fb830f056ed1dfe4',
        stoneId: 'onyx',
        name: 'Pardo Brown II',
        src: '/storage/stones/fbb57689f0a43650fb830f056ed1dfe4.png',
        created_at: '2026-09-30T00:43:00.000Z',
    },
    {
        id: 'a169f9acbb4a8e8ef8b3f309aa3224a7',
        stoneId: 'onyx',
        name: 'Prisma II',
        src: '/storage/stones/a169f9acbb4a8e8ef8b3f309aa3224a7.png',
        created_at: '2026-09-30T00:44:00.000Z',
    },
    {
        id: 'e40a35afc985c2d6a5019a6410dfd444',
        stoneId: 'onyx',
        name: 'Royal White III',
        src: '/storage/stones/e40a35afc985c2d6a5019a6410dfd444.png',
        created_at: '2026-09-30T00:45:00.000Z',
    },
    {
        id: '6465a09fc05bbe65a6bf47d29230f211',
        stoneId: 'onyx',
        name: 'Sienna White',
        src: '/storage/stones/6465a09fc05bbe65a6bf47d29230f211.png',
        created_at: '2026-09-30T00:46:00.000Z',
    },
    {
        id: '32429db4b70ad914e80ab79f031e4263',
        stoneId: 'patagonia-quartzite',
        name: 'Arctic Pink',
        src: '/storage/stones/32429db4b70ad914e80ab79f031e4263.png',
        created_at: '2026-09-30T00:47:00.000Z',
    },
    {
        id: '0c9c1c489733b01dba5b0bfeb3b1635f',
        stoneId: 'patagonia-quartzite',
        name: 'Bellagio Amber',
        src: '/storage/stones/0c9c1c489733b01dba5b0bfeb3b1635f.png',
        created_at: '2026-09-30T00:48:00.000Z',
    },
    {
        id: '71a8f35afcf3eed0c65fdf877482a027',
        stoneId: 'patagonia-quartzite',
        name: 'Follaje Rosa II',
        src: '/storage/stones/71a8f35afcf3eed0c65fdf877482a027.png',
        created_at: '2026-09-30T00:49:00.000Z',
    },
    {
        id: 'f0f1c7dcaf60b497dfb781d237c3e38e',
        stoneId: 'patagonia-quartzite',
        name: 'Glacier White',
        src: '/storage/stones/f0f1c7dcaf60b497dfb781d237c3e38e.png',
        created_at: '2026-09-30T00:50:00.000Z',
    },
    {
        id: '8c43d1c8474157f6cc3891b4f2f28129',
        stoneId: 'patagonia-quartzite',
        name: 'Imperial Gold',
        src: '/storage/stones/8c43d1c8474157f6cc3891b4f2f28129.png',
        created_at: '2026-09-30T00:51:00.000Z',
    },
    {
        id: '7970e07ed9916bc7b1f49508a8f5a1ba',
        stoneId: 'patagonia-quartzite',
        name: 'La Luz',
        src: '/storage/stones/7970e07ed9916bc7b1f49508a8f5a1ba.png',
        created_at: '2026-09-30T00:52:00.000Z',
    },
    {
        id: '566c04da147d7a6581559b9aa6da1f95',
        stoneId: 'patagonia-quartzite',
        name: 'Ora Luz',
        src: '/storage/stones/566c04da147d7a6581559b9aa6da1f95.png',
        created_at: '2026-09-30T00:53:00.000Z',
    },
    {
        id: '083119cb43c5a7baef0d176abf185e0d',
        stoneId: 'patagonia-quartzite',
        name: 'Palazza Bianco',
        src: '/storage/stones/083119cb43c5a7baef0d176abf185e0d.png',
        created_at: '2026-09-30T00:54:00.000Z',
    },
    {
        id: 'e0c134c45b4fd0ccd3141acff968878d',
        stoneId: 'patagonia-quartzite',
        name: 'Piazza Royale',
        src: '/storage/stones/e0c134c45b4fd0ccd3141acff968878d.png',
        created_at: '2026-09-30T00:55:00.000Z',
    },
    {
        id: '48fe4b787c9fe11bbe05a3b8e2409161',
        stoneId: 'patagonia-quartzite',
        name: 'Salmon Pink',
        src: '/storage/stones/48fe4b787c9fe11bbe05a3b8e2409161.png',
        created_at: '2026-09-30T00:56:00.000Z',
    },
    {
        id: '35cd4e7836a5d4c8df5163d3c42e36f3',
        stoneId: 'patagonia-quartzite',
        name: 'Veymont Green',
        src: '/storage/stones/35cd4e7836a5d4c8df5163d3c42e36f3.png',
        created_at: '2026-09-30T00:57:00.000Z',
    },
    {
        id: 'b015c44067b0478b73703b2184ca30e3',
        stoneId: 'patagonia-quartzite',
        name: 'Volere II',
        src: '/storage/stones/b015c44067b0478b73703b2184ca30e3.png',
        created_at: '2026-09-30T00:58:00.000Z',
    },
    {
        id: '5b7cb3dae550009c37ad0cb518f4d09f',
        stoneId: 'quartzite',
        name: 'Avocado Green III',
        src: '/storage/stones/5b7cb3dae550009c37ad0cb518f4d09f.png',
        created_at: '2026-09-30T00:59:00.000Z',
    },
    {
        id: 'a8c1729fa512d993142a14cb76ecbba8',
        stoneId: 'quartzite',
        name: 'Bulgari White',
        src: '/storage/stones/a8c1729fa512d993142a14cb76ecbba8.png',
        created_at: '2026-09-30T01:00:00.000Z',
    },
    {
        id: '24a81dac84104f3cb046a83c6972c280',
        stoneId: 'quartzite',
        name: 'Colorful Impression',
        src: '/storage/stones/24a81dac84104f3cb046a83c6972c280.png',
        created_at: '2026-09-30T01:01:00.000Z',
    },
    {
        id: '6ca3acbc134a9001fb9d110f576f08e9',
        stoneId: 'quartzite',
        name: 'Imperial Green',
        src: '/storage/stones/6ca3acbc134a9001fb9d110f576f08e9.png',
        created_at: '2026-09-30T01:02:00.000Z',
    },
    {
        id: '279e3525fdcabefe00ca161b43c4a616',
        stoneId: 'quartzite',
        name: 'Lumia',
        src: '/storage/stones/279e3525fdcabefe00ca161b43c4a616.png',
        created_at: '2026-09-30T01:03:00.000Z',
    },
    {
        id: '86edf5bf49016facc5f530e465a16c9c',
        stoneId: 'quartzite',
        name: 'Macabus White',
        src: '/storage/stones/86edf5bf49016facc5f530e465a16c9c.png',
        created_at: '2026-09-30T01:04:00.000Z',
    },
    {
        id: '212e74cd1b551f1c8c6106890c05aae7',
        stoneId: 'quartzite',
        name: 'Mont Blanc',
        src: '/storage/stones/212e74cd1b551f1c8c6106890c05aae7.png',
        created_at: '2026-09-30T01:05:00.000Z',
    },
    {
        id: '1fb226ee943af210b9d88bc0a5a013cd',
        stoneId: 'quartzite',
        name: 'Ocean Blue (Leather)',
        src: '/storage/stones/1fb226ee943af210b9d88bc0a5a013cd.png',
        created_at: '2026-09-30T01:06:00.000Z',
    },
    {
        id: 'c557e2d01f757f5e9711cadc860f472c',
        stoneId: 'quartzite',
        name: 'Orion',
        src: '/storage/stones/c557e2d01f757f5e9711cadc860f472c.png',
        created_at: '2026-09-30T01:07:00.000Z',
    },
    {
        id: '2c97834e9a7581354206c6fd3261a7ea',
        stoneId: 'quartzite',
        name: 'Picasso',
        src: '/storage/stones/2c97834e9a7581354206c6fd3261a7ea.png',
        created_at: '2026-09-30T01:08:00.000Z',
    },
    {
        id: '989e490404798231e7cad1ccca3869e7',
        stoneId: 'quartzite',
        name: 'Selene',
        src: '/storage/stones/989e490404798231e7cad1ccca3869e7.png',
        created_at: '2026-09-30T01:09:00.000Z',
    },
    {
        id: '51500690352a53c1478bb7c601d0ffa6',
        stoneId: 'quartzite',
        name: 'Taj Mahal (Polished)',
        src: '/storage/stones/51500690352a53c1478bb7c601d0ffa6.png',
        created_at: '2026-09-30T01:10:00.000Z',
    },
    {
        id: 'ce31a253387dfbda62aa77552d66dea5',
        stoneId: 'quartzite',
        name: 'Taj Mahal (leather)',
        src: '/storage/stones/51500690352a53c1478bb7c601d0ffa6.png',
        created_at: '2026-09-30T01:11:00.000Z',
    },
    {
        id: 'bac6a5d8d41046a9fe8cb35205cbd34c',
        stoneId: 'travertine',
        name: 'Beige',
        src: '/storage/stones/bac6a5d8d41046a9fe8cb35205cbd34c.png',
        created_at: '2026-09-30T01:12:00.000Z',
    },
    {
        id: '9609b19a9fdfe36cd384b8530f21a9b3',
        stoneId: 'travertine',
        name: 'Silver',
        src: '/storage/stones/9609b19a9fdfe36cd384b8530f21a9b3.png',
        created_at: '2026-09-30T01:13:00.000Z',
    },
    {
        id: 'f6aae9c7bf39e9d0550e224e90441c97',
        stoneId: 'travertine',
        name: 'White',
        src: '/storage/stones/f6aae9c7bf39e9d0550e224e90441c97.png',
        created_at: '2026-09-30T01:14:00.000Z',
    },
];

/** One stone's photographs, most recently catalogued first. */
const getStoneGallery = (stoneId: string): StoneImage[] =>
    stoneImages
        .filter((image) => image.stoneId === stoneId)
        .sort((a, b) => b.created_at.localeCompare(a.created_at));

export const stoneProfiles: Record<string, StoneProfile> = {
    cobblestone: {
        id: 'cobblestone',
        name: 'Cobblestone',
        thumbnail: '/storage/stones/ef03fcfd4ff0e6063df004f6b897fe7a.png',
        recommended: false,
        description:
            'Granite cut into cubes and setts, the oldest paving material still in daily use. A flamed or brushed face grips underfoot in the wet, and because the stone is hard-wearing rather than soft, a laid courtyard keeps its pattern for decades.',
        gallery: getStoneGallery('cobblestone'),
        specifications: {
            stoneType: 'Cobblestone',
            origin: 'Brazil, India',
            thickness: '50-100 mm per cube',
        },
        applications: [
            'Driveways and garden paths',
            'Courtyards',
            'Street and plaza paving',
            'Landscape edging',
            'Patio borders',
            'Outdoor dining areas',
        ],
        finishes: ['Flamed', 'Flamed + Brushed', 'Natural'],
        care: [
            'Re-bed the joints as the base settles',
            'Clean with pH-neutral cleaner',
            'Sweep grit out of the joints regularly',
            'Re-joint gaps before weeds take hold',
            'Avoid pressure washing into the joints',
            'Always use trivets for hot pans on counters',
        ],
    },
    'crazy-cut': {
        id: 'crazy-cut',
        name: 'Crazy Cut',
        thumbnail: '/storage/stones/1715fd73a1ddbc8f8ae32158bd35ae4a.png',
        recommended: true,
        description:
            'Irregular flagstone pieces split and shaped to follow the ground instead of a saw line. No two pieces repeat, so a laid surface has a looser, more natural character than a rectangular tile ever will. Cut from granite, marble and limestone.',
        gallery: getStoneGallery('crazy-cut'),
        specifications: {
            stoneType: 'Crazy Cut',
            origin: 'Brazil, Italy, Turkey',
            thickness: '20-30 mm per piece',
        },
        applications: [
            'Paving and pathways',
            'Feature walls',
            'Outdoor patios',
            'Pool surrounds',
            'Entrance lobbies',
            'Landscape edging',
        ],
        finishes: ['Flamed', 'Brushed', 'Honed', 'Natural Split'],
        care: [
            'Seal periodically',
            'Re-bed loose pieces as the soil settles',
            'Clean with pH-neutral cleaner',
            'Wipe spills immediately',
            'Avoid acidic cleaners on marble and limestone',
            'Use soft microfiber cloths',
        ],
    },
    granite: {
        id: 'granite',
        name: 'Granite',
        thumbnail: '/storage/stones/8f7804fd9673f34737ecd0c1dfca9723.png',
        recommended: true,
        description:
            'The workhorse of natural stone, granite is a dense igneous rock prized for its exceptional hardness, heat resistance, and stone-deep durability. A broad palette of speckles, veins, and tones makes it a safe, lasting choice for busy kitchens.',
        gallery: getStoneGallery('granite'),
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
    },
    marble: {
        id: 'marble',
        name: 'Marble',
        thumbnail: '/storage/stones/a2a72c93b7369e681b72f0e05380df17.png',
        recommended: false,
        description:
            'A classic metamorphic stone celebrated for its luminous surface and elegant, dramatic veining. Each slab is unique, bringing quiet luxury to vanities, counters, and walls.',
        gallery: getStoneGallery('marble'),
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
    },
    monumental: {
        id: 'monumental',
        name: 'Monumental',
        thumbnail: '/storage/stones/20845a043f5abd823694192363b27458.png',
        recommended: false,
        description:
            'The densest and hardest natural stone available, cut for a glass-like, mirror-polished finish. Its extreme durability and scratch resistance make it ideal for high-traffic surfaces that must stay beautiful.',
        gallery: getStoneGallery('monumental'),
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
    },
    onyx: {
        id: 'onyx',
        name: 'Onyx',
        thumbnail: '/storage/stones/4b683a754fea6cc2e9c52669a4c2ca34.png',
        recommended: true,
        description:
            'A translucent, banded stone that literally glows when lit from behind. Prized for statement backlit features, onyx turns any surface into a piece of art.',
        gallery: getStoneGallery('onyx'),
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
    },
    'patagonia-quartzite': {
        id: 'patagonia-quartzite',
        name: 'Patagonia Quartzite',
        thumbnail: '/storage/stones/32429db4b70ad914e80ab79f031e4263.png',
        recommended: true,
        description:
            'A show-stopping quartzite defined by sweeping cream, grey, and copper veining on a luminous white base. It pairs the beauty of marble with granite-grade toughness, performing beautifully in kitchens and on clad features.',
        gallery: getStoneGallery('patagonia-quartzite'),
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
    },
    quartzite: {
        id: 'quartzite',
        name: 'Quartzite',
        thumbnail: '/storage/stones/5b7cb3dae550009c37ad0cb518f4d09f.png',
        recommended: false,
        description:
            'A naturally metamorphosed, quartz-rich stone that pairs marble-like movement with granite-grade strength. Its low porosity and heat resistance make it a favorite for high-traffic kitchen counters.',
        gallery: getStoneGallery('quartzite'),
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
    },
    travertine: {
        id: 'travertine',
        name: 'Travertine',
        thumbnail: '/storage/stones/bac6a5d8d41046a9fe8cb35205cbd34c.png',
        recommended: false,
        description:
            'A warm, porous sedimentary stone with soft, earthy tones and a naturally pitted texture. Its time-honored character brings old-world warmth to bathrooms, patios, and feature walls.',
        gallery: getStoneGallery('travertine'),
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
    },
};
/*
 * The requirements step's fixed option lists. They are plain strings rather than
 * `{ value, label }` pairs because the value is also the wording the client
 * reads: splitting the two would let the stored answer drift from the answer
 * that was offered.
 */
/**
 * What the edge and finish lists end with.
 *
 * Appended rather than offered as one of the real options because "I do not know
 * yet" is a real answer here, and a client who cannot pick from the list still
 * has to be able to hand the decision back.
 */
const ADVISE_OPTION = 'Not sure yet, advise me';

export const EDGE_PROFILES: readonly string[] = [
    'Reverse Chamfer',
    'Full Bullnose',
    'Double Bullnose',
    'Half Bullnose',
    'Straight Edge',
    'Half Bevel',
    'Full Bevel',
    ADVISE_OPTION,
];

/**
 * The finishes the works can apply.
 *
 * Written down rather than derived from the chosen stones, because these are ones
 * any of the stones can take: there is nothing a stone's own profile would add,
 * and a list that changed with step 1 would only make the answer harder to give.
 */
export const FINISHES: readonly string[] = [
    'Leather',
    'Honed',
    'Polished',
    ADVISE_OPTION,
];

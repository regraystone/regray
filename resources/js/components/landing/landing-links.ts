import { show as brandsShow } from '@/routes/brands';

/** The brand whose stones and furniture the landing page's catalog links open. */
export const HOME_BRAND = 'regray-stone';

/** Regray Stone's public page, showing only its stones or its furniture. */
export const collectionUrl = (collection: 'stones' | 'furniture') =>
    brandsShow.url({ brand: HOME_BRAND }, { query: { collection } });

/** The showroom on Google Maps. */
export const SHOWROOM_MAP =
    'https://www.google.com/maps/place/Regray+Stone+%26+Furniture/@14.5314156,121.0230854,17z';

/** The showroom's address and hours, as regraystone.com gives them. */
export const SHOWROOM = {
    address:
        'Calcan Building, 1195 Chino Roces Avenue, corner Yakal Street, San Antonio, Makati City',
    hours: 'Open Monday to Sunday, 10AM to 6PM',
};

/** Where to find Regray, as regraystone.com links them. */
export const SOCIALS = [
    { label: 'Instagram', href: 'https://www.instagram.com/regraystoneph/' },
    { label: 'Facebook', href: 'https://www.facebook.com/Regraystone' },
    { label: 'Pinterest', href: 'https://ph.pinterest.com/regraystone/' },
    { label: 'YouTube', href: 'https://www.youtube.com/@regraystone' },
    { label: 'TikTok', href: 'https://www.tiktok.com/@regraystone' },
    { label: 'LinkedIn', href: 'https://ph.linkedin.com/company/regraystone' },
] as const;

/** The landing page's own sections, in the order the header lists them. */
export const LANDING_NAV = [
    { label: 'Stones', href: collectionUrl('stones') },
    { label: 'Furniture', href: collectionUrl('furniture') },
    { label: 'Brands', href: '#brands' },
    { label: 'Projects', href: '#projects' },
    { label: 'Catalogs', href: '#catalogs' },
    { label: 'Showroom', href: '#showroom' },
];

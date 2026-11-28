import { about, brands, contact, news, showroom } from '@/routes/company';
import { show as brandsShow } from '@/routes/brands';

/**
 * Everything the public pages say about where and how to reach Regray, in one
 * place: the facts are regraystone.com's (its About and Contact pages).
 */

/** The brand whose stones and furniture the catalog links open. */
export const HOME_BRAND = 'regray-stone';

/** Regray Stone's public page, showing only its stones or its furniture. */
export const collectionUrl = (collection: 'stones' | 'furniture') =>
    brandsShow.url({ brand: HOME_BRAND }, { query: { collection } });

/** A brand's public page. */
export const brandUrl = (slug: string) => brandsShow.url({ brand: slug });

/**
 * The showroom's Google Maps place, as Google lists it: "Regray Stone &
 * Furniture, Yakal, Corner Yakal, Makati City, Metro Manila, Philippines".
 * Directions open Google Maps with it as the destination; the embed shows it.
 */
const SHOWROOM_PLACE = encodeURIComponent(
    'Regray Stone & Furniture, Yakal, Corner Yakal, Makati City, Metro Manila, Philippines',
);
export const SHOWROOM_MAP = `https://www.google.com/maps/dir/?api=1&destination=${SHOWROOM_PLACE}`;
export const SHOWROOM_MAP_EMBED = `https://www.google.com/maps?q=${SHOWROOM_PLACE}&z=17&output=embed`;

/** The showroom's address and hours. */
export const SHOWROOM = {
    address:
        'Calcan Building, 1195 Chino Roces Avenue, corner Yakal Street, San Antonio, Makati City',
    hours: 'Open Monday to Sunday, 10AM to 6PM',
};

/** How to reach the team. */
export const CONTACT = {
    email: 'info@regraystone.com',
    phones: [
        { label: '0945 436 1056', href: 'tel:+639454361056' },
        { label: '0917 543 1811', href: 'tel:+639175431811' },
        { label: '(02) 8561 4157', href: 'tel:+63285614157' },
    ],
};

/** An email to the team, with its subject filled in. */
export const mailTo = (subject: string) =>
    `mailto:${CONTACT.email}?subject=${encodeURIComponent(subject)}`;

/** Where to find Regray online. */
export const SOCIALS = [
    {
        label: 'Instagram',
        href: 'https://www.instagram.com/regraystoneph/',
        icon: 'instagram',
    },
    {
        label: 'Facebook',
        href: 'https://www.facebook.com/Regraystone',
        icon: 'facebook',
    },
    {
        label: 'Pinterest',
        href: 'https://ph.pinterest.com/regraystone/',
        icon: 'pinterest',
    },
    {
        label: 'YouTube',
        href: 'https://www.youtube.com/@regraystone',
        icon: 'youtube',
    },
    {
        label: 'TikTok',
        href: 'https://www.tiktok.com/@regraystone',
        icon: 'tiktok',
    },
    {
        label: 'LinkedIn',
        href: 'https://ph.linkedin.com/company/regraystone',
        icon: 'linkedin',
    },
] as const;

/**
 * Each brand logo's height in px at full size, by slug, so the names read at
 * about one size: the square Lube badge is tallest, the long Faer wordmark
 * lowest. The landing page's brand wall and a brand's page scale it.
 */
export const BRAND_LOGO_HEIGHT: Record<string, number> = {
    'regray-stone': 26,
    cucine: 84,
    reform: 52,
    ascale: 30,
    faer: 18,
    'res-italia': 56,
};
export const DEFAULT_BRAND_LOGO_HEIGHT = 40;

/** A menu link; `children` are shown indented under it, as the pages within it. */
export type MenuLink = {
    label: string;
    href: string;
    external?: boolean;
    children?: MenuLink[];
    /** Kept for later, but not shown in the menu. */
    hidden?: boolean;
    /** Drawn with the menu's chevron after its label; other links have no icon. */
    chevron?: boolean;
};

/**
 * A panel's links as the rows the menu draws, each child after its parent,
 * one level in. A hidden link is left out, with its children.
 */
export const menuRows = (
    links: MenuLink[],
): Array<MenuLink & { depth: 0 | 1 }> =>
    links
        .filter((link) => !link.hidden)
        .flatMap((link) => [
            { ...link, depth: 0 as const },
            ...(link.children ?? [])
                .filter((child) => !child.hidden)
                .map((child) => ({ ...child, depth: 1 as const })),
        ]);

/** A tab that opens a panel: what it holds, its main link, its links and a photo. */
export type MenuPanel = {
    kind: 'panel';
    label: string;
    /** A line about the section; Discover has none, as Sir Mark asked. */
    description?: string;
    /** The panel's main link, under its line; left out when its list already starts with it. */
    cta?: MenuLink;
    links: MenuLink[];
    image: string;
};

/** A tab that is a plain link to its page. */
export type MenuPage = { kind: 'page'; label: string; href: string };

export type MenuItem = MenuPanel | MenuPage;

/**
 * The header's menu, in the agreed order. Our brands, News and Contact are
 * plain links to their one page each; Discover opens a panel. The
 * showroom is a link under Discover, not a tab of its own: its one page
 * holds the photos, the location and booking a visit. (A tab may
 * instead be a plain link to its page, `kind: 'page'`.) Pages a guest cannot
 * open yet (the Inquire page) go through Login first.
 */
export const menuItems = (): MenuItem[] => [
    // One page introduces every brand, so the tab goes straight to it.
    { kind: 'page', label: 'Our brands', href: brands.url() },
    {
        kind: 'panel',
        label: 'Discover',
        links: [
            // Only About, Showroom and Careers are shown, as Sir Mark asked;
            // the About page's other sections stay here, hidden, for later.
            {
                label: 'About Us',
                href: about.url(),
                children: [
                    {
                        label: 'Our story',
                        href: `${about.url()}#story`,
                        hidden: true,
                    },
                    {
                        label: 'Mission & vision',
                        href: `${about.url()}#vision`,
                        hidden: true,
                    },
                    {
                        label: 'The Regray Group',
                        href: `${about.url()}#group`,
                        hidden: true,
                    },
                ],
            },
            // One page holds the showroom: its photos, where it is and booking a visit.
            { label: 'Showroom', href: showroom.url(), chevron: true },
            {
                label: 'Partnerships',
                href: `${about.url()}#partnerships`,
                hidden: true,
            },
            { label: 'Careers', href: `${about.url()}#careers` },
        ],
        image: '/images/regray-slabs.jpg',
    },
    // One page holds the news, so the tab goes straight to it.
    { kind: 'page', label: 'News', href: news.url() },
    // One page holds every way to reach the team, so the tab goes straight to it.
    { kind: 'page', label: 'Contact', href: contact.url() },
];

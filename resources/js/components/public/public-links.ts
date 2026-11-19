import { about, brands, contact, news, showroom } from '@/routes/company';
import { show as brandsShow } from '@/routes/brands';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';

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

/** Regray Stone's journal, on regraystone.com. */
export const JOURNAL = 'https://regraystone.com/journal/';

/** Where to find Regray online. */
export const SOCIALS = [
    { label: 'Instagram', href: 'https://www.instagram.com/regraystoneph/' },
    { label: 'Facebook', href: 'https://www.facebook.com/Regraystone' },
    { label: 'Pinterest', href: 'https://ph.pinterest.com/regraystone/' },
    { label: 'YouTube', href: 'https://www.youtube.com/@regraystone' },
    { label: 'TikTok', href: 'https://www.tiktok.com/@regraystone' },
    { label: 'LinkedIn', href: 'https://ph.linkedin.com/company/regraystone' },
] as const;

/**
 * The brands by what they make, by slug, as Our brands groups them. A brand
 * may sit in more than one group; one in none is listed under "More houses".
 */
export const BRAND_CATEGORIES = [
    {
        id: 'stone-surfaces',
        label: 'Stone & Surfaces',
        description:
            'Natural stone, sintered stone and engineered surfaces for countertops, walls, floors and façades.',
        slugs: ['regray-stone', 'ascale', 'reform'],
    },
    {
        id: 'kitchens',
        label: 'Kitchens',
        description:
            'Italian kitchens with customisable layouts, premium materials and contemporary finishes.',
        slugs: ['cucine'],
    },
    {
        id: 'wardrobes-doors',
        label: 'Wardrobes & Doors',
        description:
            'Wardrobes, bedrooms, interior doors and door systems, made in Italy.',
        slugs: ['faer', 'res-italia'],
    },
    {
        id: 'furniture-interiors',
        label: 'Furniture & Interiors',
        description:
            'Bespoke stone tables and pedestals, and furniture for the rest of the home.',
        slugs: ['regray-stone', 'faer'],
    },
] as const;

export type MenuLink = { label: string; href: string; external?: boolean };

/** A tab that opens a panel: what it holds, its main link, its links and a photo. */
export type MenuPanel = {
    kind: 'panel';
    label: string;
    description: string;
    cta: MenuLink;
    links: MenuLink[];
    image: string;
};

/** A tab that is a plain link to its page. */
export type MenuPage = { kind: 'page'; label: string; href: string };

export type MenuItem = MenuPanel | MenuPage;

/**
 * The header's menu, in the agreed order: every tab opens a panel. (A tab may
 * instead be a plain link to its page, `kind: 'page'`.) Pages a guest cannot
 * open yet (booking a visit, the Inquire page) go through Login first.
 */
export const menuItems = (brandPhoto: string | null): MenuItem[] => [
    {
        kind: 'panel',
        label: 'Our brands',
        description:
            'Six houses in stone, kitchens, wardrobes, doors and interiors, each with its own collection and official website.',
        cta: { label: 'All brands', href: brands.url() },
        links: BRAND_CATEGORIES.map((category) => ({
            label: category.label,
            href: `${brands.url()}#${category.id}`,
        })),
        image: brandPhoto ?? '/images/regray-slabs.jpg',
    },
    {
        kind: 'panel',
        label: 'Showroom',
        description:
            'Discover and experience Regray’s brands in person, at our showroom in Makati.',
        cta: { label: 'Visit the showroom', href: showroom.url() },
        links: [
            { label: 'Showroom overview', href: showroom.url() },
            { label: 'Gallery', href: `${showroom.url()}#gallery` },
            {
                label: 'Location & directions',
                href: `${showroom.url()}#location`,
            },
            { label: 'Book a visit', href: book.url() },
        ],
        image: '/images/regray-showroom.jpg',
    },
    {
        kind: 'panel',
        label: 'Discover',
        description:
            'Learn about Regray: its story, its vision and how to work with us.',
        cta: { label: 'About Regray', href: about.url() },
        links: [
            { label: 'Our story', href: `${about.url()}#story` },
            { label: 'The Regray Group', href: `${about.url()}#group` },
            { label: 'Mission & vision', href: `${about.url()}#vision` },
            { label: 'Partnerships', href: `${about.url()}#partnerships` },
            { label: 'Careers', href: `${about.url()}#careers` },
        ],
        image: '/images/regray-slabs.jpg',
    },
    {
        kind: 'panel',
        label: 'News',
        description:
            'The latest from Regray and its brands: finished projects, new stones and design stories.',
        cta: { label: 'All news', href: news.url() },
        links: [
            { label: 'Latest projects', href: news.url() },
            { label: 'Regray Journal', href: JOURNAL, external: true },
            { label: 'Instagram', href: SOCIALS[0].href, external: true },
            { label: 'Facebook', href: SOCIALS[1].href, external: true },
        ],
        image: '/images/regray-hero.jpg',
    },
    {
        kind: 'panel',
        label: 'Contact',
        description: 'Connect with the Regray team.',
        cta: { label: 'Contact us', href: contact.url() },
        links: [
            { label: 'General inquiries', href: `${contact.url()}#general` },
            { label: 'Sales & quotations', href: inquire.url() },
            {
                label: 'Architect & designer partnerships',
                href: `${contact.url()}#trade`,
            },
            {
                label: 'Supplier & business inquiries',
                href: `${contact.url()}#business`,
            },
            { label: 'Contact information', href: `${contact.url()}#info` },
        ],
        image: '/images/regray-showroom.jpg',
    },
];

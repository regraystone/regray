import { Link, usePage } from '@inertiajs/react';
import {
    CONTACT,
    JOURNAL,
    SHOWROOM,
    SOCIALS,
    brandUrl,
    collectionUrl,
} from '@/components/public/public-links';
import { login, register } from '@/routes';
import { about, brands, contact, news, showroom } from '@/routes/company';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import type { BrandCardData } from '@/types/home';

type FooterLink = {
    label: string;
    href: string;
    kind?: 'page' | 'anchor' | 'external';
};

const linkClass = 'text-sm text-white transition-colors hover:text-white/70';

const FooterAnchor = ({ link }: { link: FooterLink }) => {
    if (link.kind === 'external') {
        return (
            <a
                href={link.href}
                target="_blank"
                rel="noopener noreferrer"
                className={linkClass}
            >
                {link.label}
            </a>
        );
    }

    if (link.kind === 'anchor') {
        return (
            <a href={link.href} className={linkClass}>
                {link.label}
            </a>
        );
    }

    return (
        <Link href={link.href} className={linkClass}>
            {link.label}
        </Link>
    );
};

/**
 * The public pages' black footer: the collections, every brand, the company
 * pages, what a client can do on the platform, and where to find Regray
 * online and in person. Pages a guest cannot open yet (the Inquire page,
 * booking a visit, Messages) go through Login first.
 */
export default function PublicFooter() {
    const siteBrands =
        usePage<{ siteBrands?: BrandCardData[] | null }>().props.siteBrands ??
        [];

    const columns: { title: string; links: FooterLink[] }[] = [
        {
            title: 'Collections',
            links: [
                { label: 'Natural stone', href: collectionUrl('stones') },
                { label: 'Furniture', href: collectionUrl('furniture') },
                {
                    label: 'Featured projects',
                    href: '/#projects',
                    kind: 'anchor',
                },
                { label: 'Catalogs', href: '/#catalogs', kind: 'anchor' },
            ],
        },
        {
            title: 'Brands',
            links: [
                { label: 'All brands', href: brands.url() },
                ...siteBrands.map((brand) => ({
                    label: brand.name,
                    href: brandUrl(brand.slug),
                })),
            ],
        },
        {
            title: 'Regray',
            links: [
                { label: 'About Regray', href: about.url() },
                { label: 'News', href: news.url() },
                { label: 'Showroom', href: showroom.url() },
                { label: 'Contact', href: contact.url() },
                { label: 'Journal', href: JOURNAL, kind: 'external' },
            ],
        },
        {
            title: 'Platform',
            links: [
                { label: 'Send an inquiry', href: inquire.url() },
                { label: 'Book a site visit', href: book.url() },
                { label: 'Message us', href: inboxIndex.url() },
                { label: 'Create an account', href: register.url() },
            ],
        },
        {
            title: 'Find us on',
            links: SOCIALS.map((social) => ({
                ...social,
                kind: 'external' as const,
            })),
        },
    ];

    return (
        <footer className="flex flex-col gap-[clamp(4rem,10vw,8.75rem)] bg-black px-4 pt-10 pb-8 text-white sm:px-8">
            <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_minmax(0,1.5fr)]">
                {columns.map((column) => (
                    <nav
                        key={column.title}
                        aria-label={column.title}
                        className="flex flex-col gap-1.5"
                    >
                        <h2 className="mb-1 font-sans text-sm font-bold">
                            {column.title}
                        </h2>
                        {column.links.map((link) => (
                            <FooterAnchor key={link.label} link={link} />
                        ))}
                    </nav>
                ))}

                <div className="flex flex-col gap-1.5">
                    <h2 className="mb-1 font-sans text-sm font-bold">
                        The Regray showroom
                    </h2>
                    <p className="max-w-80 text-sm leading-relaxed text-white/80">
                        {SHOWROOM.address}
                    </p>
                    <p className="text-sm text-white/80">{SHOWROOM.hours}</p>
                    <a
                        href={`mailto:${CONTACT.email}`}
                        className={`mt-2 ${linkClass}`}
                    >
                        {CONTACT.email}
                    </a>
                    {CONTACT.phones.map((phone) => (
                        <a
                            key={phone.href}
                            href={phone.href}
                            className={linkClass}
                        >
                            {phone.label}
                        </a>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-x-6 gap-y-3 text-[0.8125rem] font-semibold uppercase sm:grid-cols-2 lg:grid-cols-4">
                <span className="font-normal normal-case">
                    © {new Date().getFullYear()} Regray
                </span>
                <Link href={showroom.url()} className={linkClass}>
                    Showroom
                </Link>
                <Link href={contact.url()} className={linkClass}>
                    Contact
                </Link>
                <Link href={login.url()} className={linkClass}>
                    Client login
                </Link>
            </div>
        </footer>
    );
}

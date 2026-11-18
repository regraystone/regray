import { Link } from '@inertiajs/react';
import {
    SHOWROOM,
    SOCIALS,
    collectionUrl,
} from '@/components/landing/landing-links';
import { login, register } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import type { LandingBrand } from '@/types/home';

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
 * The landing page's black footer: the collections, every brand, what a
 * client can do on the platform, and where to find Regray online and in
 * person. Pages a guest cannot open yet (the Inquire page, booking a visit,
 * Messages) go through Login first.
 */
export default function LandingFooter({ brands }: { brands: LandingBrand[] }) {
    const columns: { title: string; links: FooterLink[] }[] = [
        {
            title: 'Collections',
            links: [
                { label: 'Natural stone', href: collectionUrl('stones') },
                { label: 'Furniture', href: collectionUrl('furniture') },
                {
                    label: 'Featured projects',
                    href: '#projects',
                    kind: 'anchor',
                },
                { label: 'Catalogs', href: '#catalogs', kind: 'anchor' },
            ],
        },
        {
            title: 'Brands',
            links: brands.map((brand) => ({
                label: brand.name,
                href: brandsShow.url({ brand: brand.slug }),
            })),
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
            <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,1.6fr)]">
                {columns.map((column) =>
                    column.links.length === 0 ? null : (
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
                    ),
                )}

                <div className="flex flex-col gap-1.5">
                    <h2 className="mb-1 font-sans text-sm font-bold">
                        The Regray showroom
                    </h2>
                    <p className="max-w-80 text-sm leading-relaxed text-white/80">
                        {SHOWROOM.address}
                    </p>
                    <p className="text-sm text-white/80">{SHOWROOM.hours}</p>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-x-6 gap-y-3 text-[0.8125rem] font-semibold uppercase sm:grid-cols-2 lg:grid-cols-4">
                <span className="font-normal normal-case">
                    © {new Date().getFullYear()} Regray
                </span>
                <a href="#showroom" className={linkClass}>
                    Showroom
                </a>
                <Link href={login.url()} className={linkClass}>
                    Client login
                </Link>
            </div>
        </footer>
    );
}

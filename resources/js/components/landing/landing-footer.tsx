import { Link } from '@inertiajs/react';
import { login, register } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import type { BrandCardData } from '@/types/home';

type FooterLink = { label: string; href: string; inPage?: boolean };

/**
 * The landing page's footer: the brands, the ways to start working with the
 * studio, and the account links. Every page a guest cannot open yet (the
 * Inquire page, booking a visit, Messages) goes through Login first.
 */
export default function LandingFooter({ brands }: { brands: BrandCardData[] }) {
    const columns: { title: string; links: FooterLink[] }[] = [
        {
            title: 'Brands',
            links: brands.map((brand) => ({
                label: brand.name,
                href: brandsShow.url({ brand: brand.slug }),
            })),
        },
        {
            title: 'Work with us',
            links: [
                { label: 'Send an inquiry', href: inquire.url() },
                { label: 'Book a site visit', href: book.url() },
                { label: 'Services', href: '#services', inPage: true },
                { label: 'Message us', href: inboxIndex.url() },
            ],
        },
        {
            title: 'Account',
            links: [
                { label: 'Login', href: login.url() },
                { label: 'Create an account', href: register.url() },
            ],
        },
    ];

    return (
        <footer className="bg-paper px-4 pt-16 pb-8 text-ink sm:px-5 lg:px-8 lg:pt-28">
            <div className="grid grid-cols-1 gap-12 border-b border-line pb-16 sm:grid-cols-2 lg:grid-cols-4">
                <div className="flex flex-col gap-4">
                    <span className="text-xl font-semibold tracking-[0.32em]">
                        REGRAY
                    </span>
                    <p className="max-w-64 text-[0.9375rem] leading-relaxed text-ink-soft">
                        Natural stone and bespoke furniture, from sourcing to
                        installation nationwide.
                    </p>
                </div>

                {columns.map((column) =>
                    column.links.length === 0 ? null : (
                        <nav
                            key={column.title}
                            aria-label={column.title}
                            className="flex flex-col gap-3.5"
                        >
                            <h2 className="mb-1.5 text-xs font-medium tracking-[0.2em] text-ink-muted uppercase">
                                {column.title}
                            </h2>
                            {column.links.map((link) =>
                                link.inPage ? (
                                    <a
                                        key={link.label}
                                        href={link.href}
                                        className="text-[0.9375rem] transition-colors hover:text-ink-muted"
                                    >
                                        {link.label}
                                    </a>
                                ) : (
                                    <Link
                                        key={link.label}
                                        href={link.href}
                                        className="text-[0.9375rem] transition-colors hover:text-ink-muted"
                                    >
                                        {link.label}
                                    </Link>
                                ),
                            )}
                        </nav>
                    ),
                )}
            </div>

            <p className="pt-7 text-[0.8125rem] text-ink-muted">
                © {new Date().getFullYear()} Regray. All rights reserved.
            </p>
        </footer>
    );
}

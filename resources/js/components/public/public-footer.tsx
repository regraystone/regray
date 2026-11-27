import { Link } from '@inertiajs/react';
import { CONTACT, SHOWROOM } from '@/components/public/public-links';
import Wordmark from '@/components/wordmark';
import { home, login } from '@/routes';
import { about, brands, contact, news, showroom } from '@/routes/company';

/** The group's own pages. Regray Stone's website and social accounts are not here: the footer speaks for the whole group of brands. */
const links: { label: string; href: string }[] = [
    { label: 'The group', href: about.url() },
    { label: 'Our brands', href: brands.url() },
    { label: 'Showroom', href: showroom.url() },
    { label: 'News', href: news.url() },
    { label: 'Contact', href: contact.url() },
    { label: 'Client login', href: login.url() },
];

/** The size and case of the header's menu, so the two read alike. */
const linkClass =
    'text-[0.9375rem] font-medium tracking-[0.04em] uppercase transition-opacity hover:opacity-70';

/**
 * The public pages' dark blue footer (the site's navy), kept short. On a phone it is one centred
 * column: the wordmark, the main pages and the copyright, then the showroom's
 * address, hours and how to reach the team. From a tablet up it spreads out:
 * the wordmark and the showroom on the left, the pages in two columns on the
 * right (on a desktop the showroom takes a third column), and the copyright
 * on a ruled line along the foot. It speaks for the whole group, so Regray
 * Stone's own website and social accounts are not in it (the Contact page
 * still lists the social accounts).
 */
export default function PublicFooter() {
    return (
        <footer className="border-t border-white/10 bg-navy px-5 pt-[clamp(4.5rem,9vw,7.5rem)] pb-28 text-white sm:px-8">
            <div className="flex flex-col items-center gap-[clamp(3rem,6vw,4.5rem)] text-center md:grid md:grid-cols-[minmax(0,1fr)_auto] md:items-start md:gap-x-16 md:gap-y-14 md:text-left lg:grid-cols-[minmax(0,1.2fr)_auto_minmax(0,1fr)] lg:gap-x-[clamp(4rem,7vw,8rem)]">
                <Link
                    href={home()}
                    aria-label="Regray Design Group home"
                    className="text-xl md:col-start-1 md:row-start-1 md:text-2xl"
                >
                    <Wordmark />
                </Link>

                <nav
                    aria-label="Footer"
                    className="md:col-start-2 md:row-span-2 md:row-start-1 lg:row-span-1"
                >
                    <ul className="flex flex-col items-center gap-5 md:grid md:grid-cols-2 md:items-start md:gap-x-14 md:gap-y-4">
                        {links.map((link) => (
                            <li key={link.label}>
                                <Link href={link.href} className={linkClass}>
                                    {link.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="flex flex-col items-center gap-5 md:col-span-full md:row-start-3 md:flex-row md:justify-between md:gap-8 md:border-t md:border-white/20 md:pt-8 lg:row-start-2">
                    <p className="text-sm tracking-[0.04em] text-white/75 uppercase">
                        © Regray Design Group {new Date().getFullYear()}
                    </p>
                </div>

                <address className="mt-[clamp(1rem,2vw,2rem)] flex flex-col gap-2 self-stretch text-left text-[0.9375rem] leading-relaxed text-white/85 not-italic md:col-start-1 md:row-start-2 md:mt-0 md:self-auto lg:col-start-3 lg:row-start-1">
                    <span className="text-[0.9375rem] font-semibold tracking-[0.04em] text-white uppercase">
                        Regray Design Group
                    </span>
                    <span>{SHOWROOM.address}</span>
                    <span>{SHOWROOM.hours}</span>
                    <span className="flex flex-wrap gap-x-2">
                        {CONTACT.phones.map((phone) => (
                            <a
                                key={phone.href}
                                href={phone.href}
                                className="text-white underline-offset-4 after:ml-2 after:text-white/50 after:content-['·'] hover:underline"
                            >
                                {phone.label}
                            </a>
                        ))}
                        <a
                            href={`mailto:${CONTACT.email}`}
                            className="text-white underline-offset-4 hover:underline"
                        >
                            {CONTACT.email}
                        </a>
                    </span>
                </address>
            </div>
        </footer>
    );
}

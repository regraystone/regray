import { Link } from '@inertiajs/react';
import {
    CONTACT,
    SHOWROOM,
    SOCIALS,
} from '@/components/public/public-links';
import { home, login } from '@/routes';
import { about, brands, contact, news, showroom } from '@/routes/company';

/** Regray Stone's own website. */
const REGRAY_SITE = 'https://regraystone.com/';

/** The wordmark, painted white: the transparent PNG masks the colour. */
const WORDMARK = '/images/regray_logo.png';

const links: { label: string; href: string; external?: boolean }[] = [
    { label: 'The group', href: about.url() },
    { label: 'Our brands', href: brands.url() },
    { label: 'Showroom', href: showroom.url() },
    { label: 'News', href: news.url() },
    { label: 'Contact', href: contact.url() },
    { label: 'Client login', href: login.url() },
    { label: 'Regraystone.com', href: REGRAY_SITE, external: true },
];

const linkClass =
    'text-[1.0625rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70';

/**
 * The public pages' black footer, kept short: the wordmark, one centred
 * column of the main pages, Regray's social accounts and the copyright, then
 * the showroom's address, hours and how to reach the team.
 */
export default function PublicFooter() {
    return (
        <footer className="flex flex-col gap-[clamp(4rem,8vw,6.5rem)] bg-black px-5 pt-[clamp(4.5rem,9vw,7.5rem)] pb-28 text-white sm:px-8 lg:pb-10">
            <div className="flex flex-col items-center gap-[clamp(3rem,6vw,4.5rem)] text-center">
                <Link href={home()} aria-label="Regray home">
                    <span
                        aria-hidden
                        className="block aspect-[426/76.5] h-[clamp(1.75rem,3vw,2.5rem)] bg-white"
                        style={{
                            maskImage: `url(${WORDMARK})`,
                            maskSize: 'contain',
                            maskRepeat: 'no-repeat',
                            maskPosition: 'center',
                            WebkitMaskImage: `url(${WORDMARK})`,
                            WebkitMaskSize: 'contain',
                            WebkitMaskRepeat: 'no-repeat',
                            WebkitMaskPosition: 'center',
                        }}
                    />
                </Link>

                <nav aria-label="Footer">
                    <ul className="flex flex-col items-center gap-5">
                        {links.map((link) => (
                            <li key={link.label}>
                                {link.external ? (
                                    <a
                                        href={link.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={linkClass}
                                    >
                                        {link.label}
                                    </a>
                                ) : (
                                    <Link href={link.href} className={linkClass}>
                                        {link.label}
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="flex flex-col items-center gap-5">
                    <ul
                        aria-label="Follow Regray"
                        className="flex flex-wrap justify-center gap-x-7 gap-y-2 text-[1.0625rem] font-medium"
                    >
                        {SOCIALS.map((social) => (
                            <li key={social.label}>
                                <a
                                    href={social.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="transition-opacity hover:opacity-70"
                                >
                                    {social.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    <p className="text-[0.9375rem] tracking-[0.04em] text-white/75 uppercase">
                        © Regray {new Date().getFullYear()}
                    </p>
                </div>
            </div>

            <address className="flex flex-col gap-2 text-base leading-relaxed text-white/85 not-italic">
                <span className="font-semibold tracking-[0.04em] text-white uppercase">
                    Regray Stone & Furniture
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
        </footer>
    );
}

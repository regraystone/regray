import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Logo from '@/components/logo';
import { login } from '@/routes';
import { inquire } from '@/routes/quote';

/** The landing page's own sections, in the order they appear. */
export const LANDING_SECTIONS = [
    { label: 'Brands', href: '#brands' },
    { label: 'Projects', href: '#projects' },
    { label: 'Services', href: '#services' },
    { label: 'How it works', href: '#how-it-works' },
] as const;

/** How far the page scrolls before the header turns solid. */
const SOLID_AFTER_PX = 40;

const linkText = 'text-xs font-medium tracking-[0.16em] uppercase';

/**
 * The landing page's header: see-through over the hero, then solid once the
 * page scrolls, so it reads over both the photo and the paper below. Below
 * `lg` the links fold into a full-screen menu.
 *
 * Sending an inquiry needs an account: a guest is taken to Login first and
 * comes back to the Inquire page once signed in.
 */
export default function LandingHeader() {
    const [scrolled, setScrolled] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const solid = scrolled || menuOpen;

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > SOLID_AFTER_PX);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        };
        const overflow = document.body.style.overflow;

        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKeyDown);

        return () => {
            document.body.style.overflow = overflow;
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [menuOpen]);

    const closeMenu = () => setMenuOpen(false);

    return (
        <>
            <header
                className={`fixed inset-x-0 top-0 z-50 border-b transition-colors duration-400 ${
                    solid
                        ? 'border-line bg-paper/97 text-ink'
                        : 'border-white/25 bg-transparent text-on-image'
                }`}
            >
                <nav
                    aria-label="Primary"
                    className="flex h-19 items-center justify-between gap-6 px-4 sm:px-5 lg:px-8"
                >
                    <Logo />

                    <ul className="hidden items-center gap-8 lg:flex xl:gap-10">
                        {LANDING_SECTIONS.map((section) => (
                            <li key={section.href}>
                                <a
                                    href={section.href}
                                    className={`${linkText} border-b border-transparent py-1.5 transition-colors hover:border-current`}
                                >
                                    {section.label}
                                </a>
                            </li>
                        ))}
                    </ul>

                    <div className="hidden items-center gap-6 lg:flex">
                        <Link
                            href={login.url()}
                            className={`${linkText} transition-opacity hover:opacity-70`}
                        >
                            Login
                        </Link>
                        <Link
                            href={inquire.url()}
                            className={`${linkText} border border-current px-5 py-3 transition-opacity hover:opacity-70`}
                        >
                            Send an inquiry
                        </Link>
                    </div>

                    <button
                        type="button"
                        onClick={() => setMenuOpen((open) => !open)}
                        aria-expanded={menuOpen}
                        aria-controls="landing-menu"
                        className={`${linkText} min-h-11 px-1 lg:hidden`}
                    >
                        {menuOpen ? 'Close' : 'Menu'}
                    </button>
                </nav>
            </header>

            {menuOpen ? (
                <div
                    id="landing-menu"
                    className="fixed inset-x-0 top-19 bottom-0 z-40 flex flex-col overflow-auto bg-paper px-6 py-8 text-ink lg:hidden"
                >
                    <ul>
                        {LANDING_SECTIONS.map((section) => (
                            <li key={section.href}>
                                <a
                                    href={section.href}
                                    onClick={closeMenu}
                                    className="block border-b border-line py-3.5 text-3xl"
                                >
                                    {section.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-8 flex flex-col gap-3">
                        <Link
                            href={inquire.url()}
                            className={`${linkText} bg-ink p-4.5 text-center text-paper`}
                        >
                            Send an inquiry
                        </Link>
                        <Link
                            href={login.url()}
                            className={`${linkText} border border-ink p-4.5 text-center`}
                        >
                            Login
                        </Link>
                    </div>
                </div>
            ) : null}
        </>
    );
}

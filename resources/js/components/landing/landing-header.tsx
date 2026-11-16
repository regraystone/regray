import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LANDING_NAV } from '@/components/landing/landing-links';
import Logo from '@/components/logo';
import { login } from '@/routes';
import { inquire } from '@/routes/quote';

/** The header's height: the hero fills the screen below it. */
export const HEADER_HEIGHT = 'h-20';

const navLink =
    'border-b border-transparent py-1.5 transition-colors hover:border-foreground';

/**
 * The landing page's header: white and always in view, the wordmark, the
 * page's sections and the catalog, then Login and Inquire. Below 1180px the
 * links fold into a full-screen menu (Escape closes it).
 *
 * Sending an inquiry needs an account: a guest is taken to Login first and
 * comes back to the Inquire page once signed in.
 */
export default function LandingHeader() {
    const [menuOpen, setMenuOpen] = useState(false);

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
                className={`sticky top-0 z-50 flex ${HEADER_HEIGHT} items-center justify-between gap-6 bg-background px-4 sm:px-8`}
            >
                <Logo className="[&>span:first-child]:h-5" />

                <nav
                    aria-label="Primary"
                    className="hidden items-center gap-[clamp(1rem,1.6vw,1.625rem)] text-[0.8125rem] tracking-[0.02em] uppercase min-[1180px]:flex"
                >
                    {LANDING_NAV.map((item) =>
                        item.href.startsWith('#') ? (
                            <a
                                key={item.label}
                                href={item.href}
                                className={navLink}
                            >
                                {item.label}
                            </a>
                        ) : (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={navLink}
                            >
                                {item.label}
                            </Link>
                        ),
                    )}
                    <span aria-hidden className="h-4 w-px bg-border" />
                    <Link href={login.url()} className={navLink}>
                        Login
                    </Link>
                    <Link
                        href={inquire.url()}
                        className="bg-foreground px-4 py-2.5 font-semibold text-background transition-colors hover:bg-foreground/80"
                    >
                        Inquire
                    </Link>
                </nav>

                <button
                    type="button"
                    onClick={() => setMenuOpen((open) => !open)}
                    aria-expanded={menuOpen}
                    aria-controls="landing-menu"
                    className="min-h-11 px-1 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase min-[1180px]:hidden"
                >
                    {menuOpen ? 'Close' : 'Menu'}
                </button>
            </header>

            {menuOpen ? (
                <div
                    id="landing-menu"
                    className="fixed inset-x-0 top-20 bottom-0 z-40 flex flex-col overflow-auto bg-background px-5 pt-2 pb-8 min-[1180px]:hidden"
                >
                    <ul>
                        {LANDING_NAV.map((item) => (
                            <li key={item.label}>
                                {item.href.startsWith('#') ? (
                                    <a
                                        href={item.href}
                                        onClick={closeMenu}
                                        className="block border-b border-border py-4 font-display text-2xl font-light uppercase"
                                    >
                                        {item.label}
                                    </a>
                                ) : (
                                    <Link
                                        href={item.href}
                                        onClick={closeMenu}
                                        className="block border-b border-border py-4 font-display text-2xl font-light uppercase"
                                    >
                                        {item.label}
                                    </Link>
                                )}
                            </li>
                        ))}
                    </ul>
                    <div className="mt-7 flex flex-col gap-3 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase">
                        <Link
                            href={inquire.url()}
                            className="bg-foreground p-4.5 text-center text-background"
                        >
                            Send an inquiry
                        </Link>
                        <Link
                            href={login.url()}
                            className="border border-foreground p-4.5 text-center"
                        >
                            Login
                        </Link>
                    </div>
                </div>
            ) : null}
        </>
    );
}

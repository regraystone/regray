import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    HOME_BRAND,
    menuItems,
    type MenuLink,
    type MenuPanel,
} from '@/components/public/public-links';
import Logo from '@/components/logo';
import { dashboard, login, register } from '@/routes';
import type { BrandCardData } from '@/types/home';

/** Below this width the tabs fold into the full-screen menu. */
const DESKTOP_QUERY = '(min-width: 1180px)';

/**
 * A link out of the menu: to another site in a new tab, a full page load for
 * one with a #section (so the browser scrolls to it), an Inertia visit
 * otherwise.
 */
const MenuAnchor = ({
    link,
    className,
    onNavigate,
    tabIndex,
    children,
}: {
    link: MenuLink;
    className?: string;
    onNavigate?: () => void;
    tabIndex?: number;
    children: React.ReactNode;
}) => {
    if (link.external) {
        return (
            <a
                href={link.href}
                target="_blank"
                rel="noopener noreferrer"
                className={className}
                onClick={onNavigate}
                tabIndex={tabIndex}
            >
                {children}
            </a>
        );
    }

    if (link.href.includes('#')) {
        return (
            <a
                href={link.href}
                className={className}
                onClick={onNavigate}
                tabIndex={tabIndex}
            >
                {children}
            </a>
        );
    }

    return (
        <Link
            href={link.href}
            className={className}
            onClick={onNavigate}
            tabIndex={tabIndex}
        >
            {children}
        </Link>
    );
};

/** The "+" that turns into an "×" when its tab is open. */
const Plus = ({
    open,
    className = '',
}: {
    open: boolean;
    className?: string;
}) => (
    <span
        aria-hidden
        className={`inline-block font-light transition-transform duration-300 motion-reduce:transition-none ${open ? 'rotate-45' : ''} ${className}`}
    >
        +
    </span>
);

/**
 * The public header: the wordmark, five tabs (Our brands, Showroom, Discover,
 * News, Contact) and, for a guest, Login and Sign up, or for anyone signed
 * in, their Dashboard.
 *
 * Each tab opens a panel (a tab marked as a page instead goes straight to it,
 * underlined on its own page). From 1180px a panel
 * opens under the header on hover, focus or click: what the tab holds, its
 * main link, its links and a photo, over a dimmed page. Leaving the header,
 * Escape or the dimmed page closes it. Below 1180px the tabs fold into a
 * full-screen menu, the panels as accordions and the pages as links.
 */
export default function PublicHeader() {
    const { siteBrands, auth } = usePage<{
        siteBrands?: BrandCardData[] | null;
        auth: { user: unknown };
    }>().props;
    const isSignedIn = auth.user !== null;
    const { url } = usePage();
    const items = menuItems(
        siteBrands?.find((brand) => brand.slug === HOME_BRAND)?.thumbnail ??
            null,
    );
    const firstPanel = items.find(
        (item): item is MenuPanel => item.kind === 'panel',
    );

    /** The open panel tab's index in `items` from 1180px; -1 when none is. */
    const [open, setOpen] = useState(-1);
    const [menuOpen, setMenuOpen] = useState(false);
    /** The open accordion in the phone menu; -1 when none is. */
    const [accordion, setAccordion] = useState(0);
    const openItem = open >= 0 ? items[open] : undefined;
    const current =
        openItem?.kind === 'panel' ? openItem : (firstPanel as MenuPanel);
    const isOpen = openItem?.kind === 'panel';
    /** Whether a page tab is the page being read. */
    const isHere = (href: string) =>
        url.split(/[?#]/)[0] === href.split(/[?#]/)[0];

    const closeAll = () => {
        setOpen(-1);
        setMenuOpen(false);
    };

    // Escape closes whichever menu is open.
    useEffect(() => {
        if (!isOpen && !menuOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                closeAll();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [isOpen, menuOpen]);

    // The full-screen menu holds the page still under it, and closes once the
    // window is wide enough for the tabs.
    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const overflow = document.body.style.overflow;
        const desktop = window.matchMedia(DESKTOP_QUERY);
        const onChange = () => desktop.matches && setMenuOpen(false);

        document.body.style.overflow = 'hidden';
        desktop.addEventListener('change', onChange);

        return () => {
            document.body.style.overflow = overflow;
            desktop.removeEventListener('change', onChange);
        };
    }, [menuOpen]);

    const accountLinks = isSignedIn
        ? [{ label: 'Dashboard', href: dashboard.url(), primary: true }]
        : [
              { label: 'Login', href: login.url(), primary: false },
              { label: 'Sign up', href: register.url(), primary: true },
          ];

    return (
        <>
            <header
                onMouseLeave={() => setOpen(-1)}
                className="sticky top-0 z-50 bg-background"
            >
                <div
                    className={`relative z-20 flex h-20 items-center justify-between gap-6 border-b bg-background px-4 transition-colors duration-300 sm:px-8 ${
                        isOpen ? 'border-border' : 'border-transparent'
                    }`}
                >
                    <div onMouseEnter={() => setOpen(-1)}>
                        <Logo className="[&>span:first-child]:h-5" />
                    </div>

                    <nav
                        aria-label="Primary"
                        className="hidden self-stretch min-[1180px]:flex min-[1180px]:gap-[clamp(1.25rem,2.4vw,2.5rem)]"
                    >
                        {items.map((item, index) =>
                            item.kind === 'page' ? (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    onMouseEnter={() => setOpen(-1)}
                                    onFocus={() => setOpen(-1)}
                                    aria-current={
                                        isHere(item.href) ? 'page' : undefined
                                    }
                                    className={`-mb-px flex h-full items-center border-b text-[0.8125rem] tracking-[0.06em] uppercase transition-colors duration-200 hover:border-foreground ${
                                        isHere(item.href)
                                            ? 'border-foreground'
                                            : 'border-transparent'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            ) : (
                                <button
                                    key={item.label}
                                    type="button"
                                    onMouseEnter={() => setOpen(index)}
                                    onFocus={() => setOpen(index)}
                                    // Hover and focus open it first, so a click
                                    // only keeps it open; Escape, leaving the
                                    // header or the dimmed page close it.
                                    onClick={() => setOpen(index)}
                                    aria-expanded={open === index}
                                    aria-controls="public-menu-panel"
                                    className={`-mb-px flex h-full cursor-pointer items-center gap-2 border-b text-[0.8125rem] tracking-[0.06em] uppercase transition-colors duration-200 ${
                                        open === index
                                            ? 'border-foreground'
                                            : 'border-transparent'
                                    }`}
                                >
                                    {item.label}
                                    <Plus
                                        open={open === index}
                                        className="text-sm"
                                    />
                                </button>
                            ),
                        )}
                    </nav>

                    <div
                        onMouseEnter={() => setOpen(-1)}
                        className="hidden items-center gap-5 text-[0.8125rem] tracking-[0.02em] uppercase min-[1180px]:flex"
                    >
                        {accountLinks.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className={
                                    link.primary
                                        ? 'bg-foreground px-4 py-2.5 font-semibold text-background transition-colors hover:bg-foreground/80'
                                        : 'border-b border-transparent py-1.5 transition-colors hover:border-foreground'
                                }
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>

                    <button
                        type="button"
                        onClick={() => setMenuOpen((now) => !now)}
                        aria-expanded={menuOpen}
                        aria-controls="public-menu"
                        className="min-h-11 px-1 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase min-[1180px]:hidden"
                    >
                        {menuOpen ? 'Close' : 'Menu'}
                    </button>
                </div>

                {/* The open tab's panel, from 1180px. */}
                <div
                    id="public-menu-panel"
                    aria-hidden={!isOpen}
                    inert={!isOpen}
                    className={`absolute inset-x-0 top-20 z-10 hidden bg-background shadow-[0_24px_40px_-32px_rgb(0_0_0/0.25)] transition-[opacity,transform,visibility] duration-450 ease-[cubic-bezier(.2,.7,.2,1)] motion-reduce:transition-none min-[1180px]:block ${
                        isOpen
                            ? 'visible translate-y-0 opacity-100'
                            : 'invisible -translate-y-3 opacity-0'
                    }`}
                >
                    <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)] gap-[clamp(2rem,4vw,4.5rem)] px-8 pt-12 pb-14">
                        <div className="flex flex-col gap-4">
                            <p className="text-xs tracking-[0.14em] text-muted-foreground uppercase">
                                {current.label}
                            </p>
                            <p className="font-display text-[clamp(1.375rem,1.8vw,1.75rem)] leading-snug font-light text-pretty">
                                {current.description}
                            </p>
                            <MenuAnchor
                                link={current.cta}
                                onNavigate={closeAll}
                                className="mt-2 self-start border-b border-foreground pb-2 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70"
                            >
                                {current.cta.label}
                            </MenuAnchor>
                        </div>
                        <ul className="flex flex-col border-t border-border">
                            {current.links.map((link, index) => (
                                <li
                                    key={`${current.label}-${link.label}`}
                                    style={{
                                        transitionDelay: isOpen
                                            ? `${80 + index * 40}ms`
                                            : '0ms',
                                    }}
                                    className={`border-b border-border transition-[opacity,transform] duration-500 motion-reduce:transition-none ${
                                        isOpen
                                            ? 'translate-y-0 opacity-100'
                                            : 'translate-y-2 opacity-0'
                                    }`}
                                >
                                    <MenuAnchor
                                        link={link}
                                        onNavigate={closeAll}
                                        className="flex items-center justify-between gap-4 py-3.5 font-display text-[clamp(1.0625rem,1.3vw,1.25rem)] font-light transition-[padding,color] hover:pl-2 hover:text-muted-foreground motion-reduce:transition-none"
                                    >
                                        {link.label}
                                        <span aria-hidden className="text-sm">
                                            {link.external ? '↗' : '→'}
                                        </span>
                                    </MenuAnchor>
                                </li>
                            ))}
                        </ul>
                        <div className="aspect-4/3 overflow-hidden bg-muted">
                            <img
                                src={current.image}
                                alt=""
                                className="size-full object-cover"
                            />
                        </div>
                    </div>
                </div>
            </header>

            {/* The page dims under an open panel; touching it closes the panel. */}
            <div
                aria-hidden
                onMouseEnter={() => setOpen(-1)}
                onClick={() => setOpen(-1)}
                className={`fixed inset-0 z-40 hidden bg-black/35 transition-opacity duration-350 motion-reduce:transition-none min-[1180px]:block ${
                    isOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
            />

            {menuOpen ? (
                <div
                    id="public-menu"
                    role="dialog"
                    aria-label="Menu"
                    className="fixed inset-x-0 top-20 bottom-0 z-40 flex flex-col overflow-auto bg-background px-5 pt-2 pb-8 min-[1180px]:hidden"
                >
                    {items.map((section, index) => {
                        const expanded = accordion === index;

                        if (section.kind === 'page') {
                            return (
                                <Link
                                    key={section.label}
                                    href={section.href}
                                    onClick={closeAll}
                                    aria-current={
                                        isHere(section.href)
                                            ? 'page'
                                            : undefined
                                    }
                                    className="flex min-h-16 items-center border-b border-border font-display text-2xl font-light uppercase"
                                >
                                    {section.label}
                                </Link>
                            );
                        }

                        return (
                            <div
                                key={section.label}
                                className="border-b border-border"
                            >
                                <button
                                    type="button"
                                    onClick={() =>
                                        setAccordion(expanded ? -1 : index)
                                    }
                                    aria-expanded={expanded}
                                    className="flex min-h-16 w-full cursor-pointer items-center justify-between gap-4 text-left font-display text-2xl font-light uppercase"
                                >
                                    {section.label}
                                    <Plus
                                        open={expanded}
                                        className="text-[1.375rem]"
                                    />
                                </button>
                                <div
                                    className={`grid transition-[grid-template-rows] duration-350 ease-out motion-reduce:transition-none ${
                                        expanded
                                            ? 'grid-rows-[1fr]'
                                            : 'grid-rows-[0fr]'
                                    }`}
                                >
                                    <div
                                        className="overflow-hidden"
                                        inert={!expanded}
                                    >
                                        <p className="mb-3 text-[0.9375rem] leading-normal text-muted-foreground">
                                            {section.description}
                                        </p>
                                        <ul className="flex flex-col pb-5">
                                            {[section.cta, ...section.links]
                                                .filter(
                                                    (link, position, all) =>
                                                        all.findIndex(
                                                            (other) =>
                                                                other.href ===
                                                                link.href,
                                                        ) === position,
                                                )
                                                .map((link) => (
                                                    <li key={link.label}>
                                                        <MenuAnchor
                                                            link={link}
                                                            onNavigate={
                                                                closeAll
                                                            }
                                                            className="flex min-h-11 items-center gap-3 text-base"
                                                        >
                                                            <span
                                                                aria-hidden
                                                                className="text-muted-foreground"
                                                            >
                                                                {link.external
                                                                    ? '↗'
                                                                    : '›'}
                                                            </span>
                                                            {link.label}
                                                        </MenuAnchor>
                                                    </li>
                                                ))}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                    <div className="mt-7 flex flex-col gap-3 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase">
                        {[...accountLinks].reverse().map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                onClick={closeAll}
                                className={
                                    link.primary
                                        ? 'bg-foreground p-4.5 text-center text-background'
                                        : 'border border-foreground p-4.5 text-center'
                                }
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>
                </div>
            ) : null}
        </>
    );
}

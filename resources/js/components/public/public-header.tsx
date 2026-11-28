import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    menuItems,
    menuRows,
    type MenuLink,
    type MenuPanel,
} from '@/components/public/public-links';
import Logo from '@/components/logo';
import { dashboard, login, register } from '@/routes';

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

/** A link's mark after its label: the menu's chevron, ↗ for another site, or none. */
const LinkMark = ({ link }: { link: MenuLink }) =>
    link.chevron ? (
        <ChevronRight
            aria-hidden
            strokeWidth={1.5}
            className="size-4.5 shrink-0"
        />
    ) : link.external ? (
        <span aria-hidden className="text-sm">
            ↗
        </span>
    ) : null;

/** The chevron that turns to point up when its tab is open. */
const Chevron = ({
    open,
    className = '',
}: {
    open: boolean;
    className?: string;
}) => (
    <ChevronDown
        aria-hidden
        strokeWidth={1.5}
        className={`shrink-0 transition-transform duration-300 motion-reduce:transition-none ${open ? 'rotate-180' : ''} ${className}`}
    />
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
 * full-screen menu, opened by the ☰ button (its lines turn into ✕), whose
 * sections slide their links in with a chevron and go back with another, as
 * Poliform's menu does.
 */
export default function PublicHeader() {
    const { auth } = usePage<{ auth: { user: unknown } }>().props;
    const isSignedIn = auth.user !== null;
    const { url } = usePage();
    const items = menuItems();
    const firstPanel = items.find(
        (item): item is MenuPanel => item.kind === 'panel',
    );

    /** The open panel tab's index in `items` from 1180px; -1 when none is. */
    const [open, setOpen] = useState(-1);
    const [menuOpen, setMenuOpen] = useState(false);
    /** The section whose links the phone menu has slid in, by index in `items`; -1 for the menu itself. */
    const [sub, setSub] = useState(-1);
    /** The last section slid in, kept while it slides out again, so going back wipes its links away rather than blanking them. */
    const [shown, setShown] = useState(-1);
    const subSection = shown >= 0 ? items[shown] : undefined;
    const backButton = useRef<HTMLButtonElement>(null);
    const sectionButtons = useRef<Array<HTMLButtonElement | null>>([]);
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
        setSub(-1);
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

    // Opening a section moves the focus to its back arrow; going back returns it
    // to the section's own row, so a keyboard or screen reader never loses its place.
    const openSection = (index: number) => {
        setSub(index);
        setShown(index);
        window.setTimeout(() => backButton.current?.focus(), 0);
    };
    const closeSection = () => {
        const from = sub;
        setSub(-1);
        window.setTimeout(() => sectionButtons.current[from]?.focus(), 0);
    };

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
                        <Logo className="text-[0.9375rem] min-[360px]:text-[1.0625rem] sm:text-xl" />
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
                                    className={`-mb-px flex h-full items-center border-b text-[0.9375rem] font-medium tracking-[0.04em] uppercase transition-colors duration-200 hover:border-foreground ${
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
                                    className={`-mb-px flex h-full cursor-pointer items-center gap-2 border-b text-[0.9375rem] font-medium tracking-[0.04em] uppercase transition-colors duration-200 ${
                                        open === index
                                            ? 'border-foreground'
                                            : 'border-transparent'
                                    }`}
                                >
                                    {item.label}
                                    <Chevron
                                        open={open === index}
                                        className="size-3.5"
                                    />
                                </button>
                            ),
                        )}
                    </nav>

                    <div
                        onMouseEnter={() => setOpen(-1)}
                        className="hidden items-center gap-5 text-[0.9375rem] font-medium min-[1180px]:flex"
                    >
                        {accountLinks.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className={
                                    link.primary
                                        ? 'rounded-md bg-foreground px-5 py-3 font-semibold text-background transition-colors hover:bg-foreground/80'
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
                        aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                        className="-mr-2 flex size-11 items-center justify-center rounded-md transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none min-[1180px]:hidden"
                    >
                        {/* Three lines, as Poliform's: the middle one shrinks away to the right while the top and bottom turn into an X. */}
                        <span aria-hidden className="relative block h-4.5 w-6">
                            <span
                                className={`absolute right-0 h-0.5 w-full rounded-full bg-foreground transition-all duration-300 ease-in-out motion-reduce:transition-none ${
                                    menuOpen
                                        ? 'top-[calc(50%-1px)] -rotate-45'
                                        : 'top-0'
                                }`}
                            />
                            <span
                                className={`absolute top-[calc(50%-1px)] right-0 h-0.5 w-full origin-right rounded-full bg-foreground transition-transform duration-300 ease-in-out motion-reduce:transition-none ${
                                    menuOpen ? 'scale-x-0' : 'scale-x-100'
                                }`}
                            />
                            <span
                                className={`absolute right-0 h-0.5 w-full rounded-full bg-foreground transition-all duration-300 ease-in-out motion-reduce:transition-none ${
                                    menuOpen
                                        ? 'bottom-[calc(50%-1px)] rotate-45'
                                        : 'bottom-0'
                                }`}
                            />
                        </span>
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
                            <p className="text-[0.9375rem] font-semibold tracking-[0.04em] text-muted-foreground uppercase">
                                {current.label}
                            </p>
                            {current.description ? (
                                <p className="font-display text-[clamp(1.375rem,1.8vw,1.75rem)] leading-snug font-light text-pretty">
                                    {current.description}
                                </p>
                            ) : null}
                            {current.cta ? (
                                <MenuAnchor
                                    link={current.cta}
                                    onNavigate={closeAll}
                                    className="mt-2 self-start border-b border-foreground pb-1.5 text-base font-medium transition-opacity hover:opacity-70"
                                >
                                    {current.cta.label}
                                </MenuAnchor>
                            ) : null}
                        </div>
                        <ul className="flex flex-col border-t border-border">
                            {menuRows(current.links).map((link, index) => (
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
                                        className={`flex items-center justify-between gap-4 font-display transition-[padding,color] hover:text-muted-foreground motion-reduce:transition-none ${
                                            link.depth === 1
                                                ? 'py-2.5 pl-6 text-[clamp(1rem,1.15vw,1.125rem)] text-foreground/80 hover:pl-8'
                                                : 'py-3.5 text-[clamp(1.125rem,1.3vw,1.25rem)] hover:pl-2'
                                        }`}
                                    >
                                        {link.label}
                                        <LinkMark link={link} />
                                    </MenuAnchor>
                                </li>
                            ))}
                        </ul>
                        <div className="aspect-4/3 overflow-hidden rounded-md bg-muted">
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

            {/*
              The phone menu, as Poliform's: it slides down over the page on a
              phone and in from the right on a tablet. Each section has a chevron;
              tapping it nudges the arrow on and wipes that section's links in
              over the menu, where a back arrow returns, the section's name
              sliding in beside it. It stays in the page while closed, so it
              can slide out again, but out of reach (inert) until it opens.
            */}
            <div
                id="public-menu"
                role="dialog"
                aria-label="Menu"
                aria-hidden={!menuOpen}
                inert={!menuOpen}
                className={`fixed inset-x-0 top-20 bottom-0 z-40 overflow-hidden bg-background transition-[translate,visibility] duration-600 ease-in-out motion-reduce:transition-none min-[1180px]:hidden ${
                    menuOpen
                        ? 'visible translate-0'
                        : 'invisible max-[575px]:-translate-y-[101%] min-[576px]:translate-x-[101%]'
                }`}
            >
                <div
                    inert={sub >= 0}
                    className="flex h-full flex-col overflow-auto px-5 pt-2 pb-8"
                >
                    {items.map((section, index) =>
                        section.kind === 'page' ? (
                            <Link
                                key={section.label}
                                href={section.href}
                                onClick={closeAll}
                                aria-current={
                                    isHere(section.href) ? 'page' : undefined
                                }
                                className="flex min-h-16 items-center border-b border-border font-display text-2xl font-light uppercase"
                            >
                                {section.label}
                            </Link>
                        ) : (
                            <button
                                key={section.label}
                                ref={(button) => {
                                    sectionButtons.current[index] = button;
                                }}
                                type="button"
                                onClick={() => openSection(index)}
                                aria-expanded={sub === index}
                                aria-controls="public-menu-section"
                                className="group flex min-h-16 w-full cursor-pointer items-center justify-between gap-4 border-b border-border text-left font-display text-2xl font-light uppercase"
                            >
                                {section.label}
                                <ChevronRight
                                    aria-hidden
                                    strokeWidth={1.5}
                                    className={`size-6 shrink-0 transition-transform duration-400 ease-out group-hover:translate-x-1 motion-reduce:transition-none ${
                                        sub === index ? 'translate-x-2' : ''
                                    }`}
                                />
                            </button>
                        ),
                    )}
                    <div className="mt-7 flex flex-col gap-3 text-base font-semibold">
                        {[...accountLinks].reverse().map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                onClick={closeAll}
                                className={
                                    link.primary
                                        ? 'rounded-md bg-foreground p-4.5 text-center text-background'
                                        : 'rounded-md border border-foreground p-4.5 text-center'
                                }
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>
                </div>

                {/* The open section's links, wiped in from the right over the menu. */}
                <div
                    id="public-menu-section"
                    aria-hidden={sub < 0}
                    inert={sub < 0}
                    className={`absolute inset-0 flex flex-col overflow-auto bg-background px-5 pt-2 pb-8 transition-[clip-path] duration-400 ease-in-out motion-reduce:transition-none ${
                        sub >= 0
                            ? '[clip-path:inset(0_0_0_0)]'
                            : 'pointer-events-none [clip-path:inset(0_0_0_100%)]'
                    }`}
                >
                    {subSection?.kind === 'panel' ? (
                        <>
                            <button
                                ref={backButton}
                                type="button"
                                onClick={closeSection}
                                className="group flex min-h-16 w-full cursor-pointer items-center gap-4 border-b border-border text-left"
                            >
                                <ChevronLeft
                                    aria-hidden
                                    strokeWidth={1.5}
                                    className="size-6 shrink-0 transition-transform duration-300 ease-out group-hover:-translate-x-1 motion-reduce:transition-none"
                                />
                                <span className="sr-only">
                                    Back to the menu:{' '}
                                </span>
                                {/* The name slides in from behind the arrow, as Poliform's back label does. */}
                                <span className="overflow-hidden">
                                    <span
                                        className={`block font-display text-2xl font-light uppercase transition-transform duration-400 ease-in-out motion-reduce:transition-none ${
                                            sub >= 0
                                                ? 'translate-x-0'
                                                : '-translate-x-24'
                                        }`}
                                    >
                                        {subSection.label}
                                    </span>
                                </span>
                            </button>
                            {subSection.description ? (
                                <p className="mt-5 text-[1.0625rem] leading-normal text-muted-foreground">
                                    {subSection.description}
                                </p>
                            ) : null}
                            {/* The section's main link, under its line as on a laptop; its list holds only its links. */}
                            {subSection.cta ? (
                                <MenuAnchor
                                    link={subSection.cta}
                                    onNavigate={closeAll}
                                    className="mt-3 mb-4 self-start border-b border-foreground pb-1 text-[1.0625rem] font-medium"
                                >
                                    {subSection.cta.label}
                                </MenuAnchor>
                            ) : subSection.description ? (
                                <span className="mb-4" />
                            ) : null}
                            {/* With no line above it, the list starts right under the back button's own rule. */}
                            <ul
                                className={`flex flex-col ${
                                    subSection.description || subSection.cta
                                        ? 'border-t border-border'
                                        : ''
                                }`}
                            >
                                {menuRows(subSection.links)
                                    .filter(
                                        (link) =>
                                            link.href !== subSection.cta?.href,
                                    )
                                    .map((link, index, rows) => (
                                        <li
                                            key={link.label}
                                            // A group's links share one line under its last.
                                            className={
                                                link.depth === 1 &&
                                                rows[index + 1]?.depth === 1
                                                    ? ''
                                                    : 'border-b border-border'
                                            }
                                        >
                                            <MenuAnchor
                                                link={link}
                                                onNavigate={closeAll}
                                                className={`group flex items-center justify-between gap-3 ${
                                                    link.depth === 1
                                                        ? 'min-h-12 pl-6 text-[1.0625rem] text-foreground/80'
                                                        : 'min-h-14 text-[1.125rem]'
                                                }`}
                                            >
                                                {link.label}
                                                <span className="text-muted-foreground transition-transform duration-300 ease-out group-hover:translate-x-1 motion-reduce:transition-none">
                                                    <LinkMark link={link} />
                                                </span>
                                            </MenuAnchor>
                                        </li>
                                    ))}
                            </ul>
                        </>
                    ) : null}
                </div>
            </div>
        </>
    );
}

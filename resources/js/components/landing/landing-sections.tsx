import { Link } from '@inertiajs/react';
import { ArrowUpRight, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import {
    HOME_BRAND,
    SHOWROOM,
    SHOWROOM_MAP,
    collectionUrl,
} from '@/components/public/public-links';
import { register } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import { show as projectShow } from '@/routes/stone';
import type { LandingBrand, LandingProject } from '@/types/home';

/** The white margin every photo sits in, matching the header's. */
const frame = 'px-4 sm:px-8';

/** A section's title, in the heading face. */
const title =
    'font-display text-[clamp(1.625rem,2.2vw,2.125rem)] font-light tracking-[-0.005em]';

/** The same, as an uppercase label over a set of tiles. */
const capsTitle =
    'font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light tracking-[0.02em] uppercase';

/** A paragraph of a centred story. */
const story = 'max-w-170 text-[1.0625rem] leading-relaxed text-pretty';

/** An underlined uppercase link. */
const textLink =
    'border-b border-current px-3 pb-3.5 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70';

/** A photo that eases in a little on hover. */
const zoom =
    'transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none';

/**
 * Each logo's height over a photo, as a multiple of the row's base height
 * (26px on phones, 36px from 700px), by brand slug, so every name reads at
 * about one size: the square Lube badge is tallest, the long Faer wordmark
 * lowest. A brand not listed gets the default.
 */
const LOGO_SCALE: Record<string, number> = {
    'regray-stone': 1.6,
    cucine: 3,
    ascale: 1.1,
    faer: 0.9,
    reform: 1.8,
    'res-italia': 2,
};
const DEFAULT_LOGO_SCALE = 1.4;

const numberWords = [
    'no',
    'one',
    'two',
    'three',
    'four',
    'five',
    'six',
    'seven',
    'eight',
    'nine',
    'ten',
];
const inWords = (count: number) => numberWords[count] ?? String(count);

/** A brand's public page. */
const brandUrl = (slug: string) => brandsShow.url({ brand: slug });

/** Centred copy under a photo: a title, a paragraph and its links. */
const Story = ({
    id,
    eyebrow,
    heading,
    headingLevel = 2,
    caps = false,
    children,
    links,
}: {
    id?: string;
    eyebrow?: string;
    heading: string;
    headingLevel?: 1 | 2;
    caps?: boolean;
    children?: ReactNode;
    links: ReactNode;
}) => {
    const Heading = headingLevel === 1 ? 'h1' : 'h2';

    return (
        <section
            id={id}
            className="flex scroll-mt-20 flex-col items-center gap-7 px-5 pt-[clamp(3.5rem,7vw,6rem)] pb-[clamp(5rem,9vw,8rem)] text-center"
        >
            {eyebrow ? (
                <p className="text-[0.8125rem] tracking-[0.04em] text-muted-foreground uppercase">
                    {eyebrow}
                </p>
            ) : null}
            <Heading className={caps ? capsTitle : title}>{heading}</Heading>
            {children ? <p className={story}>{children}</p> : null}
            <div className="mt-3 flex flex-wrap justify-center gap-x-10 gap-y-4">
                {links}
            </div>
        </section>
    );
};

/**
 * A brand's photo across the full width, at its own shape, with the brand's
 * logo in the middle. A photo taller than the screen is cropped to the
 * screen's height rather than shrunk, so it never leaves bars at its sides.
 */
const BrandPhoto = ({
    brand,
    logoClassName,
}: {
    brand: LandingBrand;
    logoClassName?: string;
}) => (
    <div className={frame}>
        <Link
            href={brandUrl(brand.slug)}
            aria-label={`Discover ${brand.name}`}
            className="group relative block overflow-hidden bg-night"
        >
            {brand.thumbnail ? (
                <img
                    src={brand.thumbnail}
                    alt=""
                    loading="lazy"
                    className={`block h-auto max-h-[calc(100svh-5rem)] w-full object-cover ${zoom}`}
                />
            ) : (
                <div className="aspect-video" />
            )}
            <div aria-hidden className="absolute inset-0 bg-black/25" />
            <img
                src={brand.logo}
                alt={brand.name}
                className={
                    logoClassName ??
                    'absolute top-1/2 left-1/2 max-w-[70%] -translate-x-1/2 -translate-y-1/2 object-contain'
                }
                style={
                    logoClassName
                        ? undefined
                        : {
                              height: `calc(var(--logo-base) * ${LOGO_SCALE[brand.slug] ?? DEFAULT_LOGO_SCALE})`,
                          }
                }
            />
        </Link>
    </div>
);

/** The framed photo that opens the page, and the arrow down to the story. */
export function LandingHero() {
    return (
        <section aria-label="Regray" className={frame}>
            <div className="relative h-[calc(100svh-8.5rem)] min-h-105 overflow-hidden bg-[#d8d4cd]">
                <img
                    src="/images/regray-hero.jpg"
                    alt="A living room with a book-matched marble wall and a travertine table"
                    fetchPriority="high"
                    className="size-full object-cover object-[center_40%]"
                />
            </div>
            <a
                href="#platform"
                aria-label="Scroll to the introduction"
                className="flex h-14 items-center justify-center"
            >
                <span
                    aria-hidden
                    className="size-3 -translate-y-0.75 rotate-45 border-r-[1.5px] border-b-[1.5px] border-foreground"
                />
            </a>
        </section>
    );
}

/** Regray as a platform: one group of brands, one team, one showroom. */
export function LandingPlatform({ brands }: { brands: LandingBrand[] }) {
    const partners = brands.filter((brand) => brand.slug !== HOME_BRAND);
    const hasHome = partners.length < brands.length;

    return (
        <Story
            id="platform"
            eyebrow="The Regray group"
            heading="Every brand. One platform."
            headingLevel={1}
            links={
                <a href="#brands" className={textLink}>
                    Discover our houses
                </a>
            }
        >
            Regray is home to {inWords(brands.length)} houses in stone, kitchens
            and interiors
            {hasHome
                ? `: its own Regray Stone and ${inWords(partners.length)} European design brands`
                : ''}
            . Each keeps its craft and identity. Together they share one
            showroom, one team and one platform, from the first idea to the
            final installation.
        </Story>
    );
}

/** Every brand: its photo and logo, then what it makes and a way in. Regray Stone first. */
export function LandingBrands({ brands }: { brands: LandingBrand[] }) {
    const home = brands.find((brand) => brand.slug === HOME_BRAND);
    const partners = brands.filter((brand) => brand.slug !== HOME_BRAND);

    return (
        <div
            id="brands"
            aria-label="Our houses"
            className="scroll-mt-20 [--logo-base:26px] min-[700px]:[--logo-base:36px]"
        >
            {home ? (
                <article aria-label={home.name}>
                    <BrandPhoto
                        brand={home}
                        logoClassName="absolute top-1/2 left-1/2 h-auto w-[min(60%,38.75rem)] -translate-x-1/2 -translate-y-1/2"
                    />
                    <Story
                        heading={home.name}
                        links={
                            <Link
                                href={brandUrl(home.slug)}
                                className={textLink}
                            >
                                Discover more
                            </Link>
                        }
                    >
                        {home.description}
                    </Story>
                </article>
            ) : null}

            {partners.map((brand) => (
                <article key={brand.slug} aria-label={brand.name}>
                    <BrandPhoto brand={brand} />
                    <Story
                        heading={brand.name}
                        links={
                            <Link
                                href={brandUrl(brand.slug)}
                                className={textLink}
                            >
                                Discover {brand.name}
                            </Link>
                        }
                    >
                        {brand.description}
                    </Story>
                </article>
            ))}
        </div>
    );
}

/** Regray Stone's slabs and furniture, over a wall of book-matched slabs. */
export function LandingCollection() {
    return (
        <>
            <div className={frame}>
                <Link
                    href={collectionUrl('stones')}
                    aria-label="Discover the premium slabs collection"
                    className="group block aspect-4/3 overflow-hidden bg-[#d8d4cd] min-[700px]:aspect-[21/9]"
                >
                    <img
                        src="/images/regray-slabs.jpg"
                        alt="A wall of book-matched quartzite slabs in a restaurant"
                        loading="lazy"
                        className={`size-full object-cover ${zoom}`}
                    />
                </Link>
            </div>
            <Story
                heading="Premium slabs collection"
                caps
                links={
                    <>
                        <Link
                            href={collectionUrl('stones')}
                            className={textLink}
                        >
                            Discover slabs
                        </Link>
                        <Link
                            href={collectionUrl('furniture')}
                            className={textLink}
                        >
                            Discover furniture
                        </Link>
                    </>
                }
            >
                Marble, quartzite, onyx and travertine, each slab with its own
                colour and pattern. Choose yours for countertops, feature walls,
                backlit installations and furniture, then send it straight into
                an inquiry.
            </Story>
        </>
    );
}

/** The latest finished projects, each opening its public profile. */
export function LandingProjects({ projects }: { projects: LandingProject[] }) {
    if (projects.length === 0) {
        return null;
    }

    return (
        <section
            id="projects"
            aria-labelledby="landing-projects-heading"
            className={`scroll-mt-20 pb-[clamp(5rem,9vw,8rem)] ${frame}`}
        >
            <div className="flex flex-col items-center gap-4 pb-[clamp(2.5rem,5vw,4rem)] text-center">
                <h2 id="landing-projects-heading" className={capsTitle}>
                    Featured projects
                </h2>
                <p className="text-[0.9375rem] text-muted-foreground">
                    Spaces we have finished in stone, across the Philippines.
                </p>
            </div>
            <ul className="grid grid-cols-1 gap-4 min-[700px]:gap-7 lg:grid-cols-2">
                {projects.map((project) => (
                    <li key={project.slug}>
                        <Link
                            href={projectShow.url({ slug: project.slug })}
                            className="group relative block aspect-4/5 overflow-hidden bg-night text-white min-[700px]:aspect-square"
                        >
                            <img
                                src={project.thumbnail}
                                alt=""
                                loading="lazy"
                                className={`absolute inset-0 size-full object-cover ${zoom}`}
                            />
                            <div
                                aria-hidden
                                className="absolute inset-0 bg-black/30"
                            />
                            <span className="absolute inset-x-6 top-1/2 flex -translate-y-1/2 flex-col items-center gap-2 text-center">
                                <span className="font-display text-[clamp(1.25rem,1.8vw,1.75rem)] tracking-[0.02em] uppercase">
                                    {project.name}
                                </span>
                                <span className="text-sm text-white/85">
                                    {project.location} · {project.completedYear}
                                </span>
                            </span>
                            <span className="absolute bottom-[clamp(1.25rem,2.4vw,2rem)] left-1/2 -translate-x-1/2 border-b border-white px-3 pb-2.5 text-[0.8125rem] font-semibold tracking-[0.04em] uppercase">
                                Discover
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

const STEPS = ['Inquire', 'Quote', 'Pay', 'Install'];

/** What a client does on the platform, from inquiry to installation. */
export function LandingClients() {
    return (
        <section
            id="clients"
            aria-labelledby="landing-clients-heading"
            className="flex scroll-mt-20 flex-col items-center gap-7 px-5 pb-[clamp(5rem,9vw,8rem)] text-center"
        >
            <h2 id="landing-clients-heading" className={title}>
                For clients: from inquiry to installation
            </h2>
            <p className={story}>
                Send one inquiry. Our team visits and measures, sends a
                quotation, then produces, delivers and installs. You follow
                every step, payment and message in your Regray account, and we
                email you as each one moves.
            </p>
            <ol className="mt-5 grid w-full max-w-240 grid-cols-2 gap-8 sm:grid-cols-4">
                {STEPS.map((step, index) => (
                    <li
                        key={step}
                        className="flex flex-col items-center gap-2.5 border-t border-foreground pt-5"
                    >
                        <span className="text-xs font-semibold tracking-[0.08em] text-muted-foreground tabular-nums">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <span className="font-display text-[1.0625rem] tracking-[0.04em] uppercase">
                            {step}
                        </span>
                    </li>
                ))}
            </ol>
            <div className="mt-5 flex flex-wrap justify-center gap-4 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase">
                <Link
                    href={inquire.url()}
                    className="bg-foreground px-6.5 py-4 text-background transition-colors hover:bg-foreground/80"
                >
                    Send an inquiry
                </Link>
                <Link
                    href={register.url()}
                    className="border border-foreground px-6.5 py-3.75 transition-colors hover:bg-foreground hover:text-background"
                >
                    Create an account
                </Link>
            </div>
        </section>
    );
}

/**
 * What each brand's catalog holds, by slug, under its card. A brand not
 * listed reads "{name} collection".
 */
const CATALOG_LABELS: Record<string, string> = {
    'regray-stone': 'Regray Stone collection',
    cucine: 'Cucine Lube kitchens',
    ascale: 'Ascale sintered stone',
    faer: 'Faer Ambienti interiors',
    reform: 'Reform kitchens',
    'res-italia': 'RES Italia doors',
};

/**
 * Every brand's collection as a row of tall cards that scrolls sideways,
 * running off the right edge of the page, beside the section's title. A
 * trackpad, a touch screen or the keyboard scroll it; the arrows move it a
 * card at a time for a mouse.
 */
export function LandingCatalogs({ brands }: { brands: LandingBrand[] }) {
    const row = useRef<HTMLUListElement>(null);
    const [edges, setEdges] = useState({ atStart: true, atEnd: false });

    useEffect(() => {
        const element = row.current;

        if (!element) {
            return;
        }

        const measure = () =>
            setEdges({
                atStart: element.scrollLeft <= 1,
                atEnd:
                    element.scrollLeft + element.clientWidth >=
                    element.scrollWidth - 1,
            });

        measure();
        element.addEventListener('scroll', measure, { passive: true });
        window.addEventListener('resize', measure);

        return () => {
            element.removeEventListener('scroll', measure);
            window.removeEventListener('resize', measure);
        };
    }, [brands.length]);

    if (brands.length === 0) {
        return null;
    }

    /** Move the row one card along, either way. */
    const step = (direction: 1 | -1) => {
        const element = row.current;
        const card = element?.querySelector('li');

        if (!element || !card) {
            return;
        }

        const gap = parseFloat(getComputedStyle(element).columnGap) || 0;

        element.scrollBy({
            left: direction * (card.getBoundingClientRect().width + gap),
            behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        });
    };

    const arrow =
        'flex size-11 items-center justify-center rounded-full border border-foreground transition-colors hover:bg-foreground hover:text-background disabled:pointer-events-none disabled:opacity-25';

    return (
        <section
            id="catalogs"
            aria-labelledby="landing-catalogs-heading"
            className="grid scroll-mt-20 grid-cols-1 gap-y-8 pb-[clamp(5rem,9vw,8rem)] pl-4 sm:pl-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
        >
            <div className="flex flex-col items-center justify-between gap-6 pr-4 text-center sm:pr-8 lg:pr-0">
                <div className="flex flex-col items-center gap-4 lg:flex-1 lg:justify-center">
                    <h2 id="landing-catalogs-heading" className={capsTitle}>
                        Catalogs
                    </h2>
                    <p className="max-w-64 text-[0.9375rem] text-muted-foreground">
                        Browse each house&rsquo;s stones, furniture and finished
                        projects.
                    </p>
                    <div className="mt-2 flex gap-3">
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            disabled={edges.atStart}
                            aria-label="Previous catalogs"
                            aria-controls="landing-catalog-row"
                            className={arrow}
                        >
                            <ChevronLeft aria-hidden className="size-4" />
                        </button>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            disabled={edges.atEnd}
                            aria-label="Next catalogs"
                            aria-controls="landing-catalog-row"
                            className={arrow}
                        >
                            <ChevronRight aria-hidden className="size-4" />
                        </button>
                    </div>
                </div>
                <a href="#brands" className={textLink}>
                    Discover
                </a>
            </div>

            <ul
                ref={row}
                id="landing-catalog-row"
                aria-label="Catalogs"
                tabIndex={0}
                className="flex snap-x snap-mandatory [scrollbar-width:none] gap-4 overflow-x-auto overscroll-x-contain pr-4 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-foreground motion-safe:scroll-smooth min-[700px]:gap-8 sm:pr-8 [&::-webkit-scrollbar]:hidden"
            >
                {brands.map((brand) => (
                    <li
                        key={brand.slug}
                        className="w-[min(78vw,20rem)] shrink-0 snap-start lg:w-[clamp(16rem,23vw,27rem)]"
                    >
                        <CatalogCard brand={brand} />
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** One brand's catalog: its photo with its logo, and what it holds. */
const CatalogCard = ({ brand }: { brand: LandingBrand }) => {
    const { slug } = brand;
    const label = CATALOG_LABELS[slug] ?? `${brand.name} collection`;
    // Regray Stone's photo is already bright enough under its white logo.
    const dim = slug === 'regray-stone' ? 'bg-black/15' : 'bg-black/35';

    return (
        <Link href={brandUrl(slug)} className="group flex flex-col gap-4">
            <div className="relative aspect-3/4 overflow-hidden bg-night">
                {brand.thumbnail ? (
                    <img
                        src={brand.thumbnail}
                        alt=""
                        loading="lazy"
                        className={`absolute inset-0 size-full object-cover ${zoom}`}
                    />
                ) : null}
                <div aria-hidden className={`absolute inset-0 ${dim}`} />
                <img
                    src={brand.logo}
                    alt={brand.name}
                    className="absolute top-[42%] left-1/2 max-w-[70%] -translate-x-1/2 -translate-y-1/2 object-contain"
                    style={{
                        height: `calc(26px * ${(LOGO_SCALE[slug] ?? DEFAULT_LOGO_SCALE) * 0.8})`,
                    }}
                />
            </div>
            <span className="flex items-center justify-center gap-2 text-center text-[0.8125rem] tracking-[0.04em] uppercase">
                {label}
                <ArrowUpRight
                    aria-hidden
                    className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
                />
            </span>
        </Link>
    );
};

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/** The showroom: its photo, where it is and when it is open. */
export function LandingShowroom() {
    return (
        <>
            <div id="showroom" className={`scroll-mt-20 ${frame}`}>
                <a
                    href={SHOWROOM_MAP}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Get directions to the Regray showroom (opens Google Maps)"
                    className="relative block aspect-4/3 overflow-hidden bg-night text-white min-[700px]:aspect-[21/9]"
                >
                    <img
                        src="/images/regray-showroom.jpg"
                        alt=""
                        loading="lazy"
                        className="size-full object-cover object-[center_60%]"
                    />
                    <div aria-hidden className="absolute inset-0 bg-black/30" />
                    <span className="absolute inset-x-6 top-1/2 -translate-y-1/2 text-center font-display text-[clamp(1.375rem,2.2vw,2.125rem)] tracking-[0.02em] uppercase">
                        The Regray showroom, Makati
                    </span>
                </a>
            </div>
            <Story
                heading="See the stone in person"
                links={
                    <>
                        <Link href={book.url()} className={textLink}>
                            Book a site visit
                        </Link>
                        <a
                            href={SHOWROOM_MAP}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={textLink}
                        >
                            Get directions
                        </a>
                    </>
                }
            >
                {SHOWROOM.address}. {SHOWROOM.hours}. Or book a site visit and
                our technical team comes to you.
            </Story>
        </>
    );
}

import { Link } from '@inertiajs/react';
import {
    body,
    frame,
    label,
    outlineButton,
    solidButton,
    textLink,
} from '@/components/public/company-ui';
import {
    BRAND_LOGO_HEIGHT,
    DEFAULT_BRAND_LOGO_HEIGHT,
    SHOWROOM,
    SHOWROOM_MAP,
    brandUrl,
    collectionUrl,
} from '@/components/public/public-links';
import {
    about,
    brands as brandsPage,
    contact,
    news,
    showroom,
} from '@/routes/company';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import { show as projectShow } from '@/routes/stone';
import type { LandingBrand, LandingProject } from '@/types/home';

/** A section's title: spaced capitals in the heading face. */
const sectionTitle =
    'font-display text-[clamp(1.625rem,2.4vw,2.375rem)] font-light tracking-[0.1em] uppercase';

/** A section's opening paragraph. */
const lead =
    'max-w-190 text-[clamp(1.125rem,1.3vw,1.25rem)] leading-[1.65] text-pretty';

/** A photo that eases in a little on hover. */
const zoom =
    'transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none';

/** A row of the contact list. */
const contactRow =
    'flex items-center justify-between gap-4 py-5.5 font-display text-[clamp(1.1875rem,1.5vw,1.375rem)] transition-[padding] duration-300 hover:pl-2.5 motion-reduce:transition-none';

/**
 * 01. The full-screen opening: the Regray Group and its line over the photo,
 * and the arrow down to Discover.
 */
export function LandingHero() {
    return (
        <section aria-label="The Regray Group" className={frame}>
            <div className="relative h-[calc(100svh-8.5rem)] min-h-115 overflow-hidden rounded-md bg-[#d8d4cd] text-white">
                <img
                    src="/images/regray-hero.jpg"
                    alt="A living room with a book-matched marble wall and a travertine table"
                    fetchPriority="high"
                    className="size-full object-cover object-[center_40%]"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0)_45%,rgb(0_0_0/0.55)_100%)]"
                />
                <div className="absolute inset-x-[clamp(1.25rem,4vw,4rem)] bottom-[clamp(1.75rem,5vw,4rem)] flex max-w-205 flex-col gap-3.5">
                    <p className={label}>The Regray Group</p>
                    <h1 className="font-display text-[clamp(2rem,4vw,3.75rem)] leading-[1.08] font-light tracking-[-0.005em] text-balance">
                        Natural stone, kitchens and interiors for your home.
                    </h1>
                </div>
            </div>
            <a
                href="#discover"
                aria-label="Scroll to Discover Regray"
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

/** 01. Regray as a group: what it brings together, and its story. */
export function LandingDiscover() {
    return (
        <section
            id="discover"
            aria-labelledby="landing-discover"
            className="flex scroll-mt-20 flex-col items-center gap-7 px-5 pt-[clamp(3.5rem,7vw,6.5rem)] pb-[clamp(5rem,9vw,8.5rem)] text-center"
        >
            <h2 id="landing-discover" className={sectionTitle}>
                Discover Regray
            </h2>
            <p className={lead}>
                Regray brings its own Regray Stone and trusted European brands
                together in one place. One team helps you choose, measure and
                install, from the first idea to the finished room.
            </p>
            <Link href={about.url()} className={`mt-3 ${textLink}`}>
                Our story
            </Link>
        </section>
    );
}

/** 02. The brands as a wall of logos on navy, each opening its page. */
export function LandingBrandWall({ brands }: { brands: LandingBrand[] }) {
    return (
        <section
            id="brands"
            aria-labelledby="landing-brands"
            className={`flex scroll-mt-20 flex-col items-center gap-[clamp(3rem,6vw,5.5rem)] bg-navy pt-[clamp(4.5rem,8vw,7.5rem)] pb-[clamp(5.5rem,9vw,8.5rem)] text-center text-white ${frame}`}
        >
            <div className="flex flex-col items-center gap-[clamp(1.75rem,3vw,2.75rem)]">
                <h2 id="landing-brands" className={sectionTitle}>
                    Our brands
                </h2>
                <p className={lead}>
                    Stone, kitchens, wardrobes, doors and furniture, each from a
                    brand that specialises in it.
                </p>
            </div>
            {brands.length > 0 ? (
                <ul className="grid w-full max-w-330 grid-cols-2 items-center gap-x-[clamp(1.5rem,3vw,3rem)] gap-y-[clamp(2.5rem,5vw,4.5rem)] [--wall:0.6] md:grid-cols-3 md:[--wall:0.85] lg:grid-cols-6 lg:[--wall:1]">
                    {brands.map((brand) => (
                        <li
                            key={brand.slug}
                            className="flex min-w-0 justify-center"
                        >
                            <Link
                                href={brandUrl(brand.slug)}
                                title={brand.name}
                                className="flex min-h-24 w-full items-center justify-center opacity-90 transition-[opacity,transform] duration-300 hover:-translate-y-0.75 hover:opacity-100 motion-reduce:transition-none"
                            >
                                <img
                                    src={brand.logo}
                                    alt={brand.name}
                                    loading="lazy"
                                    className="max-w-full object-contain"
                                    style={{
                                        // Full size on laptops, scaled down on tablets and phones.
                                        height: `calc(${BRAND_LOGO_HEIGHT[brand.slug] ?? DEFAULT_BRAND_LOGO_HEIGHT}px * var(--wall))`,
                                    }}
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : null}
            <Link href={brandsPage.url()} className={textLink}>
                All brands
            </Link>
        </section>
    );
}

/** The three steps of working with Regray, each with a photo of it. */
const ONLINE_STEPS = [
    {
        title: 'Choose what you like',
        text: 'Look through our stones, kitchens and furniture online, or see them at our showroom.',
        image: '/images/regray-slabs.jpg',
        alt: 'A wall of book-matched quartzite slabs',
    },
    {
        title: 'Get your quotation',
        text: 'Tell us what you need. We visit to measure your space, then send you a clear price.',
        image: '/images/regray-kitchen.jpg',
        alt: 'A modern kitchen with a marble backsplash and an island',
    },
    {
        title: 'We install it for you',
        text: 'Our team delivers and installs everything, and keeps you updated by email along the way.',
        image: '/images/regray-stone-kitchen.jpg',
        alt: 'A finished kitchen with a marble island and shelves',
    },
];

/**
 * 03. How working with Regray goes, in three plain steps with a photo each,
 * then the two ways to start: browse the collection, or ask for a quotation.
 */
export function LandingOnline() {
    return (
        <section
            id="online"
            aria-labelledby="landing-online"
            className={`flex scroll-mt-20 flex-col items-center gap-[clamp(2.5rem,5vw,4rem)] py-[clamp(5rem,9vw,8.5rem)] ${frame}`}
        >
            <div className="flex flex-col items-center gap-5 text-center">
                <p className={`${label} text-muted-foreground`}>How it works</p>
                <h2
                    id="landing-online"
                    className="max-w-200 font-display text-[clamp(1.75rem,2.6vw,2.5rem)] leading-[1.15] font-light text-balance"
                >
                    From choosing to installing, we take care of it
                </h2>
            </div>
            <ul className="grid w-full max-w-330 grid-cols-1 gap-x-7 gap-y-12 md:grid-cols-3">
                {ONLINE_STEPS.map((step) => (
                    <li key={step.title} className="flex flex-col gap-5">
                        <div className="aspect-4/3 overflow-hidden rounded-md bg-[#d8d4cd]">
                            <img
                                src={step.image}
                                alt={step.alt}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <h3 className="font-display text-[clamp(1.25rem,1.6vw,1.5rem)]">
                                {step.title}
                            </h3>
                            <p className={`text-muted-foreground ${body}`}>
                                {step.text}
                            </p>
                        </div>
                    </li>
                ))}
            </ul>
            <div className="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
                <Link href={collectionUrl('stones')} className={solidButton}>
                    See our collection
                </Link>
                <Link href={inquire.url()} className={outlineButton}>
                    Request a quotation
                </Link>
            </div>
        </section>
    );
}

/** 04. The showroom: a full-height photo, then where it is and the ways in. */
export function LandingShowroom() {
    return (
        <section
            id="showroom"
            aria-labelledby="landing-showroom"
            className="scroll-mt-20"
        >
            <div className={frame}>
                <Link
                    href={showroom.url()}
                    className="group relative block h-[calc(100svh-5rem)] min-h-115 overflow-hidden rounded-md bg-night text-white"
                >
                    <img
                        src="/images/regray-showroom.jpg"
                        alt=""
                        loading="lazy"
                        className={`size-full object-cover object-[center_60%] ${zoom}`}
                    />
                    <div aria-hidden className="absolute inset-0 bg-black/30" />
                    <span className="absolute inset-x-6 top-1/2 flex -translate-y-1/2 flex-col items-center gap-4 text-center">
                        <span className={label}>Showroom</span>
                        <span
                            id="landing-showroom"
                            className="font-display text-[clamp(1.875rem,3.6vw,3.5rem)] font-light tracking-[0.02em] text-balance"
                        >
                            Experience every brand in person
                        </span>
                    </span>
                </Link>
            </div>
            <div className="flex flex-col items-center gap-7 px-5 pt-[clamp(3.5rem,7vw,6rem)] pb-[clamp(5rem,9vw,8rem)] text-center">
                <p className={lead}>
                    {SHOWROOM.address}. {SHOWROOM.hours}.
                </p>
                <div className="mt-2 flex flex-wrap justify-center gap-x-10 gap-y-4">
                    <Link href={book.url()} className={textLink}>
                        Book a visit
                    </Link>
                    <a href={`${showroom.url()}#gallery`} className={textLink}>
                        Gallery
                    </a>
                    <a
                        href={SHOWROOM_MAP}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={textLink}
                    >
                        Get directions
                    </a>
                </div>
            </div>
        </section>
    );
}

/** 05. The latest from Regray: its newest finished projects, each a story. */
export function LandingNews({ stories }: { stories: LandingProject[] }) {
    if (stories.length === 0) {
        return null;
    }

    return (
        <section
            id="news"
            aria-labelledby="landing-news"
            className={`scroll-mt-20 pb-[clamp(5rem,9vw,8.5rem)] ${frame}`}
        >
            <div className="flex flex-col items-center gap-5 pb-[clamp(2.5rem,5vw,4rem)] text-center">
                <h2 id="landing-news" className={sectionTitle}>
                    News
                </h2>
                <Link href={news.url()} className={textLink}>
                    All news
                </Link>
            </div>
            <ul className="grid grid-cols-1 gap-x-4 gap-y-12 min-[700px]:gap-x-7 sm:grid-cols-2 lg:grid-cols-3">
                {stories.map((story) => (
                    <li key={story.slug}>
                        <StoryCard story={story} />
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** A finished project told as news: its photo, the year it was finished, its name and where. */
export const StoryCard = ({ story }: { story: LandingProject }) => (
    <Link
        href={projectShow.url({ slug: story.slug })}
        className="group flex flex-col gap-4.5"
    >
        <div className="aspect-4/3 overflow-hidden rounded-md bg-[#d8d4cd]">
            <img
                src={story.thumbnail}
                alt=""
                loading="lazy"
                className={`size-full object-cover ${zoom}`}
            />
        </div>
        <span className="text-[0.9375rem] text-muted-foreground">
            Finished in {story.completedYear}
        </span>
        <span className="font-display text-[clamp(1.1875rem,1.5vw,1.375rem)] leading-snug text-pretty">
            {story.name}, {story.location}
        </span>
    </Link>
);

/** 06. How to reach the team, by what the question is about. */
export function LandingContact() {
    const links = [
        { label: 'General inquiries', href: `${contact.url()}#general` },
        { label: 'Sales & quotations', href: inquire.url() },
        {
            label: 'Architect & designer partnerships',
            href: `${contact.url()}#trade`,
        },
        {
            label: 'Supplier & business inquiries',
            href: `${contact.url()}#business`,
        },
    ];

    return (
        <section
            id="contact"
            aria-labelledby="landing-contact"
            className={`grid scroll-mt-20 grid-cols-1 gap-[clamp(2.5rem,5vw,6rem)] border-t border-border py-[clamp(4.5rem,8vw,7.5rem)] min-[960px]:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] ${frame}`}
        >
            <div className="flex flex-col gap-6">
                <h2 id="landing-contact" className={sectionTitle}>
                    Contact
                </h2>
                <p className={`max-w-110 ${body}`}>
                    Connect with the Regray team. Tell us what you are working
                    on and the right person will reply.
                </p>
            </div>
            <ul className="border-t border-foreground">
                {links.map((link) => (
                    <li key={link.label} className="border-b border-border">
                        {link.href.includes('#') ? (
                            <a href={link.href} className={contactRow}>
                                <span>{link.label}</span>
                                <span aria-hidden>→</span>
                            </a>
                        ) : (
                            <Link href={link.href} className={contactRow}>
                                <span>{link.label}</span>
                                <span aria-hidden>→</span>
                            </Link>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}

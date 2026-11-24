import { Link } from '@inertiajs/react';
import {
    frame,
    label,
    outlineButton,
    textLink,
} from '@/components/public/company-ui';
import {
    BRAND_LOGO_HEIGHT,
    DEFAULT_BRAND_LOGO_HEIGHT,
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
import { register } from '@/routes';
import { show as showcaseShow } from '@/routes/stone';
import type { LandingBrand, LandingShowcase } from '@/types/home';

/** A section's title: spaced capitals in the heading face. */
const sectionTitle =
    'font-display text-[clamp(1.625rem,2.4vw,2.375rem)] font-light tracking-[0.1em] uppercase';

/** A section's opening paragraph. */
const lead =
    'max-w-190 text-[clamp(1.125rem,1.3vw,1.25rem)] leading-[1.65] text-pretty';

/** A photo that eases in a little on hover. */
const zoom =
    'transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none';

/**
 * 01. The full-screen opening: the Regray Group and its line over the photo,
 * and the arrow down to Discover. The photo is a wall-cladding project Regray
 * did (backlit stone either side of a travertine fireplace), not a generated
 * one.
 */
export function LandingHero() {
    return (
        <section aria-label="The Regray Group" className={frame}>
            <div className="relative h-[calc(100svh-8.5rem)] min-h-115 overflow-hidden rounded-md bg-[#d8d4cd] text-white">
                <img
                    src="/images/regray-wall-cladding.jpg"
                    alt="A living room wall clad in backlit stone, either side of a travertine fireplace"
                    fetchPriority="high"
                    className="size-full object-cover object-[center_45%]"
                />
                {/* The floor and sofa under the headline are light, so the shade is deeper there than on the stone above. */}
                <div
                    aria-hidden
                    className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0)_30%,rgb(0_0_0/0.72)_100%),linear-gradient(90deg,rgb(0_0_0/0.35)_0%,rgb(0_0_0/0)_60%)]"
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

/**
 * 03. Regray Online, as one poster: a navy panel with a short, plain line on
 * what a client can do from home, and the way in (sign up, or look at the
 * stones first), beside a photo of a finished stone kitchen. Written for
 * clients who are not used to websites: everyday words, short sentences, big
 * text and buttons. Beside the photo from a laptop; under it on a phone.
 */
export function LandingOnline() {
    return (
        <section
            id="online"
            aria-labelledby="landing-online"
            className={`scroll-mt-20 py-[clamp(5rem,9vw,8.5rem)] ${frame}`}
        >
            <div className="grid overflow-hidden rounded-md bg-navy text-white lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
                <div className="aspect-4/3 overflow-hidden lg:order-last lg:aspect-auto">
                    <img
                        src="/images/regray-stone-kitchen.jpg"
                        alt="A finished kitchen with a marble island and shelves"
                        loading="lazy"
                        className="size-full object-cover"
                    />
                </div>
                <div className="flex flex-col items-start gap-6 px-[clamp(1.5rem,4.5vw,4.5rem)] py-[clamp(2.5rem,6vw,5.5rem)]">
                    <p className={`${label} text-white/75`}>Regray Online</p>
                    <h2
                        id="landing-online"
                        className="font-display text-[clamp(2rem,3.4vw,3.25rem)] leading-[1.1] font-light text-balance"
                    >
                        See our stones from home
                    </h2>
                    <p className="max-w-130 text-[clamp(1.125rem,1.4vw,1.3125rem)] leading-relaxed text-pretty text-white/90">
                        Look at our stones, kitchens and furniture on your phone
                        or computer. When you find something you like, send us a
                        request and our team will help you with the rest.
                    </p>
                    <div className="mt-2 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                        <Link
                            href={register.url()}
                            className="inline-flex min-h-14 items-center justify-center rounded-md bg-white px-8 py-3.5 text-[1.0625rem] font-semibold text-black transition-colors hover:bg-white/85"
                        >
                            Sign up for free
                        </Link>
                        <Link
                            href={collectionUrl('stones')}
                            className="inline-flex min-h-14 items-center justify-center rounded-md border border-white px-8 py-3.5 text-[1.0625rem] font-semibold transition-colors hover:bg-white hover:text-black"
                        >
                            See our stones
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}

/**
 * 04. The showroom, promoted: one large photo with a single message and a
 * single way in, the showroom's page (where to find it, the gallery and
 * booking a visit).
 */
export function LandingShowroom() {
    return (
        <section
            id="showroom"
            aria-labelledby="landing-showroom"
            className={`scroll-mt-20 pb-[clamp(5rem,9vw,8.5rem)] ${frame}`}
        >
            <div className="relative flex h-[min(calc(100svh-5rem),52rem)] min-h-115 flex-col items-center justify-center overflow-hidden rounded-md bg-night px-6 text-center text-white">
                <img
                    src="/images/regray-showroom.jpg"
                    alt="The Regray showroom in Makati, with stone slabs on display"
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover object-[center_60%]"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgb(0_0_0/0.62)_0%,rgb(0_0_0/0.5)_45%,rgb(0_0_0/0.3)_100%)]"
                />
                <div className="relative flex max-w-170 flex-col items-center gap-5">
                    <p className={label}>Our showroom in Makati</p>
                    <h2
                        id="landing-showroom"
                        className="font-display text-[clamp(2rem,3.8vw,3.5rem)] leading-[1.1] font-light text-balance"
                    >
                        Come and see it in person
                    </h2>
                    <p className="text-[clamp(1.125rem,1.4vw,1.3125rem)] leading-relaxed text-pretty text-white/90">
                        Touch the stone, open the kitchens and talk to our team.
                        Open every day, 10AM to 6PM.
                    </p>
                    <Link
                        href={showroom.url()}
                        className="mt-3 inline-flex min-h-13 items-center justify-center rounded-md bg-white px-8 py-3.5 text-base font-semibold text-black transition-colors hover:bg-white/85"
                    >
                        Plan your visit
                    </Link>
                </div>
            </div>
        </section>
    );
}

/**
 * 05. News, as one general photo beside a short line and the way to the News
 * page, where the finished showcases are told one by one. One photo rather
 * than a row of stories, whose photos and titles never line up: three marble
 * tiles floating on grey (a portrait photo, cropped around the tiles). Side
 * by side from a laptop, the photo on top on a phone.
 */
export function LandingNews() {
    return (
        <section
            id="news"
            aria-labelledby="landing-news"
            className={`grid scroll-mt-20 items-center gap-[clamp(2.25rem,5vw,5.5rem)] pb-[clamp(5rem,9vw,8.5rem)] md:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] ${frame}`}
        >
            <Link
                href={news.url()}
                tabIndex={-1}
                aria-hidden
                className="group block aspect-4/3 overflow-hidden rounded-md bg-[#d9d9d9] md:aspect-5/4"
            >
                <img
                    src="/images/regray-marble-tiles.jpg"
                    alt=""
                    loading="lazy"
                    className={`size-full object-cover object-[center_60%] ${zoom}`}
                />
            </Link>
            <div className="flex flex-col items-center gap-6 text-center md:items-start md:text-left">
                <h2 id="landing-news" className={sectionTitle}>
                    News
                </h2>
                <p className={lead}>
                    Homes and spaces we have recently finished across the
                    Philippines.
                </p>
                <Link href={news.url()} className={`mt-2 ${outlineButton}`}>
                    All news
                </Link>
            </div>
        </section>
    );
}

/** A finished showcase told as news: its photo, the year it was finished, its name and where. */
export const StoryCard = ({ story }: { story: LandingShowcase }) => (
    <Link
        href={showcaseShow.url({ slug: story.slug })}
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

/**
 * 06. Contact, kept short: a photo, one plain line and the way to the
 * Contact page, which holds the details (each kind of question, the
 * showroom, its hours and numbers). The photo is a lounge Regray finished (a
 * curved sofa, a travertine table and a backlit stone shelf): a place to sit
 * and talk, cropped from a portrait photo to keep all three. It mirrors News
 * above it, the text first and the photo second from a laptop, and the photo
 * on top on a phone.
 */
export function LandingContact() {
    return (
        <section
            id="contact"
            aria-labelledby="landing-contact"
            className={`grid scroll-mt-20 items-center gap-[clamp(2.25rem,5vw,5.5rem)] border-t border-border py-[clamp(4.5rem,8vw,7.5rem)] md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] ${frame}`}
        >
            <Link
                href={contact.url()}
                tabIndex={-1}
                aria-hidden
                className="group block aspect-4/3 overflow-hidden rounded-md bg-night md:order-last md:aspect-5/4"
            >
                <img
                    src="/images/regray-lounge.jpg"
                    alt=""
                    loading="lazy"
                    className={`size-full object-cover object-[center_65%] ${zoom}`}
                />
            </Link>
            <div className="flex flex-col items-center gap-6 text-center md:items-start md:text-left">
                <h2 id="landing-contact" className={sectionTitle}>
                    Contact
                </h2>
                <p className={lead}>
                    Have a question about a stone, a project or a visit? Our
                    team is happy to help.
                </p>
                <Link href={contact.url()} className={`mt-2 ${outlineButton}`}>
                    Contact us
                </Link>
            </div>
        </section>
    );
}

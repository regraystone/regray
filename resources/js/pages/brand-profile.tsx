import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import BrandCollection from '@/components/home/brand-collection';
import {
    BRAND_LOGO_HEIGHT,
    DEFAULT_BRAND_LOGO_HEIGHT,
} from '@/components/public/public-links';
import SocialIcon, { SOCIAL_LABELS } from '@/components/social-icon';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { collectionQuery } from '@/lib/brand-collection';
import { login } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { brands as brandsPage, showroom } from '@/routes/company';
import {
    index as dashboardBrandsIndex,
    show as dashboardBrandShow,
} from '@/routes/dashboard/brands';
import { book } from '@/routes/profile/schedule';
import type { BrandOffering, BrandPageProps, LandingBrand } from '@/types/home';

/** A section's title: spaced capitals in the heading face. */
const sectionTitle =
    'font-display text-[clamp(1.5rem,2vw,2rem)] font-light tracking-[0.06em] uppercase';

/** A small spaced label. */
const eyebrow = 'text-[0.8125rem] tracking-[0.14em] uppercase';

/** A square button on the photo or on navy. */
const lightButton =
    'flex h-12.5 items-center justify-center gap-2.5 border border-white text-[0.8125rem] font-semibold tracking-[0.08em] text-white uppercase transition-colors hover:bg-white hover:text-foreground';

/** Where a section starts once the header and the section bar are above it. */
const sectionScroll = 'scroll-mt-40';

const plural = (count: number, one: string, many: string) =>
    `${count.toLocaleString()} ${count === 1 ? one : many}`;

/** How many of a type the brand has, in the type's own words. */
const offeringCount = (offering: BrandOffering) =>
    offering.collection === 'stones'
        ? plural(offering.count, 'stone', 'stones')
        : plural(offering.count, 'piece', 'pieces');

/** The section the reader is in: the last one whose top has passed under the bar. */
const useCurrentSection = (ids: string[]) => {
    const [current, setCurrent] = useState('');
    const key = ids.join(' ');

    useEffect(() => {
        const sections = key.split(' ');
        const update = () => {
            let found = '';

            for (const id of sections) {
                const element = document.getElementById(id);

                if (element && element.getBoundingClientRect().top <= 170) {
                    found = id;
                }
            }

            setCurrent(found);
        };

        update();
        window.addEventListener('scroll', update, { passive: true });

        return () => window.removeEventListener('scroll', update);
    }, [key]);

    return current;
};

/** A type the brand's products come in: its photo, name and count, opening it in the collection. */
const OfferingCard = ({
    offering,
    href,
}: {
    offering: BrandOffering;
    href: string;
}) => (
    <Link
        href={href}
        preserveScroll={false}
        className="group flex flex-col gap-3.5"
    >
        <span className="block aspect-4/5 overflow-hidden bg-muted">
            {offering.thumbnail ? (
                <img
                    src={offering.thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-full object-cover transition-transform duration-1400 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.04] motion-reduce:transition-none"
                />
            ) : null}
        </span>
        <span className="flex items-baseline justify-between gap-3">
            <span className="font-display text-[1.0625rem] font-normal tracking-[0.04em] uppercase">
                {offering.name}
            </span>
            <span className="text-[0.8125rem] whitespace-nowrap text-muted-foreground">
                {offeringCount(offering)}
            </span>
        </span>
        <span className="text-[0.8125rem] tracking-[0.04em] text-muted-foreground uppercase transition-colors group-hover:text-foreground">
            View in the collection →
        </span>
    </Link>
);

/** Another brand of the group, by its logo. */
const OtherBrand = ({ brand, href }: { brand: LandingBrand; href: string }) => (
    <li className="flex justify-center">
        <Link
            href={href}
            title={brand.name}
            className="flex min-h-18 w-full items-center justify-center opacity-85 transition-opacity duration-300 hover:opacity-100"
        >
            <img
                src={brand.logo}
                alt={brand.name}
                loading="lazy"
                className="max-w-full object-contain"
                style={{
                    height: `calc(${BRAND_LOGO_HEIGHT[brand.slug] ?? DEFAULT_BRAND_LOGO_HEIGHT}px * var(--other))`,
                }}
            />
        </Link>
    </li>
);

/*
 * A brand's page, for every brand: its photo with the logo and tagline, a bar
 * of its sections, then who it is, what it offers (each type its products
 * come in, counted from the catalog, or, before it has products online, what
 * it makes), its collection and the group's other brands. A section with nothing
 * real to show is left out, and so is its place in the bar.
 */
const BrandProfile = () => {
    const {
        brand: { data: brand },
        hasProducts,
        offer,
        otherBrands,
        auth,
    } = usePage<BrandPageProps & { auth: { user: unknown } }>().props;
    const isSignedIn = auth?.user != null;
    const inquireRoute = useInquireRoute();
    // Set on the admin's Brands page.
    const details = { facts: brand.facts ?? [], makes: brand.makes ?? [] };

    const brandHref = (slug: string, query?: Record<string, string>) =>
        isSignedIn
            ? dashboardBrandShow.url({ brand: slug }, { query })
            : brandsShow.url({ brand: slug }, { query });
    const allBrandsHref = isSignedIn
        ? dashboardBrandsIndex.url()
        : brandsPage.url();
    const quoteUrl = isSignedIn ? (inquireRoute?.url() ?? null) : login.url();
    const visitUrl = isSignedIn ? book.url() : `${showroom.url()}#book`;

    // The page's own frame, for the bands that run edge to edge.
    const bleed = isSignedIn
        ? '-mx-4 px-4 sm:-mx-5 sm:px-5 lg:-mx-8 lg:px-8'
        : '-mx-4 px-4 sm:-mx-8 sm:px-8';
    const barTop = isSignedIn ? 'top-14 lg:top-16' : 'top-20';

    const inCatalog = [
        offer.stones > 0 ? plural(offer.stones, 'stone', 'stones') : null,
        offer.furniture > 0
            ? plural(
                  offer.furniture,
                  'piece of furniture',
                  'pieces of furniture',
              )
            : null,
    ].filter((part): part is string => part !== null);
    // Its facts, every one set on the admin's Brands page, then the one line
    // counted live from its products.
    const facts = [
        ...details.facts,
        {
            label: 'In our catalog',
            value:
                inCatalog.length > 0
                    ? inCatalog.join(' · ')
                    : 'Coming online soon',
        },
    ];

    const sections = [
        { id: 'overview', label: 'Overview' },
        ...(offer.types.length > 0 || details.makes.length > 0
            ? [{ id: 'offer', label: 'What we offer' }]
            : []),
        ...(hasProducts ? [{ id: 'collection', label: 'Collection' }] : []),
    ];
    const current = useCurrentSection(sections.map((section) => section.id));

    return (
        <div className="w-full font-light">
            <Head title={brand.name} />

            <section aria-label={brand.name}>
                <div className="relative h-[72svh] min-h-105 overflow-hidden bg-night text-white sm:h-[calc(100svh-10.5rem)]">
                    {brand.thumbnail ? (
                        <img
                            src={brand.thumbnail}
                            alt=""
                            className="absolute inset-0 size-full object-cover"
                        />
                    ) : null}
                    <div
                        aria-hidden
                        className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0.25)_0%,rgb(0_0_0/0)_30%,rgb(0_0_0/0)_45%,rgb(0_0_0/0.6)_100%)]"
                    />
                    <nav
                        aria-label="Breadcrumb"
                        className="absolute top-[clamp(1.25rem,2.5vw,2.25rem)] left-[clamp(1.25rem,3vw,3rem)] text-xs tracking-[0.08em] uppercase"
                    >
                        <ol className="flex flex-wrap gap-2">
                            <li>
                                <Link
                                    href={allBrandsHref}
                                    className="hover:underline"
                                >
                                    ← {isSignedIn ? 'Brands' : 'Our brands'}
                                </Link>
                            </li>
                            <li aria-hidden>/</li>
                            <li aria-current="page">{brand.name}</li>
                        </ol>
                    </nav>
                    <div className="absolute inset-x-[clamp(1.25rem,3vw,3rem)] bottom-[clamp(1.75rem,4vw,3.5rem)] flex flex-wrap items-end justify-between gap-6">
                        <div className="flex max-w-180 flex-col gap-4.5">
                            <h1 className="leading-none [--logo:1.1] sm:[--logo:1.5]">
                                <img
                                    src={brand.logo}
                                    alt={brand.name}
                                    className="max-h-28 max-w-[80vw] object-contain object-left"
                                    style={{
                                        height: `calc(${BRAND_LOGO_HEIGHT[brand.slug] ?? DEFAULT_BRAND_LOGO_HEIGHT}px * var(--logo))`,
                                    }}
                                />
                            </h1>
                            {brand.tagline ? (
                                <p className="font-display text-[clamp(1.125rem,1.6vw,1.5rem)] leading-[1.35] font-light text-balance">
                                    {brand.tagline}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex flex-wrap items-center gap-2.5">
                            {brand.websiteUrl ? (
                                <a
                                    href={brand.websiteUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`${lightButton} px-5.5`}
                                >
                                    Visit website
                                    <ArrowUpRight
                                        className="size-4"
                                        aria-hidden
                                    />
                                    <span className="sr-only">
                                        (opens in a new tab)
                                    </span>
                                </a>
                            ) : null}
                            {brand.socials.map((social) => (
                                <a
                                    key={social.platform}
                                    href={social.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label={`${brand.name} on ${SOCIAL_LABELS[social.platform]} (opens in a new tab)`}
                                    title={`${brand.name} on ${SOCIAL_LABELS[social.platform]}`}
                                    className={`${lightButton} w-12.5`}
                                >
                                    <SocialIcon platform={social.platform} />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            <nav
                aria-label="On this page"
                className={`sticky ${barTop} z-20 border-y border-border bg-background/95 backdrop-blur-md ${bleed}`}
            >
                <div className="flex items-center justify-between gap-6">
                    <span className="hidden flex-none font-display text-sm font-normal tracking-[0.1em] uppercase lg:block">
                        {brand.name}
                    </span>
                    <ul className="flex [scrollbar-width:none] items-stretch gap-[clamp(1.125rem,2.6vw,2.25rem)] overflow-x-auto text-xs tracking-[0.1em] uppercase">
                        {sections.map((section) => (
                            <li key={section.id} className="flex">
                                <a
                                    href={`#${section.id}`}
                                    aria-current={
                                        current === section.id
                                            ? 'true'
                                            : undefined
                                    }
                                    className={`flex h-14 items-center whitespace-nowrap transition-[color,box-shadow] hover:text-foreground ${
                                        current === section.id
                                            ? 'font-semibold text-foreground shadow-[inset_0_-2px_0_currentColor]'
                                            : 'text-muted-foreground'
                                    }`}
                                >
                                    {section.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                    {quoteUrl ? (
                        <Link
                            href={quoteUrl}
                            className="hidden h-9.5 flex-none items-center bg-foreground px-4 text-xs font-semibold tracking-[0.08em] text-background uppercase transition-colors hover:bg-foreground/80 lg:flex"
                        >
                            Request a quote
                        </Link>
                    ) : null}
                </div>
            </nav>

            <section
                id="overview"
                className={`grid gap-[clamp(2.5rem,6vw,7.5rem)] py-[clamp(4rem,8vw,7.5rem)] lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] ${sectionScroll}`}
            >
                <div className="flex max-w-190 flex-col gap-6">
                    <p className={`${eyebrow} text-muted-foreground`}>
                        About {brand.name}
                    </p>
                    <p className="font-display text-[clamp(1.5rem,2.3vw,2.25rem)] leading-[1.3] font-light text-pretty">
                        {brand.description ?? brand.tagline}
                    </p>
                    {brand.websiteUrl ? (
                        <a
                            href={brand.websiteUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="self-start border-b border-current pb-1 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70"
                        >
                            {new URL(brand.websiteUrl).hostname.replace(
                                /^www\./,
                                '',
                            )}
                            <ArrowUpRight
                                className="ml-1 inline size-3.5 align-[-0.125em]"
                                aria-hidden
                            />
                            <span className="sr-only">
                                {' '}
                                (opens in a new tab)
                            </span>
                        </a>
                    ) : null}
                </div>
                <dl className="self-start border-t border-foreground">
                    {facts.map((fact) => (
                        <div
                            key={fact.label}
                            className="grid grid-cols-[8.75rem_1fr] gap-4 border-b border-border py-4"
                        >
                            <dt className="pt-0.5 text-xs tracking-[0.1em] text-muted-foreground uppercase">
                                {fact.label}
                            </dt>
                            <dd className="text-base leading-normal">
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            {offer.types.length > 0 ? (
                <section
                    id="offer"
                    aria-labelledby="brand-offer"
                    className={`pb-[clamp(4.5rem,8vw,7.5rem)] ${sectionScroll}`}
                >
                    <h2 id="brand-offer" className={`mb-7 ${sectionTitle}`}>
                        What we offer
                    </h2>
                    <div className="grid grid-cols-2 gap-x-4 gap-y-9 sm:grid-cols-3 sm:gap-x-6 xl:grid-cols-5">
                        {offer.types.map((offering) => (
                            <OfferingCard
                                key={`${offering.collection}-${offering.value}`}
                                offering={offering}
                                href={`${brandHref(
                                    brand.slug,
                                    collectionQuery({
                                        collection: offering.collection,
                                        search: '',
                                        filter: offering.value,
                                    }),
                                )}#collection`}
                            />
                        ))}
                    </div>
                </section>
            ) : details.makes.length > 0 ? (
                <section
                    id="offer"
                    aria-labelledby="brand-offer"
                    className={`grid gap-[clamp(2rem,5vw,6rem)] pb-[clamp(4.5rem,8vw,7.5rem)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] ${sectionScroll}`}
                >
                    <div className="flex flex-col gap-4">
                        <h2 id="brand-offer" className={sectionTitle}>
                            What we offer
                        </h2>
                        <p className="max-w-105 text-base leading-relaxed text-pretty text-muted-foreground">
                            The {brand.name} range isn&rsquo;t in our online
                            catalog yet. See it at our Makati showroom, or ask
                            the team for a quotation.
                        </p>
                        <div className="mt-2 flex flex-wrap gap-3">
                            <Link
                                href={visitUrl}
                                className="bg-foreground px-6 py-3.5 text-[0.8125rem] font-semibold tracking-[0.06em] text-background uppercase transition-colors hover:bg-foreground/80"
                            >
                                Book a visit
                            </Link>
                            {quoteUrl ? (
                                <Link
                                    href={quoteUrl}
                                    className="border border-foreground px-6 py-3.25 text-[0.8125rem] font-semibold tracking-[0.06em] uppercase transition-colors hover:bg-foreground hover:text-background"
                                >
                                    Request a quote
                                </Link>
                            ) : null}
                        </div>
                    </div>
                    <ul className="grid grid-cols-1 border-t border-foreground sm:grid-cols-2 sm:gap-x-8">
                        {details.makes.map((item, index) => (
                            <li
                                key={item}
                                className="flex items-baseline gap-5 border-b border-border py-5"
                            >
                                <span className="text-xs tracking-[0.1em] text-muted-foreground">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <span className="font-display text-[clamp(1.0625rem,1.4vw,1.25rem)] font-light">
                                    {item}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {hasProducts ? (
                <div id="collection" className={sectionScroll}>
                    <BrandCollection />
                </div>
            ) : null}

            {otherBrands.length > 0 ? (
                <section
                    aria-labelledby="brand-others"
                    className={`mt-[clamp(4.5rem,8vw,7.5rem)] -mb-6 flex flex-col items-center gap-10 bg-navy py-[clamp(4.5rem,8vw,7.5rem)] text-white sm:-mb-8 ${bleed}`}
                >
                    <h2
                        id="brand-others"
                        className="text-[0.8125rem] font-semibold tracking-[0.14em] uppercase"
                    >
                        More from the Regray Group
                    </h2>
                    <ul className="grid w-full max-w-275 grid-cols-2 items-center gap-x-8 gap-y-10 [--other:0.6] sm:grid-cols-3 sm:[--other:0.8] lg:grid-cols-5">
                        {otherBrands.map((other) => (
                            <OtherBrand
                                key={other.slug}
                                brand={other}
                                href={brandHref(other.slug)}
                            />
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
};

export default BrandProfile;

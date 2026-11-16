import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { show as brandsShow } from '@/routes/brands';
import { index as inboxIndex } from '@/routes/inbox';
import { book } from '@/routes/profile/schedule';
import { inquire } from '@/routes/quote';
import { show as projectShow } from '@/routes/stone';
import type {
    BrandCardData,
    LandingProject,
    LandingServiceType,
} from '@/types/home';

const pad = (value: number) => String(value).padStart(2, '0');

/** The small uppercase label above a section's headline. */
const eyebrow = 'text-xs font-medium tracking-[0.24em] uppercase';

/** A section's headline. */
const statement =
    'text-[clamp(1.875rem,3.4vw,3.125rem)] leading-[1.1] font-normal tracking-[-0.015em] text-pretty';

/** An underlined text link, uppercase. */
const textLink =
    'border-b border-current pb-1 text-xs font-medium tracking-[0.16em] whitespace-nowrap uppercase transition-opacity hover:opacity-70';

/** Side padding every section shares, matching the header's. */
const gutter = 'px-4 sm:px-5 lg:px-8';

/** Regray in two sentences, under the hero. */
export function LandingIntro() {
    return (
        <section
            className={`mx-auto grid max-w-400 grid-cols-1 items-end gap-x-[clamp(2.5rem,6vw,7.5rem)] gap-y-12 py-[clamp(5rem,12vw,10rem)] lg:grid-cols-2 ${gutter}`}
        >
            <p className="text-[clamp(1.875rem,3.6vw,3.375rem)] leading-[1.12] font-normal tracking-[-0.015em] text-pretty">
                Natural stone and bespoke furniture, sourced, made and installed
                by one studio.
            </p>
            <div className="flex max-w-120 flex-col gap-7">
                <p className="text-[1.0625rem] leading-[1.7] text-pretty text-ink-soft">
                    Regray brings together the stones, the furniture and the
                    brands we work with. Browse the catalog, save what you like,
                    and send an inquiry: our team quotes it, makes it and
                    delivers it nationwide, and keeps you updated at every step.
                </p>
                <a href="#projects" className={`self-start ${textLink}`}>
                    See our projects
                </a>
            </div>
        </section>
    );
}

/** Each brand as a wide photo, its name, what it makes and a way in. */
export function LandingBrands({ brands }: { brands: BrandCardData[] }) {
    if (brands.length === 0) {
        return null;
    }

    return (
        <section
            id="brands"
            aria-labelledby="landing-brands-heading"
            className="scroll-mt-19 bg-paper-deep py-[clamp(5rem,10vw,8.75rem)]"
        >
            <div className="mx-4 mb-[clamp(2.5rem,5vw,4.5rem)] flex items-baseline justify-between gap-6 border-b border-line pb-6 sm:mx-5 lg:mx-8">
                <h2 id="landing-brands-heading" className={eyebrow}>
                    Our brands
                </h2>
                <span className="text-[0.8125rem] text-ink-muted">
                    {brands.length} brands, one platform
                </span>
            </div>

            <div className="flex flex-col gap-[clamp(3.5rem,7vw,6.5rem)]">
                {brands.map((brand, index) => {
                    const href = brandsShow.url({ brand: brand.slug });

                    return (
                        <article
                            key={brand.slug}
                            aria-labelledby={`landing-brand-${brand.slug}`}
                            className="flex flex-col gap-[clamp(1.25rem,2.4vw,2rem)]"
                        >
                            <Link
                                href={href}
                                aria-label={`Discover ${brand.name}`}
                                className="group block overflow-hidden bg-line"
                            >
                                {brand.thumbnail ? (
                                    <img
                                        src={brand.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="aspect-4/3 w-full object-cover transition-transform duration-1400 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.025] motion-reduce:transition-none sm:aspect-video lg:aspect-[1811/540]"
                                    />
                                ) : (
                                    <div className="flex aspect-4/3 w-full items-center justify-center sm:aspect-video lg:aspect-[1811/540]">
                                        <img
                                            src={brand.logo}
                                            alt=""
                                            loading="lazy"
                                            className="h-12 w-auto object-contain"
                                        />
                                    </div>
                                )}
                            </Link>
                            <div
                                className={`grid grid-cols-1 items-baseline gap-x-[clamp(1.5rem,4vw,4rem)] gap-y-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] ${gutter}`}
                            >
                                <div className="flex items-baseline gap-5">
                                    <span className="text-xs text-ink-muted tabular-nums">
                                        {pad(index + 1)}
                                    </span>
                                    <h3
                                        id={`landing-brand-${brand.slug}`}
                                        className="text-[clamp(1.875rem,3.2vw,3rem)] leading-none font-normal tracking-[-0.02em]"
                                    >
                                        {brand.name}
                                    </h3>
                                </div>
                                {brand.tagline ? (
                                    <p className="max-w-130 text-[1.0625rem] leading-relaxed text-pretty text-ink-soft">
                                        {brand.tagline}
                                    </p>
                                ) : (
                                    <span />
                                )}
                                <Link
                                    href={href}
                                    className={`justify-self-start ${textLink}`}
                                >
                                    Discover
                                </Link>
                            </div>
                        </article>
                    );
                })}
            </div>
        </section>
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
            className={`scroll-mt-19 py-[clamp(5rem,10vw,8.75rem)] ${gutter}`}
        >
            <div className="mb-[clamp(2rem,4vw,3.5rem)] flex flex-col gap-4">
                <h2 id="landing-projects-heading" className={eyebrow}>
                    Projects
                </h2>
                <p className={`max-w-180 ${statement}`}>
                    Spaces we have finished in stone.
                </p>
            </div>

            <ul className="grid grid-cols-1 gap-x-[clamp(1rem,2vw,2rem)] gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                {projects.map((project) => (
                    <li key={project.slug}>
                        <Link
                            href={projectShow.url({ slug: project.slug })}
                            className="group flex flex-col gap-4"
                        >
                            <div className="aspect-3/4 overflow-hidden bg-paper-deep">
                                <img
                                    src={project.thumbnail}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover transition-transform duration-1400 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.025] motion-reduce:transition-none"
                                />
                            </div>
                            <div className="flex justify-between gap-4 border-t border-line pt-3.5">
                                <span className="text-[1.0625rem]">
                                    {project.name}
                                </span>
                                <span className="text-right text-sm text-ink-muted">
                                    {project.location} · {project.completedYear}
                                </span>
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** The services a client can ask for, grouped as the Inquire page groups them. */
export function LandingServices({
    serviceTypes,
}: {
    serviceTypes: LandingServiceType[];
}) {
    if (serviceTypes.length === 0) {
        return null;
    }

    // Services are numbered across the groups: where each group's count starts.
    const starts = serviceTypes.map((_, index) =>
        serviceTypes
            .slice(0, index)
            .reduce((count, type) => count + type.services.length, 0),
    );

    return (
        <section
            id="services"
            aria-labelledby="landing-services-heading"
            className={`grid scroll-mt-19 grid-cols-1 gap-x-[clamp(2.5rem,6vw,7.5rem)] gap-y-12 pb-[clamp(5rem,10vw,8.75rem)] lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] ${gutter}`}
        >
            <div className="flex flex-col gap-5 self-start lg:sticky lg:top-28">
                <h2 id="landing-services-heading" className={eyebrow}>
                    Services
                </h2>
                <p className={statement}>What we make for you.</p>
                <p className="max-w-105 text-[1.0625rem] leading-[1.7] text-ink-soft">
                    Pick a service when you send an inquiry. Each one asks only
                    the questions that matter for that job.
                </p>
            </div>

            <div className="flex flex-col gap-12">
                {serviceTypes.map((type, typeIndex) => (
                    <div key={type.slug}>
                        <h3 className="mb-4 text-xs font-medium tracking-[0.2em] text-ink-muted uppercase">
                            {type.name}
                        </h3>
                        <ul className="border-t border-ink">
                            {type.services.map((service, index) => {
                                const number = starts[typeIndex] + index + 1;

                                return (
                                    <li
                                        key={service.slug}
                                        className="border-b border-line"
                                    >
                                        <Link
                                            href={inquire.url()}
                                            className="grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-4 py-5 transition-[padding] duration-300 hover:pl-3 motion-reduce:transition-none sm:grid-cols-[3rem_minmax(0,1fr)_auto]"
                                        >
                                            <span className="text-xs text-ink-muted tabular-nums">
                                                {pad(number)}
                                            </span>
                                            <span className="flex flex-col gap-1">
                                                <span className="text-[clamp(1.25rem,1.8vw,1.625rem)] leading-tight">
                                                    {service.name}
                                                </span>
                                                <span className="text-sm leading-relaxed text-ink-muted">
                                                    {service.description}
                                                </span>
                                            </span>
                                            <ArrowRight
                                                aria-hidden
                                                className="size-5"
                                                strokeWidth={1.5}
                                            />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}

const STEPS = [
    {
        title: 'Inquire',
        text: 'Pick a service and your stones, answer a few questions about the job, and send it. We email you a confirmation right away.',
    },
    {
        title: 'Quote',
        text: 'Our team reviews it, visits your site when it needs measuring, and sends you a quotation to approve.',
    },
    {
        title: 'Approve and pay',
        text: 'Approve the quotation and pay in full or in parts. Every receipt is kept on your inquiry.',
    },
    {
        title: 'Made and delivered',
        text: 'We source, produce, check, deliver and install it. You are emailed at every step, and can follow it live.',
    },
];

/** The inquiry's path, from sending it to installation. */
export function LandingProcess() {
    return (
        <section
            id="how-it-works"
            aria-labelledby="landing-process-heading"
            className={`scroll-mt-19 bg-night py-[clamp(5rem,10vw,8.75rem)] text-paper ${gutter}`}
        >
            <div className="mb-[clamp(3rem,6vw,5.5rem)] flex flex-col gap-4">
                <h2
                    id="landing-process-heading"
                    className={`${eyebrow} text-night-text`}
                >
                    How it works
                </h2>
                <p className={`max-w-180 ${statement}`}>
                    From your inquiry to installation, followed step by step.
                </p>
            </div>

            <ol className="grid grid-cols-1 gap-x-[clamp(1.5rem,3vw,3rem)] gap-y-10 sm:grid-cols-2 lg:grid-cols-4">
                {STEPS.map((step, index) => (
                    <li
                        key={step.title}
                        className="flex flex-col gap-4 border-t border-night-line pt-6"
                    >
                        <span className="text-xs text-night-text tabular-nums">
                            {pad(index + 1)}
                        </span>
                        <h3 className="text-2xl font-normal">{step.title}</h3>
                        <p className="text-base leading-[1.65] text-pretty text-night-text">
                            {step.text}
                        </p>
                    </li>
                ))}
            </ol>
        </section>
    );
}

/** The brand whose photo sits behind the site visit, and the photo when it is missing. */
const SITE_VISIT_BRAND = 'res-italia';
const SITE_VISIT_FALLBACK = '/images/regray-hero.jpg';

/** Booking a site visit, over a full-width photo. */
export function LandingSiteVisit({ brands }: { brands: BrandCardData[] }) {
    const photo =
        brands.find((brand) => brand.slug === SITE_VISIT_BRAND)?.thumbnail ??
        SITE_VISIT_FALLBACK;

    return (
        <section
            aria-labelledby="landing-visit-heading"
            className="relative flex min-h-[clamp(30rem,70vh,45rem)] items-center overflow-hidden bg-night text-on-image"
        >
            <img
                src={photo}
                alt=""
                loading="lazy"
                className="absolute inset-0 size-full object-cover"
            />
            <div
                aria-hidden
                className="absolute inset-0 bg-[rgb(20_18_16/0.5)]"
            />
            <div
                className={`relative flex max-w-205 flex-col gap-6 py-20 ${gutter}`}
            >
                <p className={eyebrow}>Site visit</p>
                <h2
                    id="landing-visit-heading"
                    className="text-[clamp(2.25rem,5vw,4.5rem)] leading-[1.02] font-normal tracking-[-0.02em]"
                >
                    Let us measure it in person.
                </h2>
                <p className="max-w-130 text-lg leading-relaxed">
                    Book a visit and our technical team will come to your site,
                    take the measurements and talk you through the stones.
                </p>
                <div className="mt-3 flex flex-wrap items-center gap-x-7 gap-y-4">
                    <Link
                        href={book.url()}
                        className="bg-paper px-7 py-4.5 text-xs font-semibold tracking-[0.16em] text-ink uppercase transition-colors hover:bg-white"
                    >
                        Book a site visit
                    </Link>
                    <Link
                        href={inboxIndex.url()}
                        className="border-b border-current py-2 text-xs font-medium tracking-[0.16em] uppercase transition-opacity hover:opacity-75"
                    >
                        Ask the team first
                    </Link>
                </div>
            </div>
        </section>
    );
}

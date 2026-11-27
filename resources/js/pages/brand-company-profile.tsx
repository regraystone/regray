import { Head, Link, usePage } from '@inertiajs/react';
import {
    BRAND_LOGO_HEIGHT,
    CONTACT,
    DEFAULT_BRAND_LOGO_HEIGHT,
    SHOWROOM,
} from '@/components/public/public-links';
import {
    REGRAY_STONE_FOUNDED,
    REGRAY_STONE_INSTALLED,
    REGRAY_STONE_SERVICES,
    REGRAY_STONE_STATEMENTS,
    REGRAY_STONE_STORY,
} from '@/components/public/regray-stone-story';
import SocialIcon, { SOCIAL_LABELS } from '@/components/social-icon';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { login } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { news, showroom } from '@/routes/company';
import { show as dashboardBrandShow } from '@/routes/dashboard/brands';
import { book } from '@/routes/profile/schedule';
import { show as showcaseShow } from '@/routes/stone';
import type { BrandCompanyProfileProps, LandingShowcase } from '@/types/home';

/** A small spaced label. */
const eyebrow = 'text-[0.8125rem] tracking-[0.14em] uppercase';

/** A section's title: spaced capitals in the heading face. */
const sectionTitle =
    'font-display text-[clamp(1.5rem,2vw,2rem)] font-light tracking-[0.06em] uppercase';

/** The black button and the outlined one. */
const solidButton =
    'inline-flex min-h-12.5 items-center justify-center bg-foreground px-6 text-[0.8125rem] font-semibold tracking-[0.08em] text-background uppercase transition-colors hover:bg-foreground/80';
const outlineButton =
    'inline-flex min-h-12.5 items-center justify-center border border-foreground px-6 text-[0.8125rem] font-semibold tracking-[0.08em] uppercase transition-colors hover:bg-foreground hover:text-background';

/** Room above and below a section. */
const sectionSpace = 'py-[clamp(4rem,8vw,7.5rem)]';

const plural = (count: number, one: string, many: string) =>
    count === 1 ? one : many;

/** A finished project: its photo, the year and place, and its name, opening its page. */
const ProjectCard = ({ project }: { project: LandingShowcase }) => (
    <Link
        href={showcaseShow.url({ slug: project.slug })}
        className="group flex flex-col gap-4"
        data-testid="company-profile-project"
    >
        <span className="block aspect-4/3 overflow-hidden bg-muted">
            <img
                src={project.thumbnail}
                alt=""
                loading="lazy"
                className="size-full object-cover transition-transform duration-1400 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.03] motion-reduce:transition-none"
            />
        </span>
        <span className="flex flex-col gap-1.5">
            <span className="text-[0.8125rem] tracking-[0.06em] text-muted-foreground uppercase">
                {project.completedYear} · {project.location}
            </span>
            <span className="font-display text-[clamp(1.125rem,1.4vw,1.3125rem)] leading-snug font-light underline-offset-4 group-hover:underline">
                {project.name}
            </span>
        </span>
    </Link>
);

/*
 * A brand's Company profile, opened from its page's About section: who it is,
 * its story, in figures, what it does, its mission and vision, its latest
 * finished projects, and how to visit or reach it. Only Regray Stone has one
 * (Brand::COMPANY_PROFILES): its words are its own, from regraystone.com,
 * shared with the About page. The figures are counted from the catalog, and
 * one with nothing to count is left out.
 */
const BrandCompanyProfile = () => {
    const {
        brand: { data: brand },
        counts,
        showcases,
        auth,
    } = usePage<BrandCompanyProfileProps & { auth: { user: unknown } }>().props;
    const isSignedIn = auth?.user != null;
    const inquireRoute = useInquireRoute();

    const brandHref = isSignedIn
        ? dashboardBrandShow.url({ brand: brand.slug })
        : brandsShow.url({ brand: brand.slug });
    const quoteUrl = isSignedIn ? (inquireRoute?.url() ?? null) : login.url();
    const visitUrl = isSignedIn ? book.url() : `${showroom.url()}#book`;

    // The page's own frame, for the bands that run edge to edge.
    const bleed = isSignedIn
        ? '-mx-4 px-4 sm:-mx-5 sm:px-5 lg:-mx-8 lg:px-8'
        : '-mx-4 px-4 sm:-mx-8 sm:px-8';

    const figures = [
        { value: REGRAY_STONE_INSTALLED, label: 'Spaces installed' },
        { value: REGRAY_STONE_FOUNDED, label: 'Established' },
        counts.stones > 0
            ? {
                  value: counts.stones.toLocaleString(),
                  label: `${plural(counts.stones, 'Stone', 'Stones')} in our collection`,
              }
            : null,
        counts.furniture > 0
            ? {
                  value: counts.furniture.toLocaleString(),
                  label: `${plural(counts.furniture, 'Piece', 'Pieces')} of furniture`,
              }
            : null,
        counts.showcases > 0
            ? {
                  value: counts.showcases.toLocaleString(),
                  label: `Finished ${plural(counts.showcases, 'project', 'projects')}`,
              }
            : null,
    ].filter(
        (figure): figure is { value: string; label: string } => figure !== null,
    );

    return (
        <div className="w-full font-light">
            <Head title={`${brand.name} company profile`} />

            <section
                aria-label={`${brand.name} company profile`}
                className="relative h-[60svh] min-h-100 overflow-hidden bg-night text-white"
            >
                {brand.thumbnail ? (
                    <img
                        src={brand.thumbnail}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : null}
                <div
                    aria-hidden
                    className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0.3)_0%,rgb(0_0_0/0)_30%,rgb(0_0_0/0.1)_50%,rgb(0_0_0/0.7)_100%)]"
                />
                {/* Signed in, the header's trail (or Back on a phone) leads back instead. */}
                {isSignedIn ? null : (
                    <nav
                        aria-label="Breadcrumb"
                        className="absolute top-[clamp(1.25rem,2.5vw,2.25rem)] left-[clamp(1.25rem,3vw,3rem)] text-xs tracking-[0.08em] uppercase"
                    >
                        <ol className="flex flex-wrap gap-2">
                            <li>
                                <Link
                                    href={brandHref}
                                    className="hover:underline"
                                >
                                    ← {brand.name}
                                </Link>
                            </li>
                            <li aria-hidden>/</li>
                            <li aria-current="page">Company profile</li>
                        </ol>
                    </nav>
                )}
                <div className="absolute inset-x-[clamp(1.25rem,3vw,3rem)] bottom-[clamp(1.75rem,4vw,3.5rem)] flex max-w-200 flex-col gap-5">
                    <p className={`${eyebrow} text-white/85`}>
                        Company profile
                    </p>
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
            </section>

            <section
                aria-labelledby="profile-who"
                className={`grid gap-[clamp(2.5rem,6vw,7.5rem)] ${sectionSpace} lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]`}
            >
                <div className="flex max-w-190 flex-col gap-6">
                    <h2
                        id="profile-who"
                        className={`${eyebrow} text-muted-foreground`}
                    >
                        Who we are
                    </h2>
                    <p className="font-display text-[clamp(1.5rem,2.3vw,2.25rem)] leading-[1.3] font-light text-pretty">
                        {brand.description ?? brand.tagline}
                    </p>
                </div>
                {brand.facts.length > 0 ? (
                    <dl className="self-start border-t border-foreground">
                        {brand.facts.map((fact) => (
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
                ) : null}
            </section>

            <section
                aria-label={`${brand.name} in figures`}
                className="border-y border-border py-[clamp(2.5rem,5vw,4rem)]"
            >
                <dl className="flex flex-wrap justify-center gap-x-8 gap-y-10">
                    {figures.map((figure) => (
                        <div
                            key={figure.label}
                            className="flex basis-[calc((100%-2rem)/2)] flex-col items-center gap-2 text-center sm:basis-[calc((100%-4rem)/3)] lg:basis-[calc((100%-8rem)/5)]"
                        >
                            <dd className="order-first font-display text-[clamp(2rem,3vw,2.75rem)] font-light tabular-nums">
                                {figure.value}
                            </dd>
                            <dt className={`${eyebrow} text-muted-foreground`}>
                                {figure.label}
                            </dt>
                        </div>
                    ))}
                </dl>
            </section>

            <section
                aria-labelledby="profile-story"
                className={`grid gap-x-16 gap-y-8 ${sectionSpace} md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]`}
            >
                <h2 id="profile-story" className={sectionTitle}>
                    Our story
                </h2>
                <div className="flex max-w-170 flex-col gap-5 text-[1.0625rem] leading-relaxed text-pretty">
                    {REGRAY_STONE_STORY.map((paragraph) => (
                        <p key={paragraph}>{paragraph}</p>
                    ))}
                </div>
            </section>

            <section
                aria-labelledby="profile-services"
                className={`border-t border-border ${sectionSpace}`}
            >
                <h2 id="profile-services" className={`mb-10 ${sectionTitle}`}>
                    What we do
                </h2>
                <ol className="grid grid-cols-1 gap-x-8 border-t border-foreground md:grid-cols-3">
                    {REGRAY_STONE_SERVICES.map((service, index) => (
                        <li
                            key={service.title}
                            className="flex flex-col gap-3 border-b border-border py-6 md:border-b-0"
                        >
                            <span className="text-xs tracking-[0.1em] text-muted-foreground">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <span className="font-display text-[clamp(1.25rem,1.6vw,1.5rem)] font-light">
                                {service.title}
                            </span>
                            <span className="text-base leading-relaxed text-pretty text-muted-foreground">
                                {service.text}
                            </span>
                        </li>
                    ))}
                </ol>
                {brand.makes.length > 0 ? (
                    <div className="mt-10 flex flex-wrap items-center gap-3">
                        <span
                            className={`${eyebrow} mr-2 text-muted-foreground`}
                        >
                            What we make
                        </span>
                        <ul className="flex flex-wrap gap-2.5">
                            {brand.makes.map((item) => (
                                <li
                                    key={item}
                                    className="border border-border px-4 py-2 text-[0.9375rem]"
                                >
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}
            </section>

            <section
                aria-labelledby="profile-vision"
                className={`bg-black py-[clamp(4.5rem,9vw,8rem)] text-white ${bleed}`}
            >
                <h2 id="profile-vision" className="sr-only">
                    Mission & vision
                </h2>
                <div className="mx-auto grid max-w-6xl grid-cols-1 gap-14 md:grid-cols-2">
                    {REGRAY_STONE_STATEMENTS.map((statement) => (
                        <div
                            key={statement.title}
                            className="flex flex-col gap-5 border-t border-white/40 pt-6"
                        >
                            <p className={`${eyebrow} text-white/75`}>
                                {statement.title}
                            </p>
                            <p className="font-display text-[clamp(1.375rem,2vw,1.875rem)] leading-snug font-light text-pretty">
                                {statement.text}
                            </p>
                        </div>
                    ))}
                </div>
            </section>

            {showcases.length > 0 ? (
                <section
                    aria-labelledby="profile-work"
                    className={sectionSpace}
                >
                    <div className="mb-10 flex flex-wrap items-end justify-between gap-4">
                        <h2 id="profile-work" className={sectionTitle}>
                            Our work
                        </h2>
                        <Link
                            href={news.url()}
                            className="border-b border-current pb-1 text-[0.8125rem] tracking-[0.06em] uppercase transition-opacity hover:opacity-70"
                        >
                            See all on News
                        </Link>
                    </div>
                    <ul className="grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
                        {showcases.map((project) => (
                            <li key={project.slug}>
                                <ProjectCard project={project} />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section
                aria-labelledby="profile-visit"
                className={`grid gap-[clamp(2.5rem,6vw,6rem)] border-t border-border ${sectionSpace} lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]`}
            >
                <div className="flex flex-col gap-5">
                    <h2 id="profile-visit" className={sectionTitle}>
                        Visit us
                    </h2>
                    <p className="max-w-110 text-[1.0625rem] leading-relaxed text-pretty text-muted-foreground">
                        See the stones in person at our Makati showroom, or ask
                        the team for a quotation.
                    </p>
                    <div className="mt-2 flex flex-wrap gap-3">
                        <Link href={visitUrl} className={solidButton}>
                            Book a visit
                        </Link>
                        {quoteUrl ? (
                            <Link href={quoteUrl} className={outlineButton}>
                                Request a quote
                            </Link>
                        ) : null}
                        <Link
                            href={`${brandHref}#collection`}
                            className={outlineButton}
                        >
                            See the collection
                        </Link>
                    </div>
                </div>
                <dl className="self-start border-t border-foreground">
                    {[
                        { label: 'Showroom', value: SHOWROOM.address },
                        { label: 'Hours', value: SHOWROOM.hours },
                    ].map((row) => (
                        <div
                            key={row.label}
                            className="grid grid-cols-[7rem_1fr] gap-4 border-b border-border py-4"
                        >
                            <dt className="pt-0.5 text-xs tracking-[0.1em] text-muted-foreground uppercase">
                                {row.label}
                            </dt>
                            <dd className="text-base leading-normal">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                    <div className="grid grid-cols-[7rem_1fr] gap-4 border-b border-border py-4">
                        <dt className="pt-0.5 text-xs tracking-[0.1em] text-muted-foreground uppercase">
                            Call
                        </dt>
                        <dd className="flex flex-wrap gap-x-4 gap-y-1 text-base leading-normal">
                            {CONTACT.phones.map((phone) => (
                                <a
                                    key={phone.href}
                                    href={phone.href}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {phone.label}
                                </a>
                            ))}
                        </dd>
                    </div>
                    <div className="grid grid-cols-[7rem_1fr] gap-4 border-b border-border py-4">
                        <dt className="pt-0.5 text-xs tracking-[0.1em] text-muted-foreground uppercase">
                            Email
                        </dt>
                        <dd className="text-base leading-normal">
                            <a
                                href={`mailto:${CONTACT.email}`}
                                className="underline-offset-4 hover:underline"
                            >
                                {CONTACT.email}
                            </a>
                        </dd>
                    </div>
                    {brand.socials.length > 0 ? (
                        <div className="grid grid-cols-[7rem_1fr] items-center gap-4 border-b border-border py-4">
                            <dt className="text-xs tracking-[0.1em] text-muted-foreground uppercase">
                                Follow
                            </dt>
                            <dd className="flex flex-wrap gap-2.5">
                                {brand.socials.map((social) => (
                                    <a
                                        key={social.platform}
                                        href={social.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={`${brand.name} on ${SOCIAL_LABELS[social.platform]} (opens in a new tab)`}
                                        title={`${brand.name} on ${SOCIAL_LABELS[social.platform]}`}
                                        className="grid size-11 place-items-center border border-foreground transition-colors hover:bg-foreground hover:text-background"
                                    >
                                        <SocialIcon
                                            platform={social.platform}
                                        />
                                    </a>
                                ))}
                            </dd>
                        </div>
                    ) : null}
                </dl>
            </section>
        </div>
    );
};

export default BrandCompanyProfile;

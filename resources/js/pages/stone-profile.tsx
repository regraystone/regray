import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Heart, Star } from 'lucide-react';
import ProductPhotos, { productPhotos } from '@/components/product-photos';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { login } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { show as brandStoneShow } from '@/routes/brands/stones';
import { showroom } from '@/routes/company';
import { show as dashboardBrandShow } from '@/routes/dashboard/brands';
import { show as dashboardStoneShow } from '@/routes/dashboard/brands/stones';
import { show as dashboardShowcaseShow } from '@/routes/home';
import { book } from '@/routes/profile/schedule';
import { show as showcaseShow } from '@/routes/stone';
import type { LandingShowcase } from '@/types/home';
import type {
    StoneCardData,
    StoneProfile as StoneProfileData,
} from '@/types/stone';

type StoneStep = { slug: string; name: string } | null;

type StoneProfileProps = {
    stone: { data: StoneProfileData };
    previous: StoneStep;
    next: StoneStep;
    related: StoneCardData[];
    showcases: LandingShowcase[];
};

/** A small uppercase heading over a block of facts. */
const FactHeading = ({ children }: { children: React.ReactNode }) => (
    <h3 className="mb-3 font-sans text-xs font-semibold tracking-[0.12em] uppercase">
        {children}
    </h3>
);

const List = ({ items }: { items: string[] }) => (
    <ul className="flex flex-col gap-2.5">
        {items.map((item) => (
            <li
                key={item}
                className="flex items-start gap-3 text-[0.9375rem] leading-relaxed text-secondary-foreground"
            >
                <span
                    aria-hidden
                    className="mt-2.5 size-1 shrink-0 rounded-full bg-foreground"
                />
                {item}
            </li>
        ))}
    </ul>
);

/**
 * A stone: its photo at its own portrait shape (click to see it large) beside
 * its type and brand, its name, the Best Seller mark, what the type is, its
 * origin, the slabs it comes in, its finishes and edge profiles, with Ask a quote and Save
 * together. Below, where it suits and how to care for it, the showcases it
 * was used in, and more stones of its type. A breadcrumb leads back to the
 * brand, and the previous and next stones of its type are a click away.
 *
 * The same page serves guests (the public frame) and signed-in readers (the
 * workspace), through each of its doors: links follow whichever the reader
 * is. A guest's Ask a quote and Save go through Login first.
 */
const StoneProfile = ({
    stone: { data: stone },
    previous,
    next,
    related,
    showcases,
}: StoneProfileProps) => {
    const { auth } = usePage<{ auth: { user: unknown } }>().props;
    const isSignedIn = auth.user != null;
    const inquireRoute = useInquireRoute();
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const isSaved = savedIds.has(stone.slug);
    const brand = stone.brand;

    const brandUrl = (collection?: 'stones') =>
        (isSignedIn ? dashboardBrandShow : brandsShow).url(
            { brand: brand.slug },
            collection ? { query: { collection } } : undefined,
        );
    const stoneUrl = (slug: string) =>
        (isSignedIn ? dashboardStoneShow : brandStoneShow).url({
            brand: brand.slug,
            stone: slug,
        });
    const showcaseUrl = (slug: string) =>
        isSignedIn
            ? dashboardShowcaseShow.url({ slug })
            : showcaseShow.url({ slug });
    const quoteUrl = isSignedIn ? (inquireRoute?.url() ?? null) : login.url();
    const visitUrl = isSignedIn ? book.url() : `${showroom.url()}#book`;
    const { slabs, sizeNote, origin } = stone.specifications;

    const actions = (compact: boolean) => (
        <div
            className={`grid gap-2 ${canSave || !isSignedIn ? 'grid-cols-[1fr_auto]' : 'grid-cols-1'}`}
        >
            {quoteUrl ? (
                <Link
                    href={quoteUrl}
                    className={`flex items-center justify-center bg-foreground text-[0.8125rem] font-semibold tracking-[0.1em] text-background uppercase transition-colors hover:bg-foreground/85 ${compact ? 'h-13' : 'h-14'}`}
                >
                    Ask a quote
                </Link>
            ) : null}
            {canSave ? (
                <button
                    type="button"
                    onClick={() => toggleSave(stone.slug)}
                    aria-pressed={isSaved}
                    className={`flex cursor-pointer items-center gap-2 border border-foreground px-5 text-[0.8125rem] font-semibold tracking-[0.1em] uppercase transition-colors ${compact ? 'h-13' : 'h-14'} ${isSaved ? 'bg-foreground text-background' : 'hover:bg-muted'}`}
                >
                    <Heart
                        aria-hidden
                        className={`size-4 ${isSaved ? 'fill-current' : ''}`}
                    />
                    {isSaved ? 'Saved' : 'Save'}
                </button>
            ) : !isSignedIn ? (
                <Link
                    href={login.url()}
                    className={`flex items-center gap-2 border border-foreground px-5 text-[0.8125rem] font-semibold tracking-[0.1em] uppercase transition-colors hover:bg-muted ${compact ? 'h-13' : 'h-14'}`}
                >
                    <Heart aria-hidden className="size-4" />
                    Save
                </Link>
            ) : null}
        </div>
    );

    return (
        <div className="w-full pb-24 lg:pb-0">
            <Head title={stone.name} />

            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 pb-5 text-[0.8125rem]">
                <nav aria-label="Breadcrumb">
                    <ol className="flex flex-wrap items-center gap-2 text-secondary-foreground">
                        <li>
                            <Link
                                href={brandUrl()}
                                className="transition-colors hover:text-foreground"
                            >
                                {brand.name}
                            </Link>
                        </li>
                        <li aria-hidden>/</li>
                        <li>
                            <Link
                                href={brandUrl('stones')}
                                className="transition-colors hover:text-foreground"
                            >
                                {stone.type.name}
                            </Link>
                        </li>
                        <li aria-hidden>/</li>
                        <li aria-current="page" className="text-foreground">
                            {stone.name}
                        </li>
                    </ol>
                </nav>
                {previous || next ? (
                    <div className="flex gap-5 text-xs tracking-[0.06em] text-secondary-foreground uppercase">
                        {previous ? (
                            <Link
                                href={stoneUrl(previous.slug)}
                                className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                            >
                                <ArrowLeft aria-hidden className="size-3.5" />
                                {previous.name}
                            </Link>
                        ) : null}
                        {next ? (
                            <Link
                                href={stoneUrl(next.slug)}
                                className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                            >
                                {next.name}
                                <ArrowRight aria-hidden className="size-3.5" />
                            </Link>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <section
                aria-label={stone.name}
                className="grid grid-cols-1 items-start gap-[clamp(1.5rem,4vw,4.5rem)] pb-[clamp(3.5rem,7vw,6rem)] md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:grid-cols-[minmax(0,1fr)_minmax(22rem,1.2fr)]"
            >
                <ProductPhotos
                    key={stone.slug}
                    name={stone.name}
                    alt={`${stone.name}, ${stone.type.name.toLowerCase()}`}
                    photos={productPhotos(
                        stone.thumbnail,
                        stone.gallery,
                        stone.name,
                    )}
                    frameClassName="aspect-4/5 max-w-md lg:max-w-[32rem]"
                />

                <div className="flex flex-col gap-7">
                    <div className="flex flex-col gap-3">
                        <p className="text-xs tracking-[0.14em] text-secondary-foreground uppercase">
                            {stone.type.name} · {brand.name}
                        </p>
                        <h1 className="text-[clamp(2rem,3vw,2.875rem)] leading-[1.05] font-light tracking-[0.02em] uppercase">
                            {stone.name}
                        </h1>
                        {stone.bestSeller ? (
                            <span className="inline-flex items-center gap-1 self-start bg-foreground px-2 py-1 text-[0.65rem] font-bold tracking-[0.05em] text-background uppercase">
                                <Star
                                    aria-hidden
                                    className="size-3 fill-current"
                                />
                                Best Seller
                            </span>
                        ) : null}
                        {stone.description ? (
                            <p className="text-base leading-relaxed text-pretty text-secondary-foreground">
                                {stone.description}
                            </p>
                        ) : null}
                    </div>

                    <dl className="grid grid-cols-2 border-t border-border pt-5 text-[0.9375rem]">
                        <div>
                            <dt className="text-xs tracking-[0.12em] text-secondary-foreground uppercase">
                                Stone type
                            </dt>
                            <dd className="mt-1">{stone.type.name}</dd>
                        </div>
                        <div>
                            <dt className="text-xs tracking-[0.12em] text-secondary-foreground uppercase">
                                Origin
                            </dt>
                            <dd className="mt-1">{origin}</dd>
                        </div>
                    </dl>

                    {slabs.length > 0 ? (
                        <div className="border-t border-border pt-5">
                            <FactHeading>Available slabs</FactHeading>
                            <table
                                className="w-full table-fixed border-collapse text-left text-[0.9375rem]"
                                data-testid="available-slabs"
                            >
                                <thead>
                                    <tr className="border-b border-border">
                                        <th
                                            scope="col"
                                            className="w-1/2 py-2 pr-4 text-xs font-normal tracking-[0.08em] text-secondary-foreground uppercase"
                                        >
                                            Size
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-2 text-xs font-normal tracking-[0.08em] text-secondary-foreground uppercase"
                                        >
                                            Thickness
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {slabs.map((slab) => (
                                        <tr
                                            key={slab.size ?? 'none'}
                                            className="border-b border-border last:border-0"
                                        >
                                            <td className="py-2.5 pr-4 align-top break-words">
                                                {slab.size ??
                                                    sizeNote ??
                                                    'Any size'}
                                            </td>
                                            <td className="py-2.5 align-top break-words">
                                                {slab.thicknesses.join(' · ')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : null}

                    {(
                        [
                            ['Finishes', stone.finishes],
                            ['Edge profiles', stone.edgeProfiles],
                        ] as const
                    ).map(([heading, values]) =>
                        values.length > 0 ? (
                            <div
                                key={heading}
                                className="border-t border-border pt-5"
                            >
                                <FactHeading>{heading}</FactHeading>
                                <ul className="flex flex-wrap gap-2">
                                    {values.map((value) => (
                                        <li
                                            key={value}
                                            className="border border-border px-3 py-1.5 text-sm text-secondary-foreground"
                                        >
                                            {value}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : null,
                    )}

                    <div className="flex flex-col gap-3 border-t border-border pt-5">
                        <div className="hidden lg:block">{actions(false)}</div>
                        <p className="text-[0.8125rem] leading-relaxed text-secondary-foreground">
                            Prefer to see the slabs in person?{' '}
                            <Link
                                href={visitUrl}
                                className="border-b border-current text-foreground"
                            >
                                Book a showroom visit
                            </Link>
                        </p>
                    </div>
                </div>
            </section>

            {stone.applications.length > 0 || stone.care.length > 0 ? (
                <section
                    aria-label="Uses and care"
                    className="grid grid-cols-1 gap-x-16 gap-y-10 border-t border-border pt-10 pb-[clamp(3rem,6vw,5rem)] md:grid-cols-2"
                >
                    {stone.applications.length > 0 ? (
                        <div>
                            <FactHeading>Applications</FactHeading>
                            <List items={stone.applications} />
                        </div>
                    ) : null}
                    {stone.care.length > 0 ? (
                        <div>
                            <FactHeading>Care &amp; maintenance</FactHeading>
                            <List items={stone.care} />
                        </div>
                    ) : null}
                </section>
            ) : null}

            {showcases.length > 0 ? (
                <section
                    aria-labelledby="stone-showcases"
                    className="border-t border-border pt-10 pb-[clamp(3rem,6vw,5rem)]"
                >
                    <h2
                        id="stone-showcases"
                        className="mb-7 text-[clamp(1.375rem,1.8vw,1.75rem)] font-light tracking-[0.02em] uppercase"
                    >
                        Used in
                    </h2>
                    <ul className="grid grid-cols-1 gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                        {showcases.map((showcase) => (
                            <li key={showcase.slug}>
                                <Link
                                    href={showcaseUrl(showcase.slug)}
                                    className="group flex flex-col gap-3"
                                >
                                    <div className="aspect-4/3 overflow-hidden bg-muted">
                                        <img
                                            src={showcase.thumbnail}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                                        />
                                    </div>
                                    <span className="flex flex-col gap-0.5">
                                        <span className="text-[0.9375rem]">
                                            {showcase.name}
                                        </span>
                                        <span className="text-xs text-secondary-foreground">
                                            {showcase.location} ·{' '}
                                            {showcase.completedYear}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {related.length > 0 ? (
                <section
                    aria-labelledby="stone-related"
                    className="border-t border-border pt-10 pb-[clamp(3rem,6vw,5rem)]"
                >
                    <div className="mb-7 flex items-baseline justify-between gap-4">
                        <h2
                            id="stone-related"
                            className="text-[clamp(1.375rem,1.8vw,1.75rem)] font-light tracking-[0.02em] uppercase"
                        >
                            More {stone.type.name.toLowerCase()}
                        </h2>
                        <Link
                            href={brandUrl('stones')}
                            className="border-b border-current pb-1 text-[0.8125rem] tracking-[0.04em] whitespace-nowrap uppercase transition-opacity hover:opacity-70"
                        >
                            View all
                        </Link>
                    </div>
                    <ul className="grid grid-cols-2 gap-x-3.5 gap-y-8 md:gap-x-5 lg:grid-cols-4">
                        {related.map((other) => (
                            <li key={other.slug}>
                                <Link
                                    href={stoneUrl(other.slug)}
                                    className="group flex flex-col gap-3"
                                >
                                    <div className="aspect-4/5 overflow-hidden bg-muted">
                                        <img
                                            src={other.thumbnail}
                                            alt=""
                                            loading="lazy"
                                            className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                                        />
                                    </div>
                                    <span className="flex items-baseline justify-between gap-3">
                                        <span className="font-display text-[0.9375rem] tracking-[0.04em] uppercase">
                                            {other.name}
                                        </span>
                                        <span className="truncate text-xs text-secondary-foreground">
                                            {other.type.name}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {/* On phones and tablets, the two actions stay in reach. */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
                {actions(true)}
            </div>
        </div>
    );
};

export default StoneProfile;

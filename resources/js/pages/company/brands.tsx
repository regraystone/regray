import { Head, Link } from '@inertiajs/react';
import {
    PageIntro,
    SectionTitle,
    frame,
    textLink,
} from '@/components/public/company-ui';
import { BRAND_CATEGORIES, brandUrl } from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import type { LandingBrand } from '@/types/home';

/** A brand in a category: its photo with its logo, what it makes, and the ways in. */
const BrandCard = ({ brand }: { brand: LandingBrand }) => (
    <article className="flex flex-col gap-5">
        <Link
            href={brandUrl(brand.slug)}
            aria-label={`Discover ${brand.name}`}
            className="group relative block aspect-4/3 overflow-hidden bg-night"
        >
            {brand.thumbnail ? (
                <img
                    src={brand.thumbnail}
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover transition-transform duration-1600 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.02] motion-reduce:transition-none"
                />
            ) : null}
            <div aria-hidden className="absolute inset-0 bg-black/30" />
            <img
                src={brand.logo}
                alt={brand.name}
                className="absolute top-1/2 left-1/2 h-[clamp(2rem,4vw,3.25rem)] max-w-[70%] -translate-x-1/2 -translate-y-1/2 object-contain"
            />
        </Link>
        <div className="flex flex-col items-center gap-3 text-center">
            <h3 className="font-display text-[clamp(1.375rem,1.8vw,1.75rem)] font-light">
                {brand.name}
            </h3>
            {brand.description ? (
                <p className="max-w-120 text-[0.9375rem] leading-relaxed text-pretty text-muted-foreground">
                    {brand.description}
                </p>
            ) : null}
            <div className="mt-2 flex flex-wrap justify-center gap-x-8 gap-y-3">
                <Link href={brandUrl(brand.slug)} className={textLink}>
                    Discover {brand.name}
                </Link>
                {brand.websiteUrl ? (
                    <a
                        href={brand.websiteUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={textLink}
                    >
                        Official website ↗
                    </a>
                ) : null}
            </div>
        </div>
    </article>
);

/**
 * Our brands: Regray's houses grouped by what they make (Stone & Surfaces,
 * Kitchens, Wardrobes & Doors, Furniture & Interiors), each with its photo,
 * logo, description, page and official website. A brand in no group is
 * listed at the end.
 */
export default function CompanyBrands({ brands }: { brands: LandingBrand[] }) {
    const bySlug = new Map(brands.map((brand) => [brand.slug, brand]));
    const grouped = new Set<string>(
        BRAND_CATEGORIES.flatMap((category) => [...category.slugs]),
    );
    const others = brands.filter((brand) => !grouped.has(brand.slug));
    const categories = [
        ...BRAND_CATEGORIES.map((category) => ({
            ...category,
            brands: category.slugs.flatMap((slug) => {
                const brand = bySlug.get(slug);

                return brand ? [brand] : [];
            }),
        })),
        {
            id: 'more',
            label: 'More houses',
            description: 'The rest of the Regray group.',
            brands: others,
        },
    ].filter((category) => category.brands.length > 0);

    return (
        <PublicLayout>
            <Head title="Our brands" />

            <PageIntro eyebrow="Our brands" title="Every brand. One platform.">
                <p>
                    Regray brings {brands.length} houses together under one
                    roof: its own Regray Stone, and European makers of surfaces,
                    kitchens, wardrobes, doors and furniture. Browse them by
                    what they make, open each one&rsquo;s collection, or visit
                    its official website.
                </p>
            </PageIntro>

            <nav
                aria-label="Brand categories"
                className="flex flex-wrap justify-center gap-x-8 gap-y-3 px-5 pb-[clamp(3rem,6vw,5rem)]"
            >
                {categories.map((category) => (
                    <a
                        key={category.id}
                        href={`#${category.id}`}
                        className="border-b border-transparent pb-1 text-[0.8125rem] tracking-[0.04em] uppercase transition-colors hover:border-foreground"
                    >
                        {category.label}
                    </a>
                ))}
            </nav>

            {categories.map((category) => (
                <section
                    key={category.id}
                    aria-labelledby={`brands-${category.id}`}
                    id={category.id}
                    className={`scroll-mt-24 pb-[clamp(5rem,9vw,8rem)] ${frame}`}
                >
                    <SectionTitle
                        id={`brands-${category.id}`}
                        title={category.label}
                    >
                        {category.description}
                    </SectionTitle>
                    <div
                        className={`mx-auto grid grid-cols-1 gap-x-7 gap-y-14 ${
                            category.brands.length > 1
                                ? 'md:grid-cols-2'
                                : 'max-w-3xl'
                        } ${category.brands.length > 2 ? 'xl:grid-cols-3' : ''}`}
                    >
                        {category.brands.map((brand) => (
                            <BrandCard key={brand.slug} brand={brand} />
                        ))}
                    </div>
                </section>
            ))}
        </PublicLayout>
    );
}

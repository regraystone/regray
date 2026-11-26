import { Head, Link } from '@inertiajs/react';
import {
    PageIntro,
    body,
    frame,
    textLink,
} from '@/components/public/company-ui';
import { brandUrl } from '@/components/public/public-links';
import PublicLayout from '@/layouts/public-layout';
import type { LandingBrand } from '@/types/home';

/** A brand: its photo with its logo, what it makes, and the way to its page. */
const BrandCard = ({ brand }: { brand: LandingBrand }) => (
    <article className="flex flex-col gap-5" data-testid="brand-introduction">
        <Link
            href={brandUrl(brand.slug)}
            aria-label={`Discover ${brand.name}`}
            className="group relative block aspect-4/3 overflow-hidden rounded-md bg-night"
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
            {brand.description ? (
                <p className={`max-w-120 text-muted-foreground ${body}`}>
                    {brand.description}
                </p>
            ) : null}
            <Link href={brandUrl(brand.slug)} className={`mt-2 ${textLink}`}>
                Discover {brand.name}
            </Link>
        </div>
    </article>
);

/**
 * Our brands: the one page the menu's Our brands opens, introducing every
 * brand of the group, each once, in the order the admin sets on the Brands
 * page: its photo with its logo, what it makes, and its page. Not grouped by
 * what they make: this page only introduces the brands.
 */
export default function CompanyBrands({ brands }: { brands: LandingBrand[] }) {
    return (
        <PublicLayout>
            <Head title="Our brands" />

            <PageIntro eyebrow="Our brands" title="Every brand. One platform.">
                <p>
                    Regray Stone and leading European makers of surfaces,
                    kitchens, wardrobes, doors and furniture, under one roof.
                </p>
            </PageIntro>

            <section
                aria-label="The brands"
                className={`pb-[clamp(5rem,9vw,8rem)] ${frame}`}
            >
                <div className="mx-auto grid grid-cols-1 gap-x-7 gap-y-14 md:grid-cols-2 xl:grid-cols-3">
                    {brands.map((brand) => (
                        <BrandCard key={brand.slug} brand={brand} />
                    ))}
                </div>
            </section>
        </PublicLayout>
    );
}

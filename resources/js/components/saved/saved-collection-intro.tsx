import { Link } from '@inertiajs/react';
import { ArrowRight, Heart } from 'lucide-react';
import { show as brandShow } from '@/routes/dashboard/brands';
import { index as homeIndex } from '@/routes/home';
import type { BrandCardData } from '@/types/home';

/** What the "Explore by category" cards are told about the catalog. */
export type SavedCategories = {
    /** Regray Stone's active stones. */
    stones: number;
    /** Regray Stone's active furniture. */
    furniture: number;
    /** The brands behind Kitchens and Wardrobes, when active. */
    brands: BrandCardData[];
};

/** The brand whose stones and furniture Natural Stone and Furniture open. */
const HOME_BRAND = 'regray-stone';

const plural = (count: number, one: string, many: string) =>
    `${count} ${count === 1 ? one : many}`;

type Category = {
    label: string;
    detail: string;
    image: string;
    /** Where the photo's crop centres, when not in the middle. */
    position?: string;
    href: string;
};

/**
 * The Saved page's opening, in Sir Mark's words: "Your Saved Collection", a
 * line on what it is, Explore collections (the Home catalogue), then
 * "Explore by category", four ways into the catalog, each saying what it
 * holds. Natural Stone and Furniture open Regray Stone's page on its stones
 * or its furniture; Kitchens and Wardrobes open Cucine Lube's and Faer
 * Ambienti's pages, and are left out while their brand is not active.
 */
export default function SavedCollectionIntro({
    categories,
}: {
    categories: SavedCategories;
}) {
    const brandBySlug = new Map(
        categories.brands.map((brand) => [brand.slug, brand]),
    );
    const collection = (kind: 'stones' | 'furniture') =>
        brandShow.url({ brand: HOME_BRAND }, { query: { collection: kind } });
    const brandCategory = (slug: string, label: string): Category | null => {
        const brand = brandBySlug.get(slug);

        return brand?.thumbnail
            ? {
                  label,
                  detail: brand.name,
                  image: brand.thumbnail,
                  href: brandShow.url({ brand: slug }),
              }
            : null;
    };

    const cards = [
        {
            label: 'Natural Stone',
            detail:
                categories.stones > 0
                    ? `${plural(categories.stones, 'stone', 'stones')} · Regray Stone`
                    : 'Regray Stone',
            image: '/images/regray-slabs.jpg',
            // The slab wall is on the photo's left.
            position: '12% center',
            href: collection('stones'),
        },
        {
            label: 'Furniture',
            detail:
                categories.furniture > 0
                    ? `${plural(categories.furniture, 'piece', 'pieces')} · Regray Stone`
                    : 'Regray Stone',
            image: '/images/regray-hero.jpg',
            href: collection('furniture'),
        },
        brandCategory('cucine', 'Kitchens'),
        brandCategory('faer', 'Wardrobes'),
    ].filter((card): card is Category => card !== null);

    return (
        <>
            <section className="flex flex-col items-center gap-4 pt-6 pb-12 text-center sm:pt-10 sm:pb-14">
                <Heart
                    aria-hidden
                    strokeWidth={1.5}
                    className="size-7 text-foreground"
                />
                <h1 className="text-[clamp(1.875rem,3.2vw,2.625rem)] leading-tight font-normal tracking-[-0.01em]">
                    Your Saved Collection
                </h1>
                <p className="max-w-xl text-[0.9375rem] leading-relaxed text-secondary-foreground sm:text-base">
                    A personal selection of materials, furniture, and designs
                    for your next project.
                </p>
                <Link
                    href={homeIndex.url()}
                    className="mt-2 border-b border-foreground px-3 pb-3 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70"
                >
                    Explore collections
                </Link>
            </section>

            <section
                aria-labelledby="saved-categories"
                className="border-t border-border pt-8 pb-4"
            >
                <h2
                    id="saved-categories"
                    className="mb-5 font-sans text-xs font-bold tracking-[0.16em] uppercase"
                >
                    Explore by category
                </h2>
                <ul className="grid grid-cols-2 gap-x-3.5 gap-y-6 md:gap-x-5 lg:grid-cols-4">
                    {cards.map((card) => (
                        <li key={card.label}>
                            <Link
                                href={card.href}
                                className="group flex flex-col gap-3"
                            >
                                <div className="aspect-4/5 overflow-hidden rounded-xl bg-muted">
                                    <img
                                        src={card.image}
                                        style={{
                                            objectPosition: card.position,
                                        }}
                                        alt=""
                                        loading="lazy"
                                        className="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.03] motion-reduce:transition-none"
                                    />
                                </div>
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-[0.9375rem] sm:text-base">
                                            {card.label}
                                        </p>
                                        <p className="mt-0.5 truncate text-xs text-secondary-foreground sm:text-[0.8125rem]">
                                            {card.detail}
                                        </p>
                                    </div>
                                    <ArrowRight
                                        aria-hidden
                                        strokeWidth={1.5}
                                        className="mt-1 size-4 shrink-0 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                                    />
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>
        </>
    );
}

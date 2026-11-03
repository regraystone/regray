import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { MessageCircle, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import ProductCard from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useGridPageSize } from '@/hooks/use-grid-page-size';
import { useSavedFurniture, useSavedStones } from '@/hooks/use-saved-stones';
import { usePermissions } from '@/hooks/use-permissions';
import { show as brandsShow } from '@/routes/brands';
import { show as furnitureShow } from '@/routes/furniture';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import { index as messageIndex } from '@/routes/message';
import type {
    BrandCardData,
    Filters,
    HomeCollection,
    HomePageProps,
} from '@/types/home';

const hero = '/images/regray-hero.jpg';
const END_OF_LIST_TOAST_ID = 'home-end-of-list';

/**
 * The share of each logo image's height its main lettering takes up, ignoring
 * sub-lines like "Gruppo Lube" and the badge around "Lube", keyed by brand
 * slug. The tile sizes each logo by it, so the brand names read at one
 * height and wide wordmarks like Ascale and Faer stay narrow. Regray Stone's
 * mark has no lettering, so it takes Cucine's share and the two square marks
 * match. A brand not listed here gets the default.
 */
const BRAND_NAME_SHARE: Record<string, number> = {
    'regray-stone': 0.46,
    cucine: 0.46,
    ascale: 1,
    faer: 0.51,
    reform: 0.7,
    'res-italia': 0.59,
};
const DEFAULT_NAME_SHARE = 0.6;

/** The first select, Natural Stone first as the default. */
const COLLECTIONS: HomeCollection[] = ['stones', 'furniture'];
const COLLECTION_LABELS: Record<HomeCollection, string> = {
    stones: 'Natural Stone',
    furniture: 'Furniture',
};

const toCollection = (value: string | null | undefined): HomeCollection =>
    value === 'furniture' ? 'furniture' : 'stones';

/** The query param the second select sets in each collection. */
const FILTER_PARAM: Record<HomeCollection, string> = {
    stones: 'category',
    furniture: 'project_category',
};

const filterOf = (filters: Filters, collection: HomeCollection) =>
    collection === 'furniture' ? filters.projectCategory : filters.category;

/** The hero's height, which every brand tile matches. */
const HERO_HEIGHT = 'min-h-[320px] sm:min-h-[560px]';

const Hero = () => (
    <section
        className={`relative flex items-center overflow-hidden text-on-image ${HERO_HEIGHT}`}
    >
        <img
            src={hero}
            alt=""
            aria-hidden
            className="absolute inset-0 size-full object-cover"
        />
        <div className="image-tint absolute inset-0" aria-hidden />
        <div className="relative z-10 mx-auto w-full max-w-6xl px-5 py-10 sm:py-20">
            <p className="eyebrow text-on-image/85">
                Stone &amp; furniture, all in one place
            </p>
            <h1 className="hero-title mt-5 max-w-3xl text-on-image">
                Every brand.
                <br />
                <span className="text-on-image/70">One platform.</span>
            </h1>
            <p className="mt-6 hidden max-w-xl text-base leading-relaxed text-on-image/90 sm:block sm:text-lg">
                Explore a curated catalog of natural stone products and finished
                projects from leading studios — saved, compared, and requested
                in a single workspace.
            </p>
        </div>
    </section>
);

/*
 * One full-width tile per brand, as tall as the hero, linking to its
 * profile: the logo in the bottom left corner on a plain tile, since there
 * are no brand photos, and the brand's description right beside it
 * (under it on phones). The logo names the brand, so its alt text carries
 * the name.
 *
 * Every logo's lettering is `--brand-name-height` tall: the image is that
 * divided by the share of it the lettering takes up.
 */
const BrandTile = ({ brand }: { brand: BrandCardData }) => {
    const nameShare = BRAND_NAME_SHARE[brand.slug] ?? DEFAULT_NAME_SHARE;

    return (
        <Link
            href={brandsShow.url({ brand: brand.slug })}
            className={`flex flex-col justify-end rounded-none bg-muted p-4 transition-opacity [--brand-name-height:1.125rem] hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-6 sm:[--brand-name-height:1.5rem] lg:[--brand-name-height:2rem] ${HERO_HEIGHT}`}
        >
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                <img
                    src={brand.logo}
                    alt={brand.name}
                    loading="lazy"
                    style={{
                        height: `calc(var(--brand-name-height) / ${nameShare})`,
                    }}
                    className="w-auto max-w-full shrink-0 self-start object-contain object-left sm:self-center"
                />
                {brand.description ? (
                    <p className="max-w-md text-sm leading-relaxed text-secondary-foreground sm:max-w-2xl sm:text-base">
                        {brand.description}
                    </p>
                ) : null}
            </div>
        </Link>
    );
};

const Brands = ({ brands }: { brands: BrandCardData[] }) =>
    brands.length === 0 ? null : (
        <ul
            aria-label="Our brands"
            className="grid grid-cols-1 gap-6"
        >
            {brands.map((brand) => (
                <li key={brand.slug}>
                    <BrandTile brand={brand} />
                </li>
            ))}
        </ul>
    );

const CardSkeleton = ({ showHeart }: { showHeart: boolean }) => (
    <div>
        <Skeleton className="aspect-[4/3] w-full rounded-none" />
        <div className="flex items-start justify-between gap-2 pt-3">
            <div className="min-w-0 flex-1 space-y-2 pt-1">
                <Skeleton className="h-5 w-2/3 rounded-none" />
                <Skeleton className="h-4 w-1/2 rounded-none" />
            </div>
            {showHeart && (
                <div className="-mt-2 -mr-3 grid size-11 shrink-0 place-items-center">
                    <Skeleton className="size-5 rounded-none" />
                </div>
            )}
        </div>
    </div>
);

const ScrollFooter = ({
    loading,
    loadingPrevious,
    hasMore,
    isUpdating,
    itemCount,
    listKey,
    showHeart,
    mode,
    perPage,
}: {
    loading: boolean;
    loadingPrevious: boolean;
    hasMore: boolean;
    isUpdating: boolean;
    itemCount: number;
    listKey: string;
    showHeart: boolean;
    mode: HomeCollection;
    perPage: number;
}) => {
    const sentinelRef = useRef<HTMLDivElement>(null);
    const notifiedRef = useRef(false);
    const previousListKeyRef = useRef(listKey);
    const requestStartCountRef = useRef<number | null>(null);
    const hasPaginatedRef = useRef(false);

    useEffect(() => {
        if (previousListKeyRef.current !== listKey) {
            previousListKeyRef.current = listKey;
            notifiedRef.current = false;
            requestStartCountRef.current = null;
            hasPaginatedRef.current = false;
            toast.dismiss(END_OF_LIST_TOAST_ID);
        }

        if (isUpdating) {
            requestStartCountRef.current = null;
            toast.dismiss(END_OF_LIST_TOAST_ID);
            return;
        }

        if (loading) {
            requestStartCountRef.current ??= itemCount;
            return;
        }

        if (requestStartCountRef.current !== null) {
            hasPaginatedRef.current ||=
                itemCount > requestStartCountRef.current;
            requestStartCountRef.current = null;
        }

        const sentinel = sentinelRef.current;
        if (
            !sentinel ||
            hasMore ||
            loadingPrevious ||
            itemCount <= perPage ||
            !hasPaginatedRef.current ||
            notifiedRef.current
        ) {
            return;
        }

        let active = true;
        const observer = new IntersectionObserver((entries) => {
            if (
                !active ||
                notifiedRef.current ||
                !entries.some((entry) => entry.isIntersecting)
            ) {
                return;
            }

            notifiedRef.current = true;
            const message =
                mode === 'stones'
                    ? "That's every stone in this collection."
                    : "That's every piece of furniture in this collection.";
            toast.info("You've reached the bottom.", {
                id: END_OF_LIST_TOAST_ID,
                description: message,
            });
            observer.disconnect();
        });
        observer.observe(sentinel);

        return () => {
            active = false;
            observer.disconnect();
        };
    }, [
        loading,
        loadingPrevious,
        hasMore,
        isUpdating,
        itemCount,
        listKey,
        mode,
        perPage,
    ]);

    if (isUpdating || loading) {
        return (
            <div
                aria-hidden="true"
                className="mt-4 grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5"
            >
                {Array.from({ length: 5 }, (_, index) => (
                    <div
                        key={index}
                        className={
                            index >= 4
                                ? 'hidden xl:block'
                                : index >= 2
                                  ? 'hidden md:block'
                                  : undefined
                        }
                    >
                        <CardSkeleton showHeart={showHeart} />
                    </div>
                ))}
            </div>
        );
    }

    return !hasMore && !loadingPrevious && itemCount > perPage ? (
        <div ref={sentinelRef} aria-hidden="true" className="h-px" />
    ) : null;
};

const Home = () => {
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const savedFurniture = useSavedFurniture();
    const permissions = usePermissions();
    const pageProps = usePage<HomePageProps>().props;
    const canMessage = permissions.can('messages.view');
    const gridPageSize = useGridPageSize();

    const [searchInput, setSearchInput] = useState(
        pageProps.filters.search ?? '',
    );
    const initialCollection = toCollection(pageProps.filters.collection);
    const [selectedCollection, setSelectedCollection] =
        useState<HomeCollection>(initialCollection);
    /*
     * The second select's value: a stone category under Natural Stone, a
     * project type under Furniture. Empty is its "All" option.
     */
    const [selectedFilter, setSelectedFilter] = useState(
        filterOf(pageProps.filters, initialCollection) ?? '',
    );
    const [isUpdating, setIsUpdating] = useState(false);

    const debounceTimerRef = useRef<number | null>(null);
    const lastAppliedRef = useRef({
        search: pageProps.filters.search,
        collection: initialCollection,
        filter: filterOf(pageProps.filters, initialCollection),
    });

    const applyFilters = (
        search: string,
        collection: HomeCollection,
        filter: string,
    ) => {
        const trimmedSearch = search.trim();
        const query: Record<string, string> = {
            per_page: String(gridPageSize ?? pageProps.filters.perPage),
        };

        if (collection === 'furniture') {
            query.collection = 'furniture';
        }

        if (trimmedSearch !== '') {
            query.search = trimmedSearch;
        }

        if (filter !== '') {
            query[FILTER_PARAM[collection]] = filter;
        }

        lastAppliedRef.current = {
            search: trimmedSearch || null,
            collection,
            filter: filter || null,
        };

        setIsUpdating(true);

        router.get(homeIndex.url({ query }), undefined, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
            onFinish: () => setIsUpdating(false),
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchInput(value);

        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
        }

        debounceTimerRef.current = window.setTimeout(() => {
            const trimmed = value.trim();
            if (trimmed !== (lastAppliedRef.current.search ?? '')) {
                applyFilters(trimmed, selectedCollection, selectedFilter);
            }
        }, 300);
    };

    const handleSearchClear = () => {
        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
        setSearchInput('');
        applyFilters('', selectedCollection, selectedFilter);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }
            applyFilters(searchInput, selectedCollection, selectedFilter);
        }
    };

    // A new collection starts from "All": the old filter means nothing there.
    const handleCollectionChange = (value: string) => {
        const collection = toCollection(value);
        setSelectedCollection(collection);
        setSelectedFilter('');
        applyFilters(searchInput, collection, '');
    };

    const handleFilterChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedFilter(value);
        applyFilters(searchInput, selectedCollection, value);
    };

    useEffect(() => {
        const currentUrl = new URL(window.location.href);
        const urlSearch = currentUrl.searchParams.get('search') ?? '';
        const urlCollection = toCollection(
            currentUrl.searchParams.get('collection'),
        );
        const urlFilter =
            currentUrl.searchParams.get(FILTER_PARAM[urlCollection]) ?? '';

        if (
            urlSearch !== (lastAppliedRef.current.search ?? '') ||
            urlCollection !== lastAppliedRef.current.collection ||
            urlFilter !== (lastAppliedRef.current.filter ?? '')
        ) {
            setSearchInput(urlSearch);
            setSelectedCollection(urlCollection);
            setSelectedFilter(urlFilter);
            lastAppliedRef.current = {
                search: urlSearch || null,
                collection: urlCollection,
                filter: urlFilter || null,
            };
        }
    }, [pageProps.filters]);

    /**
     * Pages must all be the same size for the infinite scroll to line up, so a
     * page size that is not this screen's (the server's default on the first
     * visit, or the old screen's after a resize or rotation) reloads the list
     * from its first page at the right size, keeping the current filters.
     */
    useEffect(() => {
        if (
            gridPageSize === null ||
            gridPageSize === pageProps.filters.perPage
        ) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.set('per_page', String(gridPageSize));

        router.get(`${url.pathname}${url.search}`, undefined, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    }, [gridPageSize, pageProps.filters.perPage]);

    useEffect(() => {
        return () => {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    // What the server sent, which lags the selects while a request is out.
    const collection = toCollection(pageProps.filters.collection);
    const isFurniture = collection === 'furniture';
    const list = isFurniture ? pageProps.furniture : pageProps.stones;
    const stones = pageProps.stones?.data ?? [];
    const furniture = pageProps.furniture?.data ?? [];
    const resultCount = isFurniture ? furniture.length : stones.length;
    const filterOptions = pageProps.filterOptions;
    const listKey = JSON.stringify([
        collection,
        pageProps.filters.search,
        pageProps.filters.category,
        pageProps.filters.projectCategory,
        pageProps.filters.perPage,
    ]);
    const choosingFurniture = selectedCollection === 'furniture';
    const searchLabel = choosingFurniture
        ? 'Search furniture'
        : 'Search stones';
    const filterOptionsShown =
        (choosingFurniture
            ? filterOptions.projectTypes
            : filterOptions.categories) ?? [];
    const allLabel = choosingFurniture
        ? 'All Product Types'
        : 'All Natural Stones';
    const foundLabel = isFurniture
        ? resultCount === 1
            ? '1 piece of furniture found'
            : `${resultCount} pieces of furniture found`
        : resultCount === 1
          ? '1 stone found'
          : `${resultCount} stones found`;

    return (
        <div className="w-full">
            <Hero />
            <section
                id="collection"
                aria-labelledby="collection-heading"
                className="mt-8 md:mt-12"
            >
                <div className="mb-6 border-b border-sidebar-border pb-4">
                    <h2
                        id="collection-heading"
                        className="min-w-0 text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
                    >
                        Our Collection
                    </h2>
                </div>

                <Brands brands={pageProps.brands ?? []} />

                <div className="mt-16 mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4 sm:flex-row sm:items-center sm:justify-between md:mt-24">
                    <div className="relative w-full sm:max-w-xs">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                            aria-hidden
                        />
                        <Input
                            type="search"
                            value={searchInput}
                            onChange={(event) =>
                                handleSearchChange(event.target.value)
                            }
                            onKeyDown={handleSearchKeyDown}
                            placeholder={searchLabel}
                            aria-label={searchLabel}
                            className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                        />
                        {searchInput ? (
                            <button
                                type="button"
                                onClick={handleSearchClear}
                                aria-label="Clear search"
                                className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <X className="size-4" aria-hidden />
                            </button>
                        ) : null}
                    </div>

                    <div className="flex w-full flex-col gap-4 sm:w-auto sm:flex-row">
                        <div className="w-full sm:w-44">
                            <Select
                                value={selectedCollection}
                                onValueChange={handleCollectionChange}
                            >
                                <SelectTrigger
                                    className="w-full rounded-none"
                                    aria-label="Collection"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {COLLECTIONS.map((value) => (
                                        <SelectItem
                                            key={value}
                                            value={value}
                                            className="rounded-none"
                                        >
                                            {COLLECTION_LABELS[value]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="w-full sm:w-52">
                            <Select
                                value={selectedFilter || 'all'}
                                onValueChange={handleFilterChange}
                            >
                                <SelectTrigger
                                    className="w-full rounded-none"
                                    aria-label={
                                        choosingFurniture
                                            ? 'Product type'
                                            : 'Stone category'
                                    }
                                >
                                    <SelectValue placeholder={allLabel} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="all"
                                        className="rounded-none"
                                    >
                                        {allLabel}
                                    </SelectItem>
                                    {filterOptionsShown.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                            className="rounded-none"
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </div>

                <div
                    aria-busy={isUpdating}
                    aria-live="polite"
                    className={isUpdating ? 'opacity-60' : ''}
                >
                    <div
                        className="sr-only"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        {foundLabel}
                    </div>
                    {isUpdating || !list ? (
                        <div
                            aria-hidden="true"
                            className="mt-4 grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5"
                        >
                            {Array.from({ length: 10 }, (_, index) => (
                                <div
                                    key={index}
                                    className={
                                        index >= 8
                                            ? 'hidden xl:block'
                                            : index >= 4
                                              ? 'hidden md:block'
                                              : undefined
                                    }
                                >
                                    <CardSkeleton showHeart={canSave} />
                                </div>
                            ))}
                        </div>
                    ) : resultCount === 0 ? (
                        <EmptyState
                            title={
                                isFurniture
                                    ? 'No furniture found'
                                    : 'No stones found'
                            }
                            description="The search and filter didn't match any items."
                        />
                    ) : (
                        <InfiniteScroll
                            data={collection}
                            next={({ loading, loadingPrevious, hasMore }) => (
                                <ScrollFooter
                                    loading={loading}
                                    loadingPrevious={loadingPrevious}
                                    hasMore={hasMore}
                                    isUpdating={isUpdating}
                                    itemCount={resultCount}
                                    listKey={listKey}
                                    showHeart={canSave}
                                    mode={collection}
                                    perPage={pageProps.filters.perPage}
                                />
                            )}
                        >
                            <div className="grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5">
                                {isFurniture
                                    ? furniture.map((item, index) => (
                                          <ProductCard
                                              key={item.slug}
                                              product={{
                                                  ...item,
                                                  category: item.projectType,
                                              }}
                                              aspect="aspect-[4/3]"
                                              priority={index < 5}
                                              href={furnitureShow.url({
                                                  furniture: item.slug,
                                              })}
                                              saved={savedFurniture.savedIds.has(
                                                  item.slug,
                                              )}
                                              onToggleSave={
                                                  canSave
                                                      ? () =>
                                                            savedFurniture.toggleSave(
                                                                item.slug,
                                                            )
                                                      : undefined
                                              }
                                          />
                                      ))
                                    : stones.map((stone, index) => (
                                          <ProductCard
                                              key={stone.slug}
                                              product={stone}
                                              aspect="aspect-[4/3]"
                                              priority={index < 5}
                                              href={homeShow.url({
                                                  slug: stone.slug,
                                              })}
                                              saved={savedIds.has(stone.slug)}
                                              onToggleSave={
                                                  canSave
                                                      ? () =>
                                                            toggleSave(
                                                                stone.slug,
                                                            )
                                                      : undefined
                                              }
                                          />
                                      ))}
                            </div>
                        </InfiniteScroll>
                    )}
                </div>
            </section>

            {/*
             * A way to reach the team from anywhere on the page, for whoever may
             * use messages. Fixed to the corner, clear of the phone's bars.
             */}
            {canMessage ? (
                <Button
                    asChild
                    size="lg"
                    className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-40 shadow-lg"
                >
                    <Link href={messageIndex.url()}>
                        <MessageCircle aria-hidden />
                        Message us
                    </Link>
                </Button>
            ) : null}
        </div>
    );
};

export default Home;

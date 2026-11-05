import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Search, Star, X } from 'lucide-react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import ProductCard from '@/components/product-card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    gridPageSizeCookieIs,
    useGridPageSize,
} from '@/hooks/use-grid-page-size';
import { useSavedFurniture, useSavedStones } from '@/hooks/use-saved-stones';
import {
    COLLECTION_OPTIONS,
    collectionQuery,
    noResultsTitle,
    searchLabel,
    secondFilter,
    toCollection,
    type BrandCollectionKind,
} from '@/lib/brand-collection';
import { login } from '@/routes';
import { show as brandFurnitureShow } from '@/routes/brands/furniture';
import { show as brandStoneShow } from '@/routes/brands/stones';
import { show as dashboardBrandFurnitureShow } from '@/routes/dashboard/brands/furniture';
import { show as dashboardBrandStoneShow } from '@/routes/dashboard/brands/stones';
import type { BrandFilters, BrandPageProps, BrandProduct } from '@/types/home';

const END_OF_LIST_TOAST_ID = 'brand-end-of-list';
const GRID_CLASSES =
    'grid grid-cols-2 gap-3.5 md:grid-cols-3 md:gap-4 lg:grid-cols-3 lg:gap-5';

const filterOf = (filters: BrandFilters, collection: BrandCollectionKind) =>
    collection === 'furniture'
        ? filters.furnitureType
        : collection === 'stones'
          ? filters.category
          : null;

const endOfListMessage = (collection: BrandCollectionKind) =>
    ({
        all: "That's every product in this collection.",
        stones: "That's every stone in this collection.",
        furniture: "That's every piece of furniture in this collection.",
    })[collection];

const foundLabel = (collection: BrandCollectionKind, count: number) => {
    const [one, many] = {
        all: ['product', 'products'],
        stones: ['stone', 'stones'],
        furniture: ['piece of furniture', 'pieces of furniture'],
    }[collection];

    return `${count} ${count === 1 ? one : many} found`;
};

const CardSkeleton = ({ showHeart }: { showHeart: boolean }) => (
    <div>
        <Skeleton className="aspect-[4/3] w-full rounded-none" />
        <div className="flex items-start justify-between gap-2 pt-3">
            <div className="min-w-0 flex-1 space-y-2 pt-1">
                <Skeleton className="h-5 w-2/3 rounded-none" />
                <Skeleton className="h-4 w-1/2 rounded-none" />
            </div>
            {showHeart && (
                <div className="-mt-2 -mr-3 grid size-11 shrink-0 place-items-center lg:hidden">
                    <Skeleton className="size-5 rounded-none" />
                </div>
            )}
        </div>
    </div>
);

const SkeletonRow = ({ showHeart }: { showHeart: boolean }) => (
    <div aria-hidden="true" className={`mt-4 ${GRID_CLASSES}`}>
        {Array.from({ length: 3 }, (_, index) => (
            <div
                key={index}
                className={index >= 2 ? 'hidden md:block' : undefined}
            >
                <CardSkeleton showHeart={showHeart} />
            </div>
        ))}
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
    mode: BrandCollectionKind;
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
            toast.info("You've reached the bottom.", {
                id: END_OF_LIST_TOAST_ID,
                description: endOfListMessage(mode),
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
        return <SkeletonRow showHeart={showHeart} />;
    }

    return !hasMore && !loadingPrevious && itemCount > perPage ? (
        <div ref={sentinelRef} aria-hidden="true" className="h-px" />
    ) : null;
};

/**
 * A brand's "Our Collection": its stones and furniture, one list with one
 * search and one pagination. With no active products at all it says so and
 * shows no filters, because there is nothing to narrow.
 */
export default function BrandCollection() {
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const savedFurniture = useSavedFurniture();
    const pageProps = usePage<BrandPageProps & { auth: { user: unknown } }>()
        .props;
    const isSignedIn = pageProps.auth?.user != null;
    const brand = pageProps.brand.data;
    const gridPageSize = useGridPageSize();

    const [searchInput, setSearchInput] = useState(
        pageProps.filters.search ?? '',
    );
    const initialCollection = toCollection(pageProps.filters.collection);
    const [selectedCollection, setSelectedCollection] =
        useState<BrandCollectionKind>(initialCollection);
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
        collection: BrandCollectionKind,
        filter: string,
    ) => {
        lastAppliedRef.current = {
            search: search.trim() || null,
            collection,
            filter: filter || null,
        };

        setIsUpdating(true);

        // The same page answers for guests and signed-in users, each at their own URL.
        router.get(
            window.location.pathname,
            collectionQuery({
                collection,
                search,
                filter,
            }),
            {
                preserveState: false,
                preserveScroll: true,
                replace: true,
                onFinish: () => setIsUpdating(false),
            },
        );
    };

    const clearDebounce = () => {
        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
    };

    const handleSearchChange = (value: string) => {
        setSearchInput(value);
        clearDebounce();

        debounceTimerRef.current = window.setTimeout(() => {
            const trimmed = value.trim();
            if (trimmed !== (lastAppliedRef.current.search ?? '')) {
                applyFilters(trimmed, selectedCollection, selectedFilter);
            }
        }, 300);
    };

    const handleSearchClear = () => {
        clearDebounce();
        setSearchInput('');
        applyFilters('', selectedCollection, selectedFilter);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            clearDebounce();
            applyFilters(searchInput, selectedCollection, selectedFilter);
        }
    };

    // Changing the first select resets the second.
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
        const urlParam = secondFilter(urlCollection, {})?.param;
        const urlFilter = urlParam
            ? (currentUrl.searchParams.get(urlParam) ?? '')
            : '';

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

    /*
     * A first visit, or a screen that crossed the tablet width, was answered at
     * the other size. The cookie is set by now, so asking again corrects it. If
     * the browser refused the cookie, asking again would only repeat forever.
     */
    useEffect(() => {
        if (
            gridPageSize === null ||
            gridPageSize === pageProps.filters.perPage ||
            !gridPageSizeCookieIs(gridPageSize)
        ) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        url.searchParams.delete('per_page');

        router.get(`${url.pathname}${url.search}`, undefined, {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    }, [gridPageSize, pageProps.filters.perPage]);

    useEffect(() => clearDebounce, []);

    const handleGuestSave = () => {
        router.visit(login.url());
    };

    const productHref = (product: BrandProduct) => {
        if (product.kind === 'stone') {
            const params = { brand: product.brand.slug, stone: product.slug };

            return isSignedIn
                ? dashboardBrandStoneShow.url(params)
                : brandStoneShow.url(params);
        }

        const params = { brand: product.brand.slug, furniture: product.slug };

        return isSignedIn
            ? dashboardBrandFurnitureShow.url(params)
            : brandFurnitureShow.url(params);
    };

    const collection = toCollection(pageProps.filters.collection);
    const products = pageProps.products?.data ?? [];
    const second = secondFilter(selectedCollection, pageProps.filterOptions);
    const label = searchLabel(selectedCollection);
    const listKey = JSON.stringify([
        collection,
        pageProps.filters.search,
        pageProps.filters.category,
        pageProps.filters.furnitureType,
        pageProps.filters.perPage,
    ]);

    return (
        <section aria-labelledby="brand-collection-heading" className="pb-12">
            <div className="mb-6 border-b border-sidebar-border pb-4">
                <h2
                    id="brand-collection-heading"
                    className="text-2xl font-bold tracking-[-0.04em] uppercase sm:text-3xl"
                >
                    Our Collection
                </h2>
            </div>

            {!pageProps.hasProducts ? (
                <EmptyState
                    title="Products coming soon"
                    description={`The ${brand.name} collection is on its way. Check back soon.`}
                />
            ) : (
                <>
                    <div className="mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4 sm:flex-row sm:items-center sm:justify-between">
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
                                placeholder={label}
                                aria-label={label}
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
                                        aria-label="Category"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent avoidCollisions>
                                        {COLLECTION_OPTIONS.map((option) => (
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

                            {second ? (
                                <div className="w-full sm:w-52">
                                    <Select
                                        value={selectedFilter || 'all'}
                                        onValueChange={handleFilterChange}
                                    >
                                        <SelectTrigger
                                            className="w-full rounded-none"
                                            aria-label={second.ariaLabel}
                                        >
                                            <SelectValue
                                                placeholder={
                                                    second.defaultLabel
                                                }
                                            />
                                        </SelectTrigger>
                                        <SelectContent avoidCollisions>
                                            <SelectItem
                                                value="all"
                                                className="rounded-none"
                                            >
                                                {second.defaultLabel}
                                            </SelectItem>
                                            {second.options.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                    className="rounded-none"
                                                >
                                                    {option.label}
                                                    {option.bestSeller ? (
                                                        <>
                                                            <Star
                                                                className="size-3 shrink-0 fill-current"
                                                                aria-hidden
                                                            />
                                                            <span className="sr-only">
                                                                Best seller
                                                            </span>
                                                        </>
                                                    ) : null}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : null}
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
                            {foundLabel(collection, products.length)}
                        </div>
                        {isUpdating || !pageProps.products ? (
                            <SkeletonRow showHeart={canSave} />
                        ) : products.length === 0 ? (
                            <EmptyState
                                title={noResultsTitle(collection)}
                                description="The search and filter didn't match any items."
                            />
                        ) : (
                            <InfiniteScroll
                                data="products"
                                next={({
                                    loading,
                                    loadingPrevious,
                                    hasMore,
                                }) => (
                                    <ScrollFooter
                                        loading={loading}
                                        loadingPrevious={loadingPrevious}
                                        hasMore={hasMore}
                                        isUpdating={isUpdating}
                                        itemCount={products.length}
                                        listKey={listKey}
                                        showHeart={canSave}
                                        mode={collection}
                                        perPage={pageProps.filters.perPage}
                                    />
                                )}
                            >
                                <div className={GRID_CLASSES}>
                                    {products.map((product, index) => (
                                        <ProductCard
                                            key={`${product.kind}-${product.slug}`}
                                            product={
                                                product.kind === 'stone'
                                                    ? product
                                                    : {
                                                          ...product,
                                                          category:
                                                              product.furnitureType,
                                                      }
                                            }
                                            aspect="aspect-[4/3]"
                                            priority={index < 5}
                                            href={productHref(product)}
                                            saved={
                                                product.kind === 'stone'
                                                    ? savedIds.has(product.slug)
                                                    : savedFurniture.savedIds.has(
                                                          product.slug,
                                                      )
                                            }
                                            onToggleSave={
                                                canSave
                                                    ? () =>
                                                          product.kind ===
                                                          'stone'
                                                              ? toggleSave(
                                                                    product.slug,
                                                                )
                                                              : savedFurniture.toggleSave(
                                                                    product.slug,
                                                                )
                                                    : handleGuestSave
                                            }
                                        />
                                    ))}
                                </div>
                            </InfiniteScroll>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

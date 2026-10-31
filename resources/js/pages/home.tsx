import { InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { MessageCircle, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import StoneCard from '@/components/stone-card';
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
import { useSavedStones } from '@/hooks/use-saved-stones';
import { usePermissions } from '@/hooks/use-permissions';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import { index as messageIndex } from '@/routes/message';
import type { HomePageProps } from '@/types/home';

const hero = '/images/regray-hero.jpg';
const END_OF_LIST_TOAST_ID = 'home-end-of-list';

const Hero = () => (
    <section className="relative flex min-h-[280px] items-center overflow-hidden text-on-image sm:min-h-[480px]">
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

const CardSkeleton = ({ showHeart }: { showHeart: boolean }) => (
    <div className="overflow-hidden rounded-md border border-sidebar-border bg-background">
        <Skeleton className="aspect-[4/3] w-full rounded-none" />
        <div className="flex h-[60px] items-center justify-between gap-2 px-3 py-2">
            <div className="min-w-0 flex-1 space-y-1">
                <Skeleton className="h-4 w-2/3 rounded-none" />
                <Skeleton className="h-3 w-1/2 rounded-none" />
            </div>
            {showHeart && (
                <div className="grid size-11 shrink-0 place-items-center">
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
    mode: 'stones' | 'projects';
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
                    : "That's every project in this collection.";
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
                className="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-5 xl:grid-cols-5"
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
                        <CardSkeleton
                            showHeart={showHeart && mode === 'stones'}
                        />
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
    const permissions = usePermissions();
    const pageProps = usePage<HomePageProps>().props;
    const canMessage = permissions.can('messages.view');
    const gridPageSize = useGridPageSize();

    const [searchInput, setSearchInput] = useState(
        pageProps.filters.search ?? '',
    );
    const [selectedCategory, setSelectedCategory] = useState(
        pageProps.filters.category ?? '',
    );
    const [isUpdating, setIsUpdating] = useState(false);

    const debounceTimerRef = useRef<number | null>(null);
    const lastAppliedRef = useRef({
        search: pageProps.filters.search,
        category: pageProps.filters.category,
    });

    const applyFilters = (search: string, category: string) => {
        const trimmedSearch = search.trim();
        const query: Record<string, string> = {
            per_page: String(gridPageSize ?? pageProps.filters.perPage),
        };

        if (trimmedSearch !== '') {
            query.search = trimmedSearch;
        }

        if (category !== '') {
            query.category = category;
        }

        lastAppliedRef.current = {
            search: trimmedSearch || null,
            category: category || null,
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
                applyFilters(trimmed, selectedCategory);
            }
        }, 300);
    };

    const handleSearchClear = () => {
        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
        setSearchInput('');
        applyFilters('', selectedCategory);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }
            applyFilters(searchInput, selectedCategory);
        }
    };

    const handleCategoryChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedCategory(value);
        applyFilters(searchInput, value);
    };

    useEffect(() => {
        const currentUrl = new URL(window.location.href);
        const urlSearch = currentUrl.searchParams.get('search') ?? '';
        const urlCategory = currentUrl.searchParams.get('category') ?? '';

        if (
            urlSearch !== (lastAppliedRef.current.search ?? '') ||
            urlCategory !== (lastAppliedRef.current.category ?? '')
        ) {
            setSearchInput(urlSearch);
            setSelectedCategory(urlCategory);
            lastAppliedRef.current = {
                search: urlSearch || null,
                category: urlCategory || null,
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

    const stones = pageProps.stones?.data ?? [];
    const resultCount = stones.length;
    const filterOptions = pageProps.filterOptions;
    const listKey = JSON.stringify([
        pageProps.filters.search,
        pageProps.filters.category,
        pageProps.filters.perPage,
    ]);

    return (
        <div className="w-full">
            <Hero />
            <section
                id="products"
                aria-labelledby="products-heading"
                className="mt-8 md:mt-12"
            >
                <div className="mb-6 border-b border-sidebar-border pb-4">
                    <h2
                        id="products-heading"
                        className="min-w-0 text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
                    >
                        Our Collections
                    </h2>

                    <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
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
                                placeholder="Search stones"
                                aria-label="Search stones"
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

                        <div className="w-full sm:w-44">
                            <Select
                                value={selectedCategory || 'all'}
                                onValueChange={handleCategoryChange}
                            >
                                <SelectTrigger
                                    className="w-full rounded-none"
                                    aria-label="Stone category"
                                >
                                    <SelectValue placeholder="All Natural Stones" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="all"
                                        className="rounded-none"
                                    >
                                        All Natural Stones
                                    </SelectItem>
                                    {filterOptions.categories?.map((cat) => (
                                        <SelectItem
                                            key={cat.value}
                                            value={cat.value}
                                            className="rounded-none"
                                        >
                                            {cat.label}
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
                        {resultCount === 1
                            ? '1 stone found'
                            : `${resultCount} stones found`}
                    </div>
                    {isUpdating || !pageProps.stones ? (
                        <div
                            aria-hidden="true"
                            className="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-5 xl:grid-cols-5"
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
                            title="No stones found"
                            description="The search and filter didn't match any items."
                        />
                    ) : (
                        <InfiniteScroll
                            data="stones"
                            next={({ loading, loadingPrevious, hasMore }) => (
                                <ScrollFooter
                                    loading={loading}
                                    loadingPrevious={loadingPrevious}
                                    hasMore={hasMore}
                                    isUpdating={isUpdating}
                                    itemCount={resultCount}
                                    listKey={listKey}
                                    showHeart={canSave}
                                    mode="stones"
                                    perPage={pageProps.filters.perPage}
                                />
                            )}
                        >
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-5 xl:grid-cols-5">
                                {stones.map((stone, index) => (
                                    <StoneCard
                                        key={stone.slug}
                                        stone={stone}
                                        aspect="aspect-[4/3]"
                                        priority={index < 5}
                                        href={homeShow.url({
                                            slug: stone.slug,
                                        })}
                                        saved={savedIds.has(stone.slug)}
                                        onToggleSave={
                                            canSave
                                                ? () => toggleSave(stone.slug)
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

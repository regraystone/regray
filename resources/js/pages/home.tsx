import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type MouseEvent } from 'react';
import { ArrowDown, Search, X } from 'lucide-react';
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
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex, stone as homeStone } from '@/routes/home';
import type { HomePageProps } from '@/types/home';
const hero = '/images/regray-hero.jpg';
const END_OF_LIST_TOAST_ID = 'home-end-of-list';

const handleBrowseClick = (e: MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    document.getElementById('products')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};

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
            <Button asChild className="mt-7 sm:mt-9">
                <a href="#products" onClick={handleBrowseClick}>
                    Browse More <ArrowDown className="size-4" aria-hidden />
                </a>
            </Button>
        </div>
    </section>
);

const StoneCardSkeleton = ({ showHeart }: { showHeart: boolean }) => (
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

const HomeScrollFooter = ({
    loading,
    loadingPrevious,
    hasMore,
    isUpdating,
    itemCount,
    listKey,
    showHeart,
}: {
    loading: boolean;
    loadingPrevious: boolean;
    hasMore: boolean;
    isUpdating: boolean;
    itemCount: number;
    listKey: string;
    showHeart: boolean;
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
            itemCount <= 12 ||
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
                description: "That's every stone in this collection.",
            });
            observer.disconnect();
        });
        observer.observe(sentinel);

        return () => {
            active = false;
            observer.disconnect();
        };
    }, [loading, loadingPrevious, hasMore, isUpdating, itemCount, listKey]);

    if (isUpdating) {
        return null;
    }

    if (loading) {
        return (
            <div
                aria-hidden="true"
                className="mt-4 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3"
            >
                {Array.from({ length: 3 }, (_, index) => (
                    <div
                        key={index}
                        className={index === 2 ? 'hidden lg:block' : undefined}
                    >
                        <StoneCardSkeleton showHeart={showHeart} />
                    </div>
                ))}
            </div>
        );
    }

    return !hasMore && !loadingPrevious && itemCount > 12 ? (
        <div ref={sentinelRef} aria-hidden="true" className="h-px" />
    ) : null;
};

const Home = () => {
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const { stones, filters, filterOptions } = usePage<HomePageProps>().props;

    const [searchInput, setSearchInput] = useState(filters.search ?? '');
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category ?? '',
    );
    const [selectedSize, setSelectedSize] = useState(filters.size ?? '');
    const [selectedThickness, setSelectedThickness] = useState(
        filters.thickness ?? '',
    );
    const [isUpdating, setIsUpdating] = useState(false);

    const debounceTimerRef = useRef<number | null>(null);
    const lastAppliedRef = useRef({
        search: filters.search,
        category: filters.category,
        size: filters.size,
        thickness: filters.thickness,
    });

    const applyFilters = (
        search: string,
        category: string,
        size: string,
        thickness: string,
    ) => {
        const trimmedSearch = search.trim();
        const query: Record<string, string> = {};

        if (trimmedSearch !== '') {
            query.search = trimmedSearch;
        }
        if (category !== '') {
            query.category = category;
        }
        if (size !== '') {
            query.size = size;
        }
        if (thickness !== '') {
            query.thickness = thickness;
        }

        lastAppliedRef.current = {
            search: trimmedSearch || null,
            category: category || null,
            size: size || null,
            thickness: thickness || null,
        };

        setIsUpdating(true);

        router.get(homeIndex.url({ query }), undefined, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['stones', 'filters'],
            reset: ['stones'],
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
                applyFilters(
                    trimmed,
                    selectedCategory,
                    selectedSize,
                    selectedThickness,
                );
            }
        }, 300);
    };

    const handleSearchClear = () => {
        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
        setSearchInput('');
        applyFilters('', selectedCategory, selectedSize, selectedThickness);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }
            applyFilters(
                searchInput,
                selectedCategory,
                selectedSize,
                selectedThickness,
            );
        }
    };

    const handleCategoryChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedCategory(value);
        applyFilters(searchInput, value, selectedSize, selectedThickness);
    };

    const handleSizeChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedSize(value);
        applyFilters(searchInput, selectedCategory, value, selectedThickness);
    };

    const handleThicknessChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedThickness(value);
        applyFilters(searchInput, selectedCategory, selectedSize, value);
    };

    useEffect(() => {
        const currentUrl = new URL(window.location.href);
        const urlSearch = currentUrl.searchParams.get('search') ?? '';
        const urlCategory = currentUrl.searchParams.get('category') ?? '';
        const urlSize = currentUrl.searchParams.get('size') ?? '';
        const urlThickness = currentUrl.searchParams.get('thickness') ?? '';

        if (
            urlSearch !== (lastAppliedRef.current.search ?? '') ||
            urlCategory !== (lastAppliedRef.current.category ?? '') ||
            urlSize !== (lastAppliedRef.current.size ?? '') ||
            urlThickness !== (lastAppliedRef.current.thickness ?? '')
        ) {
            setSearchInput(urlSearch);
            setSelectedCategory(urlCategory);
            setSelectedSize(urlSize);
            setSelectedThickness(urlThickness);
            lastAppliedRef.current = {
                search: urlSearch || null,
                category: urlCategory || null,
                size: urlSize || null,
                thickness: urlThickness || null,
            };
        }
    }, [filters]);

    useEffect(() => {
        return () => {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    const resultCount = stones.data.length;
    const listKey = JSON.stringify([
        filters.search,
        filters.category,
        filters.size,
        filters.thickness,
    ]);

    return (
        <div className="mx-auto w-full max-w-6xl">
            <Hero />
            <section
                id="products"
                aria-labelledby="products-heading"
                className="mt-8 md:mt-12"
            >
                <div className="mb-6 border-b border-sidebar-border pb-4">
                    <h2
                        id="products-heading"
                        className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
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

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3">
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
                                        {filterOptions.categories.map(
                                            (stone) => (
                                                <SelectItem
                                                    key={stone.value}
                                                    value={stone.value}
                                                    className="rounded-none"
                                                >
                                                    {stone.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-full sm:w-44">
                                <Select
                                    value={selectedSize || 'all'}
                                    onValueChange={handleSizeChange}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Size"
                                    >
                                        <SelectValue placeholder="All Sizes" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            value="all"
                                            className="rounded-none"
                                        >
                                            All Sizes
                                        </SelectItem>
                                        {filterOptions.sizes.map((cat) => (
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
                            <div className="w-full sm:w-44">
                                <Select
                                    value={selectedThickness || 'all'}
                                    onValueChange={handleThicknessChange}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Thickness"
                                    >
                                        <SelectValue placeholder="All Thicknesses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            value="all"
                                            className="rounded-none"
                                        >
                                            All Thicknesses
                                        </SelectItem>
                                        {filterOptions.thicknesses.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                    className="rounded-none"
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
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
                    {stones.data.length === 0 ? (
                        <EmptyState
                            title="No stones found"
                            description="The search and filters didn't match any stones."
                        />
                    ) : (
                        <InfiniteScroll
                            data="stones"
                            next={({ loading, loadingPrevious, hasMore }) => (
                                <HomeScrollFooter
                                    loading={loading}
                                    loadingPrevious={loadingPrevious}
                                    hasMore={hasMore}
                                    isUpdating={isUpdating}
                                    itemCount={resultCount}
                                    listKey={listKey}
                                    showHeart={canSave}
                                />
                            )}
                        >
                            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                {stones.data.map((stone, index) => (
                                    <StoneCard
                                        key={stone.slug}
                                        stone={stone}
                                        aspect="aspect-[4/3]"
                                        priority={index < 3}
                                        href={homeStone.url({
                                            stone: stone.slug,
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
        </div>
    );
};

export default Home;

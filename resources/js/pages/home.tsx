import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type MouseEvent } from 'react';
import { ArrowDown, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import StoneCard from '@/components/stone-card';
import ProjectCard from '@/components/project-card';
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
import { usePermissions } from '@/hooks/use-permissions';
import { index as homeIndex, show as homeShow } from '@/routes/home';
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

const CollectionSwitch = ({
    activeCollection,
    onCollectionChange,
}: {
    activeCollection: 'stones' | 'projects';
    onCollectionChange: (collection: 'stones' | 'projects') => void;
}) => {
    const options = [
        { value: 'stones', label: 'Natural Stones' },
        { value: 'projects', label: 'Design Applicant' },
    ];

    return (
        <div
            role="radiogroup"
            className="inline-flex items-center gap-1 rounded-md border border-sidebar-border p-1"
        >
            {options.map((option) => (
                <button
                    key={option.value}
                    role="radio"
                    aria-checked={activeCollection === option.value}
                    onClick={() =>
                        onCollectionChange(
                            option.value as 'stones' | 'projects',
                        )
                    }
                    className={`rounded-sm px-2.5 py-1 text-sm font-medium transition-colors ${
                        activeCollection === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-background text-foreground hover:bg-sidebar-border'
                    }`}
                >
                    <span className="hidden sm:inline">{option.label}</span>
                    <span className="sm:hidden">
                        {option.value === 'stones' ? 'Stones' : 'Projects'}
                    </span>
                </button>
            ))}
        </div>
    );
};

const ScrollFooter = ({
    loading,
    loadingPrevious,
    hasMore,
    isUpdating,
    itemCount,
    listKey,
    showHeart,
    mode,
}: {
    loading: boolean;
    loadingPrevious: boolean;
    hasMore: boolean;
    isUpdating: boolean;
    itemCount: number;
    listKey: string;
    showHeart: boolean;
    mode: 'stones' | 'projects';
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
    ]);

    if (isUpdating || loading) {
        return (
            <div
                aria-hidden="true"
                className="mt-4 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3"
            >
                {Array.from({ length: 6 }, (_, index) => (
                    <div
                        key={index}
                        className={
                            index >= 4
                                ? 'hidden md:block'
                                : index >= 2
                                  ? 'hidden lg:block'
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

    return !hasMore && !loadingPrevious && itemCount > 12 ? (
        <div ref={sentinelRef} aria-hidden="true" className="h-px" />
    ) : null;
};

const Home = () => {
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const permissions = usePermissions();
    const pageProps = usePage<HomePageProps>().props;
    const canViewProjects = permissions.can('home-projects.view');

    const [searchInput, setSearchInput] = useState(
        pageProps.filters.search ?? '',
    );
    const [selectedCategory, setSelectedCategory] = useState(
        pageProps.filters.category ?? '',
    );
    const [selectedSize, setSelectedSize] = useState(
        pageProps.filters.size ?? '',
    );
    const [selectedThickness, setSelectedThickness] = useState(
        pageProps.filters.thickness ?? '',
    );
    const [selectedProjectCategory, setSelectedProjectCategory] = useState(
        pageProps.filters.projectCategory ?? '',
    );
    const [isUpdating, setIsUpdating] = useState(false);
    const [activeCollection, setActiveCollection] = useState<
        'stones' | 'projects'
    >(pageProps.filters.collection === 'projects' ? 'projects' : 'stones');

    const debounceTimerRef = useRef<number | null>(null);
    const lastAppliedRef = useRef({
        search: pageProps.filters.search,
        category: pageProps.filters.category,
        size: pageProps.filters.size,
        thickness: pageProps.filters.thickness,
        projectCategory: pageProps.filters.projectCategory,
        collection: activeCollection,
    });

    const applyFilters = (
        search: string,
        category: string,
        size: string,
        thickness: string,
        projectCategory: string,
        collection: 'stones' | 'projects',
    ) => {
        const trimmedSearch = search.trim();
        const query: Record<string, string> = {};

        if (trimmedSearch !== '') {
            query.search = trimmedSearch;
        }

        if (collection === 'projects') {
            if (projectCategory !== '') {
                query.project_category = projectCategory;
            }
            query.collection = 'projects';
        } else {
            if (category !== '') {
                query.category = category;
            }
            if (size !== '') {
                query.size = size;
            }
            if (thickness !== '') {
                query.thickness = thickness;
            }
        }

        lastAppliedRef.current = {
            search: trimmedSearch || null,
            category: collection === 'stones' ? category || null : null,
            size: collection === 'stones' ? size || null : null,
            thickness: collection === 'stones' ? thickness || null : null,
            projectCategory:
                collection === 'projects' ? projectCategory || null : null,
            collection,
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
                applyFilters(
                    trimmed,
                    selectedCategory,
                    selectedSize,
                    selectedThickness,
                    selectedProjectCategory,
                    activeCollection,
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
        applyFilters(
            '',
            selectedCategory,
            selectedSize,
            selectedThickness,
            selectedProjectCategory,
            activeCollection,
        );
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
                selectedProjectCategory,
                activeCollection,
            );
        }
    };

    const handleCollectionChange = (collection: 'stones' | 'projects') => {
        setActiveCollection(collection);
        setSearchInput('');
        setSelectedCategory('');
        setSelectedSize('');
        setSelectedThickness('');
        setSelectedProjectCategory('');
        applyFilters('', '', '', '', '', collection);
    };

    const handleCategoryChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedCategory(value);
        applyFilters(
            searchInput,
            value,
            selectedSize,
            selectedThickness,
            selectedProjectCategory,
            activeCollection,
        );
    };

    const handleProjectCategoryChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedProjectCategory(value);
        applyFilters(
            searchInput,
            selectedCategory,
            selectedSize,
            selectedThickness,
            value,
            activeCollection,
        );
    };

    const handleSizeChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedSize(value);
        applyFilters(
            searchInput,
            selectedCategory,
            value,
            selectedThickness,
            selectedProjectCategory,
            activeCollection,
        );
    };

    const handleThicknessChange = (value: string) => {
        value = value === 'all' ? '' : value;
        setSelectedThickness(value);
        applyFilters(
            searchInput,
            selectedCategory,
            selectedSize,
            value,
            selectedProjectCategory,
            activeCollection,
        );
    };

    useEffect(() => {
        const currentUrl = new URL(window.location.href);
        const urlSearch = currentUrl.searchParams.get('search') ?? '';
        const urlCategory = currentUrl.searchParams.get('category') ?? '';
        const urlSize = currentUrl.searchParams.get('size') ?? '';
        const urlThickness = currentUrl.searchParams.get('thickness') ?? '';
        const urlProjectCategory =
            currentUrl.searchParams.get('project_category') ?? '';
        const urlCollection = (currentUrl.searchParams.get('collection') ??
            'stones') as 'stones' | 'projects';

        if (
            urlSearch !== (lastAppliedRef.current.search ?? '') ||
            urlCategory !== (lastAppliedRef.current.category ?? '') ||
            urlSize !== (lastAppliedRef.current.size ?? '') ||
            urlThickness !== (lastAppliedRef.current.thickness ?? '') ||
            urlProjectCategory !==
                (lastAppliedRef.current.projectCategory ?? '') ||
            urlCollection !== lastAppliedRef.current.collection
        ) {
            setSearchInput(urlSearch);
            setSelectedCategory(urlCategory);
            setSelectedSize(urlSize);
            setSelectedThickness(urlThickness);
            setSelectedProjectCategory(urlProjectCategory);
            setActiveCollection(urlCollection);
            lastAppliedRef.current = {
                search: urlSearch || null,
                category: urlCategory || null,
                size: urlSize || null,
                thickness: urlThickness || null,
                projectCategory: urlProjectCategory || null,
                collection: urlCollection,
            };
        }
    }, [pageProps.filters]);

    useEffect(() => {
        return () => {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
            }
        };
    }, []);

    const isStonesMode = activeCollection === 'stones';
    const stones =
        isStonesMode && pageProps.stones ? (pageProps.stones.data ?? []) : [];
    const projects =
        !isStonesMode && pageProps.projects
            ? (pageProps.projects.data ?? [])
            : [];
    const resultCount = isStonesMode ? stones.length : projects.length;
    const hasDataForMode = isStonesMode
        ? !!pageProps.stones
        : !!pageProps.projects;
    const filterOptions = pageProps.filterOptions;
    const listKey = JSON.stringify([
        activeCollection,
        pageProps.filters.search,
        pageProps.filters.category,
        pageProps.filters.size,
        pageProps.filters.thickness,
        pageProps.filters.projectCategory,
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
                    <div className="flex flex-nowrap items-center justify-between gap-3">
                        <h2
                            id="products-heading"
                            className="min-w-0 text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
                        >
                            Our Collections
                        </h2>
                        {canViewProjects && (
                            <CollectionSwitch
                                activeCollection={activeCollection}
                                onCollectionChange={handleCollectionChange}
                            />
                        )}
                    </div>

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
                                placeholder={
                                    isStonesMode
                                        ? 'Search stones'
                                        : 'Search projects'
                                }
                                aria-label={
                                    isStonesMode
                                        ? 'Search stones'
                                        : 'Search projects'
                                }
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

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-2">
                            {isStonesMode ? (
                                <>
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
                                                {filterOptions.categories?.map(
                                                    (cat) => (
                                                        <SelectItem
                                                            key={cat.value}
                                                            value={cat.value}
                                                            className="rounded-none"
                                                        >
                                                            {cat.label}
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
                                                {filterOptions.sizes?.map(
                                                    (size) => (
                                                        <SelectItem
                                                            key={size.value}
                                                            value={size.value}
                                                            className="rounded-none"
                                                        >
                                                            {size.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="w-full sm:w-44">
                                        <Select
                                            value={selectedThickness || 'all'}
                                            onValueChange={
                                                handleThicknessChange
                                            }
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
                                                {filterOptions.thicknesses?.map(
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
                                </>
                            ) : (
                                <div className="w-full sm:w-64">
                                    <Select
                                        value={selectedProjectCategory || 'all'}
                                        onValueChange={
                                            handleProjectCategoryChange
                                        }
                                    >
                                        <SelectTrigger
                                            className="w-full rounded-none"
                                            aria-label="Project category"
                                        >
                                            <SelectValue placeholder="All Project Categories" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                value="all"
                                                className="rounded-none"
                                            >
                                                All Project Categories
                                            </SelectItem>
                                            {filterOptions.projectCategories?.map(
                                                (cat) => (
                                                    <SelectItem
                                                        key={cat.value}
                                                        value={cat.value}
                                                        className="rounded-none"
                                                    >
                                                        {cat.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
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
                        {isStonesMode
                            ? resultCount === 1
                                ? '1 stone found'
                                : `${resultCount} stones found`
                            : resultCount === 1
                              ? '1 project found'
                              : `${resultCount} projects found`}
                    </div>
                    {isUpdating || !hasDataForMode ? (
                        <div
                            aria-hidden="true"
                            className="mt-4 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3"
                        >
                            {Array.from({ length: 6 }, (_, index) => (
                                <div key={index}>
                                    <CardSkeleton
                                        showHeart={isStonesMode && canSave}
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <>
                            {resultCount === 0 && (
                                <EmptyState
                                    title={
                                        isStonesMode
                                            ? 'No stones found'
                                            : 'No projects found'
                                    }
                                    description="The search and filter didn't match any items."
                                />
                            )}
                            {resultCount > 0 && isStonesMode && (
                                <InfiniteScroll
                                    data="stones"
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
                                            itemCount={resultCount}
                                            listKey={listKey}
                                            showHeart={canSave}
                                            mode="stones"
                                        />
                                    )}
                                >
                                    <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                        {stones.map((stone, index) => (
                                            <StoneCard
                                                key={stone.slug}
                                                stone={stone}
                                                aspect="aspect-[4/3]"
                                                priority={index < 3}
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
                            {resultCount > 0 && !isStonesMode && (
                                <InfiniteScroll
                                    data="projects"
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
                                            itemCount={resultCount}
                                            listKey={listKey}
                                            showHeart={false}
                                            mode="projects"
                                        />
                                    )}
                                >
                                    <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                        {projects.map((project, index) => (
                                            <ProjectCard
                                                key={project.slug}
                                                project={project}
                                                priority={index < 3}
                                            />
                                        ))}
                                    </div>
                                </InfiniteScroll>
                            )}
                        </>
                    )}
                </div>
            </section>
        </div>
    );
};

export default Home;

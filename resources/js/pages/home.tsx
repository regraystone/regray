import { InfiniteScroll, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type MouseEvent } from 'react';
import { ArrowDown, Search, X } from 'lucide-react';
import EmptyState from '@/components/empty-state';
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
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import type { HomePageProps } from '@/types/home';
const hero = '/images/regray-hero.jpg';

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

const ProjectCardSkeleton = () => (
    <div className="overflow-hidden rounded-md border border-sidebar-border bg-background">
        <Skeleton className="aspect-[4/3] w-full rounded-md" />
    </div>
);

const Home = () => {
    const { can } = usePermissions();
    const { projects, filters, filterOptions } = usePage<HomePageProps>().props;

    const [searchInput, setSearchInput] = useState(filters.search ?? '');
    const [selectedStone, setSelectedStone] = useState(filters.stone ?? '');
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category ?? '',
    );
    const [isUpdating, setIsUpdating] = useState(false);

    const debounceTimerRef = useRef<number | null>(null);
    const lastAppliedRef = useRef({
        search: filters.search,
        stone: filters.stone,
        category: filters.category,
    });

    const applyFilters = (search: string, stone: string, category: string) => {
        const trimmedSearch = search.trim();
        const query: Record<string, string> = {};

        if (trimmedSearch !== '') {
            query.search = trimmedSearch;
        }
        if (stone !== '') {
            query.stone = stone;
        }
        if (category !== '') {
            query.category = category;
        }

        lastAppliedRef.current = {
            search: trimmedSearch || null,
            stone: stone || null,
            category: category || null,
        };

        setIsUpdating(true);

        router.get(homeIndex.url({ query }), undefined, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['projects', 'filters'],
            reset: ['projects'],
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
                applyFilters(trimmed, selectedStone, selectedCategory);
            }
        }, 300);
    };

    const handleSearchClear = () => {
        if (debounceTimerRef.current !== null) {
            window.clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
        setSearchInput('');
        applyFilters('', selectedStone, selectedCategory);
    };

    const handleSearchKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            if (debounceTimerRef.current !== null) {
                window.clearTimeout(debounceTimerRef.current);
                debounceTimerRef.current = null;
            }
            applyFilters(searchInput, selectedStone, selectedCategory);
        }
    };

    const handleStoneChange = (value: string) => {
        setSelectedStone(value);
        applyFilters(searchInput, value, selectedCategory);
    };

    const handleCategoryChange = (value: string) => {
        setSelectedCategory(value);
        applyFilters(searchInput, selectedStone, value);
    };

    useEffect(() => {
        const currentUrl = new URL(window.location.href);
        const urlSearch = currentUrl.searchParams.get('search') ?? '';
        const urlStone = currentUrl.searchParams.get('stone') ?? '';
        const urlCategory = currentUrl.searchParams.get('category') ?? '';

        if (
            urlSearch !== (lastAppliedRef.current.search ?? '') ||
            urlStone !== (lastAppliedRef.current.stone ?? '') ||
            urlCategory !== (lastAppliedRef.current.category ?? '')
        ) {
            setSearchInput(urlSearch);
            setSelectedStone(urlStone);
            setSelectedCategory(urlCategory);
            lastAppliedRef.current = {
                search: urlSearch || null,
                stone: urlStone || null,
                category: urlCategory || null,
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

    const resultCount = projects.data.length;

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
                                placeholder="Search projects"
                                aria-label="Search projects"
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
                                    value={selectedStone}
                                    onValueChange={handleStoneChange}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Filter by natural stone"
                                    >
                                        <SelectValue placeholder="All Natural Stones" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            value=""
                                            className="rounded-none"
                                        >
                                            All Natural Stones
                                        </SelectItem>
                                        {filterOptions.stones.map((stone) => (
                                            <SelectItem
                                                key={stone.value}
                                                value={stone.value}
                                                className="rounded-none"
                                            >
                                                {stone.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-full sm:w-56">
                                <Select
                                    value={selectedCategory}
                                    onValueChange={handleCategoryChange}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Filter by category"
                                    >
                                        <SelectValue placeholder="All Project Categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            value=""
                                            className="rounded-none"
                                        >
                                            All Project Categories
                                        </SelectItem>
                                        {filterOptions.categories.map((cat) => (
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
                            ? '1 project found'
                            : `${resultCount} projects found`}
                    </div>
                    {projects.data.length === 0 ? (
                        <EmptyState
                            title="No projects found"
                            description="The search and filters didn't match any projects."
                        />
                    ) : (
                        <InfiniteScroll
                            data="projects"
                            next={({ loading }) =>
                                loading ? (
                                    <div className="mt-4 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                        {Array.from({ length: 3 }, (_, i) => (
                                            <ProjectCardSkeleton key={i} />
                                        ))}
                                    </div>
                                ) : null
                            }
                        >
                            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                {projects.data.map(
                                    (
                                        project: {
                                            slug: string;
                                            name: string;
                                            thumbnail: string;
                                        },
                                        index: number,
                                    ) => (
                                        <ProjectCard
                                            key={project.slug}
                                            project={project}
                                            aspect="aspect-[4/3]"
                                            priority={index < 3}
                                            href={
                                                can(
                                                    permissions.homeProjectsView,
                                                )
                                                    ? homeShow.url({
                                                          project: project.slug,
                                                      })
                                                    : undefined
                                            }
                                        />
                                    ),
                                )}
                            </div>
                        </InfiniteScroll>
                    )}
                </div>
            </section>
        </div>
    );
};

export default Home;

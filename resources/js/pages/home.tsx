import { useEffect, useMemo, useState, type MouseEvent } from 'react';
import { ArrowDown, Search, X } from 'lucide-react';
import { toast } from 'sonner';
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
import {
    projects,
    PROJECT_CATEGORIES,
    type Project,
    type ProjectCategory,
} from '@/data/projects';
import { stones } from '@/data/stones';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { show as homeShow } from '@/routes/home';

const cucineLogo = '/images/brands/cucine_logo.webp';
const ascaleLogo = '/images/brands/ascale_logo.webp';
const reformLogo = '/images/brands/reform_logo.webp';
const resLogo = '/images/brands/res_logo.webp';
const regrayLogo = '/images/brands/regray-stone.webp';
const hero = '/images/regray-hero.jpg';

type Brand = {
    src: string;
    alt: string;
    url?: string;
};

const brandLogos: Brand[] = [
    { src: regrayLogo, alt: 'Regray Stone', url: 'https://regraystone.com/' },
    { src: cucineLogo, alt: 'Cucine' },
    { src: ascaleLogo, alt: 'Ascale' },
    { src: reformLogo, alt: 'Reform' },
    { src: resLogo, alt: 'Res Italia' },
];

const BrandCarousel = () => {
    return (
        <section
            aria-label="Featured in"
            className="mt-2 border-y border-sidebar-border bg-background py-10"
        >
            <div className="flex snap-x snap-proximity [scrollbar-width:none] overflow-x-auto overscroll-x-contain md:justify-center md:overflow-x-visible [&::-webkit-scrollbar]:hidden">
                <div className="flex gap-6 px-4 sm:gap-8 sm:px-5 md:gap-10">
                    {brandLogos.map((brand) => (
                        <button
                            key={brand.alt}
                            type="button"
                            onClick={() => {
                                if (brand.url) {
                                    window.open(brand.url, '_blank');
                                } else {
                                    toast.info(
                                        'This feature is currently unavailable.',
                                    );
                                }
                            }}
                            className="group flex shrink-0 cursor-pointer snap-start transition-transform hover:scale-105 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transform-none motion-reduce:hover:scale-100"
                            aria-label={brand.alt}
                        >
                            <img
                                src={brand.src}
                                alt=""
                                className="h-12 w-auto object-contain grayscale sm:h-14"
                            />
                        </button>
                    ))}
                </div>
            </div>
        </section>
    );
};

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

const HomeSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <section className="relative flex min-h-[280px] items-center overflow-hidden sm:min-h-[480px]">
            <Skeleton className="absolute inset-0 rounded-none" />
        </section>

        <section className="mt-2 border-y border-sidebar-border bg-background py-10">
            <div className="flex justify-center gap-6 px-4 sm:gap-8 sm:px-5 md:gap-10">
                {Array.from({ length: brandLogos.length }, (_, index) => (
                    <div key={index} className="flex shrink-0 items-center">
                        <Skeleton className="h-12 w-12 sm:h-14 sm:w-14" />
                    </div>
                ))}
            </div>
        </section>

        <section className="mt-8 md:mt-12">
            <div className="mb-6 border-b border-sidebar-border pb-4">
                <Skeleton className="h-8 w-56" />
                <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Skeleton className="h-9 w-full sm:max-w-xs" />
                    <div className="hidden gap-3 sm:flex">
                        <Skeleton className="h-9 w-44" />
                        <Skeleton className="h-9 w-56" />
                    </div>
                    <div className="flex items-center gap-3 sm:hidden">
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                {Array.from({ length: 6 }, (_, index) => (
                    <div
                        key={index}
                        className="border border-sidebar-border bg-background"
                    >
                        <Skeleton className="aspect-[4/3] w-full rounded-none" />
                    </div>
                ))}
            </div>
        </section>
    </div>
);

const Home = () => {
    const { can } = usePermissions();
    const [search, setSearch] = useState('');
    const [selectedStone, setSelectedStone] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('');
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    const visibleProjects = useMemo<Project[]>(() => {
        const query = search.trim().toLowerCase();
        const hasSearch = query !== '';
        const hasStoneFilter = selectedStone !== '';
        const hasCategoryFilter = selectedCategory !== '';

        return projects.filter((project) => {
            if (hasSearch) {
                const matches =
                    project.name.toLowerCase().includes(query) ||
                    project.stones.some((stoneId) => {
                        const stone = stones.find((s) => s.id === stoneId);
                        return stone?.name.toLowerCase().includes(query);
                    }) ||
                    project.categories.some((catId) => {
                        const cat = PROJECT_CATEGORIES.find(
                            (c) => c.value === catId,
                        );
                        return cat?.label.toLowerCase().includes(query);
                    });

                if (!matches) {
                    return false;
                }
            }

            if (hasStoneFilter && !project.stones.includes(selectedStone)) {
                return false;
            }

            if (
                hasCategoryFilter &&
                !project.categories.includes(
                    selectedCategory as ProjectCategory,
                )
            ) {
                return false;
            }

            return true;
        });
    }, [search, selectedStone, selectedCategory]);

    if (isLoading) {
        return <HomeSkeleton />;
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <Hero />
            <BrandCarousel />
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
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search projects"
                                aria-label="Search projects"
                                className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                            />
                            {search ? (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    aria-label="Clear search"
                                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <X className="size-4" aria-hidden />
                                </button>
                            ) : null}
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3">
                            <div className="w-full sm:w-44">
                                <Select
                                    value={selectedStone}
                                    onValueChange={setSelectedStone}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Filter by natural stone"
                                    >
                                        <SelectValue placeholder="All Natural Stones" />
                                    </SelectTrigger>
                                    <SelectContent className="rounded-none">
                                        <SelectItem
                                            value=""
                                            className="rounded-none"
                                        >
                                            All Natural Stones
                                        </SelectItem>
                                        {stones.map((stone) => (
                                            <SelectItem
                                                key={stone.id}
                                                value={stone.id}
                                                className="rounded-none"
                                            >
                                                {stone.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-full sm:w-56">
                                <Select
                                    value={selectedCategory}
                                    onValueChange={setSelectedCategory}
                                >
                                    <SelectTrigger
                                        className="w-full rounded-none"
                                        aria-label="Filter by category"
                                    >
                                        <SelectValue placeholder="All Project Categories" />
                                    </SelectTrigger>
                                    <SelectContent className="rounded-none">
                                        <SelectItem
                                            value=""
                                            className="rounded-none"
                                        >
                                            All Project Categories
                                        </SelectItem>
                                        {PROJECT_CATEGORIES.map((cat) => (
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

                {visibleProjects.length === 0 ? (
                    <EmptyState
                        title="No projects found"
                        description="The search and filters didn't match any projects."
                    />
                ) : (
                    <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                        {visibleProjects.map((project) => (
                            <ProjectCard
                                key={project.id}
                                project={project}
                                aspect="aspect-[4/3]"
                                // The feed reads as Home; the project behind a
                                // card is its own page and says so in its own
                                // permission, so a reader of the feed who holds
                                // neither sees the card and not the link.
                                href={
                                    can(permissions.homeProjectsView)
                                        ? homeShow.url({
                                              project: project.id,
                                          })
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
};

export default Home;

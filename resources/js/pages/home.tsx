import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type MouseEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import { ArrowDown, Search, X } from 'lucide-react';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { stones, stoneProfiles, type Stone } from '@/data/stones';

const cucineLogo = '/images/brands/cucine_logo.webp';
const ascaleLogo = '/images/brands/ascale_logo.webp';
const reformLogo = '/images/brands/reform_logo.webp';
const resLogo = '/images/brands/res_logo.webp';
const hero = '/images/regray-hero.jpg';

type Filter = 'latest' | 'recommended';

const stoneTabs: Array<{ value: Filter; label: string }> = [
    { value: 'latest', label: 'Latest' },
    { value: 'recommended', label: 'Recommended' },
];

const brandLogos = [
    { src: cucineLogo, alt: 'Cucine' },
    { src: ascaleLogo, alt: 'Ascale' },
    { src: reformLogo, alt: 'Reform' },
    { src: resLogo, alt: 'Res' },
];

const BRAND_FRAME = 1000 / 60;
const BRAND_MAX_DELTA = 64;
const BRAND_SCROLL_SPEED = 40;
const BRAND_FLING_DECAY = 0.94;
const BRAND_FLING_CUTOFF = 0.4;
const BRAND_MAX_FLING = 60;
const BRAND_VELOCITY_SAMPLE_LIMIT = 120;

const BrandCarousel = () => {
    const viewportRef = useRef<HTMLDivElement>(null);
    const trackRef = useRef<HTMLDivElement>(null);
    const cycleRef = useRef(0);
    const offsetRef = useRef(0);
    const renderedOffsetRef = useRef(Number.NaN);
    const velocityRef = useRef(0);
    const isDraggingRef = useRef(false);
    const isHoveredRef = useRef(false);
    const prefersReducedMotionRef = useRef(false);
    const dragRef = useRef({
        pointerId: -1,
        startX: 0,
        startOffset: 0,
        lastX: 0,
        lastTime: 0,
    });
    const [isDragging, setIsDragging] = useState(false);

    const wrapOffset = useCallback((value: number): number => {
        const cycle = cycleRef.current;

        if (cycle <= 0) {
            return value;
        }

        const wrapped = value % cycle;

        return wrapped > 0 ? wrapped - cycle : wrapped;
    }, []);

    useEffect(() => {
        const viewport = viewportRef.current;
        const track = trackRef.current;

        if (!viewport || !track) {
            return;
        }

        const motionQuery = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        prefersReducedMotionRef.current = motionQuery.matches;

        const handleMotionPreference = (event: MediaQueryListEvent) => {
            prefersReducedMotionRef.current = event.matches;
        };

        motionQuery.addEventListener('change', handleMotionPreference);

        const measureCycle = () => {
            cycleRef.current = track.getBoundingClientRect().width / 2;
        };

        measureCycle();

        const resizeObserver = new ResizeObserver(measureCycle);
        resizeObserver.observe(track);

        const handlePointerMove = (event: PointerEvent) => {
            if (
                !isDraggingRef.current ||
                event.pointerId !== dragRef.current.pointerId
            ) {
                return;
            }

            offsetRef.current = wrapOffset(
                dragRef.current.startOffset +
                    (event.clientX - dragRef.current.startX),
            );

            const elapsed = event.timeStamp - dragRef.current.lastTime;

            if (elapsed > 0 && elapsed < BRAND_VELOCITY_SAMPLE_LIMIT) {
                velocityRef.current = Math.min(
                    Math.max(
                        (event.clientX - dragRef.current.lastX) /
                            (elapsed / BRAND_FRAME),
                        -BRAND_MAX_FLING,
                    ),
                    BRAND_MAX_FLING,
                );
            } else if (elapsed >= BRAND_VELOCITY_SAMPLE_LIMIT) {
                velocityRef.current = 0;
            }

            dragRef.current.lastX = event.clientX;
            dragRef.current.lastTime = event.timeStamp;
        };

        const endDrag = (event: PointerEvent, withFling: boolean) => {
            if (
                !isDraggingRef.current ||
                event.pointerId !== dragRef.current.pointerId
            ) {
                return;
            }

            isDraggingRef.current = false;
            velocityRef.current = withFling ? velocityRef.current : 0;
            setIsDragging(false);
        };

        const handlePointerUp = (event: PointerEvent) => endDrag(event, true);
        const handlePointerCancel = (event: PointerEvent) =>
            endDrag(event, false);
        const handleWindowBlur = () => {
            isDraggingRef.current = false;
            velocityRef.current = 0;
            setIsDragging(false);
        };

        window.addEventListener('pointermove', handlePointerMove);
        window.addEventListener('pointerup', handlePointerUp);
        window.addEventListener('pointercancel', handlePointerCancel);
        window.addEventListener('blur', handleWindowBlur);

        let frameId = 0;
        let previousTime = performance.now();

        const step = (time: number) => {
            const delta = Math.min(time - previousTime, BRAND_MAX_DELTA);
            previousTime = time;

            if (!isDraggingRef.current) {
                if (Math.abs(velocityRef.current) > BRAND_FLING_CUTOFF) {
                    const frames = delta / BRAND_FRAME;
                    offsetRef.current = wrapOffset(
                        offsetRef.current + velocityRef.current * frames,
                    );
                    velocityRef.current *= Math.pow(BRAND_FLING_DECAY, frames);
                } else {
                    velocityRef.current = 0;

                    if (
                        !isHoveredRef.current &&
                        !prefersReducedMotionRef.current
                    ) {
                        offsetRef.current = wrapOffset(
                            offsetRef.current -
                                (BRAND_SCROLL_SPEED * delta) / 1000,
                        );
                    }
                }
            }

            if (offsetRef.current !== renderedOffsetRef.current) {
                renderedOffsetRef.current = offsetRef.current;
                track.style.transform = `translate3d(${offsetRef.current}px, 0, 0)`;
            }

            frameId = window.requestAnimationFrame(step);
        };

        frameId = window.requestAnimationFrame(step);

        return () => {
            window.cancelAnimationFrame(frameId);
            resizeObserver.disconnect();
            motionQuery.removeEventListener('change', handleMotionPreference);
            window.removeEventListener('pointermove', handlePointerMove);
            window.removeEventListener('pointerup', handlePointerUp);
            window.removeEventListener('pointercancel', handlePointerCancel);
            window.removeEventListener('blur', handleWindowBlur);
        };
    }, [wrapOffset]);

    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        isDraggingRef.current = true;
        velocityRef.current = 0;

        if (event.pointerType === 'mouse') {
            isHoveredRef.current = true;
        }

        dragRef.current = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startOffset: offsetRef.current,
            lastX: event.clientX,
            lastTime: event.timeStamp,
        };
        setIsDragging(true);
    };

    return (
        <section
            aria-label="Featured in"
            className="mt-2 border-y border-sidebar-border bg-background py-10"
        >
            <div
                ref={viewportRef}
                className="brand-marquee"
                data-dragging={isDragging}
                onPointerDown={handlePointerDown}
                onPointerEnter={(event) => {
                    if (event.pointerType === 'mouse') {
                        isHoveredRef.current = true;
                    }
                }}
                onPointerLeave={(event) => {
                    if (
                        event.pointerType === 'mouse' &&
                        !isDraggingRef.current
                    ) {
                        isHoveredRef.current = false;
                    }
                }}
            >
                <div ref={trackRef} className="logo-track">
                    {[brandLogos, brandLogos].map((group, groupIndex) => (
                        <div
                            key={`group-${groupIndex}`}
                            className="logo-group"
                            aria-hidden={groupIndex > 0}
                        >
                            {group.map((brand, index) => (
                                <img
                                    key={`${brand.alt}-${index}`}
                                    src={brand.src}
                                    alt={groupIndex === 0 ? brand.alt : ''}
                                    draggable={false}
                                    className="h-16 shrink-0 object-contain grayscale"
                                />
                            ))}
                        </div>
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
            <div className="flex items-center justify-center gap-8 sm:gap-14">
                {Array.from({ length: brandLogos.length }, (_, index) => (
                    <Skeleton key={index} className="h-16 w-24 sm:w-32" />
                ))}
            </div>
        </section>

        <section className="mt-8 md:mt-12">
            <div className="mb-6 border-b border-sidebar-border pb-4">
                <Skeleton className="h-8 w-40" />
                <div className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Skeleton className="h-9 w-full sm:max-w-xs" />
                    <Skeleton className="hidden h-4 w-64 sm:block" />
                    <div className="flex items-center gap-2.5 sm:hidden">
                        <Skeleton className="h-8 w-20" />
                        <Skeleton className="h-8 w-28" />
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
    const [filter, setFilter] = useState<Filter>('latest');
    const [search, setSearch] = useState('');
    const [savedIds, setSavedIds] = useState<Set<string>>(new Set());
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    // Must stay above the early return so hook order never changes.
    const visibleStones = useMemo<Stone[]>(() => {
        const base =
            filter === 'recommended'
                ? stones.filter((stone) => stone.recommended)
                : stones;

        const query = search.trim().toLowerCase();

        if (query === '') {
            return base;
        }

        return base.filter((stone) => {
            const profile = stoneProfiles[stone.id];

            return [
                stone.name,
                profile?.specifications.stoneType,
                profile?.specifications.origin,
            ]
                .filter((value): value is string => Boolean(value))
                .some((value) => value.toLowerCase().includes(query));
        });
    }, [filter, search]);

    if (isLoading) {
        return <HomeSkeleton />;
    }

    const toggleSave = (id: string) => {
        setSavedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

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
                        Our Stones
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
                                placeholder="Search stones"
                                aria-label="Search stones"
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

                        <div
                            className="hidden items-center gap-5 text-sm sm:ml-auto sm:flex"
                            role="tablist"
                            aria-label="Filter Our Stones"
                        >
                            {stoneTabs.map((tab) => {
                                const isActive = filter === tab.value;

                                return (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        role="tab"
                                        aria-selected={isActive}
                                        onClick={() => setFilter(tab.value)}
                                        className={`-my-2.5 px-2.5 py-2.5 transition-opacity hover:opacity-70 ${
                                            isActive
                                                ? 'font-bold text-foreground underline decoration-foreground underline-offset-4'
                                                : 'text-secondary-foreground'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </div>

                        <div
                            className="flex items-center gap-2.5 sm:hidden"
                            role="tablist"
                            aria-label="Filter Our Stones"
                        >
                            {stoneTabs.map((tab) => {
                                const isActive = filter === tab.value;

                                return (
                                    <Button
                                        key={tab.value}
                                        type="button"
                                        role="tab"
                                        aria-selected={isActive}
                                        variant={
                                            isActive ? 'default' : 'secondary'
                                        }
                                        size="sm"
                                        className="px-3 py-1.5"
                                        onClick={() => setFilter(tab.value)}
                                    >
                                        {tab.label}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {visibleStones.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
                        <p className="text-lg font-bold tracking-[-0.03em]">
                            No stones found
                        </p>
                        <p className="max-w-sm text-sm text-secondary-foreground">
                            No stone in this filter matches that search.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-1"
                            onClick={() => setSearch('')}
                        >
                            Clear search
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                        {visibleStones.map((stone) => (
                            <StoneCard
                                key={stone.id}
                                stone={stone}
                                aspect="aspect-[4/3]"
                                saved={savedIds.has(stone.id)}
                                onToggleSave={() => toggleSave(stone.id)}
                            />
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
};

export default Home;

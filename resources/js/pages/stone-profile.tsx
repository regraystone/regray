import { useEffect, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Star } from 'lucide-react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import GalleryCarousel, {
    type GalleryCarouselHandle,
} from '@/components/gallery-carousel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    stoneProfiles,
    type StoneImage,
    type StoneProfile,
} from '@/data/constant';
import { index as homeIndex } from '@/routes/home';
import { index as stonesIndex } from '@/routes/stones';

type StoneProfileProps = {
    stoneId: string;
};

const StoneProfileSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <section className="relative flex min-h-[280px] items-center overflow-hidden sm:min-h-[480px]">
            <Skeleton className="absolute inset-0 rounded-none" />
            <div className="relative z-10 mx-auto w-full max-w-4xl px-5 py-16 text-center sm:py-24">
                <Skeleton className="mx-auto h-10 w-2/5 sm:h-14" />
                <Skeleton className="mx-auto mt-6 h-3 w-40" />
            </div>
        </section>

        <section className="py-16 sm:py-12">
            <div className="mx-auto max-w-4xl space-y-3">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
            </div>
        </section>

        <section>
            <Skeleton className="aspect-3/4 w-full rounded-none lg:aspect-[16/9]" />
        </section>

        <section className="py-12 sm:py-16">
            <div className="grid gap-x-12 gap-y-10 sm:grid-cols-2">
                <div className="space-y-10">
                    <div>
                        <Skeleton className="h-3 w-36" />
                        <div className="mt-4 space-y-3">
                            {Array.from({ length: 3 }, (_, index) => (
                                <Skeleton key={index} className="h-6 w-full" />
                            ))}
                        </div>
                    </div>
                    <div>
                        <Skeleton className="h-3 w-44" />
                        <div className="mt-4 flex flex-wrap gap-2">
                            {Array.from({ length: 3 }, (_, index) => (
                                <Skeleton key={index} className="h-7 w-24" />
                            ))}
                        </div>
                    </div>
                </div>
                <div className="space-y-10">
                    <div>
                        <Skeleton className="h-3 w-28" />
                        <div className="mt-4 space-y-3">
                            {Array.from({ length: 4 }, (_, index) => (
                                <Skeleton key={index} className="h-5 w-full" />
                            ))}
                        </div>
                    </div>
                    <div>
                        <Skeleton className="h-3 w-44" />
                        <div className="mt-4 space-y-3">
                            {Array.from({ length: 4 }, (_, index) => (
                                <Skeleton key={index} className="h-5 w-full" />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="projects" className="scroll-mt-24">
            <div className="mb-8 flex items-end justify-between gap-4">
                <Skeleton className="h-8 w-56" />
                <div className="flex shrink-0 items-center gap-2">
                    <Skeleton className="size-10" />
                    <Skeleton className="size-10" />
                </div>
            </div>
            <div className="flex gap-4 overflow-hidden md:gap-5">
                {Array.from({ length: 3 }, (_, index) => (
                    <div
                        key={index}
                        className="w-[85%] shrink-0 sm:w-[48%] lg:w-[32%]"
                    >
                        <div className="border border-sidebar-border bg-background">
                            <Skeleton className="aspect-[4/3] w-full rounded-none" />
                        </div>
                    </div>
                ))}
            </div>
        </section>
    </div>
);

const SpecRow = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-baseline justify-between gap-4 border-b border-sidebar-border py-2.5 last:border-0">
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="text-right text-base font-semibold text-foreground">
            {value}
        </dd>
    </div>
);

const BulletedList = ({ items }: { items: string[] }) => (
    <ul className="space-y-2">
        {items.map((item) => (
            <li
                key={item}
                className="flex items-start gap-3 text-base text-foreground"
            >
                <span
                    className="mt-2 size-1.5 shrink-0 rounded-full bg-primary"
                    aria-hidden
                />
                {item}
            </li>
        ))}
    </ul>
);

const StoneGallery = ({
    images,
    name,
}: {
    images: StoneImage[];
    name: string;
}) => {
    const carouselRef = useRef<GalleryCarouselHandle>(null);

    return (
        <GalleryCarousel
            ref={carouselRef}
            ariaLabel={`${name} gallery`}
            dots
            dotsClassName="absolute bottom-3 left-1/2 -translate-x-1/2"
            className="relative bg-background"
            controls={
                images.length > 1 ? (
                    <>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollPrev()}
                            aria-label="Previous photo"
                            className="absolute inset-y-0 left-2 z-10 my-auto grid size-11 cursor-pointer place-items-center rounded-none border border-sidebar-border bg-background/80 text-foreground backdrop-blur-sm transition hover:bg-background"
                        >
                            <ChevronLeft className="size-5" aria-hidden />
                        </button>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollNext()}
                            aria-label="Next photo"
                            className="absolute inset-y-0 right-2 z-10 my-auto grid size-11 cursor-pointer place-items-center rounded-none border border-sidebar-border bg-background/80 text-foreground backdrop-blur-sm transition hover:bg-background"
                        >
                            <ChevronRight className="size-5" aria-hidden />
                        </button>
                    </>
                ) : undefined
            }
        >
            {images.map((image, index) => (
                <div
                    key={image.src}
                    className="aspect-3/4 w-full shrink-0 snap-center lg:aspect-[16/9]"
                >
                    <img
                        src={image.src}
                        alt={`${name} - ${image.name}`}
                        draggable={false}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        className="size-full object-cover"
                    />
                </div>
            ))}
        </GalleryCarousel>
    );
};

const ProjectCard = ({
    name,
    image,
}: Omit<StoneProfile['projects'][number], 'id'>) => (
    <Link href="#" onClick={(e) => e.preventDefault()} className="block">
        <figure className="stone-card cursor-pointer border border-sidebar-border bg-background">
            <div className="stone-card-media relative aspect-[4/3] overflow-hidden bg-background">
                <img
                    src={image}
                    alt={name}
                    draggable={false}
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="image-tint absolute inset-0" aria-hidden />
                <span className="absolute bottom-2.5 left-2.5 flex max-w-[calc(100%-1.25rem)] items-center justify-center gap-1.5 bg-background py-1 pr-2.5 pl-2">
                    <span className="min-w-0 truncate text-[0.65rem] font-bold tracking-[0.05em] text-foreground uppercase sm:text-[0.8rem] sm:tracking-[0.06em]">
                        {name}
                    </span>
                </span>
            </div>
        </figure>
    </Link>
);

const ProjectsCarousel = ({
    projects,
    name,
}: {
    projects: StoneProfile['projects'];
    name: string;
}) => {
    const carouselRef = useRef<CarouselHandle>(null);
    const [atStart, setAtStart] = useState(true);
    const [atEnd, setAtEnd] = useState(false);

    const handleBoundsChange = (start: boolean, end: boolean) => {
        setAtStart(start);
        setAtEnd(end);
    };

    return (
        <div>
            <div className="mb-8 flex items-end justify-between gap-4">
                <h2
                    id="projects-heading"
                    className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
                >
                    Featured Projects
                </h2>
                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atStart}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollPrev()}
                        aria-label="Previous projects"
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atEnd}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollNext()}
                        aria-label="Next projects"
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>
            <Carousel
                ref={carouselRef}
                ariaLabel={`Projects using ${name}`}
                dragEnabled={false}
                viewportClassName="gap-4 md:gap-5"
                onBoundsChange={handleBoundsChange}
            >
                {projects.map((project) => (
                    <div
                        key={project.id}
                        className="w-[85%] shrink-0 snap-start sm:w-[48%] lg:w-[32%]"
                    >
                        <ProjectCard {...project} />
                    </div>
                ))}
            </Carousel>
        </div>
    );
};

const StoneProfile = ({ stoneId }: StoneProfileProps) => {
    const profile = stoneProfiles[stoneId];
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    // The hero sits outside the nav, so the way back belongs to the page. A
    // direct hit on the URL has no history to pop, so it falls back to the
    // feed the breadcrumb already treats as this page's parent.
    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(homeIndex.url());
        }
    };

    if (isLoading) {
        return <StoneProfileSkeleton />;
    }

    if (!profile) {
        return (
            <div className="mx-auto w-full max-w-6xl py-16 text-center">
                <h1 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                    Stone not found
                </h1>
                <p className="mt-3 text-sm text-secondary-foreground">
                    The stone you are looking for does not exist.
                </p>
                <Button asChild className="mt-6">
                    <Link href={stonesIndex.url()}>Back to Stones</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <section className="relative flex min-h-[280px] items-center overflow-hidden text-on-image sm:min-h-[480px]">
                <img
                    src={profile.thumbnail}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="image-tint absolute inset-0" aria-hidden />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={goBack}
                    aria-label="Back"
                    className="group absolute top-4 left-4 z-20 h-11 w-11 cursor-pointer justify-center gap-2.5 border-white/25 bg-black/55 px-0 tracking-[0.12em] text-white backdrop-blur-md transition-colors duration-200 hover:border-white/40 hover:bg-black/75 hover:opacity-100 focus-visible:ring-white/60 sm:top-6 sm:left-6 sm:w-auto sm:px-4 lg:left-8"
                >
                    <ArrowLeft
                        className="size-4 transition-transform duration-200 group-hover:-translate-x-0.5"
                        aria-hidden
                    />
                    <span className="hidden sm:inline">Back</span>
                </Button>
                <div className="relative z-10 mx-auto w-full max-w-4xl px-5 py-16 text-center sm:py-24">
                    <h1 className="hero-title mt-5 text-on-image">
                        {profile.name}
                    </h1>
                    <p className="mt-6 text-xs font-bold tracking-[0.2em] text-on-image/80 uppercase">
                        {profile.specifications.origin} ·{' '}
                        {profile.specifications.thickness}
                    </p>
                    {profile.recommended && (
                        <span className="mt-6 inline-flex items-center gap-1 bg-black px-2 py-1 text-[0.65rem] font-bold tracking-[0.05em] text-white uppercase">
                            <Star
                                className="size-3 shrink-0 fill-white text-white"
                                aria-hidden
                            />
                            Recommended
                        </span>
                    )}
                </div>
            </section>

            <section className="py-16 sm:py-12">
                <p className="mx-auto max-w-4xl text-justify text-sm leading-relaxed text-secondary-foreground sm:text-xl">
                    {profile.description}
                </p>
            </section>

            <section>
                <StoneGallery images={profile.gallery} name={profile.name} />
            </section>

            <section className="py-12 sm:py-16">
                <h2 className="sr-only">Facts</h2>
                <div className="grid gap-x-12 gap-y-10 sm:grid-cols-2">
                    <div className="space-y-10">
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Specifications
                            </h3>
                            <dl>
                                <SpecRow
                                    label="Stone Type"
                                    value={profile.specifications.stoneType}
                                />
                                <SpecRow
                                    label="Origin"
                                    value={profile.specifications.origin}
                                />
                                <SpecRow
                                    label="Thickness"
                                    value={profile.specifications.thickness}
                                />
                            </dl>
                        </div>
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Available Finishes
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {profile.finishes.map((finish) => (
                                    <span
                                        key={finish}
                                        className="border border-sidebar-border bg-background px-3 py-1 text-xs font-bold tracking-[0.05em] text-foreground uppercase"
                                    >
                                        {finish}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="space-y-10">
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Applications
                            </h3>
                            <BulletedList items={profile.applications} />
                        </div>
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Care &amp; Maintenance
                            </h3>
                            <BulletedList items={profile.care} />
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="projects"
                aria-labelledby="projects-heading"
                className="scroll-mt-24"
            >
                <ProjectsCarousel
                    projects={profile.projects}
                    name={profile.name}
                />
            </section>
        </div>
    );
};

export default StoneProfile;

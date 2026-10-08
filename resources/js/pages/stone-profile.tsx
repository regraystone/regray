import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Star } from 'lucide-react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import ImageLightbox, {
    LightboxTrigger,
    type LightboxImage,
} from '@/components/image-lightbox';
import { FactChip, SpecRow } from '@/components/profile-facts';
import ProfileGallery from '@/components/profile-gallery';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { projects } from '@/data/projects';
import { stoneProfiles } from '@/data/stones';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import { index as stonesIndex } from '@/routes/stones';

type StoneProfileProps = {
    stoneId: string;
    /**
     * The project this stone is read under, when it is the nested route.
     *
     * A stone reached from Saved has no project above it, so the prop is left
     * off and the page is exactly what it has always been.
     */
    projectId?: string;
};

const StoneProfileSkeleton = ({
    showHighlights,
}: {
    showHighlights: boolean;
}) => (
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

        {showHighlights && (
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
        )}
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

type HighlightCard = { name: string; image: string };

const ProjectCard = ({
    name,
    image,
    onOpen,
}: HighlightCard & { onOpen: () => void }) => (
    <LightboxTrigger onOpen={onOpen}>
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
    </LightboxTrigger>
);

const ProjectsCarousel = ({
    highlights,
    name,
    onOpenImage,
}: {
    highlights: Array<{ id: string; name: string; image: string }>;
    name: string;
    onOpenImage: (image: LightboxImage) => void;
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
                    Project Highlights
                </h2>
                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atStart}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollPrev()}
                        aria-label="Previous project highlights"
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atEnd}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollNext()}
                        aria-label="Next project highlights"
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>
            <Carousel
                ref={carouselRef}
                ariaLabel={`Project highlights using ${name}`}
                dragEnabled={false}
                viewportClassName="gap-4 md:gap-5"
                onBoundsChange={handleBoundsChange}
            >
                {highlights.map((highlight) => (
                    <div
                        key={highlight.id}
                        className="w-[85%] shrink-0 snap-start sm:w-[48%] lg:w-[32%]"
                    >
                        <ProjectCard
                            name={highlight.name}
                            image={highlight.image}
                            onOpen={() =>
                                onOpenImage({
                                    src: highlight.image,
                                    alt: highlight.name,
                                })
                            }
                        />
                    </div>
                ))}
            </Carousel>
        </div>
    );
};

const StoneProfile = ({ stoneId, projectId }: StoneProfileProps) => {
    const profile = stoneProfiles[stoneId];
    const project = projectId
        ? projects.find((entry) => entry.id === projectId)
        : undefined;
    const [isLoading, setIsLoading] = useState(true);
    const [lightboxImage, setLightboxImage] = useState<LightboxImage | null>(
        null,
    );

    const highlights = useMemo(() => {
        const matched = projects
            .filter((p) => p.stones.includes(stoneId))
            .sort((a, b) => {
                const yearDiff = b.completedYear - a.completedYear;
                return yearDiff !== 0 ? yearDiff : a.name.localeCompare(b.name);
            })
            .map((p) => ({
                id: p.id,
                name: p.name,
                image: p.thumbnail,
            }));
        return matched;
    }, [stoneId]);

    const showHighlights = highlights.length > 0;

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    /*
     * The nested route says where the stone was read from, so a project the
     * data does not know, or a stone that is not one of its stones, is the
     * same miss as an unknown stone id: the page says so rather than the
     * route. Without a project above it, the stone stands alone and only its
     * own id is checked, which is what `saved.show` has always done.
     */
    const isInProject =
        projectId === undefined ||
        (project !== undefined && project.stones.includes(stoneId));

    // The hero sits outside the nav, so the way back belongs to the page. The
    // nested stone is read inside a project, so its way back is the project
    // itself rather than wherever the reader happened to come from. A direct
    // hit on the standalone URL has no history to pop, so it falls back to the
    // feed the breadcrumb already treats as this page's parent.
    const goBack = () => {
        if (projectId) {
            router.visit(homeShow.url({ project: projectId }));

            return;
        }

        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(homeIndex.url());
        }
    };

    if (isLoading) {
        return <StoneProfileSkeleton showHighlights={showHighlights} />;
    }

    if (!profile || !isInProject) {
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
                    aria-label={projectId ? 'Back to Project' : 'Back'}
                    className="group absolute top-4 left-4 z-20 h-11 w-11 cursor-pointer justify-center gap-2.5 border-white/25 bg-black/55 px-0 tracking-[0.12em] text-white backdrop-blur-md transition-colors duration-200 hover:border-white/40 hover:bg-black/75 hover:opacity-100 focus-visible:ring-white/60 sm:top-6 sm:left-6 sm:w-auto sm:px-4 lg:left-8"
                >
                    <ArrowLeft
                        className="size-4 transition-transform duration-200 group-hover:-translate-x-0.5"
                        aria-hidden
                    />
                    <span className="hidden sm:inline">
                        {projectId ? 'Back to Project' : 'Back'}
                    </span>
                </Button>
                <div className="relative z-10 mx-auto w-full max-w-4xl px-5 py-16 text-center sm:py-24">
                    <h1 className="hero-title mt-5 text-on-image">
                        {profile.name}
                    </h1>
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
                <p className="mx-auto max-w-4xl text-center text-sm leading-relaxed text-secondary-foreground sm:text-xl">
                    {profile.description}
                </p>
            </section>

            <section>
                <ProfileGallery
                    images={profile.gallery}
                    name={profile.name}
                    onOpenImage={setLightboxImage}
                />
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
                                    <FactChip key={finish}>{finish}</FactChip>
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

            {showHighlights && (
                <section
                    id="projects"
                    aria-labelledby="projects-heading"
                    className="scroll-mt-24"
                >
                    <ProjectsCarousel
                        highlights={highlights}
                        name={profile.name}
                        onOpenImage={setLightboxImage}
                    />
                </section>
            )}

            <ImageLightbox
                image={lightboxImage}
                onClose={() => setLightboxImage(null)}
            />
        </div>
    );
};

export default StoneProfile;

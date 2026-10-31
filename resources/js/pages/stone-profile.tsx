import { useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Star } from 'lucide-react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import ImageLightbox, { type LightboxImage } from '@/components/image-lightbox';
import { FactChip, SpecRow } from '@/components/profile-facts';
import ProfileGallery from '@/components/profile-gallery';
import { Button } from '@/components/ui/button';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import { index as savedIndex } from '@/routes/saved';
import type {
    StoneProfile as StoneProfileData,
    StoneProfileProject,
} from '@/types/stone';

type StoneProfileProps = {
    stone: { data: StoneProfileData };
};

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
    href,
}: HighlightCard & { href: string }) => (
    <Link
        href={href}
        className="block size-full cursor-pointer overflow-hidden rounded-md p-0 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset"
    >
        <figure className="stone-card cursor-pointer overflow-hidden rounded-md border border-sidebar-border bg-background">
            <div className="stone-card-media relative aspect-[4/3] overflow-hidden bg-background">
                <img
                    src={image}
                    alt={name}
                    draggable={false}
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="image-tint absolute inset-0" aria-hidden />
                <span className="absolute bottom-2.5 left-2.5 flex max-w-[calc(100%-1.25rem)] items-center justify-center gap-1.5 rounded-md bg-background py-1 pr-2.5 pl-2">
                    <span className="min-w-0 truncate text-[0.65rem] font-bold tracking-[0.05em] text-foreground uppercase sm:text-[0.8rem] sm:tracking-[0.06em]">
                        {name}
                    </span>
                </span>
            </div>
        </figure>
    </Link>
);

const ProjectsCarousel = ({
    highlights,
    name,
}: {
    highlights: StoneProfileProject[];
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
                        key={highlight.slug}
                        className="w-[85%] shrink-0 snap-start sm:w-[48%] lg:w-[32%]"
                    >
                        <ProjectCard
                            name={highlight.name}
                            image={highlight.thumbnail}
                            href={homeShow.url({
                                slug: highlight.slug,
                            })}
                        />
                    </div>
                ))}
            </Carousel>
        </div>
    );
};

const StoneProfile = ({ stone: { data: stone } }: StoneProfileProps) => {
    const { url } = usePage();
    const [lightboxImage, setLightboxImage] = useState<LightboxImage | null>(
        null,
    );

    // Both stone doors return to their own feed, including on a direct visit.
    const goBack = () => {
        router.visit(
            url.startsWith('/saved/') ? savedIndex.url() : homeIndex.url(),
        );
    };

    return (
        <div className="w-full">
            <section className="relative flex min-h-[280px] items-center overflow-hidden text-on-image sm:min-h-[480px]">
                <img
                    src={stone.thumbnail}
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
                        {stone.name}
                    </h1>
                    {stone.bestSeller && (
                        <span className="mt-6 inline-flex items-center gap-1 rounded-md bg-black px-2 py-1 text-[0.65rem] font-bold tracking-[0.05em] text-white uppercase">
                            <Star
                                className="size-3 shrink-0 fill-white text-white"
                                aria-hidden
                            />
                            Best Seller
                        </span>
                    )}
                </div>
            </section>

            <section className="py-16 sm:py-12">
                <p className="mx-auto max-w-4xl text-center text-sm leading-relaxed text-secondary-foreground sm:text-xl">
                    {stone.description}
                </p>
            </section>

            <section>
                <ProfileGallery
                    images={stone.gallery}
                    name={stone.name}
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
                                    label="Stone category"
                                    value={stone.specifications.stoneType}
                                />
                                <SpecRow
                                    label="Origin"
                                    value={stone.specifications.origin}
                                />
                                <SpecRow
                                    label="Size"
                                    value={stone.specifications.size}
                                />
                                <SpecRow
                                    label="Thickness"
                                    value={`${stone.specifications.thicknessMm} mm`}
                                />
                            </dl>
                        </div>
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Available Finishes
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {stone.finishes.map((finish) => (
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
                            <BulletedList items={stone.applications} />
                        </div>
                        <div>
                            <h3 className="mb-3 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Care &amp; Maintenance
                            </h3>
                            <BulletedList items={stone.care} />
                        </div>
                    </div>
                </div>
            </section>

            {stone.projects.length > 0 && (
                <section
                    id="projects"
                    aria-labelledby="projects-heading"
                    className="scroll-mt-24"
                >
                    <ProjectsCarousel
                        highlights={stone.projects}
                        name={stone.name}
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

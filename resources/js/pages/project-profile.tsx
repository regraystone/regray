import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ImageLightbox, { type LightboxImage } from '@/components/image-lightbox';
import ProfileGallery from '@/components/profile-gallery';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { PROJECT_CATEGORIES, projects } from '@/data/projects';
import { stones } from '@/data/stones';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex, stone as homeStone } from '@/routes/home';

type ProjectProfileProps = {
    projectId: string;
};

const ProjectProfileSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        {/* One column below lg: title, image, then the facts under it. */}
        <div className="lg:grid lg:grid-cols-2 lg:gap-x-12">
            <div>
                <Skeleton className="h-9 w-24" />
                <Skeleton className="mx-auto mt-4 h-9 w-3/5 sm:h-10 md:mx-0 lg:h-12" />
            </div>
            <Skeleton className="mt-6 aspect-[4/5] w-full rounded-none lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0 lg:aspect-[6/7]" />
            <div className="mt-8 flex flex-col md:max-w-2xl lg:col-start-1 lg:row-start-2 lg:mt-0 lg:self-end">
                <div className="order-2 mt-8 grid grid-cols-2 gap-x-6 gap-y-5 lg:order-1 lg:mt-0">
                    {Array.from({ length: 4 }, (_, index) => (
                        <div key={index}>
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="mt-1.5 h-4 w-full" />
                        </div>
                    ))}
                </div>
                <div className="order-1 space-y-3 lg:order-2 lg:mt-8">
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-3/4" />
                </div>
            </div>
        </div>

        <section className="mt-8 lg:mt-16">
            <Skeleton className="aspect-3/4 w-full rounded-none lg:aspect-[16/9]" />
        </section>

        <section className="mt-8 lg:mt-16">
            <Skeleton className="h-8 w-56" />
            <div className="mt-6 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
                {Array.from({ length: 3 }, (_, index) => (
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

const ProjectProfile = ({ projectId }: ProjectProfileProps) => {
    const project = projects.find((entry) => entry.id === projectId);
    const { savedIds, toggleSave } = useSavedStones();
    const [isLoading, setIsLoading] = useState(true);
    const [lightboxImage, setLightboxImage] = useState<LightboxImage | null>(
        null,
    );

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    if (isLoading) {
        return <ProjectProfileSkeleton />;
    }

    /*
     * The route carries the id and the page resolves it here, so an id the
     * data does not know is the page's own miss rather than the route's: the
     * same block an unknown stone id draws on the stone profile. Whether that
     * becomes a 404 is the handler's TODO, not this page's.
     */
    if (!project) {
        return (
            <div className="mx-auto w-full max-w-6xl py-16 text-center">
                <h1 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                    Project not found
                </h1>
                <p className="mt-3 text-sm text-secondary-foreground">
                    The project you are looking for does not exist.
                </p>
                <Button asChild className="mt-6">
                    <Link href={homeIndex.url()}>Back to Home</Link>
                </Button>
            </div>
        );
    }

    const categories = project.categories
        .map(
            (value) =>
                PROJECT_CATEGORIES.find((entry) => entry.value === value)
                    ?.label ?? value,
        )
        .join(', ');

    const projectStones = project.stones
        .map((stoneId) => stones.find((entry) => entry.id === stoneId))
        .filter((stone) => stone !== undefined);

    const galleryImages = project.gallery.map((image) => ({ src: image.src }));

    return (
        <div className="mx-auto w-full max-w-6xl">
            {/*
             * The mobile order is the DOM order: return and a centred title,
             * the image, then the facts with Discover leading them. At lg one
             * grid splits it in two — the image spans both rows and sizes
             * them, so the left column stretches to it and the facts pinned
             * to the bottom of the second row flip back to details first,
             * leaving the same gap under the title as the reference.
             */}
            <section className="lg:grid lg:grid-cols-2 lg:gap-x-12">
                <div>
                    <Button asChild variant="ghost" className="h-9 px-0">
                        <Link href={homeIndex.url()}>
                            <ArrowLeft aria-hidden />
                            Return
                        </Link>
                    </Button>
                    <h1 className="mt-4 text-center text-3xl leading-tight font-light tracking-tight uppercase md:text-left md:text-4xl lg:text-5xl">
                        {project.name}
                    </h1>
                </div>

                <img
                    src={project.thumbnail}
                    alt={project.name}
                    className="mt-6 aspect-[4/5] w-full object-cover lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0 lg:aspect-[6/7]"
                />

                <div className="mt-8 flex flex-col md:max-w-2xl lg:col-start-1 lg:row-start-2 lg:mt-0 lg:self-end">
                    <dl className="order-2 mt-8 grid grid-cols-2 gap-x-6 gap-y-5 lg:order-1 lg:mt-0">
                        <div>
                            <dt className="text-xs font-bold text-secondary-foreground">
                                Categories
                            </dt>
                            <dd className="mt-1.5 text-sm text-foreground">
                                {categories}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold text-secondary-foreground">
                                Location
                            </dt>
                            <dd className="mt-1.5 text-sm text-foreground">
                                {project.location}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold text-secondary-foreground">
                                Materials
                            </dt>
                            <dd className="mt-1.5 text-sm text-foreground">
                                {projectStones
                                    .map((stone) => stone.name)
                                    .join(', ')}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold text-secondary-foreground">
                                Year
                            </dt>
                            <dd className="mt-1.5 text-sm text-foreground">
                                {String(project.completedYear)}
                            </dd>
                        </div>
                    </dl>

                    <div className="order-1 lg:order-2 lg:mt-8">
                        <h2 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Discover
                        </h2>
                        <p className="mt-3 text-sm leading-relaxed text-foreground">
                            {project.overview}
                        </p>
                    </div>
                </div>
            </section>

            <section className="mt-8 lg:mt-16">
                <ProfileGallery
                    images={galleryImages}
                    name={project.name}
                    onOpenImage={setLightboxImage}
                />
            </section>

            <section className="mt-8 lg:mt-16">
                <h2 className="text-2xl leading-tight font-light tracking-tight uppercase lg:text-4xl">
                    Materials
                </h2>
                <div className="mt-6 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
                    {projectStones.map((stone) => (
                        <StoneCard
                            key={stone.id}
                            stone={stone}
                            aspect="aspect-[4/3]"
                            href={homeStone.url({
                                project: project.id,
                                stone: stone.id,
                            })}
                            saved={savedIds.has(stone.id)}
                            onToggleSave={() => toggleSave(stone.id)}
                        />
                    ))}
                </div>
            </section>

            <ImageLightbox
                image={lightboxImage}
                onClose={() => setLightboxImage(null)}
            />
        </div>
    );
};

export default ProjectProfile;

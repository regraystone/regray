import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import ImageLightbox, { type LightboxImage } from '@/components/image-lightbox';
import ProfileGallery from '@/components/profile-gallery';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import type { ProjectProfile as ProjectProfileData } from '@/types/home';

type ProjectProfileProps = {
    project: { data: ProjectProfileData };
};

const ProjectProfile = ({
    project: { data: project },
}: ProjectProfileProps) => {
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const [lightboxImage, setLightboxImage] = useState<LightboxImage | null>(
        null,
    );

    const categories = project.categories
        .map((category) => category.name)
        .join(', ');

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
                        <Link
                            href={homeIndex.url({
                                query: { collection: 'projects' },
                            })}
                        >
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
                    className="mt-6 aspect-[4/5] w-full rounded-md object-cover lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:mt-0 lg:aspect-[6/7]"
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
                                {project.stones
                                    .map((stone) => stone.name)
                                    .join(', ')}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-bold text-secondary-foreground">
                                Year
                            </dt>
                            <dd className="mt-1.5 text-sm text-foreground">
                                {project.completedYear}
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
                    images={project.gallery}
                    name={project.name}
                    onOpenImage={setLightboxImage}
                />
            </section>

            <section className="mt-8 lg:mt-16">
                <h2 className="text-2xl leading-tight font-light tracking-tight uppercase lg:text-4xl">
                    Materials
                </h2>
                <div className="mt-6 grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-4">
                    {project.stones.map((stone) => (
                        <StoneCard
                            key={stone.slug}
                            stone={{
                                id: stone.slug,
                                slug: stone.slug,
                                name: stone.name,
                                thumbnail: stone.thumbnail,
                                recommended: stone.recommended,
                                category: stone.category,
                            }}
                            aspect="aspect-[4/3]"
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
            </section>

            <ImageLightbox
                image={lightboxImage}
                onClose={() => setLightboxImage(null)}
            />
        </div>
    );
};

export default ProjectProfile;

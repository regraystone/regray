import { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight } from 'lucide-react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import { Button } from '@/components/ui/button';
import { stoneProfiles, type StoneProfile } from '@/data/constant';
import { index as homeIndex } from '@/routes/home';
import { index as stonesIndex } from '@/routes/stones';

type StoneProfileProps = {
    stoneId: string;
};

const anchors = [
    { number: '01', id: 'gallery', label: 'Gallery' },
    { number: '02', id: 'facts', label: 'Facts' },
    { number: '03', id: 'projects', label: 'Projects' },
];

const scrollToId = (id: string) => {
    const element = document.getElementById(id);

    if (!element) {
        return;
    }

    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;

    element.scrollIntoView({
        behavior: prefersReducedMotion ? 'auto' : 'smooth',
        block: 'start',
    });
};

const SectionIntro = ({
    number,
    title,
    id,
}: {
    number: string;
    title: string;
    id?: string;
}) => (
    <div className="mb-8">
        <p className="mb-2 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
            {number} — {title}
        </p>
        <h2
            id={id}
            className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
        >
            {title}
        </h2>
    </div>
);

const SpecRow = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-baseline justify-between gap-6 border-b border-sidebar-border py-3.5 last:border-0">
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="text-right text-base font-semibold text-foreground">
            {value}
        </dd>
    </div>
);

const BulletedList = ({ items }: { items: string[] }) => (
    <ul className="space-y-3">
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

const StoneGallery = ({ images, name }: { images: string[]; name: string }) => {
    const carouselRef = useRef<CarouselHandle>(null);
    const [activeIndex, setActiveIndex] = useState(0);

    const counter = `${String(activeIndex + 1).padStart(2, '0')} / ${String(
        images.length,
    ).padStart(2, '0')}`;

    return (
        <Carousel
            ref={carouselRef}
            ariaLabel={`${name} gallery`}
            onActiveChange={setActiveIndex}
            className="relative bg-background"
            viewportClassName="snap-mandatory"
            controls={
                images.length > 1 ? (
                    <>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollPrev()}
                            aria-label="Previous photo"
                            className="absolute inset-y-0 left-2 z-10 my-auto grid size-11 place-items-center rounded-full border border-sidebar-border bg-background/80 text-foreground backdrop-blur-sm transition hover:bg-background"
                        >
                            <ChevronLeft className="size-5" aria-hidden />
                        </button>
                        <button
                            type="button"
                            onClick={() => carouselRef.current?.scrollNext()}
                            aria-label="Next photo"
                            className="absolute inset-y-0 right-2 z-10 my-auto grid size-11 place-items-center rounded-full border border-sidebar-border bg-background/80 text-foreground backdrop-blur-sm transition hover:bg-background"
                        >
                            <ChevronRight className="size-5" aria-hidden />
                        </button>
                        <span className="absolute right-3 bottom-3 z-10 bg-background/80 px-3 py-1.5 text-[0.7rem] font-bold tracking-[0.2em] text-foreground uppercase">
                            {counter}
                        </span>
                    </>
                ) : undefined
            }
        >
            {images.map((src, index) => (
                <div
                    key={src}
                    className="aspect-[16/9] w-full shrink-0 snap-center"
                >
                    <img
                        src={src}
                        alt={`${name} photo ${index + 1}`}
                        loading={index === 0 ? 'eager' : 'lazy'}
                        className="size-full object-cover"
                    />
                </div>
            ))}
        </Carousel>
    );
};

const ProjectCard = ({ id, name, image }: StoneProfile['projects'][number]) => (
    <div key={id} className="w-[85%] shrink-0 snap-start sm:w-[55%] lg:w-[42%]">
        <Link href="#" onClick={(e) => e.preventDefault()} className="block">
            <figure className="stone-card cursor-pointer border border-sidebar-border bg-background">
                <div className="stone-card-media relative aspect-[4/3] overflow-hidden bg-background">
                    <img
                        src={image}
                        alt={name}
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
    </div>
);

const ProjectsCarousel = ({
    projects,
    name,
}: {
    projects: StoneProfile['projects'];
    name: string;
}) => {
    const carouselRef = useRef<CarouselHandle>(null);

    return (
        <div>
            <div className="mb-8 flex items-end justify-between gap-4">
                <div>
                    <p className="mb-2 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                        03 — Projects
                    </p>
                    <h2
                        id="projects-heading"
                        className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl"
                    >
                        Featured Projects
                    </h2>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => carouselRef.current?.scrollPrev()}
                        aria-label="Previous projects"
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => carouselRef.current?.scrollNext()}
                        aria-label="Next projects"
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>
            <Carousel ref={carouselRef} ariaLabel={`Projects using ${name}`}>
                {projects.map((project) => (
                    <ProjectCard key={project.id} {...project} />
                ))}
            </Carousel>
        </div>
    );
};

const StoneProfile = ({ stoneId }: StoneProfileProps) => {
    const profile = stoneProfiles[stoneId];

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

    const floatingBackButton = (
        <Button
            asChild
            variant="default"
            size="icon"
            className="fixed right-4 bottom-4 z-40 size-11 rounded-full"
            aria-label="Back to Home"
        >
            <Link href={homeIndex.url()}>
                <ArrowLeft className="size-5" aria-hidden />
            </Link>
        </Button>
    );

    return (
        <div className="-my-8">
            {createPortal(floatingBackButton, document.body)}

            <section className="relative -mx-5 flex min-h-[55vh] items-center overflow-hidden text-on-image sm:min-h-[68vh] lg:-mx-8">
                <img
                    src={profile.image}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="image-tint absolute inset-0" aria-hidden />
                <div className="relative z-10 mx-auto w-full max-w-4xl px-5 py-16 text-center sm:py-24">
                    <p className="eyebrow text-on-image/85">
                        {profile.specifications.stoneType}
                    </p>
                    <h1 className="hero-title mt-5 text-on-image">
                        {profile.name}
                    </h1>
                    <p className="mt-6 text-xs font-bold tracking-[0.2em] text-on-image/80 uppercase">
                        {profile.specifications.origin} ·{' '}
                        {profile.specifications.thickness}
                    </p>
                </div>
            </section>

            <section className="py-16 sm:py-20">
                <p className="mx-auto max-w-4xl text-center text-lg leading-relaxed text-secondary-foreground sm:text-xl">
                    {profile.description}
                </p>
            </section>

            <nav
                aria-label="On this page"
                className="border-y border-sidebar-border py-4"
            >
                <div className="flex flex-wrap items-center justify-center gap-x-8 gap-y-2">
                    {anchors.map(({ number, id, label }) => (
                        <button
                            key={id}
                            type="button"
                            onClick={() => scrollToId(id)}
                            className="group flex items-baseline gap-2 text-xs font-bold tracking-[0.18em] text-secondary-foreground uppercase transition hover:text-foreground"
                        >
                            <span className="text-secondary-foreground/60">
                                {number}
                            </span>
                            {label}
                        </button>
                    ))}
                </div>
            </nav>

            <section
                id="gallery"
                aria-labelledby="gallery-heading"
                className="scroll-mt-24 py-16 sm:py-20"
            >
                <SectionIntro
                    number="01"
                    title="Gallery"
                    id="gallery-heading"
                />
                <StoneGallery images={profile.gallery} name={profile.name} />
            </section>

            <section
                id="facts"
                aria-labelledby="facts-heading"
                className="scroll-mt-24 border-t border-sidebar-border py-16 sm:py-20"
            >
                <SectionIntro number="02" title="Facts" id="facts-heading" />
                <div className="grid gap-12 lg:grid-cols-2">
                    <div className="border-t border-sidebar-border pt-6">
                        <h3 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
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
                    <div className="space-y-12">
                        <div className="border-t border-sidebar-border pt-6">
                            <h3 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Applications
                            </h3>
                            <BulletedList items={profile.applications} />
                        </div>
                        <div className="border-t border-sidebar-border pt-6">
                            <h3 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                                Available Finishes
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {profile.finishes.map((finish) => (
                                    <span
                                        key={finish}
                                        className="border border-sidebar-border bg-background px-3 py-1.5 text-xs font-bold tracking-[0.05em] text-foreground uppercase"
                                    >
                                        {finish}
                                    </span>
                                ))}
                            </div>
                        </div>
                        <div className="border-t border-sidebar-border pt-6">
                            <h3 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
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
                className="scroll-mt-24 border-t border-sidebar-border py-16 sm:py-20"
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

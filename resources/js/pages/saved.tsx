import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Handshake } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { products, type FeedItem } from '@/data/constant';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex, show as homeShow } from '@/routes/home';
import { show as savedShow } from '@/routes/saved';

const SavedSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <section>
            <div className="flex w-full flex-col items-center justify-between gap-6 border border-border bg-primary/5 p-6 sm:flex-row sm:gap-8 sm:px-8 sm:py-7 lg:gap-12 lg:px-10">
                <div className="flex w-full flex-col items-center gap-4 text-center sm:w-auto sm:flex-row sm:gap-6 sm:text-left">
                    <Skeleton className="size-12 shrink-0" />
                    <div className="w-full space-y-2 sm:w-64">
                        <Skeleton className="h-6 w-3/4" />
                        <Skeleton className="h-4 w-full" />
                    </div>
                </div>
                <Skeleton className="h-11 w-full sm:w-40" />
            </div>
        </section>

        <section className="mt-8">
            <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
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

        <section className="mt-8">
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

const StoneRow = ({
    title,
    items,
    savedIds,
    toggleSave,
}: {
    title: string;
    items: FeedItem[];
    savedIds: Set<string>;
    toggleSave: (id: string) => void;
}) => {
    const carouselRef = useRef<CarouselHandle>(null);
    const [atStart, setAtStart] = useState(true);
    const [atEnd, setAtEnd] = useState(false);

    const handleBoundsChange = (start: boolean, end: boolean) => {
        setAtStart(start);
        setAtEnd(end);
    };

    if (items.length === 0) {
        return (
            <p className="py-16 text-center text-sm font-semibold text-secondary-foreground">
                No {title.toLowerCase()} right now — check back soon.
            </p>
        );
    }

    return (
        <div>
            <div className="mb-8 flex items-end justify-between gap-4">
                <h2 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                    {title}
                </h2>
                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atStart}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollPrev()}
                        aria-label={`Previous ${title}`}
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={atEnd}
                        className="enabled:cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                        onClick={() => carouselRef.current?.scrollNext()}
                        aria-label={`Next ${title}`}
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>
            <Carousel
                ref={carouselRef}
                ariaLabel={title}
                dragEnabled={false}
                viewportClassName="gap-4 md:gap-5"
                onBoundsChange={handleBoundsChange}
            >
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="w-[85%] shrink-0 snap-start sm:w-[48%] lg:w-[32%]"
                    >
                        <StoneCard
                            item={item}
                            aspect="aspect-[4/3]"
                            href={homeShow.url({ stone: item.id })}
                            saved={savedIds.has(item.id)}
                            onToggleSave={() => toggleSave(item.id)}
                        />
                    </div>
                ))}
            </Carousel>
        </div>
    );
};

const Saved = () => {
    const { savedIds, toggleSave } = useSavedStones();
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    if (isLoading) {
        return <SavedSkeleton />;
    }

    const savedItems = products.filter((item) => savedIds.has(item.id));
    const recommendedItems = products.filter((item) => item.recommended);

    if (savedItems.length === 0) {
        return (
            <div className="mx-auto w-full max-w-6xl">
                <section className="mt-8 py-16 text-center">
                    <p className="text-sm font-semibold text-secondary-foreground">
                        No saved stones yet — tap the heart on a stone to save
                        it here.
                    </p>
                    <Button asChild className="mt-6">
                        <Link href={homeIndex.url()}>Browse Stones</Link>
                    </Button>
                </section>
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <section>
                <div className="flex w-full flex-col items-center justify-between gap-6 border border-border bg-primary/5 p-6 sm:flex-row sm:gap-8 sm:px-8 sm:py-7 lg:gap-12 lg:px-10">
                    <div className="flex w-full flex-col items-center gap-4 text-center sm:w-auto sm:flex-row sm:gap-6 sm:text-left">
                        <span className="grid size-12 shrink-0 place-items-center rounded-none bg-primary text-primary-foreground">
                            <Handshake className="size-6" aria-hidden />
                        </span>
                        <div>
                            <h2 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                                Ready to source these stones?
                            </h2>
                            <p className="mx-auto mt-2 max-w-md text-sm text-secondary-foreground sm:mx-0 sm:max-w-lg">
                                Inquire now and our team will get back to you
                                with a quote.
                            </p>
                        </div>
                    </div>
                    <Button
                        asChild
                        className="h-11 w-full shrink-0 sm:h-auto sm:w-auto"
                    >
                        <a href="mailto:quotes@regray.com">Request a quote</a>
                    </Button>
                </div>
            </section>

            <section className="mt-8">
                <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                    {savedItems.map((item) => (
                        <StoneCard
                            key={item.id}
                            item={item}
                            aspect="aspect-[4/3]"
                            href={savedShow.url({ stone: item.id })}
                            saved
                            onToggleSave={() => toggleSave(item.id)}
                        />
                    ))}
                </div>
            </section>

            <section className="mt-8">
                <StoneRow
                    title="Recommended for you"
                    items={recommendedItems}
                    savedIds={savedIds}
                    toggleSave={toggleSave}
                />
            </section>
        </div>
    );
};

export default Saved;

import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Handshake } from 'lucide-react';
import { useRef } from 'react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import StaggeredGrid from '@/components/staggered-grid';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { products, type FeedItem } from '@/data/constant';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex } from '@/routes/home';

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
                        className="cursor-pointer"
                        onClick={() => carouselRef.current?.scrollPrev()}
                        aria-label={`Previous ${title}`}
                    >
                        <ChevronLeft aria-hidden />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="cursor-pointer"
                        onClick={() => carouselRef.current?.scrollNext()}
                        aria-label={`Next ${title}`}
                    >
                        <ChevronRight aria-hidden />
                    </Button>
                </div>
            </div>
            <Carousel ref={carouselRef} ariaLabel={title}>
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="w-[85%] shrink-0 snap-start sm:w-[55%] lg:w-[42%]"
                    >
                        <StoneCard
                            item={item}
                            aspect="aspect-[4/3]"
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
                <div className="flex w-full flex-col items-center justify-between gap-6 rounded-xl border border-border bg-primary/5 p-6 sm:flex-row sm:gap-8 sm:px-8 sm:py-7 lg:gap-12 lg:px-10">
                    <div className="flex w-full flex-col items-center gap-4 text-center sm:w-auto sm:flex-row sm:gap-6 sm:text-left">
                        <span className="grid size-12 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
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
                <StaggeredGrid
                    items={savedItems}
                    renderItem={(item, aspect) => (
                        <StoneCard
                            item={item}
                            aspect={aspect}
                            saved
                            onToggleSave={() => toggleSave(item.id)}
                        />
                    )}
                />
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

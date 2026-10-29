import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Handshake } from 'lucide-react';
import { useState, useRef } from 'react';
import Carousel, { type CarouselHandle } from '@/components/carousel';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex } from '@/routes/home';
import { show as savedShow } from '@/routes/saved';
import type { StoneCardData } from '@/types/stone';

/**
 * Width of each stone slide in the carousel must track the saved grid's columns
 * and gaps: grid is grid-cols-2 gap-4 md:grid-cols-4 md:gap-5 xl:grid-cols-5.
 */
const STONE_SLIDE_WIDTH =
    'w-[calc((100%_-_1rem)/2)] md:w-[calc((100%_-_3.75rem)/4)] xl:w-[calc((100%_-_5rem)/5)]';

type SavedPageProps = {
    savedStones: { data: StoneCardData[] };
    recommendedStones: { data: StoneCardData[] };
};

const StoneRow = ({
    title,
    stones,
    savedIds,
    toggleSave,
    canSave,
}: {
    title: string;
    stones: StoneCardData[];
    savedIds: Set<string>;
    toggleSave: (id: string) => void;
    canSave: boolean;
}) => {
    const carouselRef = useRef<CarouselHandle>(null);
    const [atStart, setAtStart] = useState(true);
    const [atEnd, setAtEnd] = useState(false);

    const handleBoundsChange = (start: boolean, end: boolean) => {
        setAtStart(start);
        setAtEnd(end);
    };

    if (stones.length === 0) {
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
                {stones.map((stone) => (
                    <div
                        key={stone.slug}
                        className={`${STONE_SLIDE_WIDTH} shrink-0 snap-start`}
                    >
                        <StoneCard
                            stone={{
                                id: stone.slug,
                                slug: stone.slug,
                                name: stone.name,
                                thumbnail: stone.thumbnail,
                                recommended: stone.recommended,
                                category: stone.category,
                            }}
                            aspect="aspect-[4/3]"
                            href={savedShow.url({ stone: stone.slug } as {
                                stone: string;
                            })}
                            saved={savedIds.has(stone.slug)}
                            onToggleSave={
                                canSave
                                    ? () => toggleSave(stone.slug)
                                    : undefined
                            }
                        />
                    </div>
                ))}
            </Carousel>
        </div>
    );
};

const Saved = ({ savedStones, recommendedStones }: SavedPageProps) => {
    const { savedIds, toggleSave, canSave } = useSavedStones({
        refresh: ['savedStones'],
    });
    // The entry hook owns the click: a saved draft is offered the choice of
    // continuing it or starting fresh before the stepper is opened.
    const { inquire, onInquireClick, draftPrompt } = useInquiryEntry();

    const savedStonesList = savedStones.data;
    const recommendedStonesList = recommendedStones.data;

    if (savedStonesList.length === 0) {
        return (
            <div className="w-full">
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
        <div className="w-full">
            <section>
                <div className="flex w-full flex-col items-center justify-between gap-6 rounded-md border border-border bg-primary/5 p-6 sm:flex-row sm:gap-8 sm:px-8 sm:py-7 lg:gap-12 lg:px-10">
                    <div className="flex w-full flex-col items-center gap-4 text-center sm:w-auto sm:flex-row sm:gap-6 sm:text-left">
                        <span className="grid size-12 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground">
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
                    {inquire ? (
                        <Button
                            type="button"
                            className="h-11 w-full shrink-0 sm:h-auto sm:w-auto"
                            onClick={onInquireClick}
                        >
                            Request a quote
                        </Button>
                    ) : null}
                </div>
            </section>

            <section className="mt-8">
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-5 xl:grid-cols-5">
                    {savedStonesList.map((stone) => (
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
                            href={savedShow.url({ stone: stone.slug } as {
                                stone: string;
                            })}
                            saved
                            onToggleSave={
                                canSave
                                    ? () => toggleSave(stone.slug)
                                    : undefined
                            }
                        />
                    ))}
                </div>
            </section>

            <section className="mt-8">
                <StoneRow
                    title="Recommended for you"
                    stones={recommendedStonesList}
                    savedIds={savedIds}
                    toggleSave={toggleSave}
                    canSave={canSave}
                />
            </section>

            {draftPrompt}
        </div>
    );
};

export default Saved;

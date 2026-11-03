import { Link } from '@inertiajs/react';
import { Handshake } from 'lucide-react';
import ProductCard from '@/components/product-card';
import { Button } from '@/components/ui/button';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';
import { useSavedFurniture, useSavedStones } from '@/hooks/use-saved-stones';
import { index as homeIndex } from '@/routes/home';
import { show as savedShow } from '@/routes/saved';
import type { FurnitureCardData } from '@/types/home';
import type { StoneCardData } from '@/types/stone';

type SavedPageProps = {
    savedStones: { data: StoneCardData[] };
    savedFurniture: { data: FurnitureCardData[] };
};

const SectionHeading = ({ children }: { children: string }) => (
    <h2 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        {children}
    </h2>
);

const Saved = ({ savedStones, savedFurniture }: SavedPageProps) => {
    const { toggleSave, canSave } = useSavedStones({
        refresh: ['savedStones'],
    });
    const furnitureSaves = useSavedFurniture({
        refresh: ['savedFurniture'],
    });
    // The entry hook owns the click: a saved draft is offered the choice of
    // continuing it or starting fresh before the stepper is opened.
    const { inquire, onInquireClick, draftPrompt } = useInquiryEntry();

    const savedStonesList = savedStones.data;
    const savedFurnitureList = savedFurniture.data;
    // Headings only help when both kinds are on the page.
    const showHeadings =
        savedStonesList.length > 0 && savedFurnitureList.length > 0;

    if (savedStonesList.length === 0 && savedFurnitureList.length === 0) {
        return (
            <div className="w-full">
                <section className="mt-8 py-16 text-center">
                    <p className="text-sm font-semibold text-secondary-foreground">
                        Nothing saved yet — tap the heart on a stone or a piece
                        of furniture to save it here.
                    </p>
                    <Button asChild className="mt-6">
                        <Link href={homeIndex.url()}>Browse Collection</Link>
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
                                Ready to source these pieces?
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

            {savedStonesList.length > 0 ? (
                <section className="mt-8">
                    {showHeadings ? (
                        <SectionHeading>Natural Stone</SectionHeading>
                    ) : null}
                    <div className="grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5">
                        {savedStonesList.map((stone) => (
                            <ProductCard
                                key={stone.slug}
                                product={{
                                    id: stone.slug,
                                    slug: stone.slug,
                                    name: stone.name,
                                    thumbnail: stone.thumbnail,
                                    bestSeller: stone.bestSeller,
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
            ) : null}

            {savedFurnitureList.length > 0 ? (
                <section className="mt-8">
                    {showHeadings ? (
                        <SectionHeading>Furniture</SectionHeading>
                    ) : null}
                    <div className="grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5">
                        {savedFurnitureList.map((item) => (
                            <ProductCard
                                key={item.slug}
                                product={{
                                    ...item,
                                    category: item.projectType,
                                }}
                                aspect="aspect-[4/3]"
                                href={`/dashboard/saved/furniture/${item.slug}`}
                                saved
                                onToggleSave={
                                    furnitureSaves.canSave
                                        ? () =>
                                              furnitureSaves.toggleSave(
                                                  item.slug,
                                              )
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                </section>
            ) : null}

            {draftPrompt}
        </div>
    );
};

export default Saved;

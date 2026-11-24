import { Handshake } from 'lucide-react';
import ProductCard from '@/components/product-card';
import SavedCollectionIntro, {
    type SavedCategories,
} from '@/components/saved/saved-collection-intro';
import { Button } from '@/components/ui/button';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';
import { useSavedFurniture, useSavedStones } from '@/hooks/use-saved-stones';
import { show as brandFurnitureShow } from '@/routes/dashboard/brands/furniture';
import { show as brandStoneShow } from '@/routes/dashboard/brands/stones';
import type { FurnitureCardData } from '@/types/home';
import type { StoneCardData } from '@/types/stone';

type SavedPageProps = {
    savedStones: { data: StoneCardData[] };
    savedFurniture: { data: FurnitureCardData[] };
    categories: SavedCategories;
};

const SectionHeading = ({ children }: { children: string }) => (
    <h2 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        {children}
    </h2>
);

/**
 * Saved: "Your Saved Collection" and "Explore by category" (the catalog's
 * ways in), then the stones and furniture the reader has saved, with a way
 * to ask for a quote on them.
 */
const Saved = ({ savedStones, savedFurniture, categories }: SavedPageProps) => {
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
    const savedCount = savedStonesList.length + savedFurnitureList.length;
    // Headings only help when both kinds are on the page.
    const showHeadings =
        savedStonesList.length > 0 && savedFurnitureList.length > 0;

    return (
        <div className="w-full">
            <SavedCollectionIntro categories={categories} />

            <section
                aria-labelledby="saved-pieces"
                className="mt-8 border-t border-border pt-8"
            >
                <h2
                    id="saved-pieces"
                    className="mb-5 font-sans text-xs font-bold tracking-[0.16em] uppercase"
                >
                    Your saved pieces
                    {savedCount > 0 ? (
                        <span className="ml-2 font-normal text-secondary-foreground tabular-nums">
                            ({savedCount})
                        </span>
                    ) : null}
                </h2>

                {savedCount === 0 ? (
                    <p className="py-10 text-center text-sm font-semibold text-secondary-foreground">
                        Nothing saved yet — tap the heart on a stone or a piece
                        of furniture to save it here.
                    </p>
                ) : (
                    <>
                        <div className="flex w-full flex-col items-center justify-between gap-6 rounded-md border border-border bg-primary/5 p-6 sm:flex-row sm:gap-8 sm:px-8 sm:py-7 lg:gap-12 lg:px-10">
                            <div className="flex w-full flex-col items-center gap-4 text-center sm:w-auto sm:flex-row sm:gap-6 sm:text-left">
                                <span className="grid size-12 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground">
                                    <Handshake className="size-6" aria-hidden />
                                </span>
                                <div>
                                    <h3 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                                        Ready to source these pieces?
                                    </h3>
                                    <p className="mx-auto mt-2 max-w-md text-sm text-secondary-foreground sm:mx-0 sm:max-w-lg">
                                        Inquire now and our team will get back
                                        to you with a quote.
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

                        {savedStonesList.length > 0 ? (
                            <section className="mt-8">
                                {showHeadings ? (
                                    <SectionHeading>
                                        Natural Stone
                                    </SectionHeading>
                                ) : null}
                                <div className="grid grid-cols-2 gap-3.5 md:grid-cols-3 md:gap-4 lg:grid-cols-3 lg:gap-5">
                                    {savedStonesList.map((stone) => (
                                        <ProductCard
                                            key={stone.slug}
                                            product={{
                                                id: stone.slug,
                                                slug: stone.slug,
                                                name: stone.name,
                                                thumbnail: stone.thumbnail,
                                                category: stone.type,
                                            }}
                                            // Kept as history once its brand is archived, but its page is gone.
                                            href={
                                                stone.brand.archived
                                                    ? undefined
                                                    : brandStoneShow.url({
                                                          brand: stone.brand
                                                              .slug,
                                                          stone: stone.slug,
                                                      })
                                            }
                                            saved
                                            onToggleSave={
                                                canSave
                                                    ? () =>
                                                          toggleSave(stone.slug)
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
                                <div className="grid grid-cols-2 gap-3.5 md:grid-cols-3 md:gap-4 lg:grid-cols-3 lg:gap-5">
                                    {savedFurnitureList.map((item) => (
                                        <ProductCard
                                            key={item.slug}
                                            product={{
                                                ...item,
                                                category: item.furnitureType,
                                            }}
                                            href={
                                                item.brand.archived
                                                    ? undefined
                                                    : brandFurnitureShow.url({
                                                          brand: item.brand
                                                              .slug,
                                                          furniture: item.slug,
                                                      })
                                            }
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
                    </>
                )}
            </section>

            {draftPrompt}
        </div>
    );
};

export default Saved;

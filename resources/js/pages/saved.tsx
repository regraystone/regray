import { Handshake } from 'lucide-react';
import ProductCard from '@/components/product-card';
import SavedCollectionIntro from '@/components/saved/saved-collection-intro';
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
};

const SectionHeading = ({ children }: { children: string }) => (
    <h2 className="mb-4 text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
        {children}
    </h2>
);

/**
 * Saved: "Your Saved Collection", then the stones and furniture the reader
 * has saved, with "Ready to source these pieces?" below them. The bar sticks
 * to the bottom of the screen while the list runs past it, so Request a quote
 * is always in reach, and settles under the last piece at the end.
 */
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
    const savedCount = savedStonesList.length + savedFurnitureList.length;
    // Headings only help when both kinds are on the page.
    const showHeadings =
        savedStonesList.length > 0 && savedFurnitureList.length > 0;

    return (
        <div className="w-full">
            <SavedCollectionIntro />

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

                        <div
                            data-testid="saved-quote-bar"
                            className={`z-20 mt-8 flex items-center justify-between gap-4 rounded-md border border-border bg-background/95 p-3 pl-4 shadow-[0_8px_24px_rgb(0_0_0/0.08)] backdrop-blur-md sm:gap-8 sm:p-4 sm:pl-6 lg:gap-12 ${
                                inquire
                                    ? 'sticky bottom-[max(0.75rem,env(safe-area-inset-bottom,0px))] sm:bottom-5'
                                    : ''
                            }`}
                        >
                            <div className="flex min-w-0 items-center gap-4">
                                <span className="hidden size-11 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground sm:grid">
                                    <Handshake className="size-5" aria-hidden />
                                </span>
                                <div className="min-w-0">
                                    <h3 className="text-[0.9375rem] leading-snug font-bold tracking-[-0.02em] sm:text-xl">
                                        Ready to source these pieces?
                                    </h3>
                                    <p className="mt-0.5 hidden text-sm text-secondary-foreground sm:block">
                                        Inquire about your {savedCount} saved{' '}
                                        {savedCount === 1 ? 'piece' : 'pieces'}{' '}
                                        and our team will get back to you with a
                                        quote.
                                    </p>
                                </div>
                            </div>
                            {inquire ? (
                                <Button
                                    type="button"
                                    className="h-10 shrink-0 px-4 sm:h-11 sm:px-6"
                                    onClick={onInquireClick}
                                >
                                    Request a quote
                                </Button>
                            ) : null}
                        </div>
                    </>
                )}
            </section>

            {draftPrompt}
        </div>
    );
};

export default Saved;

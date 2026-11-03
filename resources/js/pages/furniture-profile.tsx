import { router } from '@inertiajs/react';
import { Armchair, ArrowLeft } from 'lucide-react';
import AskQuoteButton from '@/components/ask-quote-button';
import { Button } from '@/components/ui/button';
import { index as homeIndex } from '@/routes/home';
import type { FurnitureProfile as FurnitureProfileData } from '@/types/home';

type FurnitureProfileProps = {
    furniture: { data: FurnitureProfileData };
};

/*
 * A sample layout for a furniture piece, due a redesign: its photo (or a
 * neutral placeholder), then its name, project type and description. Like
 * the stone profile, it has "Ask a Quote" and, from the layout, a save heart.
 */
const FurnitureProfile = ({
    furniture: { data: furniture },
}: FurnitureProfileProps) => (
    <div className="w-full">
        <section className="relative flex min-h-[280px] items-center justify-center overflow-hidden bg-muted sm:min-h-[400px]">
            {furniture.thumbnail ? (
                <img
                    src={furniture.thumbnail}
                    alt=""
                    aria-hidden
                    className="absolute inset-0 size-full object-cover"
                />
            ) : (
                <Armchair
                    className="size-16 text-secondary-foreground/50"
                    aria-hidden
                />
            )}
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() =>
                    router.visit(
                        homeIndex.url({ query: { collection: 'furniture' } }),
                    )
                }
                aria-label="Back"
                className="group absolute top-4 left-4 z-20 h-11 w-11 cursor-pointer justify-center gap-2.5 border-white/25 bg-black/55 px-0 tracking-[0.12em] text-white backdrop-blur-md transition-colors duration-200 hover:border-white/40 hover:bg-black/75 hover:opacity-100 focus-visible:ring-white/60 sm:top-6 sm:left-6 sm:w-auto sm:px-4 lg:left-8"
            >
                <ArrowLeft
                    className="size-4 transition-transform duration-200 group-hover:-translate-x-0.5"
                    aria-hidden
                />
                <span className="hidden sm:inline">Back</span>
            </Button>
        </section>

        <section className="mx-auto max-w-4xl py-12 text-center sm:py-16">
            <p className="eyebrow text-secondary-foreground">
                {furniture.projectType.name}
            </p>
            <h1 className="mt-3 text-3xl font-bold tracking-[-0.04em] sm:text-4xl">
                {furniture.name}
            </h1>
            {furniture.description ? (
                <p className="mt-6 text-sm leading-relaxed text-secondary-foreground sm:text-xl">
                    {furniture.description}
                </p>
            ) : null}
        </section>

        <AskQuoteButton />
    </div>
);

export default FurnitureProfile;

import { Link } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { index as homeIndex } from '@/routes/home';

/**
 * The Saved page's opening, in Sir Mark's words: "Your Saved Collection", a
 * line on what it is, and Explore collections (the Home catalogue).
 */
export default function SavedCollectionIntro() {
    return (
        <section className="flex flex-col items-center gap-4 pt-6 pb-12 text-center sm:pt-10 sm:pb-14">
            <Heart
                aria-hidden
                strokeWidth={1.5}
                className="size-7 text-foreground"
            />
            <h1 className="text-[clamp(1.875rem,3.2vw,2.625rem)] leading-tight font-normal tracking-[-0.01em]">
                Your Saved Collection
            </h1>
            <p className="max-w-xl text-[0.9375rem] leading-relaxed text-secondary-foreground sm:text-base">
                A personal selection of materials, furniture, and designs for
                your next project.
            </p>
            <Link
                href={homeIndex.url()}
                className="mt-2 border-b border-foreground px-3 pb-3 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70"
            >
                Explore collections
            </Link>
        </section>
    );
}

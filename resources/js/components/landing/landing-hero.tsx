import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { inquire } from '@/routes/quote';
import type { BrandCardData } from '@/types/home';

/** The studio's own photo, shown first. */
const STUDIO_SLIDE = {
    src: '/images/regray-hero.jpg',
    caption: 'Regray Stone, a marble feature wall',
};

/**
 * The brands whose photos follow it, by slug: the ones large and wide enough
 * to fill a screen sharply (Ascale 2730px, Lube 1920px). A brand missing from
 * the page is skipped.
 */
const SLIDE_BRANDS = ['ascale', 'cucine'];

/** How long each slide shows before the next one, in milliseconds. */
const SLIDE_MS = 6500;

type Slide = { src: string; caption: string };

const pad = (value: number) => String(value).padStart(2, '0');

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * The full-screen opening: the studio's photo and its brands' in turn, each
 * fading in with a slow zoom, under the headline and the two ways in. It
 * stops turning on its own for anyone who asks for reduced motion; the bars
 * still pick a slide by hand.
 */
export default function LandingHero({ brands }: { brands: BrandCardData[] }) {
    const slides: Slide[] = [
        STUDIO_SLIDE,
        ...SLIDE_BRANDS.flatMap((slug) => {
            const brand = brands.find((candidate) => candidate.slug === slug);

            return brand?.thumbnail
                ? [{ src: brand.thumbnail, caption: brand.name }]
                : [];
        }),
    ];
    const [active, setActive] = useState(0);
    // Picking a slide by hand restarts the timer, so it is not cut short.
    const [picks, setPicks] = useState(0);

    useEffect(() => {
        if (slides.length < 2 || prefersReducedMotion()) {
            return;
        }

        const timer = window.setInterval(
            () => setActive((current) => (current + 1) % slides.length),
            SLIDE_MS,
        );

        return () => window.clearInterval(timer);
    }, [slides.length, picks]);

    const pick = (index: number) => {
        setActive(index);
        setPicks((count) => count + 1);
    };

    return (
        <section
            aria-label="Featured"
            className="relative h-svh min-h-150 overflow-hidden bg-night text-on-image"
        >
            {slides.map((slide, index) => (
                <div
                    key={slide.src}
                    aria-hidden={index !== active}
                    className={`absolute inset-0 transition-opacity duration-1400 ease-in-out motion-reduce:transition-none ${
                        index === active ? 'opacity-100' : 'opacity-0'
                    }`}
                >
                    <img
                        src={slide.src}
                        alt=""
                        fetchPriority={index === 0 ? 'high' : 'auto'}
                        className={`size-full object-cover transition-transform duration-7000 ease-out motion-reduce:transition-none ${
                            index === active ? 'scale-100' : 'scale-106'
                        }`}
                    />
                </div>
            ))}

            <div
                aria-hidden
                className="absolute inset-0 bg-[linear-gradient(180deg,rgb(20_18_16/0.45)_0%,rgb(20_18_16/0)_30%,rgb(20_18_16/0)_45%,rgb(20_18_16/0.7)_100%)]"
            />

            <div className="absolute inset-x-4 bottom-[clamp(5rem,12vh,7.5rem)] flex flex-wrap items-end justify-between gap-10 sm:inset-x-5 lg:inset-x-8">
                <div className="max-w-3xl">
                    <p className="mb-5 text-xs font-medium tracking-[0.24em] uppercase">
                        Stone &amp; furniture, all in one place
                    </p>
                    <h1 className="text-[clamp(2.75rem,7vw,6.5rem)] leading-[0.98] font-normal tracking-[-0.025em]">
                        Every brand.
                        <br />
                        One platform.
                    </h1>
                    <p className="mt-7 max-w-lg text-base leading-relaxed text-pretty lg:text-lg">
                        Explore natural stone, furniture and finished projects
                        from the brands we work with. Save what you like and
                        request it in a single workspace.
                    </p>
                    <div className="mt-9 flex flex-wrap items-center gap-x-7 gap-y-4">
                        <Link
                            href={inquire.url()}
                            className="bg-paper px-7 py-4.5 text-xs font-semibold tracking-[0.16em] text-ink uppercase transition-colors hover:bg-white"
                        >
                            Send an inquiry
                        </Link>
                        <a
                            href="#brands"
                            className="border-b border-current py-2 text-xs font-medium tracking-[0.16em] uppercase transition-opacity hover:opacity-75"
                        >
                            Explore our brands
                        </a>
                    </div>
                </div>

                {slides.length > 1 ? (
                    <div className="flex w-full flex-col gap-3.5 sm:w-60">
                        <p
                            aria-live="polite"
                            className="text-[0.8125rem] tracking-[0.06em]"
                        >
                            {slides[active].caption}
                        </p>
                        <div
                            role="group"
                            aria-label="Featured photos"
                            className="flex gap-2"
                        >
                            {slides.map((slide, index) => (
                                <button
                                    key={slide.src}
                                    type="button"
                                    onClick={() => pick(index)}
                                    aria-label={`Show photo ${index + 1}`}
                                    aria-current={index === active}
                                    className="flex h-6 flex-1 cursor-pointer items-center"
                                >
                                    <span
                                        className={`block h-0.5 w-full transition-colors duration-400 ${
                                            index === active
                                                ? 'bg-white'
                                                : 'bg-white/35'
                                        }`}
                                    />
                                </button>
                            ))}
                        </div>
                        <p className="text-xs tracking-[0.1em] tabular-nums">
                            {pad(active + 1)} / {pad(slides.length)}
                        </p>
                    </div>
                ) : null}
            </div>
        </section>
    );
}

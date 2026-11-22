import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Logo from '@/components/logo';
import { home } from '@/routes';
import type { BrandCardData } from '@/types/home';

/**
 * Shown when no brand has a photo: a backlit onyx wall Regray finished,
 * 2560 x 1920, sharp even when it fills the tall panel.
 */
const FALLBACK_PHOTO = '/images/regray-auth.jpg';

/** How long each brand's photo shows before the next, in milliseconds. */
const SLIDE_MS = 6000;

/**
 * Each logo's height as a share of the row's base height, by brand slug, so
 * the names read at about one size: wide wordmarks (Ascale, Faer) sit lower,
 * the square Lube badge and the stacked marks taller. A brand not listed gets
 * the default.
 */
const LOGO_SCALE: Record<string, number> = {
    'regray-stone': 0.62,
    cucine: 1.45,
    ascale: 0.62,
    faer: 0.5,
    reform: 1.05,
    'res-italia': 1.1,
};
const DEFAULT_LOGO_SCALE = 0.8;

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * The auth pages' photo side, framed like the public site's photos: the
 * brands' photos in turn, crossfading (with no zoom, which only enlarges
 * them further), the wordmark and a way back to the site at the top, and at
 * the foot a short line on what Regray does (on screens tall enough) and the
 * brands' logos in an even grid on a frosted band. A logo shows its brand's
 * photo, and the one on show is highlighted. It stops turning on its own for
 * anyone who prefers reduced motion.
 */
export default function AuthBrandPanel() {
    const brands =
        usePage<{ siteBrands?: BrandCardData[] | null }>().props.siteBrands ??
        [];
    const photographed = brands.filter((brand) => brand.thumbnail !== null);
    const slides =
        photographed.length > 0
            ? photographed.map((brand) => ({
                  slug: brand.slug,
                  photo: brand.thumbnail ?? FALLBACK_PHOTO,
              }))
            : [{ slug: '', photo: FALLBACK_PHOTO }];
    const [active, setActive] = useState(0);
    // Picking a logo restarts the timer, so its photo is not cut short.
    const [picks, setPicks] = useState(0);
    const current = Math.min(active, slides.length - 1);

    useEffect(() => {
        if (slides.length < 2 || prefersReducedMotion()) {
            return;
        }

        const timer = window.setInterval(
            () => setActive((index) => (index + 1) % slides.length),
            SLIDE_MS,
        );

        return () => window.clearInterval(timer);
    }, [slides.length, picks]);

    const show = (slug: string) => {
        const index = slides.findIndex((slide) => slide.slug === slug);

        if (index >= 0) {
            setActive(index);
            setPicks((count) => count + 1);
        }
    };

    return (
        <aside
            aria-label="Regray and its brands"
            className="flex-none bg-paper p-3 min-[900px]:sticky min-[900px]:top-0 min-[900px]:h-svh min-[900px]:flex-[1_1_54%] min-[900px]:p-4"
        >
            <div className="relative flex min-h-64 flex-col justify-between gap-6 overflow-hidden rounded-md bg-night text-white min-[900px]:h-full">
                {slides.map((slide, index) => (
                    <img
                        key={slide.slug || slide.photo}
                        src={slide.photo}
                        alt=""
                        aria-hidden
                        fetchPriority={index === 0 ? 'high' : 'auto'}
                        className={`absolute inset-0 size-full object-cover transition-opacity duration-1400 ease-out motion-reduce:transition-none ${
                            index === current ? 'opacity-100' : 'opacity-0'
                        }`}
                    />
                ))}
                <div
                    aria-hidden
                    className="absolute inset-0 bg-[linear-gradient(180deg,rgb(0_0_0/0.5)_0%,rgb(0_0_0/0)_26%,rgb(0_0_0/0)_42%,rgb(0_0_0/0.78)_100%)]"
                />

                <div className="relative flex items-center justify-between gap-4 p-5 min-[900px]:p-[clamp(1.75rem,3vw,2.75rem)] min-[900px]:pb-0">
                    <Logo className="w-fit text-white" />
                    <Link
                        href={home()}
                        className="border-b border-white/70 pb-0.5 text-base font-medium transition-colors hover:border-white"
                    >
                        Back to site
                    </Link>
                </div>

                <div className="relative flex flex-col gap-7 p-3 [--logo-base:15px] min-[900px]:p-[clamp(1rem,2vw,1.5rem)] min-[900px]:[--logo-base:20px] min-[1200px]:[--logo-base:24px]">
                    <div className="hidden max-w-130 flex-col gap-3 px-[clamp(0.75rem,1.2vw,1.25rem)] min-[900px]:flex [@media(max-height:699px)]:hidden">
                        <p className="text-[0.9375rem] font-semibold tracking-[0.04em] uppercase">
                            Regray Stone &amp; Furniture
                        </p>
                        <p className="font-display text-[clamp(1.75rem,2.6vw,2.5rem)] leading-[1.12] font-light text-balance">
                            Natural stone, kitchens and interiors for your home.
                        </p>
                    </div>

                    {brands.length > 0 ? (
                        <div className="rounded-md bg-black/45 px-4 py-4 backdrop-blur-md min-[900px]:px-6 min-[900px]:py-5">
                            <ul
                                aria-label="Our brands"
                                className="grid grid-cols-3 gap-x-4 gap-y-3 min-[1200px]:grid-cols-6 min-[1200px]:gap-x-6"
                            >
                                {brands.map((brand) => {
                                    const isCurrent =
                                        brand.slug === slides[current].slug;

                                    return (
                                        <li key={brand.slug}>
                                            <button
                                                type="button"
                                                onClick={() => show(brand.slug)}
                                                title={brand.name}
                                                aria-label={`Show ${brand.name}`}
                                                aria-pressed={isCurrent}
                                                disabled={brand.thumbnail === null}
                                                className={`flex h-[calc(var(--logo-base)*2)] w-full cursor-pointer items-center justify-center transition-opacity duration-500 hover:opacity-100 disabled:cursor-default ${
                                                    isCurrent
                                                        ? 'opacity-100'
                                                        : 'opacity-55'
                                                }`}
                                            >
                                                <img
                                                    src={brand.logo}
                                                    alt=""
                                                    loading="lazy"
                                                    style={{
                                                        height: `calc(var(--logo-base) * ${LOGO_SCALE[brand.slug] ?? DEFAULT_LOGO_SCALE})`,
                                                    }}
                                                    className="w-auto max-w-full object-contain"
                                                />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </div>
                    ) : null}
                </div>
            </div>
        </aside>
    );
}

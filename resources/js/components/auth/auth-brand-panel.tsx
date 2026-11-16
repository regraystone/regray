import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Logo from '@/components/logo';
import { home } from '@/routes';
import type { BrandCardData } from '@/types/home';

/** Shown when no brand has a photo. */
const FALLBACK_PHOTO = '/images/regray-hero.jpg';

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
    cucine: 1.5,
    ascale: 0.62,
    faer: 0.5,
    reform: 1.1,
    'res-italia': 1.15,
};
const DEFAULT_LOGO_SCALE = 0.8;

const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/**
 * The auth pages' photo side: the brands' photos in turn, each fading in with
 * a slow zoom, the wordmark and a way back to the site above, and below the
 * tagline (on screens tall enough for it) and the row of brand logos, which
 * pick a photo by hand. It stops turning on its own for anyone who prefers
 * reduced motion.
 */
export default function AuthBrandPanel({
    brands,
}: {
    brands: BrandCardData[];
}) {
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
    const currentBrand = brands.find(
        (brand) => brand.slug === slides[current].slug,
    );

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
            className="relative flex min-h-85 flex-none flex-col overflow-hidden bg-night text-on-image min-[900px]:sticky min-[900px]:top-0 min-[900px]:h-svh min-[900px]:min-h-0 min-[900px]:flex-[1_1_56%]"
        >
            {slides.map((slide, index) => (
                <img
                    key={slide.slug || slide.photo}
                    src={slide.photo}
                    alt=""
                    aria-hidden
                    fetchPriority={index === 0 ? 'high' : 'auto'}
                    className={`absolute inset-0 size-full object-cover transition-[opacity,transform] duration-[1400ms,8000ms] ease-out motion-reduce:transition-none ${
                        index === current
                            ? 'scale-100 opacity-100'
                            : 'scale-106 opacity-0'
                    }`}
                />
            ))}
            <div
                aria-hidden
                className="absolute inset-0 bg-[linear-gradient(180deg,rgb(20_18_16/0.55)_0%,rgb(20_18_16/0.1)_30%,rgb(20_18_16/0.15)_55%,rgb(20_18_16/0.82)_100%)]"
            />

            <div className="relative flex flex-1 flex-col justify-between gap-8 px-5 pt-5 pb-6 [--logo-base:15px] min-[900px]:px-[clamp(1.75rem,3.5vw,3.5rem)] min-[900px]:py-[clamp(1.75rem,3vw,3rem)] min-[900px]:[--logo-base:20px] min-[1200px]:[--logo-base:26px] [@media(max-height:719px)]:[--logo-base:20px] min-[900px]:[@media(max-height:719px)]:px-[clamp(1.5rem,3vw,2.5rem)] min-[900px]:[@media(max-height:719px)]:py-6">
                <div className="flex items-center justify-between gap-4">
                    <Logo className="w-fit text-white" />
                    <Link
                        href={home()}
                        className="border-b border-white/60 pb-1 text-xs font-medium tracking-[0.16em] uppercase transition-colors hover:border-white"
                    >
                        Back to site
                    </Link>
                </div>

                <div className="flex flex-col gap-10 [@media(max-height:719px)]:gap-6">
                    <div className="hidden max-w-140 flex-col gap-4 min-[900px]:flex [@media(max-height:639px)]:hidden">
                        <p className="text-xs font-medium tracking-[0.24em] uppercase">
                            Bespoke stone &amp; furniture
                        </p>
                        <p className="text-[clamp(2rem,3vw,3rem)] leading-[1.08] tracking-[-0.02em] text-pretty">
                            One account. Every inquiry, from first sketch to
                            final installation.
                        </p>
                    </div>

                    {brands.length > 0 ? (
                        <div className="flex flex-col gap-4.5 border-t border-white/30 pt-5">
                            <div className="flex justify-between gap-4 text-xs tracking-[0.06em]">
                                <span>Our brands</span>
                                {currentBrand ? (
                                    <span
                                        aria-live="polite"
                                        className="tabular-nums"
                                    >
                                        {String(current + 1).padStart(2, '0')}{' '}
                                        {currentBrand.name}
                                    </span>
                                ) : null}
                            </div>
                            <ul
                                aria-label="Our brands"
                                className="grid grid-cols-3 items-center gap-x-5 gap-y-4.5 min-[900px]:gap-x-8 min-[900px]:gap-y-6 min-[1200px]:flex min-[1200px]:justify-between min-[1200px]:gap-7"
                            >
                                {brands.map((brand) => {
                                    const isCurrent =
                                        brand.slug === slides[current].slug;

                                    return (
                                        <li
                                            key={brand.slug}
                                            className="flex h-[calc(var(--logo-base)*1.6)] items-center"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => show(brand.slug)}
                                                aria-label={`Show ${brand.name}`}
                                                aria-pressed={isCurrent}
                                                disabled={
                                                    brand.thumbnail === null
                                                }
                                                className={`flex h-full max-w-full cursor-pointer items-center transition-opacity duration-500 hover:opacity-100 disabled:cursor-default ${
                                                    isCurrent
                                                        ? 'opacity-100'
                                                        : 'opacity-60'
                                                }`}
                                            >
                                                <img
                                                    src={brand.logo}
                                                    alt=""
                                                    loading="lazy"
                                                    style={{
                                                        height: `calc(var(--logo-base) * ${LOGO_SCALE[brand.slug] ?? DEFAULT_LOGO_SCALE})`,
                                                    }}
                                                    className="w-auto max-w-full object-contain object-left"
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

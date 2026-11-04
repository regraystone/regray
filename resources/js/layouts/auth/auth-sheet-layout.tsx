import { useEffect, useRef, type RefObject } from 'react';
import Logo from '@/components/logo';
import type { AuthLayoutProps } from '@/types';

const heroImage = '/images/regray-hero.jpg';

/**
 * Keeps the submit button in sight while the on-screen keyboard is open: when
 * the visible viewport shrinks around a focused field, or focus moves to
 * another field, the button is scrolled up to just above the keyboard, as long
 * as the focused field still fits on screen with it. Phones only; from `md` up
 * the page never moves.
 */
function useSubmitAboveKeyboard(panel: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        const viewport = window.visualViewport;
        const element = panel.current;

        if (!viewport || !element) {
            return;
        }

        const wide = window.matchMedia('(min-width: 48rem)');

        const reveal = () => {
            const field = document.activeElement;

            if (
                wide.matches ||
                !(field instanceof HTMLElement) ||
                !element.contains(field)
            ) {
                return;
            }

            const submit = field
                .closest('form')
                ?.querySelector<HTMLElement>('[type="submit"]');

            if (
                !submit ||
                submit.getBoundingClientRect().bottom -
                    field.getBoundingClientRect().top >
                    viewport.height
            ) {
                return;
            }

            submit.scrollIntoView({ block: 'nearest' });
        };

        const revealAfterFocus = () => requestAnimationFrame(reveal);

        viewport.addEventListener('resize', reveal);
        element.addEventListener('focusin', revealAfterFocus);

        return () => {
            viewport.removeEventListener('resize', reveal);
            element.removeEventListener('focusin', revealAfterFocus);
        };
    }, [panel]);
}

/**
 * Sign In, Sign Up, Forgot and Reset Password, and Email Verification.
 *
 * From `md` up: two columns filling the whole screen inside a small white
 * margin, the photo on the left at full height (brand link top left, the
 * tagline bottom left over a dark gradient) and the form filling the right
 * column, vertically centred. The photo takes 60% and the form 40%,
 * though the form column never narrows below 26rem (30rem from `lg`): on tablets and smaller laptops the
 * photo gives up the difference so the fields stay usable.
 *
 * Below `md` the page is exactly one screen tall: the form sits in a white
 * sheet anchored to the bottom, at least half the screen tall and taller when
 * its content needs it, its rounded top corners laid over the photo, and the
 * photo fills whatever height is left. Spacing in the sheet scales with the
 * screen height so a full form still fits on a short phone; if it cannot
 * (several errors on a very small screen), the page scrolls rather than
 * hiding anything.
 *
 * The pages space their fields with `--auth-field-gap` and everything else
 * (title to form, form to button, button to links) with `--auth-block-gap`,
 * both set here, below `md` only. They tighten faster than the screen
 * shrinks: about 20px and 22px on an 844px-tall phone, 8px and 9px on 553px.
 */
export default function AuthSheetLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const panel = useRef<HTMLElement>(null);

    useSubmitAboveKeyboard(panel);

    return (
        <div className="light flex flex-col bg-background text-foreground [--auth-block-gap:clamp(0.5rem,4.5dvh_-_1rem,1.5rem)] [--auth-field-gap:clamp(0.5rem,4dvh_-_0.875rem,1.25rem)] max-md:h-dvh max-md:overflow-y-auto md:min-h-dvh md:p-4 lg:p-5">
            {/* Below md its children are the column's own flex items. */}
            <div className="max-md:contents md:grid md:w-full md:flex-1 md:grid-cols-[minmax(0,3fr)_minmax(26rem,2fr)] md:gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(30rem,2fr)] lg:gap-12">
                <aside className="relative flex min-h-14 flex-1 flex-col justify-end overflow-hidden bg-black/30 md:min-h-[560px] md:rounded-md">
                    <div className="absolute inset-0">
                        <img
                            src={heroImage}
                            alt=""
                            aria-hidden="true"
                            className="size-full object-cover"
                        />
                        <div className="absolute inset-0 bg-black/30 md:bg-transparent md:bg-linear-to-t md:from-black/75 md:via-black/15 md:to-transparent" />
                        {/* A lighter fade at the top keeps the brand link legible on pale stone. */}
                        <div className="absolute inset-x-0 top-0 hidden h-32 bg-linear-to-b from-black/35 to-transparent md:block" />
                        <div className="absolute inset-0 hidden flex-col justify-between p-8 md:flex lg:p-10">
                            <Logo className="w-fit text-white" />

                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-white/80 uppercase">
                                    Bespoke Stone &amp; Furniture
                                </p>
                                <p className="mt-3 max-w-sm text-xl font-bold tracking-[-0.02em] text-white lg:text-2xl">
                                    One account. Every inquiry, from first
                                    sketch to final installation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <Logo className="mb-[calc(var(--auth-field-gap)+0.5rem)] self-center text-white md:hidden" />
                </aside>

                <main
                    ref={panel}
                    className="flex min-w-0 items-center justify-center max-md:relative max-md:z-10 max-md:-mt-2 max-md:min-h-[50dvh] max-md:flex-none max-md:items-end max-md:rounded-t-md max-md:bg-background max-md:px-5 max-md:pt-[calc(var(--auth-block-gap)+0.25rem)] max-md:pb-[calc(var(--auth-block-gap)+0.5rem+env(safe-area-inset-bottom))] sm:max-md:px-8 md:px-12 lg:px-16 xl:px-20 2xl:px-28"
                >
                    <div className="w-full max-w-md md:max-w-none">
                        <h1
                            className={`text-2xl font-medium tracking-[-0.04em] sm:text-3xl md:font-normal ${
                                title === 'Sign In' || title === 'Sign Up'
                                    ? 'tracking-[0.08em] uppercase'
                                    : ''
                            }`}
                        >
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-2 text-sm leading-relaxed text-secondary-foreground sm:mt-3 sm:text-base">
                                {description}
                            </p>
                        ) : null}
                        <div className="mt-(--auth-block-gap) md:mt-8">
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}

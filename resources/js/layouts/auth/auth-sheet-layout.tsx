import { useEffect, useRef, type RefObject } from 'react';
import Logo from '@/components/logo';
import type { AuthLayoutProps } from '@/types';

const heroImage = '/images/regray-hero.jpg';

/**
 * Keeps the submit button in sight while the on-screen keyboard is open: when
 * the visible viewport shrinks around a focused field, or focus moves to
 * another field, the button is scrolled up to just above the keyboard, as long
 * as the focused field still fits on screen with it. Phones only; the desktop
 * split never moves.
 */
function useSubmitAboveKeyboard(panel: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        const viewport = window.visualViewport;
        const element = panel.current;

        if (!viewport || !element) {
            return;
        }

        const desktop = window.matchMedia('(min-width: 64rem)');

        const reveal = () => {
            const field = document.activeElement;

            if (
                desktop.matches ||
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
 * AuthSplitLayout on desktop, unchanged from `lg` up. Below `lg` the page is
 * exactly one screen tall: the form sits in a white sheet anchored to the
 * bottom, at least half the screen tall and taller when its content needs it,
 * its rounded top corners laid over the photo, and the photo fills whatever
 * height is left. Spacing in the sheet
 * scales with the screen height so a full form still fits on a short phone;
 * if it cannot (several errors on a very small screen), the page scrolls
 * rather than hiding anything.
 *
 * The pages space their fields with `--auth-field-gap` and everything else
 * (title to form, form to button, button to links) with `--auth-block-gap`,
 * both set here, below `lg` only. They tighten faster than the screen
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
        <div className="light flex min-h-screen flex-col bg-background text-foreground [--auth-block-gap:clamp(0.5rem,4.5dvh_-_1rem,1.5rem)] [--auth-field-gap:clamp(0.5rem,4dvh_-_0.875rem,1.25rem)] max-lg:h-dvh max-lg:min-h-0 max-lg:overflow-y-auto lg:flex-row">
            <aside className="relative w-full shrink-0 bg-black/30 max-lg:flex max-lg:min-h-14 max-lg:flex-1 max-lg:flex-col max-lg:justify-end max-lg:overflow-hidden lg:h-auto lg:w-[60%]">
                <div className="absolute inset-0 overflow-hidden">
                    <img
                        src={heroImage}
                        alt=""
                        aria-hidden="true"
                        className="size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-black/30" />
                    <div className="absolute inset-0 flex flex-col justify-between p-4 sm:p-6 lg:p-10">
                        <Logo className="w-fit text-white max-lg:hidden" />

                        <div className="hidden lg:block">
                            <p className="text-xs font-bold tracking-[0.16em] text-white/80 uppercase">
                                Bespoke Stone &amp; Furniture
                            </p>
                            <p className="mt-2 max-w-sm text-lg font-bold tracking-[-0.02em] text-white sm:text-xl lg:mt-3 lg:text-2xl">
                                One account. Every inquiry, from first sketch to
                                final installation.
                            </p>
                        </div>
                    </div>
                </div>

                <Logo className="mb-[calc(var(--auth-field-gap)+0.5rem)] self-center text-white lg:hidden" />
            </aside>

            <main
                ref={panel}
                className="flex min-w-0 flex-1 items-center justify-center px-5 pt-[calc(var(--auth-block-gap)+0.25rem)] pb-[calc(var(--auth-block-gap)+0.5rem+env(safe-area-inset-bottom))] max-lg:relative max-lg:z-10 max-lg:-mt-2 max-lg:min-h-[50dvh] max-lg:flex-none max-lg:items-end max-lg:rounded-t-md max-lg:bg-background sm:px-8 lg:px-8 lg:py-12"
            >
                <div className="w-full max-w-md">
                    <h1 className="text-2xl font-bold tracking-[-0.04em] max-lg:font-medium sm:text-3xl lg:text-[2.3rem]">
                        {title}
                    </h1>
                    {description ? (
                        <p className="mt-2 text-sm leading-relaxed text-secondary-foreground sm:mt-3 sm:text-base">
                            {description}
                        </p>
                    ) : null}
                    <div className="mt-(--auth-block-gap) lg:mt-8">
                        {children}
                    </div>
                </div>
            </main>
        </div>
    );
}

import { useEffect, useRef, type RefObject } from 'react';
import AuthBrandPanel from '@/components/auth/auth-brand-panel';
import type { AuthLayoutProps } from '@/types';

/**
 * Keeps the submit button in sight while the on-screen keyboard is open: when
 * the visible viewport shrinks around a focused field, or focus moves to
 * another field, the button is scrolled up to just above the keyboard, as long
 * as the focused field still fits on screen with it. Phones only; from 900px
 * up the page never moves.
 */
function useSubmitAboveKeyboard(panel: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        const viewport = window.visualViewport;
        const element = panel.current;

        if (!viewport || !element) {
            return;
        }

        const wide = window.matchMedia('(min-width: 900px)');

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
 * Every sign-in and sign-up page: Sign In, Sign Up, Forgot and Reset
 * Password, Email Verification, the two-factor challenge and finishing a
 * Google sign-up.
 *
 * From 900px up: a framed photo fills the left 54% at full height and stays
 * put while the form column on the right scrolls. Below it a short band of
 * the photo sits on top and the form follows it down the page.
 *
 * The form column opens with a small label (`eyebrow`), the title and one
 * line on what the page is for (`description`), at sizes that read easily.
 */
export default function AuthBrandLayout({
    children,
    eyebrow,
    title,
    description,
}: AuthLayoutProps) {
    const panel = useRef<HTMLElement>(null);

    useSubmitAboveKeyboard(panel);

    return (
        <div className="light flex min-h-svh flex-col bg-paper text-ink min-[900px]:flex-row">
            <AuthBrandPanel />

            <main
                ref={panel}
                className="flex flex-[1_1_auto] flex-col justify-center bg-paper px-5 pt-12 pb-16 min-[900px]:flex-[1_1_46%] min-[900px]:px-[clamp(2rem,5vw,6rem)] min-[900px]:py-[clamp(3rem,6vw,6rem)]"
            >
                <div className="mx-auto flex w-full max-w-105 flex-col gap-9">
                    <header className="flex flex-col gap-3.5">
                        {eyebrow ? (
                            <p className="text-[0.9375rem] font-semibold tracking-[0.04em] text-ink-soft uppercase">
                                {eyebrow}
                            </p>
                        ) : null}
                        <h1 className="text-[clamp(2.125rem,3vw,2.875rem)] leading-[1.05] tracking-[-0.02em]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-[1.0625rem] leading-relaxed text-pretty text-ink-soft">
                                {description}
                            </p>
                        ) : null}
                    </header>

                    <div>{children}</div>
                </div>
            </main>
        </div>
    );
}

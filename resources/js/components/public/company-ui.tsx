import type { ReactNode } from 'react';

/**
 * The public pages' shared pieces (the landing page and the company pages):
 * white, framed photos, centred titles in the heading face.
 */

/** The white margin every photo and grid sits in. */
export const frame = 'px-4 sm:px-8';

/**
 * The public site is written for clients of 40 to 60: text at a comfortable
 * size, labels in plain words rather than small spaced capitals, and large
 * buttons with a clear edge.
 */

/** An underlined link, for a quieter way on. */
export const textLink =
    'border-b border-current pb-1.5 text-base font-medium transition-opacity hover:opacity-70';

/** The black button. */
export const solidButton =
    'inline-flex min-h-13 items-center justify-center rounded-md bg-foreground px-7 py-3.5 text-center text-base font-semibold text-background transition-colors hover:bg-foreground/80';

/** The outlined button. */
export const outlineButton =
    'inline-flex min-h-13 items-center justify-center rounded-md border border-foreground px-7 py-3.5 text-center text-base font-semibold transition-colors hover:bg-foreground hover:text-background';

/** The small label over a title or a fact. */
export const label =
    'text-[0.9375rem] font-semibold tracking-[0.04em] uppercase';

/** Body text: at least 17px, with room between the lines. */
export const body = 'text-[1.0625rem] leading-relaxed text-pretty';

/** The page's opening: a small label, its title and a line on what it holds. */
export const PageIntro = ({
    eyebrow,
    title,
    children,
}: {
    eyebrow: string;
    title: string;
    children: ReactNode;
}) => (
    <section className="flex flex-col items-center gap-6 px-5 pt-[clamp(3.5rem,7vw,6rem)] pb-[clamp(3rem,6vw,5rem)] text-center">
        <p className={`${label} text-muted-foreground`}>{eyebrow}</p>
        <h1 className="font-display text-[clamp(2rem,3.4vw,3.25rem)] leading-[1.1] font-light tracking-[-0.01em] text-balance">
            {title}
        </h1>
        <div className="max-w-170 text-[clamp(1.125rem,1.3vw,1.25rem)] leading-relaxed text-pretty">
            {children}
        </div>
    </section>
);

/** A section's title, with an optional line under it. */
export const SectionTitle = ({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children?: ReactNode;
}) => (
    <div className="flex flex-col items-center gap-4 pb-[clamp(2rem,4vw,3.5rem)] text-center">
        <h2
            id={id}
            className="font-display text-[clamp(1.5rem,2vw,1.875rem)] font-light tracking-[0.02em] uppercase"
        >
            {title}
        </h2>
        {children ? (
            <p className={`max-w-150 text-muted-foreground ${body}`}>
                {children}
            </p>
        ) : null}
    </div>
);

/** A wide framed photo. */
export const WidePhoto = ({
    src,
    alt,
    position = 'center',
}: {
    src: string;
    alt: string;
    position?: string;
}) => (
    <div className={frame}>
        <div className="aspect-4/3 overflow-hidden rounded-md bg-muted min-[700px]:aspect-[21/9]">
            <img
                src={src}
                alt={alt}
                className="size-full object-cover"
                style={{ objectPosition: position }}
            />
        </div>
    </div>
);

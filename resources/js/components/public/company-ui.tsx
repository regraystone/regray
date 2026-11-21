import type { ReactNode } from 'react';

/**
 * The company pages' shared pieces, in the landing page's style: white,
 * framed photos, centred titles in the heading face.
 */

/** The white margin every photo and grid sits in. */
export const frame = 'px-4 sm:px-8';

/** An underlined uppercase link. */
export const textLink =
    'border-b border-current px-3 pb-3.5 text-[0.8125rem] tracking-[0.04em] uppercase transition-opacity hover:opacity-70';

/** The black button. */
export const solidButton =
    'bg-foreground px-6.5 py-4 text-center text-[0.8125rem] font-semibold tracking-[0.06em] text-background uppercase transition-colors hover:bg-foreground/80';

/** The outlined button. */
export const outlineButton =
    'border border-foreground px-6.5 py-3.75 text-center text-[0.8125rem] font-semibold tracking-[0.06em] uppercase transition-colors hover:bg-foreground hover:text-background';

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
        <p className="text-[0.8125rem] tracking-[0.04em] text-muted-foreground uppercase">
            {eyebrow}
        </p>
        <h1 className="font-display text-[clamp(2rem,3.4vw,3.25rem)] leading-[1.1] font-light tracking-[-0.01em] text-balance">
            {title}
        </h1>
        <div className="max-w-170 text-[1.0625rem] leading-relaxed text-pretty">
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
            <p className="max-w-150 text-[0.9375rem] leading-relaxed text-pretty text-muted-foreground">
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
        <div className="aspect-4/3 overflow-hidden bg-muted min-[700px]:aspect-[21/9]">
            <img
                src={src}
                alt={alt}
                className="size-full object-cover"
                style={{ objectPosition: position }}
            />
        </div>
    </div>
);

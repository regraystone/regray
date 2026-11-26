import { cn } from '@/lib/utils';

/**
 * "REGRAY Design Group", set in one family (Lexend) the way "HAWORTH
 * Lifestyle" is: REGRAY in medium capitals, spaced wide, then "Design Group"
 * in light mixed case, both on one baseline with the same cap height. One
 * typeface with two voices, so the parts read as one name.
 *
 * Sized by the font size it is given (`className`, or its parent's): its
 * capitals are about 1em tall, so `text-xl` draws them 20px high. Painted in
 * the current text colour, so a parent's `text-white` turns it white. It is a
 * picture of the name; the link around it carries the name for a screen
 * reader. "Design Group" is `data-wordmark-name`, so a tight spot can hide it.
 */
const Wordmark = ({ className }: { className?: string }) => (
    <span
        aria-hidden
        className={cn(
            'flex items-baseline gap-[0.55em] font-display leading-none whitespace-nowrap',
            className,
        )}
        data-testid="wordmark"
    >
        {/* The tracking's last gap is taken back, so the space before "Design Group" is only the gap. */}
        <span className="mr-[-0.12em] text-[1.36em] font-medium tracking-[0.12em]">
            REGRAY
        </span>
        <span
            className="text-[1.36em] font-light tracking-[0.005em]"
            data-wordmark-name
        >
            Design Group
        </span>
    </span>
);

export default Wordmark;

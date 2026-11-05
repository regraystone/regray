import { Link } from '@inertiajs/react';
import BetaBadge from '@/components/beta-badge';
import { home } from '@/routes';

/** The "Regray" wordmark: black letters on a transparent background. */
const WORDMARK_SRC = '/images/regray_logo.svg';

/**
 * The wordmark, painted in the current text colour: the SVG is used as a mask
 * over `bg-current`, so a parent's `text-white` (the auth pages) or the
 * default foreground (the workspace) sets its colour. `compact` (the collapsed
 * sidebar) has no room for it, so it shows an R on a black square instead.
 *
 * It always links to `/`, which shows a guest the landing page and sends
 * anyone signed in on to their role's own home page.
 */
const Logo = ({
    className = '',
    compact = false,
}: {
    className?: string;
    compact?: boolean;
}) => (
    <Link
        href={home()}
        className={`relative flex items-center ${
            compact ? 'justify-center' : 'gap-2'
        } ${className}`}
        aria-label="Regray Beta home"
    >
        {compact ? (
            <span
                aria-hidden
                className="grid size-8 place-items-center rounded-md bg-primary text-base font-extrabold text-primary-foreground"
            >
                R
            </span>
        ) : (
            <span
                aria-hidden
                className="block aspect-[426/76.5] h-4.5 shrink-0 bg-current lg:h-4"
                style={{
                    maskImage: `url(${WORDMARK_SRC})`,
                    maskSize: 'contain',
                    maskRepeat: 'no-repeat',
                    maskPosition: 'center',
                    WebkitMaskImage: `url(${WORDMARK_SRC})`,
                    WebkitMaskSize: 'contain',
                    WebkitMaskRepeat: 'no-repeat',
                    WebkitMaskPosition: 'center',
                }}
            />
        )}
        <BetaBadge compact={compact} />
    </Link>
);

export default Logo;

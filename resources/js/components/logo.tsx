import { Link } from '@inertiajs/react';
import BetaBadge from '@/components/beta-badge';
import { home } from '@/routes';

/** The "Regray" wordmark: black letters on a transparent background. */
const WORDMARK_SRC = '/images/regray_logo.svg';

/** The Regray mark alone, for the collapsed sidebar: a black circle with the name in white. */
const ICON_SRC = '/images/regray_icon.svg';

/** Paint an SVG in the current text colour: it masks `bg-current`. */
const maskOf = (src: string) => ({
    maskImage: `url(${src})`,
    maskSize: 'contain',
    maskRepeat: 'no-repeat',
    maskPosition: 'center',
    WebkitMaskImage: `url(${src})`,
    WebkitMaskSize: 'contain',
    WebkitMaskRepeat: 'no-repeat',
    WebkitMaskPosition: 'center',
});

/**
 * The wordmark, painted in the current text colour: the SVG is used as a mask
 * over `bg-current`, so a parent's `text-white` (the auth pages) or the
 * default foreground (the workspace) sets its colour. `compact` (the collapsed
 * sidebar) has no room for it, so it shows the Regray mark instead
 * (regray_icon.svg). The mark is drawn as an image in its own colours, not as
 * a mask: a mask would paint its white lettering in the circle's colour.
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
            <img
                src={ICON_SRC}
                alt=""
                aria-hidden
                className="block size-8 shrink-0"
            />
        ) : (
            <span
                aria-hidden
                className="block aspect-[426/76.5] h-4.5 shrink-0 bg-current lg:h-4"
                style={maskOf(WORDMARK_SRC)}
            />
        )}
        <BetaBadge compact={compact} />
    </Link>
);

export default Logo;

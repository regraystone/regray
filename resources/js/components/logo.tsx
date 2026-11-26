import { Link } from '@inertiajs/react';
import Wordmark from '@/components/wordmark';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

/** The Regray mark alone, for the collapsed sidebar: a black circle with the name in white. */
const ICON_SRC = '/images/regray_icon.png';

/**
 * The "REGRAY Design Group" wordmark (components/wordmark.tsx), in the current
 * text colour: a parent's `text-white` (the auth pages) or the default
 * foreground (the workspace) sets it, and its font size sets its size (the
 * default here, or one given in `className`). `compact` (the collapsed
 * sidebar) has no room for it, so it shows the Regray mark instead
 * (regray_icon.png), drawn as an image in its own colours.
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
        className={cn(
            'relative flex items-center',
            compact ? 'justify-center' : 'text-base',
            className,
        )}
        aria-label="Regray Design Group home"
    >
        {compact ? (
            <img
                src={ICON_SRC}
                alt=""
                aria-hidden
                className="block size-8 shrink-0"
            />
        ) : (
            <Wordmark />
        )}
    </Link>
);

export default Logo;

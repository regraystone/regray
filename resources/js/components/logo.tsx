import { Link } from '@inertiajs/react';
import BetaBadge from '@/components/beta-badge';
import { dashboard } from '@/routes';

/**
 * The wordmark: "Regray Studio", all of it bold. `compact` (the collapsed
 * sidebar) has no room for it, so it shows an R on a black square instead.
 */
const Logo = ({
    className = '',
    compact = false,
    href = dashboard(),
}: {
    className?: string;
    compact?: boolean;
    href?: string;
}) => (
    <Link
        href={href}
        className={`relative flex items-center ${
            compact ? 'justify-center' : 'gap-2'
        } ${className}`}
        aria-label="Regray Studio home"
    >
        {compact ? (
            <span
                aria-hidden
                className="grid size-8 place-items-center rounded-md bg-primary text-base font-extrabold text-primary-foreground"
            >
                R
            </span>
        ) : (
            <span className="text-2xl font-extrabold whitespace-nowrap uppercase lg:text-xl">
                Regray Studio
            </span>
        )}
        <BetaBadge compact={compact} />
    </Link>
);

export default Logo;

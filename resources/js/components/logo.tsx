import { Link } from '@inertiajs/react';
import BetaBadge from '@/components/beta-badge';
import { dashboard } from '@/routes';

const Logo = ({
    className = '',
    compact = false,
    markClassName = '',
}: {
    className?: string;
    compact?: boolean;
    markClassName?: string;
}) => (
    <Link
        href={dashboard()}
        className={`relative flex items-center ${
            compact ? 'justify-center' : 'gap-2'
        } ${className}`}
        aria-label="Regray Studio home"
    >
        <img
            src="/images/regray-logo.svg"
            alt=""
            aria-hidden
            className={`size-7 shrink-0 object-contain ${markClassName}`}
        />
        {!compact && (
            <span className="text-xl font-extrabold whitespace-nowrap">
                Regray{' '}
                <span className="font-normal text-secondary-foreground">
                    Studio
                </span>
            </span>
        )}
        <BetaBadge compact={compact} />
    </Link>
);

export default Logo;

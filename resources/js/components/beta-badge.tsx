import { Badge } from '@/components/ui/badge';

/**
 * The red "Beta" tag beside the logo, while the app is in beta.
 *
 * Self-contained so it is easy to retire: delete this file and the one
 * `<BetaBadge />` line in components/logo.tsx.
 *
 * `compact` is the logo with no wordmark (the collapsed sidebar): there the tag
 * shrinks to a red dot on the corner of the mark, so it never widens the rail.
 */
const BetaBadge = ({ compact = false }: { compact?: boolean }) =>
    compact ? (
        <span
            className="absolute -top-0.5 -right-0.5 size-2 rounded-full bg-destructive ring-2 ring-background"
            title="Beta"
            data-testid="beta-badge"
        >
            <span className="sr-only">Beta</span>
        </span>
    ) : (
        <Badge
            variant="destructive"
            className="rounded-md px-1.5 py-0 text-[0.6rem] leading-4 font-bold tracking-[0.08em] uppercase"
            data-testid="beta-badge"
        >
            Beta
        </Badge>
    );

export default BetaBadge;

import { Link } from '@inertiajs/react';

/**
 * The rows and chips the profile pages lay their facts out in.
 *
 * The stone profile has always drawn specifications as label/value rows and
 * finishes as bordered uppercase chips; the project profile reads its own
 * facts in the same two shapes, so they live here rather than being drawn
 * twice with classes that drift apart.
 */

const chipClass =
    'border border-sidebar-border bg-background px-3 py-1 text-xs font-bold tracking-[0.05em] text-foreground uppercase';

export const SpecRow = ({ label, value }: { label: string; value: string }) => (
    <div className="flex items-baseline justify-between gap-4 border-b border-sidebar-border py-2.5 last:border-0">
        <dt className="text-sm text-secondary-foreground">{label}</dt>
        <dd className="text-right text-base font-semibold text-foreground">
            {value}
        </dd>
    </div>
);

export const FactChip = ({ children }: { children: string }) => (
    <span className={chipClass}>{children}</span>
);

/**
 * The same chip as a way through to the thing it names, which is how the
 * project profile sends a stone on to its own page. It picks up the same
 * opacity and focus language the workspace's other links use, and nothing on
 * a plain chip needs them.
 */
export const FactChipLink = ({
    children,
    href,
}: {
    children: string;
    href: string;
}) => (
    <Link
        href={href}
        className={`${chipClass} cursor-pointer transition-opacity hover:opacity-60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none`}
    >
        {children}
    </Link>
);

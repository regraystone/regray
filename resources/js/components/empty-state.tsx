import type { ReactNode } from 'react';

/**
 * A page that has nothing to show, and the one thing to do about it.
 *
 * The wording is left to the caller: "no saved stones" and "no stones match
 * that search" are different sentences about the same absence, and only the
 * caller knows which one is true. The action is a node rather than a label so
 * it can be a button, a link, or nothing at all.
 */
const EmptyState = ({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action: ReactNode;
}) => (
    <div className="flex flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
        <p className="text-lg font-bold tracking-[-0.03em]">{title}</p>
        <p className="max-w-sm text-sm text-secondary-foreground">
            {description}
        </p>
        <div className="mt-1">{action}</div>
    </div>
);

export default EmptyState;

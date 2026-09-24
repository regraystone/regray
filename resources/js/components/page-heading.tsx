import type { ReactNode } from 'react';

const PageHeading = ({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) => (
    <div className="mb-8 flex flex-col gap-3 border-b border-sidebar-border pb-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="min-w-0 space-y-1.5">
            <h2 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                {title}
            </h2>
            {description ? (
                <p className="text-sm text-secondary-foreground">
                    {description}
                </p>
            ) : null}
        </div>
        {action ? (
            <div className="flex shrink-0 items-center gap-2">{action}</div>
        ) : null}
    </div>
);

export default PageHeading;

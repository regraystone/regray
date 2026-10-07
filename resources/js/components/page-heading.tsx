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
    <div className="mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4 sm:mb-8 sm:gap-3 md:flex-row md:items-end md:justify-between">
        <div className="min-w-0 space-y-2 sm:space-y-1.5">
            <h2 className="text-xl font-bold tracking-[-0.04em] sm:text-2xl md:text-3xl">
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

import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: string;
    subtitle?: string;
} & (
    | { variant?: 'default'; action?: never }
    | {
          variant: 'action';
          /** The page's own button(s), such as Add account. */
          action: ReactNode;
      }
);

/**
 * A dashboard page's title, with a line under it saying what the page is for.
 *
 * `default` is the title and subtitle alone. `action` puts the page's own
 * button(s) on the right, centred against the text; on a phone they stack
 * under it, full width.
 */
const PageHeader = (props: PageHeaderProps) => {
    const { title, subtitle } = props;
    const action = props.variant === 'action' ? props.action : null;

    return (
        <div
            className={`mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4 sm:mb-8 sm:gap-3 ${
                action ? 'sm:flex-row sm:items-center sm:justify-between' : ''
            }`}
        >
            <div className="min-w-0 space-y-2 sm:space-y-1.5">
                <h2 className="text-xl font-bold tracking-[-0.04em] sm:text-2xl md:text-3xl">
                    {title}
                </h2>
                {subtitle ? (
                    <p className="text-sm text-secondary-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </div>
            {action ? (
                <div className="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center [&>*]:w-full sm:[&>*]:w-auto">
                    {action}
                </div>
            ) : null}
        </div>
    );
};

export default PageHeader;

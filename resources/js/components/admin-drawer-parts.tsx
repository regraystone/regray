import type { ReactNode } from 'react';

/**
 * The pieces the admin's Brands and Projects drawers are built from: a part
 * under its own small heading, and a label with its value.
 */

/** A label and its value in a View drawer. */
export const Detail = ({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) => (
    <div className="min-w-0">
        <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
            {label}
        </p>
        <div className="text-sm break-words">{children}</div>
    </div>
);

/** A part of a View drawer or a form, under its own small heading. */
export const Section = ({
    title,
    children,
    as: Tag = 'section',
}: {
    title: string;
    children: ReactNode;
    as?: 'section' | 'fieldset';
}) => (
    <Tag className="space-y-4 border-t border-sidebar-border pt-5 first:border-t-0 first:pt-0">
        {Tag === 'fieldset' ? (
            <legend className="mb-4 text-xs font-bold tracking-[0.12em] text-secondary-foreground uppercase">
                {title}
            </legend>
        ) : (
            <h3 className="text-xs font-bold tracking-[0.12em] text-secondary-foreground uppercase">
                {title}
            </h3>
        )}
        {children}
    </Tag>
);

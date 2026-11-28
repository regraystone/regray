import type { ReactNode } from 'react';
import { changeFrom, comparedTo, formatFigure } from '@/lib/overview';
import { cn } from '@/lib/utils';
import type { OverviewFigure } from '@/types/overview';

/** One number, and how it moved from the same number of days just before. */
export const FigureCard = ({
    figure,
    days,
}: {
    figure: OverviewFigure;
    days: number;
}) => {
    const change = changeFrom(figure.value, figure.previous, figure.format);

    return (
        <div
            className="flex min-w-0 flex-col gap-1 border border-sidebar-border p-3 sm:p-4"
            data-testid={`figure-${figure.key}`}
        >
            <div className="flex items-start justify-between gap-2">
                <p className="text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                    {figure.label}
                </p>
                {figure.dated ? null : (
                    <span
                        className="shrink-0 rounded-sm bg-muted px-1.5 py-0.5 text-[0.625rem] font-bold tracking-[0.05em] text-secondary-foreground uppercase"
                        title="As of today: the dates do not change it"
                    >
                        Now
                    </span>
                )}
            </div>
            <p className="text-xl leading-tight font-bold tracking-[-0.03em] break-words sm:text-2xl">
                {formatFigure(figure.value, figure.format)}
            </p>
            {change ? (
                <p className="text-xs text-secondary-foreground">
                    <span
                        className={cn(
                            'font-semibold',
                            change.direction === 'same'
                                ? 'text-secondary-foreground'
                                : 'text-foreground',
                        )}
                    >
                        {change.text}
                    </span>{' '}
                    {change.direction === 'same' ? '' : comparedTo(days)}
                </p>
            ) : null}
            {figure.note ? (
                <p className="text-xs text-secondary-foreground">
                    {figure.note}
                </p>
            ) : null}
        </div>
    );
};

/** A part of a section, with its heading. */
export const Panel = ({
    title,
    action,
    children,
    className,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) => (
    <section
        className={cn(
            'min-w-0 border border-sidebar-border p-4 sm:p-5',
            className,
        )}
    >
        <div className="mb-3 flex items-baseline justify-between gap-2">
            <h3 className="text-sm font-bold">{title}</h3>
            {action}
        </div>
        {children}
    </section>
);

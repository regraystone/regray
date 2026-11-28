import { useState } from 'react';
import { bucketLabel, labelEvery } from '@/lib/overview';
import { niceTicks, share } from '@/lib/sales-report';
import { cn } from '@/lib/utils';
import type { OverviewTrend } from '@/types/overview';

/**
 * The series colours, given in this order and never cycled: categorical slots
 * blue, orange, aqua (dataviz validate_palette, light surface: every check
 * passes). Aqua is under 3:1 against white, so every value is also in the
 * hover readout and the table view.
 */
const COLORS = ['#2a78d6', '#eb6834', '#00b894'] as const;

const PLOT_HEIGHT = 176;

/**
 * Counts per day, week or month over the chosen dates: grouped columns from
 * one baseline, a legend, a readout on hover or focus, and a table view.
 */
const TrendChart = ({
    trend,
    title,
}: {
    trend: OverviewTrend;
    title: string;
}) => {
    const [active, setActive] = useState<number | null>(null);
    const [asTable, setAsTable] = useState(false);
    const series = trend.series.slice(0, COLORS.length);
    const points = trend.points.map((point) => ({
        start: point.start,
        values: series.map((line) => Number(point[line.key] ?? 0)),
    }));
    // Counts step in whole numbers: never fewer than four steps of one.
    const ticks = niceTicks(
        Math.max(4, ...points.flatMap((point) => point.values)),
    );
    const top = ticks[ticks.length - 1];
    const every = labelEvery(points.length);
    const shown = active === null ? null : points[active];

    return (
        <figure className="min-w-0" data-testid="overview-trend">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <figcaption className="text-sm font-bold">{title}</figcaption>
                <button
                    type="button"
                    className="text-xs font-semibold text-secondary-foreground underline-offset-2 hover:underline"
                    onClick={() => setAsTable((value) => !value)}
                    aria-pressed={asTable}
                >
                    {asTable ? 'Show chart' : 'Show as table'}
                </button>
            </div>

            <div className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-secondary-foreground">
                {series.map((line, index) => (
                    <span key={line.key} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className="size-2.5 rounded-[2px]"
                            style={{ backgroundColor: COLORS[index] }}
                        />
                        {line.label}
                    </span>
                ))}
            </div>

            {asTable ? (
                <div className="mt-3 max-h-72 overflow-auto">
                    <table className="w-full text-left text-xs">
                        <thead>
                            <tr className="border-b border-sidebar-border text-secondary-foreground">
                                <th className="py-1.5 pr-3 font-semibold">
                                    {trend.bucket === 'day'
                                        ? 'Day'
                                        : trend.bucket === 'week'
                                          ? 'Week'
                                          : 'Month'}
                                </th>
                                {series.map((line) => (
                                    <th
                                        key={line.key}
                                        className="py-1.5 pr-3 text-right font-semibold"
                                    >
                                        {line.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {points.map((point) => (
                                <tr
                                    key={point.start}
                                    className="border-b border-sidebar-border/60"
                                >
                                    <td className="py-1.5 pr-3">
                                        {bucketLabel(point.start, trend.bucket)}
                                    </td>
                                    {point.values.map((value, index) => (
                                        <td
                                            key={series[index].key}
                                            className="py-1.5 pr-3 text-right tabular-nums"
                                        >
                                            {value}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="mt-4 flex gap-2">
                    <div
                        aria-hidden
                        className="relative w-6 shrink-0 text-right text-[0.6875rem] text-secondary-foreground"
                        style={{ height: PLOT_HEIGHT }}
                    >
                        {ticks.map((tick) => (
                            <span
                                key={tick}
                                className="absolute right-0 translate-y-1/2"
                                style={{ bottom: `${share(tick, top)}%` }}
                            >
                                {tick}
                            </span>
                        ))}
                    </div>

                    <div className="relative min-w-0 flex-1">
                        <div
                            className="relative"
                            style={{ height: PLOT_HEIGHT }}
                        >
                            {ticks.map((tick) => (
                                <span
                                    key={tick}
                                    className={cn(
                                        'absolute inset-x-0 h-px',
                                        tick === 0
                                            ? 'bg-foreground/30'
                                            : 'bg-sidebar-border',
                                    )}
                                    style={{ bottom: `${share(tick, top)}%` }}
                                />
                            ))}

                            <div className="absolute inset-0 flex">
                                {points.map((point, index) => (
                                    <div
                                        key={point.start}
                                        className={cn(
                                            'flex h-full min-w-0 flex-1 items-end justify-center gap-[2px] px-px transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
                                            active === index && 'bg-muted/70',
                                        )}
                                        onPointerEnter={() => setActive(index)}
                                        onPointerLeave={() => setActive(null)}
                                        onFocus={() => setActive(index)}
                                        onBlur={() => setActive(null)}
                                        tabIndex={0}
                                        role="img"
                                        aria-label={`${bucketLabel(point.start, trend.bucket)}: ${series
                                            .map(
                                                (line, i) =>
                                                    `${line.label} ${point.values[i]}`,
                                            )
                                            .join(', ')}`}
                                    >
                                        {point.values.map((value, i) => (
                                            <span
                                                key={series[i].key}
                                                className={cn(
                                                    'w-full max-w-3',
                                                    value > 0 &&
                                                        'rounded-t-[4px]',
                                                )}
                                                style={{
                                                    height: `${share(value, top)}%`,
                                                    backgroundColor:
                                                        value > 0
                                                            ? COLORS[i]
                                                            : undefined,
                                                }}
                                            />
                                        ))}
                                    </div>
                                ))}
                            </div>

                            {shown !== null && active !== null ? (
                                <div
                                    aria-hidden
                                    className="pointer-events-none absolute top-0 z-10 w-max min-w-32 border border-sidebar-border bg-background p-2.5 text-xs shadow-md"
                                    style={{
                                        left: `${((active + 0.5) / points.length) * 100}%`,
                                        transform:
                                            active > points.length / 2
                                                ? 'translateX(calc(-100% - 12px))'
                                                : 'translateX(12px)',
                                    }}
                                >
                                    <p className="font-bold">
                                        {bucketLabel(shown.start, trend.bucket)}
                                    </p>
                                    {series.map((line, i) => (
                                        <p
                                            key={line.key}
                                            className="mt-1 flex items-center gap-2"
                                        >
                                            <span
                                                className="h-0.5 w-3"
                                                style={{
                                                    backgroundColor: COLORS[i],
                                                }}
                                            />
                                            <span className="font-bold">
                                                {shown.values[i]}
                                            </span>
                                            <span className="text-secondary-foreground">
                                                {line.label}
                                            </span>
                                        </p>
                                    ))}
                                </div>
                            ) : null}
                        </div>

                        <div className="mt-1.5 flex" aria-hidden>
                            {points.map((point, index) => (
                                <span
                                    key={point.start}
                                    className={cn(
                                        'min-w-0 flex-1 text-center text-[0.625rem] whitespace-nowrap text-secondary-foreground',
                                        // The last one ends at the plot's edge rather than past it.
                                        index === points.length - 1 &&
                                            'text-right',
                                    )}
                                >
                                    {(points.length - 1 - index) % every === 0
                                        ? bucketLabel(
                                              point.start,
                                              trend.bucket,
                                          ).replace('Wk of ', '')
                                        : ''}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </figure>
    );
};

export default TrendChart;

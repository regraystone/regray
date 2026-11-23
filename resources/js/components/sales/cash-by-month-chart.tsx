import { useState } from 'react';
import { formatPeso } from '@/lib/format-money';
import { compactPeso, monthLabel, niceTicks, share } from '@/lib/sales-report';
import { cn } from '@/lib/utils';
import type { SalesPageProps } from '@/types/sales';

/**
 * The two series' colours: categorical slots 1 and 2, validated together on
 * the white surface (dataviz validate_palette: every check passes).
 */
export const SERIES = {
    payments: { label: 'Payments', color: '#2a78d6' },
    refunds: { label: 'Refunds', color: '#eb6834' },
} as const;

const PLOT_HEIGHT = 192;

/**
 * Payments and refunds received each month, the last 12 months: grouped
 * columns from one baseline, a legend, and on hover or focus a month's own
 * readout. Each month is a stop for the keyboard, read out whole.
 */
const CashByMonthChart = ({ months }: { months: SalesPageProps['months'] }) => {
    const [active, setActive] = useState<number | null>(null);
    const values = months.map((month) => ({
        month: month.month,
        payments: Number(month.payments),
        refunds: Number(month.refunds),
    }));
    const ticks = niceTicks(
        Math.max(
            0,
            ...values.flatMap((value) => [value.payments, value.refunds]),
        ),
    );
    const top = ticks[ticks.length - 1];
    const shown = active === null ? null : values[active];

    return (
        <figure className="min-w-0">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-secondary-foreground">
                {(['payments', 'refunds'] as const).map((series) => (
                    <span key={series} className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className="size-2.5 rounded-[2px]"
                            style={{ backgroundColor: SERIES[series].color }}
                        />
                        {SERIES[series].label}
                    </span>
                ))}
            </div>

            <div className="mt-4 flex gap-2">
                <div
                    aria-hidden
                    className="relative w-12 shrink-0 text-right text-[0.6875rem] text-secondary-foreground"
                    style={{ height: PLOT_HEIGHT }}
                >
                    {ticks.map((tick) => (
                        <span
                            key={tick}
                            className="absolute right-0 translate-y-1/2"
                            style={{ bottom: `${share(tick, top)}%` }}
                        >
                            {compactPeso(tick)}
                        </span>
                    ))}
                </div>

                <div className="relative min-w-0 flex-1">
                    <div className="relative" style={{ height: PLOT_HEIGHT }}>
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
                            {values.map((value, index) => (
                                <div
                                    key={value.month}
                                    className={cn(
                                        'flex h-full min-w-0 flex-1 items-end justify-center gap-[2px] transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/50',
                                        active === index && 'bg-muted/70',
                                    )}
                                    onPointerEnter={() => setActive(index)}
                                    onPointerLeave={() => setActive(null)}
                                    onFocus={() => setActive(index)}
                                    onBlur={() => setActive(null)}
                                    tabIndex={0}
                                    role="img"
                                    aria-label={`${monthLabel(value.month, true)}: payments ${formatPeso(value.payments)}, refunds ${formatPeso(value.refunds)}`}
                                    data-testid="cash-month"
                                >
                                    {(['payments', 'refunds'] as const).map(
                                        (series) =>
                                            value[series] > 0 ? (
                                                <span
                                                    key={series}
                                                    className="w-full max-w-3 rounded-t-[4px]"
                                                    style={{
                                                        height: `${share(value[series], top)}%`,
                                                        backgroundColor:
                                                            SERIES[series]
                                                                .color,
                                                    }}
                                                />
                                            ) : (
                                                <span
                                                    key={series}
                                                    className="w-full max-w-3"
                                                />
                                            ),
                                    )}
                                </div>
                            ))}
                        </div>

                        {shown !== null && active !== null ? (
                            <div
                                aria-hidden
                                className="pointer-events-none absolute top-0 z-10 w-max min-w-36 border border-sidebar-border bg-background p-2.5 text-xs shadow-md"
                                style={{
                                    left: `${((active + 0.5) / values.length) * 100}%`,
                                    transform:
                                        active > values.length / 2
                                            ? 'translateX(calc(-100% - 12px))'
                                            : 'translateX(12px)',
                                }}
                            >
                                <p className="font-bold">
                                    {monthLabel(shown.month, true)}
                                </p>
                                {(['payments', 'refunds'] as const).map(
                                    (series) => (
                                        <p
                                            key={series}
                                            className="mt-1 flex items-center gap-2"
                                        >
                                            <span
                                                className="h-0.5 w-3"
                                                style={{
                                                    backgroundColor:
                                                        SERIES[series].color,
                                                }}
                                            />
                                            <span className="font-bold">
                                                {formatPeso(shown[series])}
                                            </span>
                                            <span className="text-secondary-foreground">
                                                {SERIES[series].label}
                                            </span>
                                        </p>
                                    ),
                                )}
                            </div>
                        ) : null}
                    </div>

                    <div className="mt-1.5 flex" aria-hidden>
                        {values.map((value, index) => (
                            <span
                                key={value.month}
                                className={cn(
                                    'min-w-0 flex-1 text-center text-[0.625rem] whitespace-nowrap text-secondary-foreground',
                                    // Every other month on a phone, from the latest back, so none run together.
                                    (values.length - 1 - index) % 2 === 1 &&
                                        'max-sm:invisible',
                                )}
                            >
                                {monthLabel(value.month)}
                                {index === 0 || value.month.endsWith('-01') ? (
                                    <span className="block">
                                        {value.month.slice(0, 4)}
                                    </span>
                                ) : null}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
        </figure>
    );
};

export default CashByMonthChart;

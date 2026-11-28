import { FigureCard, Panel } from '@/components/overview/figure-card';
import { formatFigure, funnelShares } from '@/lib/overview';
import { share } from '@/lib/sales-report';
import type { OverviewInsight, PopularRow } from '@/types/overview';

/** The one bar colour the page's bars use (categorical slot 1). */
const BAR = '#2a78d6';

/** A labelled bar with its figure at the end. */
const Bar = ({
    label,
    value,
    max,
    figure,
    detail,
}: {
    label: string;
    value: number;
    max: number;
    figure: string;
    detail?: string | null;
}) => (
    <li className="grid grid-cols-[minmax(0,12rem)_1fr_auto] items-center gap-3 text-sm">
        <span className="min-w-0">
            <span className="block truncate">{label}</span>
            {detail ? (
                <span className="block truncate text-xs text-secondary-foreground">
                    {detail}
                </span>
            ) : null}
        </span>
        <span className="h-2 bg-muted">
            <span
                className="block h-full"
                style={{
                    width: `${share(value, max)}%`,
                    backgroundColor: BAR,
                }}
            />
        </span>
        <span className="text-right text-xs whitespace-nowrap tabular-nums">
            {figure}
        </span>
    </li>
);

const Empty = ({ children }: { children: string }) => (
    <p className="text-sm text-secondary-foreground">{children}</p>
);

const PopularList = ({
    title,
    rows,
}: {
    title: string;
    rows: PopularRow[];
}) => {
    const max = Math.max(0, ...rows.map((row) => row.inquiries));

    return (
        <div className="min-w-0">
            <h4 className="mb-2 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                {title}
            </h4>
            {rows.length === 0 ? (
                <Empty>None asked for within the dates.</Empty>
            ) : (
                <ul className="space-y-2">
                    {rows.map((row) => (
                        <Bar
                            key={`${row.label}-${row.detail ?? ''}`}
                            label={row.label}
                            detail={row.detail}
                            value={row.inquiries}
                            max={max}
                            figure={`${row.inquiries} · ${row.won} won`}
                        />
                    ))}
                </ul>
            )}
        </div>
    );
};

/**
 * One deeper look in a section: the funnel, how long things take, delivery
 * on time, results by service, where inquiries come from, quotes, visits,
 * what is owed by age, and the stones and brands asked for.
 */
const InsightPanel = ({
    insight,
    days,
}: {
    insight: OverviewInsight;
    days: number;
}) => {
    const body = (() => {
        switch (insight.kind) {
            case 'funnel': {
                const counts = insight.steps.map((step) => step.count);
                const shares = funnelShares(counts);

                return counts[0] === 0 ? (
                    <Empty>No inquiries received within the dates.</Empty>
                ) : (
                    <ol className="space-y-2" data-testid="overview-funnel">
                        {insight.steps.map((step, index) => (
                            <Bar
                                key={step.key}
                                label={step.label}
                                detail={
                                    shares[index].ofPrevious === null
                                        ? null
                                        : `${shares[index].ofPrevious}% of the step before`
                                }
                                value={step.count}
                                max={counts[0]}
                                figure={`${step.count} · ${shares[index].ofFirst ?? 0}%`}
                            />
                        ))}
                    </ol>
                );
            }
            case 'speed':
            case 'onTime':
            case 'quotes':
            case 'visits':
                return (
                    <div className="grid grid-cols-2 gap-3">
                        {insight.figures.map((figure) => (
                            <FigureCard
                                key={figure.key}
                                figure={figure}
                                days={days}
                            />
                        ))}
                    </div>
                );
            case 'byService':
                return insight.rows.length === 0 ? (
                    <Empty>Nothing won or lost within the dates.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[26rem] text-left text-sm">
                            <thead className="text-xs text-secondary-foreground">
                                <tr className="border-b border-sidebar-border">
                                    <th className="py-2 pr-3 font-semibold">
                                        Service
                                    </th>
                                    <th className="py-2 pr-3 text-right font-semibold">
                                        Won
                                    </th>
                                    <th className="py-2 pr-3 text-right font-semibold">
                                        Lost
                                    </th>
                                    <th className="py-2 text-right font-semibold">
                                        Closing
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="tabular-nums">
                                {insight.rows.map((row) => (
                                    <tr
                                        key={row.service}
                                        className="border-b border-sidebar-border/60"
                                    >
                                        <td className="py-2 pr-3">
                                            {row.service}
                                        </td>
                                        <td className="py-2 pr-3 text-right">
                                            {row.won}
                                            {Number(row.value) > 0 ? (
                                                <span className="ml-1.5 text-xs text-secondary-foreground">
                                                    {formatFigure(
                                                        row.value,
                                                        'money',
                                                    )}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="py-2 pr-3 text-right">
                                            {row.lost}
                                        </td>
                                        <td className="py-2 text-right">
                                            {formatFigure(
                                                row.closing,
                                                'percent',
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                );
            case 'sources': {
                const channelMax = Math.max(
                    0,
                    ...insight.channels.map((row) => row.count),
                );
                const placeMax = Math.max(
                    0,
                    ...insight.places.map((row) => row.count),
                );

                return (
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="min-w-0">
                            <h4 className="mb-2 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                How
                            </h4>
                            <ul className="space-y-2">
                                {insight.channels.map((row) => (
                                    <Bar
                                        key={row.key}
                                        label={row.label}
                                        value={row.count}
                                        max={channelMax}
                                        figure={String(row.count)}
                                    />
                                ))}
                            </ul>
                        </div>
                        <div className="min-w-0">
                            <h4 className="mb-2 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                Where
                            </h4>
                            {insight.places.length === 0 ? (
                                <Empty>No inquiries within the dates.</Empty>
                            ) : (
                                <ul className="space-y-2">
                                    {insight.places.map((row) => (
                                        <Bar
                                            key={row.label}
                                            label={row.label}
                                            value={row.count}
                                            max={placeMax}
                                            figure={String(row.count)}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                );
            }
            case 'ageing': {
                const max = Math.max(
                    0,
                    ...insight.buckets.map((row) => Number(row.total)),
                );

                return (
                    <ul className="space-y-2" data-testid="overview-ageing">
                        {insight.buckets.map((row) => (
                            <Bar
                                key={row.bucket}
                                label={row.label}
                                detail={`${row.count} ${row.count === 1 ? 'inquiry' : 'inquiries'}`}
                                value={Number(row.total)}
                                max={max}
                                figure={formatFigure(row.total, 'money')}
                            />
                        ))}
                    </ul>
                );
            }
            case 'popular':
                return (
                    <div className="grid gap-5 sm:grid-cols-2">
                        <PopularList title="Stones" rows={insight.stones} />
                        <PopularList title="Brands" rows={insight.brands} />
                    </div>
                );
        }
    })();

    return (
        <Panel
            title={insight.title}
            // Two lists side by side need the full width to stay readable.
            className={
                insight.kind === 'sources' || insight.kind === 'popular'
                    ? 'lg:col-span-2'
                    : undefined
            }
        >
            <p className="-mt-2 mb-3 text-xs text-secondary-foreground">
                {insight.note}
            </p>
            {body}
        </Panel>
    );
};

export default InsightPanel;

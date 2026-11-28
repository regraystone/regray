import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FigureCard, Panel } from '@/components/overview/figure-card';
import InsightPanel from '@/components/overview/insight-panel';
import TrendChart from '@/components/overview/trend-chart';
import PageHeader from '@/components/page-header';
import { formatDate } from '@/lib/format-date';
import { formatPeso } from '@/lib/format-money';
import { clampRange, formatFigure, rangeLabel } from '@/lib/overview';
import { share } from '@/lib/sales-report';
import { cn } from '@/lib/utils';
import { index as overviewIndex } from '@/routes/overview';
import type {
    OverviewPageProps,
    OverviewRow,
    OverviewSection,
    OverviewTask,
    StatusCount,
    TeamSection,
} from '@/types/overview';

type PageProps = OverviewPageProps & { today: string };

const Rows = ({ rows, empty }: { rows: OverviewRow[]; empty: string }) =>
    rows.length === 0 ? (
        <p className="text-sm text-secondary-foreground">{empty}</p>
    ) : (
        <ul className="divide-y divide-sidebar-border">
            {rows.map((row, index) => (
                <li key={`${row.url}-${index}`}>
                    <Link
                        href={row.url}
                        className="flex items-start justify-between gap-3 py-2 hover:bg-muted/50"
                    >
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-semibold">
                                {row.title}
                            </span>
                            <span className="block truncate text-xs text-secondary-foreground">
                                {row.status ? `${row.status} · ` : ''}
                                {row.detail}
                            </span>
                        </span>
                        {row.at ? (
                            <span className="shrink-0 text-right text-xs text-secondary-foreground">
                                {row.atLabel ? (
                                    <span className="block">{row.atLabel}</span>
                                ) : null}
                                {formatDate(row.at)}
                            </span>
                        ) : null}
                    </Link>
                </li>
            ))}
        </ul>
    );

/** What is waiting on the reader, each with how many and the oldest few. */
const Tasks = ({
    tasks,
    viewing,
}: {
    tasks: OverviewTask[];
    viewing: boolean;
}) => (
    <Panel title={viewing ? 'Waiting on them' : 'Needs you now'}>
        {tasks.length === 0 ? (
            <p className="text-sm text-secondary-foreground">
                {viewing
                    ? 'Nothing is waiting on them.'
                    : 'Nothing is waiting on you.'}
            </p>
        ) : (
            <div className="space-y-4">
                {tasks.map((task) => (
                    <div key={task.key} data-testid={`task-${task.key}`}>
                        <Link
                            href={task.url}
                            className="flex items-center justify-between gap-2 text-sm font-semibold hover:underline"
                        >
                            <span>{task.label}</span>
                            <span
                                className={cn(
                                    'min-w-7 rounded-full px-2 py-0.5 text-center text-xs font-bold',
                                    task.count > 0
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-secondary-foreground',
                                )}
                            >
                                {task.count}
                            </span>
                        </Link>
                        {task.items.length > 0 ? (
                            <div className="mt-1 pl-1">
                                <Rows rows={task.items} empty="" />
                            </div>
                        ) : null}
                    </div>
                ))}
            </div>
        )}
    </Panel>
);

/** Counts by status as bars, each a link to the list at that status. */
const StatusBars = ({
    rows,
    money = false,
}: {
    rows: StatusCount[];
    money?: boolean;
}) => {
    const max = Math.max(0, ...rows.map((row) => row.count));

    return (
        <ul className="space-y-2">
            {rows.map((row) => (
                <li key={row.status}>
                    <Link
                        href={row.url}
                        className="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-3 text-sm hover:underline"
                    >
                        <span className="truncate">{row.label}</span>
                        <span className="h-2 bg-muted">
                            <span
                                className="block h-full bg-[#2a78d6]"
                                style={{ width: `${share(row.count, max)}%` }}
                            />
                        </span>
                        <span className="text-right text-xs whitespace-nowrap tabular-nums">
                            <span className="font-bold">{row.count}</span>
                            {money && row.total !== undefined ? (
                                <span className="ml-1.5 text-secondary-foreground">
                                    {formatFigure(row.total, 'money')}
                                </span>
                            ) : null}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
};

const percent = (value: number | null) => formatFigure(value, 'percent');

/** Each marketing user's and sales rep's results over the dates. */
const TeamTables = ({ section }: { section: TeamSection }) => (
    <div className="grid gap-4">
        <Panel title="Marketing">
            <div className="overflow-x-auto">
                <table
                    className="w-full min-w-[28rem] text-left text-sm"
                    data-testid="marketing-team"
                >
                    <thead className="text-xs text-secondary-foreground">
                        <tr className="border-b border-sidebar-border">
                            <th className="py-2 pr-3 font-semibold">Name</th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                New leads
                            </th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Won
                            </th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Lost
                            </th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Closing
                            </th>
                            <th className="py-2 text-right font-semibold">
                                Sold by sales
                            </th>
                        </tr>
                    </thead>
                    <tbody className="tabular-nums">
                        {section.marketingTeam.map((row) => (
                            <tr
                                key={row.id}
                                className="border-b border-sidebar-border/60"
                            >
                                <td className="py-2 pr-3">
                                    <ViewAsLink id={row.id} name={row.name} />
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {row.newLeads}
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {row.won}
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {row.lost}
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {percent(row.closing)}
                                </td>
                                <td className="py-2 text-right">
                                    {row.sold}
                                    <span className="ml-1.5 text-xs text-secondary-foreground">
                                        {formatFigure(row.soldValue, 'money')}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Panel>
        <Panel title="Sales">
            <div className="overflow-x-auto">
                <table
                    className="w-full min-w-[28rem] text-left text-sm"
                    data-testid="sales-team"
                >
                    <thead className="text-xs text-secondary-foreground">
                        <tr className="border-b border-sidebar-border">
                            <th className="py-2 pr-3 font-semibold">Name</th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Won
                            </th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Lost
                            </th>
                            <th className="py-2 pr-3 text-right font-semibold">
                                Closing
                            </th>
                            <th className="py-2 text-right font-semibold">
                                Open now
                            </th>
                        </tr>
                    </thead>
                    <tbody className="tabular-nums">
                        {section.salesTeam.map((row) => (
                            <tr
                                key={row.id}
                                className="border-b border-sidebar-border/60"
                            >
                                <td className="py-2 pr-3">
                                    <ViewAsLink id={row.id} name={row.name} />
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {row.won}
                                    <span className="ml-1.5 text-xs text-secondary-foreground">
                                        {formatFigure(row.value, 'money')}
                                    </span>
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {row.lost}
                                </td>
                                <td className="py-2 pr-3 text-right">
                                    {percent(row.closing)}
                                </td>
                                <td className="py-2 text-right">
                                    {row.open}
                                    <span className="ml-1.5 text-xs text-secondary-foreground">
                                        {formatFigure(row.openValue, 'money')}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Panel>
    </div>
);

/** A person's name; for the admin, a link to their own overview. */
const ViewAsLink = ({ id, name }: { id: number; name: string }) => {
    const { props } = usePage<PageProps>();

    if (props.viewAs === null) {
        return <>{name}</>;
    }

    return (
        <Link
            href={overviewIndex.url({
                query: { as: id, from: props.range.from, to: props.range.to },
            })}
            className="font-semibold underline-offset-2 hover:underline"
        >
            {name}
        </Link>
    );
};

const Section = ({
    section,
    days,
    viewing,
}: {
    section: OverviewSection;
    days: number;
    /** The admin is reading someone else's: "them", not "you". */
    viewing: boolean;
}) => (
    <section className="space-y-4" data-testid={`section-${section.kind}`}>
        <div>
            <h2 className="text-lg font-bold tracking-[-0.02em]">
                {section.title}
            </h2>
            <p className="text-xs text-secondary-foreground">{section.note}</p>
        </div>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {section.figures.map((figure) => (
                <FigureCard key={figure.key} figure={figure} days={days} />
            ))}
        </div>

        {section.kind === 'team' ? <TeamTables section={section} /> : null}

        <div className="grid gap-4 lg:grid-cols-2">
            {'trend' in section ? (
                <Panel title="Trend">
                    <TrendChart
                        trend={section.trend}
                        title={
                            section.kind === 'marketing'
                                ? 'New leads, won and lost'
                                : 'Won and lost'
                        }
                    />
                </Panel>
            ) : null}

            {section.kind === 'sales' ? (
                <Panel title={viewing ? 'Pipeline now' : 'Your pipeline now'}>
                    <StatusBars rows={section.pipeline} money />
                </Panel>
            ) : null}
            {section.kind === 'team' ? (
                <Panel title="Every inquiry by stage">
                    <StatusBars
                        rows={section.pipeline.filter((row) => row.count > 0)}
                    />
                </Panel>
            ) : null}
            {section.kind === 'accounting' ? (
                <Panel title="By payment status">
                    <StatusBars rows={section.paymentStatuses} money />
                </Panel>
            ) : null}
            {section.kind === 'logistics' ? (
                <Panel title="Jobs by step">
                    <StatusBars rows={section.steps} />
                </Panel>
            ) : null}

            <Tasks tasks={section.tasks} viewing={viewing} />

            {section.kind === 'technical' ? (
                <Panel
                    title={viewing ? 'Open inquiries' : 'Your open inquiries'}
                >
                    <Rows
                        rows={section.inquiries}
                        empty={
                            viewing
                                ? 'They are not on any open inquiry.'
                                : 'You are not on any open inquiry.'
                        }
                    />
                </Panel>
            ) : null}
            {section.kind === 'accounting' ? (
                <Panel title="Latest payments and refunds">
                    <Rows
                        rows={section.transactions.map((row) => ({
                            ...row,
                            detail: `${formatPeso(row.amount)} · ${row.detail}`,
                        }))}
                        empty="No payments recorded yet."
                    />
                </Panel>
            ) : null}
            {'upcoming' in section ? (
                <Panel
                    title={
                        section.kind === 'logistics'
                            ? 'Deadlines this week'
                            : 'Coming up this week'
                    }
                >
                    <Rows
                        rows={section.upcoming}
                        empty="Nothing in the next 7 days."
                    />
                </Panel>
            ) : null}
        </div>

        {section.insights && section.insights.length > 0 ? (
            <div className="space-y-3 pt-2">
                <h3 className="text-sm font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                    Insights
                </h3>
                <div className="grid gap-4 lg:grid-cols-2">
                    {section.insights.map((insight) => (
                        <InsightPanel
                            key={insight.kind}
                            insight={insight}
                            days={days}
                        />
                    ))}
                </div>
            </div>
        ) : null}
    </section>
);

/**
 * The Overview: each staff member's own summary, by role (OverviewService).
 * Sales and marketing see their results and closing %, accounting the money,
 * logistics the jobs, technical their inquiries and chats, and the owner and
 * the admin the studio's totals with each person's share.
 *
 * From and To (the first of this month to today by default) choose the dates
 * the counts cover; figures marked "as of today" ignore them. The admin may
 * open anyone's overview with View as.
 */
const Overview = () => {
    const { props } = usePage<PageProps>();
    const { range } = props;
    const [loading, setLoading] = useState(false);

    const visit = (query: Record<string, string | number | null>) =>
        router.get(
            overviewIndex.url({
                query: Object.fromEntries(
                    Object.entries(query).filter(
                        ([, value]) => value !== null && value !== '',
                    ),
                ),
            }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    const changeDate = (field: 'from' | 'to', value: string) => {
        if (value === '') {
            return;
        }

        const next = clampRange(
            field === 'from' ? value : range.from,
            field === 'to' ? value : range.to,
            props.today,
            field,
        );

        visit({ ...next, as: props.viewingAs });
    };

    const viewing = props.viewingAs !== null;

    return (
        <div className="w-full min-w-0">
            <PageHeader
                title="Overview"
                subtitle={
                    viewing
                        ? `${props.subject.name}'s work at a glance`
                        : `Your work at a glance, ${props.subject.name}`
                }
            />

            {viewing ? (
                <div
                    className="mb-6 flex flex-wrap items-center justify-between gap-2 bg-foreground px-4 py-3 text-sm text-background"
                    role="status"
                    data-testid="overview-viewing-as"
                >
                    <span>
                        Viewing <strong>{props.subject.name}</strong>'s overview
                        ({props.subject.roles.join(', ')})
                    </span>
                    <Link
                        href={overviewIndex.url({
                            query: { from: range.from, to: range.to },
                        })}
                        className="font-bold underline underline-offset-2"
                    >
                        Back to mine
                    </Link>
                </div>
            ) : null}

            <div className="mb-6 flex flex-wrap items-end gap-3">
                {props.showsDates ? (
                    <fieldset className="flex flex-wrap items-end gap-3">
                        <legend className="sr-only">Dates</legend>
                        <label className="flex flex-col gap-1 text-xs font-semibold">
                            From
                            <input
                                type="date"
                                value={range.from}
                                max={range.to}
                                onChange={(event) =>
                                    changeDate('from', event.target.value)
                                }
                                className="h-9 border border-input bg-background px-2 text-sm font-normal"
                                data-testid="overview-from"
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-semibold">
                            To
                            <input
                                type="date"
                                value={range.to}
                                min={range.from}
                                max={props.today}
                                onChange={(event) =>
                                    changeDate('to', event.target.value)
                                }
                                className="h-9 border border-input bg-background px-2 text-sm font-normal"
                                data-testid="overview-to"
                            />
                        </label>
                        <p className="pb-2 text-xs text-secondary-foreground">
                            {rangeLabel(range.from, range.to)} · compared with{' '}
                            {rangeLabel(range.previousFrom, range.previousTo)}
                            <span className="block">
                                The dates apply to every figure except those
                                marked Now, which are as of today.
                            </span>
                        </p>
                    </fieldset>
                ) : null}

                {props.viewAs !== null ? (
                    <label className="ml-auto flex flex-col gap-1 text-xs font-semibold">
                        View as
                        <select
                            value={props.viewingAs ?? ''}
                            onChange={(event) =>
                                visit({
                                    from: range.from,
                                    to: range.to,
                                    as: event.target.value || null,
                                })
                            }
                            className="h-9 min-w-52 border border-input bg-background px-2 text-sm font-normal"
                            data-testid="overview-view-as"
                        >
                            <option value="">Yourself</option>
                            {props.viewAs.options.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name} · {option.role}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : null}
            </div>

            <div
                className={cn(
                    'space-y-10 transition-opacity',
                    loading && 'opacity-60',
                )}
                aria-busy={loading}
            >
                {props.sections.length === 0 ? (
                    <p className="text-sm text-secondary-foreground">
                        There is nothing to summarise for your role yet.
                    </p>
                ) : (
                    props.sections.map((section) => (
                        <Section
                            key={section.kind}
                            section={section}
                            days={range.days}
                            viewing={viewing}
                        />
                    ))
                )}
            </div>
        </div>
    );
};

export default Overview;

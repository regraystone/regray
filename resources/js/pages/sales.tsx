import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import PageHeader from '@/components/page-header';
import CashByMonthChart, {
    SERIES,
} from '@/components/sales/cash-by-month-chart';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format-date';
import { formatPeso } from '@/lib/format-money';
import { plural, share, winRateText } from '@/lib/sales-report';
import { cn } from '@/lib/utils';
import { index as salesIndex } from '@/routes/sales';
import type { SalesPageProps, SalesPeriodValue } from '@/types/sales';

/** A figure tile: what it counts, the amount, and a line under it. */
const Figure = ({
    label,
    value,
    detail,
    testId,
}: {
    label: string;
    value: string;
    detail: ReactNode;
    testId: string;
}) => (
    <div
        className="flex min-w-0 flex-col gap-1 border border-sidebar-border p-4"
        data-testid={testId}
    >
        <p className="text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
            {label}
        </p>
        <p className="text-2xl leading-tight font-bold tracking-[-0.03em] break-words">
            {value}
        </p>
        <p className="text-xs text-secondary-foreground">{detail}</p>
    </div>
);

/** A part of the page with its heading and what it covers. */
const Panel = ({
    title,
    note,
    children,
    className,
}: {
    title: string;
    note: string;
    children: ReactNode;
    className?: string;
}) => (
    <section
        className={cn(
            'min-w-0 border border-sidebar-border p-4 sm:p-5',
            className,
        )}
    >
        <div className="mb-4">
            <h2 className="text-base font-bold tracking-[-0.02em]">{title}</h2>
            <p className="mt-0.5 text-xs text-secondary-foreground">{note}</p>
        </div>
        {children}
    </section>
);

/**
 * The Sales page: the money side of the inquiries the reader may see. Every
 * figure is read from what the inquiries hold (SalesReportService): contract
 * totals sales sets, quoted totals, and the payments and refunds accounting
 * records. Read only.
 *
 * The period row scopes what happened in a span; what is owed and the open
 * quotes are as of today, and the monthly chart is always the last 12 months,
 * each saying so.
 */
const Sales = () => {
    const { props } = usePage<SalesPageProps>();
    const { figures, period } = props;
    const [loading, setLoading] = useState(false);
    const periodLabel =
        props.periods.find((option) => option.value === period)?.label ?? '';

    const choose = (next: SalesPeriodValue) =>
        router.get(
            salesIndex.url({ query: { period: next } }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    const serviceMax = Math.max(
        0,
        ...props.byService.map((row) => Number(row.total)),
    );

    return (
        <div className="w-full min-w-0">
            <PageHeader
                title="Sales"
                subtitle={
                    props.scope === 'all'
                        ? 'What has been won, collected and is still owed, across every inquiry'
                        : 'What has been won, collected and is still owed, on the inquiries assigned to you'
                }
            />

            <div
                className="mb-6 flex flex-wrap gap-2"
                role="group"
                aria-label="Period"
            >
                {props.periods.map((option) => (
                    <Button
                        key={option.value}
                        type="button"
                        variant={
                            option.value === period ? 'default' : 'outline'
                        }
                        size="sm"
                        aria-pressed={option.value === period}
                        onClick={() => choose(option.value)}
                    >
                        {option.label}
                    </Button>
                ))}
            </div>

            <div
                className={cn(
                    'space-y-6 transition-opacity',
                    loading && 'opacity-60',
                )}
                aria-busy={loading}
            >
                <div className="grid grid-cols-1 gap-3 min-[420px]:grid-cols-2 lg:grid-cols-3">
                    <Figure
                        testId="figure-contracts-won"
                        label={`Contracts won · ${periodLabel}`}
                        value={formatPeso(figures.contractsWon.total)}
                        detail={plural(
                            figures.contractsWon.count,
                            'inquiry won',
                            'inquiries won',
                        )}
                    />
                    <Figure
                        testId="figure-collected"
                        label={`Cash collected · ${periodLabel}`}
                        value={formatPeso(figures.cash.net)}
                        detail={`${formatPeso(figures.cash.payments)} in ${plural(figures.cash.paymentsCount, 'payment', 'payments')}, less refunds`}
                    />
                    <Figure
                        testId="figure-refunds"
                        label={`Refunds · ${periodLabel}`}
                        value={formatPeso(figures.cash.refunds)}
                        detail={plural(
                            figures.cash.refundsCount,
                            'refund',
                            'refunds',
                        )}
                    />
                    <Figure
                        testId="figure-balance"
                        label="Still to collect · Today"
                        value={formatPeso(figures.balance.total)}
                        detail={`Owed on ${plural(figures.balance.count, 'won inquiry', 'won inquiries')}`}
                    />
                    <Figure
                        testId="figure-open-quotes"
                        label="Open quotes · Today"
                        value={formatPeso(figures.openQuotes.total)}
                        detail={`${plural(figures.openQuotes.count, 'quote', 'quotes')} not yet won or rejected`}
                    />
                    <Figure
                        testId="figure-win-rate"
                        label={`Win rate · ${periodLabel}`}
                        value={winRateText(figures.winRate.rate)}
                        detail={
                            figures.winRate.rate === null
                                ? 'Nothing won or rejected yet'
                                : `${figures.winRate.won} won · ${figures.winRate.rejected} rejected`
                        }
                    />
                </div>

                <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <Panel
                        title="Cash by month"
                        note="Payments and refunds received, the last 12 months"
                    >
                        <CashByMonthChart months={props.months} />
                    </Panel>

                    <Panel
                        title="By payment status"
                        note="Inquiries with a payment status today, and their contract value"
                    >
                        <ul className="divide-y divide-sidebar-border">
                            {props.byPaymentStatus.map((row) => (
                                <li
                                    key={row.status}
                                    className="flex items-baseline justify-between gap-3 py-2.5 text-sm"
                                >
                                    <span>
                                        {row.label}
                                        <span className="ml-2 text-xs text-secondary-foreground">
                                            {plural(
                                                row.count,
                                                'inquiry',
                                                'inquiries',
                                            )}
                                        </span>
                                    </span>
                                    <span className="font-bold tabular-nums">
                                        {formatPeso(row.total)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </div>

                <Panel
                    title="Contracts won by service"
                    note={`Contract value won · ${periodLabel}`}
                >
                    {props.byService.length === 0 ? (
                        <p className="text-sm text-secondary-foreground">
                            No contracts won in this period.
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {props.byService.map((row) => (
                                <li key={row.service} className="text-sm">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="min-w-0 truncate">
                                            {row.service}
                                            <span className="ml-2 text-xs text-secondary-foreground">
                                                {plural(
                                                    row.count,
                                                    'inquiry',
                                                    'inquiries',
                                                )}
                                            </span>
                                        </span>
                                        <span className="font-bold tabular-nums">
                                            {formatPeso(row.total)}
                                        </span>
                                    </div>
                                    <div className="mt-1.5 h-2 bg-muted">
                                        <div
                                            className="h-full rounded-r-[4px]"
                                            style={{
                                                width: `${share(Number(row.total), serviceMax)}%`,
                                                backgroundColor:
                                                    SERIES.payments.color,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel
                    title="Balances to collect"
                    note="Won inquiries that still owe the most, today"
                >
                    {props.balances.length === 0 ? (
                        <p className="text-sm text-secondary-foreground">
                            Every won inquiry is paid in full.
                        </p>
                    ) : (
                        <>
                            <ul
                                className="divide-y divide-sidebar-border md:hidden"
                                data-testid="balances-list"
                            >
                                {props.balances.map((row) => (
                                    <li key={row.reference} className="py-3">
                                        <Link
                                            href={row.url}
                                            className="flex items-start justify-between gap-3"
                                        >
                                            <span className="min-w-0">
                                                <span className="block text-sm font-bold">
                                                    {row.client}
                                                </span>
                                                <span className="block text-xs text-secondary-foreground">
                                                    {row.reference} ·{' '}
                                                    {row.service}
                                                </span>
                                                <span className="block text-xs text-secondary-foreground">
                                                    Paid {formatPeso(row.paid)}{' '}
                                                    of{' '}
                                                    {formatPeso(
                                                        row.contractTotal,
                                                    )}
                                                </span>
                                            </span>
                                            <span className="text-sm font-bold whitespace-nowrap tabular-nums">
                                                {formatPeso(row.balance)}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            <div className="hidden md:block">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-sidebar-border text-left text-xs tracking-[0.05em] text-secondary-foreground uppercase">
                                            <th className="py-2 pr-3 font-bold">
                                                Inquiry
                                            </th>
                                            <th className="py-2 pr-3 font-bold">
                                                Client
                                            </th>
                                            <th className="py-2 pr-3 text-right font-bold">
                                                Contract
                                            </th>
                                            <th className="py-2 pr-3 text-right font-bold">
                                                Paid
                                            </th>
                                            <th className="py-2 pr-3 text-right font-bold">
                                                Balance
                                            </th>
                                            <th className="py-2 pr-3 font-bold">
                                                Status
                                            </th>
                                            <th className="py-2 font-bold">
                                                Last payment
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {props.balances.map((row) => (
                                            <tr
                                                key={row.reference}
                                                className="border-b border-sidebar-border last:border-0"
                                            >
                                                <td className="py-2.5 pr-3">
                                                    <Link
                                                        href={row.url}
                                                        className="font-bold underline-offset-4 hover:underline"
                                                    >
                                                        {row.reference}
                                                    </Link>
                                                    <span className="block text-xs text-secondary-foreground">
                                                        {row.service}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 pr-3">
                                                    {row.client}
                                                </td>
                                                <td className="py-2.5 pr-3 text-right tabular-nums">
                                                    {formatPeso(
                                                        row.contractTotal,
                                                    )}
                                                </td>
                                                <td className="py-2.5 pr-3 text-right tabular-nums">
                                                    {formatPeso(row.paid)}
                                                </td>
                                                <td className="py-2.5 pr-3 text-right font-bold tabular-nums">
                                                    {formatPeso(row.balance)}
                                                </td>
                                                <td className="py-2.5 pr-3">
                                                    {row.paymentStatus ?? '—'}
                                                </td>
                                                <td className="py-2.5">
                                                    {row.lastPaymentAt
                                                        ? formatDate(
                                                              row.lastPaymentAt,
                                                          )
                                                        : 'None yet'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </Panel>

                <Panel
                    title="Recent payments and refunds"
                    note="The latest accounting recorded"
                >
                    {props.transactions.length === 0 ? (
                        <p className="text-sm text-secondary-foreground">
                            No payments recorded yet.
                        </p>
                    ) : (
                        <ul
                            className="divide-y divide-sidebar-border"
                            data-testid="transactions"
                        >
                            {props.transactions.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-start justify-between gap-3 py-2.5 text-sm"
                                >
                                    <span className="min-w-0">
                                        <span className="block">
                                            <span
                                                aria-hidden
                                                className="mr-2 inline-block size-2 rounded-[2px] align-middle"
                                                style={{
                                                    backgroundColor:
                                                        SERIES[
                                                            row.kind ===
                                                            'payment'
                                                                ? 'payments'
                                                                : 'refunds'
                                                        ].color,
                                                }}
                                            />
                                            {row.kind === 'payment'
                                                ? 'Payment'
                                                : 'Refund'}{' '}
                                            ·{' '}
                                            <Link
                                                href={row.url}
                                                className="underline-offset-4 hover:underline"
                                            >
                                                {row.reference}
                                            </Link>{' '}
                                            · {row.client}
                                        </span>
                                        <span className="block text-xs text-secondary-foreground">
                                            {formatDate(row.receivedAt)}
                                            {row.recordedBy
                                                ? ` · recorded by ${row.recordedBy}`
                                                : ''}
                                        </span>
                                    </span>
                                    <span className="font-bold whitespace-nowrap tabular-nums">
                                        {row.kind === 'refund' ? '−' : ''}
                                        {formatPeso(row.amount)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </div>
    );
};

export default Sales;

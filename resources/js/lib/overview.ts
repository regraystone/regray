/*
 * The Overview page's pure helpers: how a figure reads, how it changed from
 * the days before, the chart's labels and the date fields' limits. No
 * imports, for tests/js.
 */

type Format =
    | 'count'
    | 'money'
    | 'percent'
    | 'signedPercent'
    | 'decimal'
    | 'days'
    | 'hours';

/** The formats whose changes are read to one decimal place. */
const FINE: Format[] = ['days', 'hours', 'decimal', 'signedPercent'];

const PESO = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

const COUNT = new Intl.NumberFormat('en-PH');

/** A figure as the card shows it; "—" when there is nothing to show (no decisions, no wins). */
export const formatFigure = (
    value: number | string | null,
    format: Format,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const number = Number(value);

    switch (format) {
        case 'money':
            return PESO.format(number);
        case 'percent':
            return `${Math.round(number)}%`;
        case 'signedPercent':
            return `${number > 0 ? '+' : ''}${number.toFixed(1)}%`;
        case 'decimal':
            return number.toFixed(1);
        case 'days':
            return `${number.toFixed(1)} ${number === 1 ? 'day' : 'days'}`;
        case 'hours':
            return number >= 48
                ? `${(number / 24).toFixed(1)} days`
                : `${number.toFixed(1)} h`;
        default:
            return COUNT.format(number);
    }
};

/** Each step's share of the first, and of the step before it, as whole percents. */
export const funnelShares = (
    counts: number[],
): { ofFirst: number | null; ofPrevious: number | null }[] =>
    counts.map((count, index) => ({
        ofFirst: counts[0] > 0 ? Math.round((count * 100) / counts[0]) : null,
        ofPrevious:
            index === 0
                ? null
                : counts[index - 1] > 0
                  ? Math.round((count * 100) / counts[index - 1])
                  : null,
    }));

export type Change = {
    direction: 'up' | 'down' | 'same';
    text: string;
};

/**
 * How a figure moved from the same number of days just before: "▲ 3",
 * "▼ ₱12,000", "▲ 5 pts" for a percentage, "No change". Null when either side
 * has nothing to compare (as of now, or no decisions in one of them), and when
 * both are zero.
 */
export const changeFrom = (
    value: number | string | null,
    previous: number | string | null,
    format: Format,
): Change | null => {
    if (
        value === null ||
        previous === null ||
        value === '' ||
        previous === ''
    ) {
        return null;
    }

    // Nothing then and nothing now is not worth a line.
    if (Number(value) === 0 && Number(previous) === 0) {
        return null;
    }

    const difference = Number(value) - Number(previous);
    const rounded = FINE.includes(format)
        ? Math.round(difference * 10) / 10
        : Math.round(difference);

    if (rounded === 0) {
        return { direction: 'same', text: 'No change' };
    }

    const size = Math.abs(rounded);
    const amount =
        format === 'money'
            ? PESO.format(size)
            : format === 'percent' || format === 'signedPercent'
              ? `${size} pts`
              : format === 'days' || format === 'hours'
                ? formatFigure(size, format)
                : format === 'decimal'
                  ? size.toFixed(1)
                  : COUNT.format(size);

    return {
        direction: rounded > 0 ? 'up' : 'down',
        text: `${rounded > 0 ? '▲' : '▼'} ${amount}`,
    };
};

/** "vs yesterday", "vs the 7 days before". */
export const comparedTo = (days: number): string =>
    days === 1 ? 'vs the day before' : `vs the ${days} days before`;

const SHORT = { month: 'short', day: 'numeric', timeZone: 'UTC' } as const;

const toDate = (value: string): Date => {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
};

/** A chart bucket's label: "Nov 5" for a day, "Wk of Nov 3" for a week, "Nov 2026" for a month. */
export const bucketLabel = (
    start: string,
    bucket: 'day' | 'week' | 'month',
): string => {
    const date = toDate(start);

    if (bucket === 'month') {
        return date.toLocaleDateString('en-PH', {
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        });
    }

    const label = date.toLocaleDateString('en-PH', SHORT);

    return bucket === 'week' ? `Wk of ${label}` : label;
};

/** "Nov 1 – Nov 27, 2026", or one date when From is To. */
export const rangeLabel = (from: string, to: string): string => {
    const end = toDate(to).toLocaleDateString('en-PH', {
        ...SHORT,
        year: 'numeric',
    });

    return from === to
        ? end
        : `${toDate(from).toLocaleDateString('en-PH', SHORT)} – ${end}`;
};

/**
 * The dates to ask for once one field changes: To is never after today, and
 * From never after To (moving one past the other brings the other along).
 */
export const clampRange = (
    from: string,
    to: string,
    today: string,
    changed: 'from' | 'to',
): { from: string; to: string } => {
    const end = to > today ? today : to;
    const start = from > today ? today : from;

    if (start <= end) {
        return { from: start, to: end };
    }

    return changed === 'from'
        ? { from: start, to: start }
        : { from: end, to: end };
};

/**
 * Which bucket labels to print under a chart of `count` columns, so they do
 * not run together: every one up to 12, then about 8 spread evenly, always
 * keeping the last.
 */
export const labelEvery = (count: number, most = 12): number =>
    count <= most ? 1 : Math.ceil(count / 8);

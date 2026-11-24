/*
 * The Sales page's pure helpers: the chart's scale and labels, and the words
 * under each figure. No imports, for tests/js.
 */

const COMPACT = new Intl.NumberFormat('en-PH', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

/** "₱1.2M", "₱650K", "₱0": an axis tick or a tight label. */
export const compactPeso = (value: number): string =>
    `₱${COMPACT.format(value)}`;

/**
 * A clean axis for amounts from 0 to `max`: about four steps of 1, 2, 2.5 or
 * 5 times a power of ten, ending at or just above `max`. An empty chart still
 * has an axis (0 to 1).
 */
export const niceTicks = (max: number, steps = 4): number[] => {
    if (!(max > 0)) {
        return [0, 1];
    }

    const rough = max / steps;
    const power = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 2.5, 5, 10]
            .map((factor) => factor * power)
            .find((candidate) => candidate >= rough) ?? 10 * power;
    const ticks: number[] = [];

    for (let tick = 0; tick < max + step; tick += step) {
        ticks.push(Math.round(tick * 100) / 100);

        if (tick >= max) {
            break;
        }
    }

    return ticks;
};

/** "Oct" from "2026-10"; with the year ("Jan 2027") when asked. */
export const monthLabel = (month: string, withYear = false): string => {
    const [year, number] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, number - 1, 1));

    return date.toLocaleDateString('en-PH', {
        month: 'short',
        ...(withYear ? { year: 'numeric' } : {}),
        timeZone: 'UTC',
    });
};

/** "1 inquiry", "17 inquiries". */
export const plural = (count: number, one: string, many: string): string =>
    `${count} ${count === 1 ? one : many}`;

/** "89%", or "—" when nothing was decided in the span. */
export const winRateText = (rate: number | null): string =>
    rate === null ? '—' : `${rate}%`;

/** How much of `max` a value is, 0 to 100, for a bar's length. */
export const share = (value: number, max: number): number =>
    max > 0 ? Math.max(0, Math.min(100, (value / max) * 100)) : 0;

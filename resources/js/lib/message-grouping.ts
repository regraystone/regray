/*
 * How a thread is laid out: date separators between days, and runs of
 * consecutive messages from one sender. Pure, with no imports, for tests/js.
 */

export type GroupableMessage = {
    id: number | string;
    type:
        | 'text'
        | 'inquiry_card'
        | 'quotation_card'
        | 'schedule_card'
        | 'system';
    createdAt: string;
    sender: { id: number; name: string } | null;
};

export type ThreadItem<T extends GroupableMessage> =
    | { kind: 'separator'; key: string; label: string }
    | {
          kind: 'message';
          key: string;
          message: T;
          /** First of a run: the name label sits above it. */
          isRunStart: boolean;
          /** Last of a run: the avatar sits beside it. */
          isRunEnd: boolean;
      };

/** Two messages from one sender more than this apart start a new run. */
export const RUN_GAP_MS = 5 * 60 * 1000;

const DAY_MS = 24 * 60 * 60 * 1000;

const startOfDay = (date: Date): number =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();

/**
 * "Today", "Yesterday", the weekday within the last week, then the full date.
 * Computed in the reader's own timezone.
 */
export const dateSeparatorLabel = (
    value: string | Date,
    now: Date = new Date(),
    locale?: string,
): string => {
    const date = typeof value === 'string' ? new Date(value) : value;
    const days = Math.round((startOfDay(now) - startOfDay(date)) / DAY_MS);

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Yesterday';
    }

    if (days > 1 && days < 7) {
        return new Intl.DateTimeFormat(locale, { weekday: 'long' }).format(
            date,
        );
    }

    return new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
};

/** "Mon 20 Oct, 10:24": what hovering a bubble says about when it was sent. */
export const fullTimestamp = (
    value: string | Date,
    locale?: string,
): string => {
    const date = typeof value === 'string' ? new Date(value) : value;
    const day = new Intl.DateTimeFormat(locale, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(date);
    const time = new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);

    return `${day.replace(/,/g, '')}, ${time}`;
};

const isRunnable = (message: GroupableMessage): boolean =>
    message.type === 'text' && message.sender !== null;

const sameRun = (a: GroupableMessage, b: GroupableMessage): boolean =>
    isRunnable(a) &&
    isRunnable(b) &&
    a.sender?.id === b.sender?.id &&
    Math.abs(
        new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime(),
    ) <= RUN_GAP_MS &&
    startOfDay(new Date(a.createdAt)) === startOfDay(new Date(b.createdAt));

/**
 * The thread as rows: a separator wherever the day changes, and each message
 * marked as the start and end of its run. Cards and system notices are runs
 * of their own.
 */
export const buildThreadItems = <T extends GroupableMessage>(
    messages: readonly T[],
    now: Date = new Date(),
    locale?: string,
): Array<ThreadItem<T>> => {
    const items: Array<ThreadItem<T>> = [];
    let previousDay: number | null = null;

    messages.forEach((message, index) => {
        const day = startOfDay(new Date(message.createdAt));

        if (day !== previousDay) {
            items.push({
                kind: 'separator',
                key: `day-${day}`,
                label: dateSeparatorLabel(message.createdAt, now, locale),
            });
            previousDay = day;
        }

        const before = messages[index - 1];
        const after = messages[index + 1];

        items.push({
            kind: 'message',
            key: `message-${message.id}`,
            message,
            isRunStart: before === undefined || !sameRun(before, message),
            isRunEnd: after === undefined || !sameRun(message, after),
        });
    });

    return items;
};

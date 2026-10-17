/**
 * Weekday and time, the way a chat dates a run of messages.
 *
 * `Intl` rather than `toISOString()`, which reports the UTC day: a reader east
 * of Greenwich would be shown the day before the one the card was posted.
 * `undefined` as the locale is the reader's own, for the same reason.
 */
const threadTimestampFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    hour: 'numeric',
    minute: '2-digit',
});

type ThreadCardHeaderProps = {
    headline: string;
    occurredAt: string;
};

/**
 * Shared header for system cards (inquiry, schedule) in the thread.
 *
 * Shows the timestamp and the headline, centered above the card.
 * Extracted so both cards stay identical in appearance.
 */
const ThreadCardHeader = ({ headline, occurredAt }: ThreadCardHeaderProps) => (
    <>
        <p className="text-center text-xs text-secondary-foreground">
            <time dateTime={occurredAt}>
                {threadTimestampFormatter.format(new Date(occurredAt))}
            </time>
        </p>

        <span className="block text-center text-xs font-bold text-secondary-foreground">
            {headline}
        </span>
    </>
);

export default ThreadCardHeader;

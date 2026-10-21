/**
 * Weekday and time, the way a chat dates a card it posted. `Intl` in the
 * reader's own timezone, so the day is the one they lived it on.
 */
const timestampFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    hour: 'numeric',
    minute: '2-digit',
});

/** The time and the headline above a card, centred like the card itself. */
const CardHeader = ({
    headline,
    occurredAt,
}: {
    headline: string;
    occurredAt: string;
}) => (
    <div className="space-y-0.5 text-center">
        <p className="text-xs text-secondary-foreground">
            <time dateTime={occurredAt}>
                {timestampFormatter.format(new Date(occurredAt))}
            </time>
        </p>
        <p
            className="truncate text-xs font-bold text-secondary-foreground"
            data-testid="card-headline"
        >
            {headline}
        </p>
    </div>
);

export default CardHeader;

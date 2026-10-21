/** "Today", "Yesterday", a weekday or a full date, between days of messages. */
const DateSeparator = ({ label }: { label: string }) => (
    <div
        role="separator"
        aria-label={label}
        className="flex items-center gap-3 py-1"
    >
        <span className="h-px flex-1 bg-sidebar-border" aria-hidden />
        <span className="shrink-0 text-xs font-bold text-secondary-foreground">
            {label}
        </span>
        <span className="h-px flex-1 bg-sidebar-border" aria-hidden />
    </div>
);

export default DateSeparator;

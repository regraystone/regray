/**
 * "{name} is typing…" with three dots. Shown for a few seconds after the last
 * signal; the thread hook takes it down.
 */
const TypingIndicator = ({ names }: { names: string[] }) => {
    if (names.length === 0) {
        return null;
    }

    const who =
        names.length === 1
            ? `${names[0]} is typing`
            : `${names.slice(0, -1).join(', ')} and ${names.at(-1)} are typing`;

    return (
        <p
            aria-live="polite"
            className="flex items-center gap-2 px-1 text-xs text-secondary-foreground"
            data-testid="typing-indicator"
        >
            <span className="flex items-center gap-0.5" aria-hidden>
                {[0, 1, 2].map((dot) => (
                    <span
                        key={dot}
                        className="size-1.5 animate-bounce rounded-full bg-secondary-foreground motion-reduce:animate-none"
                        style={{ animationDelay: `${dot * 150}ms` }}
                    />
                ))}
            </span>
            <span className="truncate">{who}…</span>
        </p>
    );
};

export default TypingIndicator;

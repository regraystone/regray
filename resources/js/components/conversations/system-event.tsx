import type { ChatMessage } from '@/types/message';

const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
});

/** "Maria is now handling this conversation", centred in the thread. */
const SystemEvent = ({ message }: { message: ChatMessage }) => {
    const name = message.systemEvent?.userName ?? 'Someone';
    const text =
        message.systemEvent?.event === 'released'
            ? `${name} released this conversation`
            : message.systemEvent?.event === 'reassigned'
              ? `This conversation was handed to ${name}`
              : `${name} is now handling this conversation`;

    return (
        <p
            className="text-center text-xs text-secondary-foreground"
            data-testid="system-event"
        >
            <span className="font-bold">{text}</span>
            <span aria-hidden> · </span>
            <time dateTime={message.createdAt}>
                {timeFormatter.format(new Date(message.createdAt))}
            </time>
        </p>
    );
};

export default SystemEvent;

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import type { ConversationSummary } from '@/types/message';

/** One conversation in the list: who, the latest message, and how many are unread. */
const ConversationRow = ({
    conversation,
    selected,
    onSelect,
}: {
    conversation: ConversationSummary;
    selected: boolean;
    onSelect: (conversation: ConversationSummary) => void;
}) => {
    const getInitials = useInitials();
    const unread = conversation.unreadCount;

    return (
        <button
            type="button"
            onClick={() => onSelect(conversation)}
            aria-current={selected ? 'true' : undefined}
            data-testid="conversation-row"
            data-conversation-id={conversation.id}
            className={cn(
                'relative flex w-full cursor-pointer items-center gap-3 border-b border-sidebar-border px-4 py-3 text-left transition-opacity hover:opacity-85',
                'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                selected && 'bg-secondary',
            )}
        >
            {selected ? (
                <span
                    className="absolute inset-y-0 left-0 w-1 bg-primary"
                    aria-hidden
                />
            ) : null}
            <Avatar className="size-12 shrink-0 border border-border">
                {conversation.avatar ? (
                    <AvatarImage src={conversation.avatar} alt="" />
                ) : null}
                <AvatarFallback className="bg-primary text-sm font-bold text-primary-foreground">
                    {getInitials(conversation.name)}
                </AvatarFallback>
            </Avatar>
            <span className="min-w-0 flex-1">
                <span
                    className="block truncate text-sm font-bold text-foreground"
                    data-testid="conversation-name"
                >
                    {conversation.name}
                </span>
                <span
                    className={cn(
                        'block truncate text-sm',
                        unread > 0
                            ? 'font-bold text-foreground'
                            : 'text-secondary-foreground',
                    )}
                >
                    {conversation.preview}
                </span>
            </span>
            {unread > 0 ? (
                <span
                    className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-[0.7rem] font-bold text-primary-foreground"
                    data-testid="unread-badge"
                >
                    {unread > 99 ? '99+' : unread}
                    <span className="sr-only"> unread</span>
                </span>
            ) : null}
        </button>
    );
};

export default ConversationRow;

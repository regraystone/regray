import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { currentUserId, inquiries, type Conversation } from '@/data/constant';
import { useInitials } from '@/hooks/use-initials';
import { getFirstName } from '@/lib/people';
import { cn } from '@/lib/utils';

const EMPTY_PREVIEW = 'No messages yet';

export const getConversationPreview = (conversation: Conversation): string => {
    const lastMessage = conversation.messages[conversation.messages.length - 1];

    if (!lastMessage) {
        // A conversation opened for an inquiry has something to preview before
        // anyone has written, so the inquiry it was opened for stands in for the
        // empty thread rather than a flat "no messages".
        const inquiry = conversation.inquiryId
            ? inquiries.find((item) => item.id === conversation.inquiryId)
            : undefined;

        return inquiry ? `New project · ${inquiry.reference}` : EMPTY_PREVIEW;
    }

    if (lastMessage.senderId === currentUserId) {
        return `You: ${lastMessage.text}`;
    }

    const firstName = getFirstName(lastMessage.senderId);

    if (conversation.type === 'group' && firstName) {
        return `${firstName}: ${lastMessage.text}`;
    }

    return lastMessage.text;
};

type ConversationCardProps = {
    conversation: Conversation;
    selected: boolean;
    onSelect: (id: string) => void;
};

const ConversationCard = ({
    conversation,
    selected,
    onSelect,
}: ConversationCardProps) => {
    const getInitials = useInitials();
    const initials = getInitials(conversation.name);

    return (
        <button
            type="button"
            onClick={() => onSelect(conversation.id)}
            aria-current={selected ? 'true' : undefined}
            className={cn(
                'relative flex w-full cursor-pointer items-center gap-3 border-b border-sidebar-border px-4 py-3 text-left transition-opacity hover:opacity-85',
                'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                selected && 'bg-secondary',
            )}
        >
            {selected && (
                <span
                    className="absolute inset-y-0 left-0 w-1 bg-primary"
                    aria-hidden
                />
            )}
            <Avatar className="size-12 border border-border">
                {conversation.avatar ? (
                    <AvatarImage
                        src={conversation.avatar}
                        alt={conversation.name}
                    />
                ) : null}
                <AvatarFallback className="bg-primary text-sm font-bold text-primary-foreground">
                    {initials}
                </AvatarFallback>
            </Avatar>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-bold text-foreground">
                    {conversation.name}
                </span>
                <span className="block truncate text-sm text-secondary-foreground">
                    {getConversationPreview(conversation)}
                </span>
            </span>
            {conversation.unread ? (
                <>
                    <span className="size-2 shrink-0 bg-primary" aria-hidden />
                    <span className="sr-only">Unread</span>
                </>
            ) : null}
        </button>
    );
};

export default ConversationCard;

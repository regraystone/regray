import { ArrowLeft, MoreVertical, UserPlus } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import type { ConversationSummary } from '@/types/message';

/**
 * The thread's header: who it is with, what the thread is ("Handled by …" on
 * a Regray Team thread, the member count of a group), the staff actions on a
 * Regray Team thread, and, for a sales member of an inquiry's group chat,
 * adding accounting or logistics.
 */
const ThreadHeader = ({
    conversation,
    subtitle,
    canRelease,
    canReassign,
    onBack,
    onRelease,
    onReassign,
    onAddMembers,
}: {
    conversation: ConversationSummary;
    subtitle: string | null;
    canRelease: boolean;
    canReassign: boolean;
    onBack: () => void;
    onRelease: () => void;
    onReassign: () => void;
    onAddMembers: () => void;
}) => {
    const getInitials = useInitials();
    const secondLine =
        subtitle ??
        (conversation.type === 'group' && conversation.memberCount !== null
            ? `${conversation.memberCount} members`
            : conversation.isSupport && conversation.supportClient
              ? 'Regray Team'
              : null);

    return (
        <header className="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border px-4 sm:h-16">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onBack}
                aria-label="Back to conversations"
                className="h-11 w-11 shrink-0 cursor-pointer rounded-md md:hidden"
            >
                <ArrowLeft className="size-5" aria-hidden />
            </Button>
            <Avatar className="h-9 w-9 shrink-0 border border-border sm:h-10 sm:w-10">
                {conversation.avatar ? (
                    <AvatarImage src={conversation.avatar} alt="" />
                ) : null}
                <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground sm:text-sm">
                    {getInitials(conversation.name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <h2
                    className="truncate text-sm font-bold sm:text-base"
                    data-testid="thread-title"
                >
                    {conversation.name}
                </h2>
                {secondLine ? (
                    <p
                        className="truncate text-xs text-secondary-foreground"
                        data-testid="thread-subtitle"
                    >
                        {secondLine}
                    </p>
                ) : null}
            </div>
            <div className="ml-auto flex shrink-0 items-center gap-2">
                {conversation.canAddMembers ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={onAddMembers}
                        aria-label="Add accounting or logistics"
                        title="Add accounting or logistics"
                        className="cursor-pointer rounded-md"
                    >
                        <UserPlus className="size-5" aria-hidden />
                    </Button>
                ) : null}
                {canRelease || canReassign ? (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Assignment"
                                className="cursor-pointer rounded-md"
                            >
                                <MoreVertical className="size-5" aria-hidden />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {canRelease ? (
                                <DropdownMenuItem onSelect={onRelease}>
                                    Release to the team
                                </DropdownMenuItem>
                            ) : null}
                            {canReassign ? (
                                <DropdownMenuItem onSelect={onReassign}>
                                    Reassign…
                                </DropdownMenuItem>
                            ) : null}
                        </DropdownMenuContent>
                    </DropdownMenu>
                ) : null}
            </div>
        </header>
    );
};

export default ThreadHeader;

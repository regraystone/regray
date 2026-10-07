import { useEffect, useState } from 'react';
import ChatPanel from '@/components/chat-panel';
import ConversationList from '@/components/conversation-list';
import { Skeleton } from '@/components/ui/skeleton';
import {
    conversations as initialConversations,
    currentUserId,
    type ChatMessage,
    type Conversation,
} from '@/data/conversations';
import { cn } from '@/lib/utils';

const MessageSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <div className="flex h-[calc(100svh-3.5rem)] min-h-0 flex-col border border-sidebar-border bg-background md:min-h-[32rem] md:flex-row">
            <div className="flex w-full min-w-0 flex-col md:w-80 md:shrink-0 md:border-r md:border-sidebar-border lg:w-96">
                <div className="flex h-14 shrink-0 items-center border-b border-sidebar-border px-4 sm:h-16">
                    <Skeleton className="h-8 w-32 rounded-none" />
                </div>
                <div className="min-h-0 flex-1">
                    {Array.from({ length: 6 }, (_, index) => (
                        <div
                            key={index}
                            className="flex items-center gap-3 border-b border-sidebar-border px-4 py-3"
                        >
                            <Skeleton className="h-10 w-10 shrink-0 rounded-full sm:h-12 sm:w-12" />
                            <div className="min-w-0 flex-1 space-y-2">
                                <Skeleton className="h-4 w-1/2 rounded-none" />
                                <Skeleton className="h-4 w-3/4 rounded-none" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="hidden min-w-0 flex-1 flex-col md:flex">
                <div className="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border px-4 sm:h-16">
                    <Skeleton className="h-9 w-9 shrink-0 rounded-full sm:h-10 sm:w-10" />
                    <Skeleton className="h-4 w-40 rounded-none" />
                </div>
                <div className="flex min-h-0 flex-1 flex-col gap-2 p-4 md:p-5">
                    <Skeleton className="h-10 w-2/5 rounded-none" />
                    <Skeleton className="ml-auto h-10 w-3/5 rounded-none" />
                    <Skeleton className="h-10 w-1/2 rounded-none" />
                    <Skeleton className="ml-auto h-10 w-2/3 rounded-none" />
                </div>
                <div className="flex shrink-0 items-center gap-2 border-t border-sidebar-border p-3">
                    <Skeleton className="h-11 flex-1 rounded-none" />
                    <Skeleton className="h-11 w-11 shrink-0 rounded-none" />
                </div>
            </div>
        </div>
    </div>
);

const Message = () => {
    const [conversations, setConversations] =
        useState<Conversation[]>(initialConversations);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), 700);

        return () => window.clearTimeout(timer);
    }, []);

    const handleSend = (conversationId: string, text: string) => {
        setConversations((previous) => {
            const target = previous.find(
                (conversation) => conversation.id === conversationId,
            );

            if (!target) {
                return previous;
            }

            const message: ChatMessage = {
                id: `m-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                senderId: currentUserId,
                text,
                sentAt: new Date().toISOString(),
            };

            return [
                {
                    ...target,
                    unread: false,
                    messages: [...target.messages, message],
                },
                ...previous.filter(
                    (conversation) => conversation.id !== conversationId,
                ),
            ];
        });
    };

    if (isLoading) {
        return <MessageSkeleton />;
    }

    const selectedConversation =
        conversations.find((conversation) => conversation.id === selectedId) ??
        null;

    return (
        <div className="mx-auto w-full max-w-6xl">
            <div className="flex h-[calc(100svh-3.5rem)] min-h-0 flex-col border border-sidebar-border bg-background md:min-h-[32rem] md:flex-row">
                <ConversationList
                    conversations={conversations}
                    selectedId={selectedId}
                    onSelect={setSelectedId}
                    className={cn(selectedId && 'hidden md:flex')}
                />

                {selectedConversation ? (
                    <ChatPanel
                        conversation={selectedConversation}
                        onBack={() => setSelectedId(null)}
                        onSend={(text) =>
                            handleSend(selectedConversation.id, text)
                        }
                        className="min-w-0 flex-1"
                    />
                ) : (
                    <div className="hidden min-w-0 flex-1 items-center justify-center p-4 md:flex">
                        <p className="text-center text-sm font-semibold text-secondary-foreground">
                            Select a conversation to start messaging
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default Message;

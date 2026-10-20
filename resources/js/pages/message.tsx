import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ConversationList, {
    ConversationRowSkeletons,
} from '@/components/conversations/list';
import NewGroupDialog from '@/components/conversations/new-group-dialog';
import Thread from '@/components/conversations/thread';
import { Skeleton } from '@/components/ui/skeleton';
import { useIsMobile } from '@/hooks/use-is-mobile';
import { conversationListSkeleton } from '@/lib/conversation-skeleton';
import { getEcho, onReconnect } from '@/lib/echo';
import { markDelivered } from '@/lib/message-api';
import { cn } from '@/lib/utils';
import { index as messageIndex } from '@/routes/message';
import type { Auth } from '@/types';
import type {
    ConversationSummary,
    MessagePageProps,
    Realtime,
} from '@/types/message';

type PageProps = MessagePageProps & { auth: Auth; realtime?: Realtime };

type Filters = MessagePageProps['filters'];

/** Coarse pointers (fingers) get press-and-hold and Enter-adds-a-line. */
const useIsTouch = (): boolean => {
    const isMobile = useIsMobile();
    const [coarse, setCoarse] = useState(false);

    useEffect(() => {
        const query = window.matchMedia('(pointer: coarse)');
        const update = () => setCoarse(query.matches);

        update();
        query.addEventListener('change', update);

        return () => query.removeEventListener('change', update);
    }, []);

    return isMobile || coarse;
};

const MessageSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <div className="flex h-[calc(100svh-3.5rem)] min-h-0 flex-col overflow-hidden rounded-md border border-sidebar-border bg-background md:min-h-[32rem] md:flex-row">
            <div className="flex w-full min-w-0 flex-col md:w-80 md:shrink-0 md:border-r md:border-sidebar-border lg:w-96">
                <div className="shrink-0 border-b border-sidebar-border p-3">
                    <Skeleton className="h-11 w-full rounded-none" />
                    <div className="mt-3 flex gap-2.5">
                        <Skeleton className="h-8 w-12 rounded-md" />
                        <Skeleton className="h-8 w-16 rounded-md" />
                    </div>
                </div>
                <ConversationRowSkeletons />
            </div>
            <div className="hidden min-w-0 flex-1 md:flex" />
        </div>
    </div>
);

const urlWithConversation = (id: number | null): string => {
    const url = new URL(window.location.href);

    if (id === null) {
        url.searchParams.delete('conversation');
    } else {
        url.searchParams.set('conversation', String(id));
    }

    return `${url.pathname}${url.search}`;
};

/**
 * The Message page: the conversation list on the left and the open thread on
 * the right (one at a time on a phone).
 *
 * The page owns which conversation is open (kept in `?conversation=`) and the
 * list-level live updates: the user's inbox channel, and for the support pool
 * the pool channel, which is how a thread somebody else claims disappears from
 * this list.
 */
const Message = () => {
    const { props } = usePage<PageProps>();
    const {
        conversations,
        filters,
        activeConversation,
        currentUserId,
        isClient,
        canCreateGroup,
        auth,
    } = props;
    const realtime = props.realtime;
    const isTouch = useIsTouch();
    const rows = conversations?.data ?? [];

    const [selected, setSelected] = useState<ConversationSummary | null>(
        activeConversation,
    );
    const [isUpdating, setIsUpdating] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);
    const selectedRef = useRef(selected);
    const refreshTimer = useRef<number | null>(null);

    useEffect(() => {
        selectedRef.current = selected;
    }, [selected]);

    /** Read the list again from page one, a moment after a burst of changes. */
    const refreshList = useCallback(() => {
        if (refreshTimer.current !== null) {
            window.clearTimeout(refreshTimer.current);
        }

        refreshTimer.current = window.setTimeout(() => {
            router.reload({
                only: ['conversations'],
                reset: ['conversations'],
            });
        }, 300);
    }, []);

    const applyFilters = (next: Filters) => {
        const query: Record<string, string> = {};
        const search = next.search.trim();

        if (search !== '') {
            query.search = search;
        }

        if (next.filter !== 'all') {
            query.filter = next.filter;
        }

        if (selectedRef.current !== null) {
            query.conversation = String(selectedRef.current.id);
        }

        router.get(messageIndex.url({ query }), undefined, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            only: ['conversations', 'filters'],
            reset: ['conversations'],
            onStart: () => setIsUpdating(true),
            onFinish: () => setIsUpdating(false),
        });
    };

    const select = (conversation: ConversationSummary) => {
        setSelected(conversation);
        window.history.replaceState(
            window.history.state,
            '',
            urlWithConversation(conversation.id),
        );
    };

    const close = () => {
        setSelected(null);
        window.history.replaceState(
            window.history.state,
            '',
            urlWithConversation(null),
        );
    };

    const handleLostAccess = useCallback(
        (conversationId: number, assigneeName: string) => {
            if (selectedRef.current?.id === conversationId) {
                setSelected(null);
                window.history.replaceState(
                    window.history.state,
                    '',
                    urlWithConversation(null),
                );
                toast.info(
                    `${assigneeName} is handling that conversation now.`,
                );
            }

            refreshList();
        },
        [refreshList],
    );

    const handleSummaryChange = useCallback(
        (conversation: ConversationSummary) => {
            setSelected((previous) =>
                previous?.id === conversation.id ? conversation : previous,
            );
        },
        [],
    );

    // The inbox and, for the support pool, the pool.
    useEffect(() => {
        const echo = getEcho(realtime);

        if (echo === null) {
            return;
        }

        const inbox = `user.${currentUserId}`;

        echo.private(inbox)
            .listen(
                '.conversation.activity',
                (event: {
                    conversationId: number;
                    lastMessageId?: number | null;
                }) => {
                    refreshList();

                    // The message reached this browser: delivered, though not read
                    // unless the thread is open (the thread reads it itself).
                    if (
                        event.lastMessageId &&
                        selectedRef.current?.id !== event.conversationId
                    ) {
                        void markDelivered(
                            event.conversationId,
                            event.lastMessageId,
                        ).catch(() => undefined);
                    }
                },
            )
            .listen('.conversation.added', () => refreshList());

        if (!isClient) {
            echo.private('support.pool')
                .listen('.conversation.activity', () => refreshList())
                // A claimed thread leaves every other pool member's list. If it
                // is open here, the thread's own catch-up gets a 403 and closes it.
                .listen('.support.claimed', () => refreshList())
                .listen('.support.released', () => refreshList());
        }

        const stopReconnect = onReconnect(echo, refreshList);

        return () => {
            stopReconnect();
            echo.leave(inbox);

            if (!isClient) {
                echo.leave('support.pool');
            }
        };
    }, [realtime, currentUserId, isClient, refreshList]);

    useEffect(
        () => () => {
            if (refreshTimer.current !== null) {
                window.clearTimeout(refreshTimer.current);
            }
        },
        [],
    );

    const initial = conversationListSkeleton({
        isFetching: conversations === undefined,
        hasRows: false,
        mode: 'initial',
    });

    if (initial.rows) {
        return <MessageSkeleton />;
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <div className="flex h-[calc(100svh-3.5rem)] min-h-0 flex-col overflow-hidden rounded-md border border-sidebar-border bg-background md:min-h-[32rem] md:flex-row">
                <ConversationList
                    conversations={rows}
                    selectedId={selected?.id ?? null}
                    onSelect={select}
                    filters={filters}
                    onFiltersChange={applyFilters}
                    isUpdating={isUpdating}
                    canCreateGroup={canCreateGroup}
                    onNewGroup={() => setGroupOpen(true)}
                    className={cn(selected && 'hidden md:flex')}
                />

                {selected ? (
                    <Thread
                        key={selected.id}
                        conversationId={selected.id}
                        initialSummary={selected}
                        currentUserId={currentUserId}
                        currentUserName={auth.user.name}
                        isClient={isClient}
                        isTouch={isTouch}
                        realtime={realtime}
                        onBack={close}
                        onLostAccess={handleLostAccess}
                        onSummaryChange={handleSummaryChange}
                    />
                ) : (
                    <div className="hidden min-w-0 flex-1 items-center justify-center p-4 md:flex">
                        <p className="text-center text-sm font-semibold text-secondary-foreground">
                            Select a conversation to start messaging
                        </p>
                    </div>
                )}
            </div>

            {canCreateGroup ? (
                <NewGroupDialog
                    open={groupOpen}
                    onOpenChange={setGroupOpen}
                    onCreated={(conversation) => {
                        toast.success(`${conversation.name} created`);
                        select(conversation);
                        refreshList();
                    }}
                />
            ) : null}
        </div>
    );
};

export default Message;

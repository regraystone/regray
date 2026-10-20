import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, onReconnect } from '@/lib/echo';
import {
    ApiError,
    claimConversation,
    editMessage,
    fetchThread,
    markRead,
    newClientKey,
    reassignConversation,
    releaseConversation,
    removeMessage,
    sendMessage,
} from '@/lib/message-api';
import type {
    Assignment,
    ChatMessage,
    ConversationSummary,
    PendingMessage,
    Pointer,
    Realtime,
} from '@/types/message';

/** How long "{name} is typing…" stays up after the last signal. */
export const TYPING_VISIBLE_MS = 4000;

/** How often this browser tells the others it is typing, at most. */
const TYPING_THROTTLE_MS = 1500;

export type ThreadState = {
    conversation: ConversationSummary | null;
    messages: ChatMessage[];
    pending: PendingMessage[];
    hasMore: boolean;
    pointers: Pointer[];
    assignment: Assignment | null;
    loaded: boolean;
    loadingOlder: boolean;
    error: string | null;
};

const EMPTY: ThreadState = {
    conversation: null,
    messages: [],
    pending: [],
    hasMore: false,
    pointers: [],
    assignment: null,
    loaded: false,
    loadingOlder: false,
    error: null,
};

/** Add or replace messages by id, keeping the thread in id order. */
const mergeMessages = (
    current: ChatMessage[],
    incoming: ChatMessage[],
): ChatMessage[] => {
    const byId = new Map(current.map((message) => [message.id, message]));

    for (const message of incoming) {
        byId.set(message.id, message);
    }

    return [...byId.values()].sort((a, b) => a.id - b.id);
};

type Options = {
    currentUserId: number;
    realtime: Realtime | undefined;
    /** A new message from someone else arrived; the page decides whether to scroll. */
    onIncoming?: (message: ChatMessage) => void;
    /** Someone else claimed the thread and this viewer can no longer read it. */
    onLostAccess?: (assigneeName: string) => void;
    /** The list row for this conversation changed (assignment, last message). */
    onSummaryChange?: (conversation: ConversationSummary) => void;
};

/**
 * One open thread: its messages, the viewer's unsent ones, the receipts, the
 * assignment, typing, and the live updates that keep all of it current.
 *
 * Socket delivery is never treated as a guarantee. A card is announced by id
 * and fetched; a reconnect fetches everything after the last message held.
 */
export function useConversationThread(
    conversationId: number | null,
    {
        currentUserId,
        realtime,
        onIncoming,
        onLostAccess,
        onSummaryChange,
    }: Options,
) {
    const [state, setState] = useState<ThreadState>(EMPTY);
    const [typing, setTyping] = useState<
        Array<{ userId: number; name: string }>
    >([]);
    const stateRef = useRef(state);
    const typingTimers = useRef(new Map<number, number>());
    const lastWhisper = useRef(0);
    const callbacks = useRef({ onIncoming, onLostAccess, onSummaryChange });

    useEffect(() => {
        stateRef.current = state;
    }, [state]);

    useEffect(() => {
        callbacks.current = { onIncoming, onLostAccess, onSummaryChange };
    }, [onIncoming, onLostAccess, onSummaryChange]);

    const lastId = (): number => stateRef.current.messages.at(-1)?.id ?? 0;

    /** Fetch everything newer than what is held: reconnects and card announcements. */
    const catchUp = useCallback(async () => {
        if (conversationId === null) {
            return;
        }

        try {
            const payload = await fetchThread(conversationId, {
                after: lastId(),
                limit: 100,
            });

            setState((previous) => ({
                ...previous,
                conversation: payload.conversation,
                messages: mergeMessages(previous.messages, payload.messages),
                pointers: payload.pointers,
                assignment: payload.assignment,
            }));
            callbacks.current.onSummaryChange?.(payload.conversation);
        } catch (error) {
            if (error instanceof ApiError && error.status === 403) {
                callbacks.current.onLostAccess?.(
                    stateRef.current.conversation?.assignee?.name ??
                        'Someone else',
                );
            }
        }
    }, [conversationId]);

    // The first page: the latest messages.
    useEffect(() => {
        if (conversationId === null) {
            setState(EMPTY);

            return;
        }

        let cancelled = false;
        setState(EMPTY);
        setTyping([]);

        fetchThread(conversationId)
            .then((payload) => {
                if (cancelled) {
                    return;
                }

                setState({
                    ...EMPTY,
                    conversation: payload.conversation,
                    messages: payload.messages,
                    hasMore: payload.hasMore,
                    pointers: payload.pointers,
                    assignment: payload.assignment,
                    loaded: true,
                });
                callbacks.current.onSummaryChange?.(payload.conversation);
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setState({
                        ...EMPTY,
                        loaded: true,
                        error:
                            error instanceof ApiError
                                ? error.message
                                : 'This conversation could not be loaded.',
                    });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [conversationId]);

    // Live updates, only while the thread is open.
    useEffect(() => {
        const echo = getEcho(realtime);

        if (conversationId === null || echo === null) {
            return;
        }

        const channelName = `conversation.${conversationId}`;
        const channel = echo.private(channelName);
        const timers = typingTimers.current;

        channel
            .listen(
                '.message.created',
                (event: { message?: ChatMessage; needsFetch?: boolean }) => {
                    if (event.message === undefined || event.needsFetch) {
                        void catchUp();

                        return;
                    }

                    const message = event.message;

                    setState((previous) => ({
                        ...previous,
                        messages: mergeMessages(previous.messages, [message]),
                        pending: previous.pending.filter(
                            (item) => item.clientKey !== message.clientKey,
                        ),
                    }));

                    if (message.sender?.id !== currentUserId) {
                        callbacks.current.onIncoming?.(message);
                    }

                    if (message.sender !== null) {
                        clearTyping(message.sender.id);
                    }
                },
            )
            .listen('.message.updated', (event: { message: ChatMessage }) =>
                setState((previous) => ({
                    ...previous,
                    messages: mergeMessages(previous.messages, [event.message]),
                })),
            )
            .listen('.message.removed', (event: { message: ChatMessage }) =>
                setState((previous) => ({
                    ...previous,
                    messages: mergeMessages(previous.messages, [event.message]),
                })),
            )
            .listen('.pointer.moved', (event: Pointer) =>
                setState((previous) => ({
                    ...previous,
                    pointers: [
                        ...previous.pointers.filter(
                            (pointer) => pointer.userId !== event.userId,
                        ),
                        event,
                    ],
                })),
            )
            // Staff who are not the assignee lose the thread on a claim; a
            // client and the super-admin keep it. The catch-up settles which,
            // by answering or by a 403.
            .listen('.support.claimed', () => void catchUp())
            .listen('.support.released', () => void catchUp())
            .listenForWhisper(
                'typing',
                (event: { userId: number; name: string }) => {
                    if (event.userId === currentUserId) {
                        return;
                    }

                    setTyping((previous) => [
                        ...previous.filter(
                            (item) => item.userId !== event.userId,
                        ),
                        event,
                    ]);

                    window.clearTimeout(timers.get(event.userId));
                    timers.set(
                        event.userId,
                        window.setTimeout(
                            () => clearTyping(event.userId),
                            TYPING_VISIBLE_MS,
                        ),
                    );
                },
            );

        const stopReconnect = onReconnect(echo, () => void catchUp());

        function clearTyping(userId: number): void {
            window.clearTimeout(timers.get(userId));
            timers.delete(userId);
            setTyping((previous) =>
                previous.filter((item) => item.userId !== userId),
            );
        }

        return () => {
            stopReconnect();
            echo.leave(channelName);
            timers.forEach((timer) => window.clearTimeout(timer));
            timers.clear();
        };
    }, [conversationId, realtime, currentUserId, catchUp]);

    /** Older messages, from the top. Stops once the oldest is in. */
    const loadOlder = useCallback(async () => {
        const current = stateRef.current;

        if (
            conversationId === null ||
            current.loadingOlder ||
            !current.hasMore ||
            current.messages.length === 0
        ) {
            return;
        }

        setState((previous) => ({ ...previous, loadingOlder: true }));

        try {
            const payload = await fetchThread(conversationId, {
                before: current.messages[0].id,
            });

            setState((previous) => ({
                ...previous,
                messages: mergeMessages(previous.messages, payload.messages),
                hasMore: payload.hasMore,
                loadingOlder: false,
            }));
        } catch {
            setState((previous) => ({ ...previous, loadingOlder: false }));
        }
    }, [conversationId]);

    const deliver = useCallback(
        async (pending: PendingMessage, replyToId: number | null) => {
            if (conversationId === null) {
                return;
            }

            try {
                const { message } = await sendMessage(conversationId, {
                    body: pending.body,
                    replyToId,
                    clientKey: pending.clientKey,
                });

                setState((previous) => ({
                    ...previous,
                    messages: mergeMessages(previous.messages, [message]),
                    pending: previous.pending.filter(
                        (item) => item.clientKey !== pending.clientKey,
                    ),
                }));
            } catch (error) {
                setState((previous) => ({
                    ...previous,
                    pending: previous.pending.map((item) =>
                        item.clientKey === pending.clientKey
                            ? {
                                  ...item,
                                  state: 'failed',
                                  error:
                                      error instanceof ApiError
                                          ? error.message
                                          : undefined,
                              }
                            : item,
                    ),
                }));
            }
        },
        [conversationId],
    );

    /** Send optimistically: the bubble shows at once and settles when the server answers. */
    const send = useCallback(
        (body: string, replyTo: ChatMessage | null) => {
            const pending: PendingMessage = {
                clientKey: newClientKey(),
                body,
                replyTo:
                    replyTo === null
                        ? null
                        : {
                              id: replyTo.id,
                              senderName: replyTo.sender?.name ?? null,
                              snippet: replyTo.body,
                              isRemoved: replyTo.isRemoved,
                          },
                createdAt: new Date().toISOString(),
                state: 'sending',
            };

            setState((previous) => ({
                ...previous,
                pending: [...previous.pending, pending],
            }));
            void deliver(pending, replyTo?.id ?? null);
        },
        [deliver],
    );

    /** Try a failed send again, with the same key: the server will not post it twice. */
    const retry = useCallback(
        (clientKey: string) => {
            const pending = stateRef.current.pending.find(
                (item) => item.clientKey === clientKey,
            );

            if (pending === undefined) {
                return;
            }

            setState((previous) => ({
                ...previous,
                pending: previous.pending.map((item) =>
                    item.clientKey === clientKey
                        ? { ...item, state: 'sending' }
                        : item,
                ),
            }));
            void deliver(
                { ...pending, state: 'sending' },
                pending.replyTo?.id ?? null,
            );
        },
        [deliver],
    );

    const discard = useCallback((clientKey: string) => {
        setState((previous) => ({
            ...previous,
            pending: previous.pending.filter(
                (item) => item.clientKey !== clientKey,
            ),
        }));
    }, []);

    const edit = useCallback(async (messageId: number, body: string) => {
        const { message } = await editMessage(messageId, body);

        setState((previous) => ({
            ...previous,
            messages: mergeMessages(previous.messages, [message]),
        }));
    }, []);

    const remove = useCallback(async (messageId: number) => {
        const { message } = await removeMessage(messageId);

        setState((previous) => ({
            ...previous,
            messages: mergeMessages(previous.messages, [message]),
        }));
    }, []);

    /** Tell the server the viewer has read up to the latest message. */
    const read = useCallback(async () => {
        const current = stateRef.current;
        const latest = current.messages.at(-1);

        if (conversationId === null || latest === undefined) {
            return;
        }

        const mine = current.pointers.find(
            (pointer) => pointer.userId === currentUserId,
        );

        if (
            mine?.lastReadMessageId !== undefined &&
            mine.lastReadMessageId !== null &&
            mine.lastReadMessageId >= latest.id
        ) {
            return;
        }

        try {
            const { pointers } = await markRead(conversationId, latest.id);
            setState((previous) => ({ ...previous, pointers }));
        } catch {
            // A missed read receipt is corrected by the next one.
        }
    }, [conversationId, currentUserId]);

    const applyAssignment = useCallback(
        (result: {
            conversation: ConversationSummary;
            assignment: Assignment | null;
        }) => {
            setState((previous) => ({
                ...previous,
                conversation: result.conversation,
                assignment: result.assignment,
            }));
            callbacks.current.onSummaryChange?.(result.conversation);
            void catchUp();
        },
        [catchUp],
    );

    const claim = useCallback(async () => {
        if (conversationId !== null) {
            applyAssignment(await claimConversation(conversationId));
        }
    }, [conversationId, applyAssignment]);

    const release = useCallback(async () => {
        if (conversationId !== null) {
            applyAssignment(await releaseConversation(conversationId));
        }
    }, [conversationId, applyAssignment]);

    const reassign = useCallback(
        async (userId: number) => {
            if (conversationId !== null) {
                applyAssignment(
                    await reassignConversation(conversationId, userId),
                );
            }
        },
        [conversationId, applyAssignment],
    );

    /** Let the others know the viewer is typing, at most every 1.5 s. */
    const signalTyping = useCallback(
        (name: string) => {
            const echo = getEcho(realtime);
            const now = Date.now();

            if (
                conversationId === null ||
                echo === null ||
                now - lastWhisper.current < TYPING_THROTTLE_MS
            ) {
                return;
            }

            lastWhisper.current = now;
            echo.private(`conversation.${conversationId}`).whisper('typing', {
                userId: currentUserId,
                name,
            });
        },
        [conversationId, currentUserId, realtime],
    );

    return {
        state,
        typing,
        loadOlder,
        send,
        retry,
        discard,
        edit,
        remove,
        read,
        claim,
        release,
        reassign,
        signalTyping,
        catchUp,
    };
}

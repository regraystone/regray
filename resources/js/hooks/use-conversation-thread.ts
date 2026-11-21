import { useCallback, useEffect, useRef, useState } from 'react';
import { getEcho, listenTo, onReconnect } from '@/lib/echo';
import { ATTACHMENT_SNIPPET } from '@/lib/message-attachments';
import { announceUnreadChanged } from '@/lib/unread-badge';
import {
    ApiError,
    claimConversation,
    editMessage,
    fetchMessage,
    fetchThread,
    markRead,
    newClientKey,
    removeMessage,
    sendMessage,
    signalTyping as signalTypingRequest,
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
    canRequestQuote: boolean;
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
    canRequestQuote: false,
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
    /** The thread can no longer be read: who claimed it, for a Regray Team thread, else null. */
    onLostAccess?: (assigneeName: string | null) => void;
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
    const lastTypingSignal = useRef(0);
    const callbacks = useRef({ onIncoming, onLostAccess, onSummaryChange });

    useEffect(() => {
        stateRef.current = state;
    }, [state]);

    useEffect(() => {
        callbacks.current = { onIncoming, onLostAccess, onSummaryChange };
    }, [onIncoming, onLostAccess, onSummaryChange]);

    /*
     * The newest message this viewer has already told the server they read,
     * whether or not the answer came back. Without it, a viewer whose own
     * pointer the server never returns — a pool member looking at an
     * unassigned thread — asked again on every render the answer caused.
     */
    const readRequestedUpTo = useRef(0);

    /*
     * Set once the server says this viewer may no longer read the thread. Every
     * request after that would only be refused again, so none is made.
     */
    const hasLostAccess = useRef(false);

    /*
     * The messages this viewer was already told about. Two announcements in
     * quick succession each fetch what is new, and both answers can hold the
     * same message; it is told once.
     */
    const toldAbout = useRef(new Set<number>());

    const lastId = (): number => stateRef.current.messages.at(-1)?.id ?? 0;

    /** Say once that the thread was taken away, and stop asking about it. */
    const loseAccess = useCallback(() => {
        if (hasLostAccess.current) {
            return;
        }

        hasLostAccess.current = true;

        // A Regray Team thread is lost to whoever claimed it; anything else
        // (an inquiry no longer assigned, an account turned off) just is.
        const conversation = stateRef.current.conversation;

        callbacks.current.onLostAccess?.(
            conversation?.isSupport
                ? (conversation.assignee?.name ?? 'Someone else')
                : null,
        );
    }, []);

    /**
     * Fetch everything newer than what is held: every new message the channel
     * announces (it carries ids only), and reconnects. Answers with what came
     * in, so the caller can tell the reader; a 403 means the reader may no
     * longer open the thread.
     */
    const catchUp = useCallback(async (): Promise<ChatMessage[]> => {
        if (conversationId === null || hasLostAccess.current) {
            return [];
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
                canRequestQuote: payload.canRequestQuote,
            }));
            callbacks.current.onSummaryChange?.(payload.conversation);

            return payload.messages;
        } catch (error) {
            if (error instanceof ApiError && error.status === 403) {
                loseAccess();
            }

            return [];
        }
    }, [conversationId, loseAccess]);

    /**
     * Fetch one message the channel says was edited or removed, and put it in
     * place of the copy held. A 403 means the reader may no longer open the
     * thread; a message gone missing meanwhile is left as it was.
     */
    const refreshMessage = useCallback(
        async (messageId: number) => {
            if (conversationId === null || hasLostAccess.current) {
                return;
            }

            try {
                const { message } = await fetchMessage(
                    conversationId,
                    messageId,
                );

                setState((previous) => ({
                    ...previous,
                    messages: mergeMessages(previous.messages, [message]),
                }));
            } catch (error) {
                if (error instanceof ApiError && error.status === 403) {
                    loseAccess();
                }
            }
        },
        [conversationId, loseAccess],
    );

    // The first page: the latest messages.
    useEffect(() => {
        if (conversationId === null) {
            setState(EMPTY);

            return;
        }

        let cancelled = false;
        readRequestedUpTo.current = 0;
        hasLostAccess.current = false;
        toldAbout.current.clear();
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
                    canRequestQuote: payload.canRequestQuote,
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
        const timers = typingTimers.current;

        const stopListening = listenTo(echo.private(channelName), {
            /*
             * The channel announces messages by id only; their content is
             * fetched over HTTP, where the server checks this reader may still
             * open the thread, so nobody who has lost access goes on receiving
             * messages on a connection opened before.
             */
            '.message.created': () => {
                void catchUp().then((incoming) => {
                    const arrived = new Set(
                        incoming.map((message) => message.clientKey),
                    );

                    setState((previous) => ({
                        ...previous,
                        pending: previous.pending.filter(
                            (item) => !arrived.has(item.clientKey),
                        ),
                    }));

                    for (const message of incoming) {
                        if (
                            message.sender === null ||
                            toldAbout.current.has(message.id)
                        ) {
                            continue;
                        }

                        toldAbout.current.add(message.id);

                        clearTyping(message.sender.id);

                        if (message.sender.id !== currentUserId) {
                            callbacks.current.onIncoming?.(message);
                        }
                    }
                });
            },
            '.message.updated': (event: { messageId: number }) =>
                void refreshMessage(event.messageId),
            '.message.removed': (event: { messageId: number }) =>
                void refreshMessage(event.messageId),
            '.pointer.moved': (event: Pointer) =>
                setState((previous) => ({
                    ...previous,
                    pointers: [
                        ...previous.pointers.filter(
                            (pointer) => pointer.userId !== event.userId,
                        ),
                        event,
                    ],
                })),
            // Staff who are not the assignee lose the thread on a claim; a
            // client and the super-admin keep it. The catch-up settles which,
            // by answering or by a 403.
            '.support.claimed': () => void catchUp(),
            // Announced by the server under the typist's own account, and only
            // for someone who may write in the thread now (UserTyping).
            '.typing': (event: { userId: number; name: string }) => {
                if (event.userId === currentUserId) {
                    return;
                }

                setTyping((previous) => [
                    ...previous.filter((item) => item.userId !== event.userId),
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
        });

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
            stopListening();
            echo.leave(channelName);
            timers.forEach((timer) => window.clearTimeout(timer));
            timers.clear();
        };
    }, [conversationId, realtime, currentUserId, catchUp, refreshMessage]);

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
                const { message } = await sendMessage(
                    conversationId,
                    {
                        body: pending.body,
                        replyToId,
                        clientKey: pending.clientKey,
                    },
                    pending.files,
                );

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
                                  retryAt:
                                      error instanceof ApiError
                                          ? error.retryAt
                                          : null,
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
        (body: string, replyTo: ChatMessage | null, files: File[] = []) => {
            const pending: PendingMessage = {
                clientKey: newClientKey(),
                body,
                files,
                replyTo:
                    replyTo === null
                        ? null
                        : {
                              id: replyTo.id,
                              senderName: replyTo.sender?.name ?? null,
                              snippet: replyTo.body || ATTACHMENT_SNIPPET,
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

        if (
            conversationId === null ||
            latest === undefined ||
            hasLostAccess.current ||
            latest.id <= readRequestedUpTo.current
        ) {
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

        readRequestedUpTo.current = latest.id;

        try {
            const { pointers } = await markRead(conversationId, latest.id);
            setState((previous) => ({ ...previous, pointers }));
            announceUnreadChanged();
        } catch (error) {
            if (error instanceof ApiError && error.status === 403) {
                loseAccess();
            }

            // Any other miss is corrected by the next message's receipt.
        }
    }, [conversationId, currentUserId, loseAccess]);

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

    /**
     * Let the others know the viewer is typing, at most every 1.5 s. Nothing
     * comes of a refusal: the indicator is a courtesy, and a send settles
     * whether the viewer may write.
     */
    const signalTyping = useCallback(() => {
        const now = Date.now();

        if (
            conversationId === null ||
            getEcho(realtime) === null ||
            hasLostAccess.current ||
            now - lastTypingSignal.current < TYPING_THROTTLE_MS
        ) {
            return;
        }

        lastTypingSignal.current = now;
        void signalTypingRequest(conversationId).catch(() => undefined);
    }, [conversationId, realtime]);

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
        signalTyping,
        catchUp,
    };
}

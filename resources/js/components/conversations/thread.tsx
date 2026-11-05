import { ArrowDown, Upload } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type DragEvent,
} from 'react';
import { toast } from 'sonner';
import AssignmentCard from '@/components/conversations/assignment-card';
import Composer, {
    type ComposerHandle,
} from '@/components/conversations/composer';
import DateSeparator from '@/components/conversations/date-separator';
import InquiryCard from '@/components/conversations/inquiry-card';
import MessageBubble from '@/components/conversations/message-bubble';
import AddMembersDialog from '@/components/conversations/add-members-dialog';
import ReassignDialog from '@/components/conversations/reassign-dialog';
import Receipts from '@/components/conversations/receipts';
import ScheduleCard from '@/components/conversations/schedule-card';
import SystemEvent from '@/components/conversations/system-event';
import ThreadHeader from '@/components/conversations/thread-header';
import TypingIndicator from '@/components/conversations/typing-indicator';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useConversationThread } from '@/hooks/use-conversation-thread';
import { assignmentView } from '@/lib/assignment';
import { threadLoadingState } from '@/lib/conversation-skeleton';
import { isFileDrag, nextDragDepth } from '@/lib/file-drop';
import { ApiError } from '@/lib/message-api';
import { buildThreadItems } from '@/lib/message-grouping';
import { latestOwnMessageId, receiptFor } from '@/lib/receipts';
import {
    anchorAfterPrepend,
    isNearBottom,
    isNearTop,
    shouldAutoScroll,
} from '@/lib/thread-scroll';
import type {
    ChatMessage,
    ConversationSummary,
    Realtime,
} from '@/types/message';

export const SUPPORT_EMPTY_TEXT = 'Message Regray any time.';

type ThreadProps = {
    conversationId: number;
    /** The list row, drawn as the header until the thread's own arrives. */
    initialSummary: ConversationSummary | null;
    currentUserId: number;
    currentUserName: string;
    isClient: boolean;
    isTouch: boolean;
    realtime: Realtime | undefined;
    onBack: () => void;
    onLostAccess: (conversationId: number, assigneeName: string) => void;
    onSummaryChange: (conversation: ConversationSummary) => void;
};

const ThreadSkeleton = () => (
    <div
        className="flex min-h-0 flex-1 flex-col gap-2 p-4 md:p-5"
        role="status"
        aria-busy="true"
    >
        <span className="sr-only">Loading messages</span>
        <Skeleton className="h-10 w-2/5 rounded-md" />
        <Skeleton className="ml-auto h-10 w-3/5 rounded-md" />
        <Skeleton className="h-10 w-1/2 rounded-md" />
        <Skeleton className="ml-auto h-10 w-2/3 rounded-md" />
    </div>
);

/**
 * One open conversation: header, messages, and the composer.
 *
 * The latest page loads first and the view starts at the bottom. Scrolling to
 * the top loads older pages, and the view is held still while they are added
 * above: the height the content grew by is added to the scroll position. New
 * messages pull the view down only when the reader is already near the bottom
 * or sent the message themselves; otherwise a "New messages" pill counts them.
 */
const Thread = ({
    conversationId,
    initialSummary,
    currentUserId,
    currentUserName,
    isClient,
    isTouch,
    realtime,
    onBack,
    onLostAccess,
    onSummaryChange,
}: ThreadProps) => {
    const scrollRef = useRef<HTMLDivElement>(null);
    const anchorRef = useRef<{ height: number; top: number } | null>(null);
    const stickToBottomRef = useRef(true);
    const [unseen, setUnseen] = useState(0);
    const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
    const [editing, setEditing] = useState<ChatMessage | null>(null);
    const [removeTarget, setRemoveTarget] = useState<ChatMessage | null>(null);
    const [removing, setRemoving] = useState(false);
    const [claiming, setClaiming] = useState(false);
    const [reassignOpen, setReassignOpen] = useState(false);
    const [addMembersOpen, setAddMembersOpen] = useState(false);
    const [highlighted, setHighlighted] = useState<number | null>(null);
    const composerRef = useRef<ComposerHandle>(null);
    const dragDepthRef = useRef(0);
    const [dragging, setDragging] = useState(false);

    const scrollToBottom = useCallback((behavior: ScrollBehavior = 'auto') => {
        const viewport = scrollRef.current;

        if (viewport) {
            viewport.scrollTo({ top: viewport.scrollHeight, behavior });
        }

        stickToBottomRef.current = true;
        setUnseen(0);
    }, []);

    const thread = useConversationThread(conversationId, {
        currentUserId,
        realtime,
        onIncoming: () => {
            const viewport = scrollRef.current;
            const near = viewport === null || isNearBottom(viewport);

            if (shouldAutoScroll({ nearBottom: near, isOwn: false })) {
                stickToBottomRef.current = true;
            } else {
                setUnseen((count) => count + 1);
            }
        },
        onLostAccess: (name) => onLostAccess(conversationId, name),
        onSummaryChange,
    });

    const { state } = thread;
    const conversation = state.conversation ?? initialSummary;

    // Reset the per-thread interface state when another thread opens.
    useEffect(() => {
        setReplyTo(null);
        setEditing(null);
        setUnseen(0);
        stickToBottomRef.current = true;
        anchorRef.current = null;
    }, [conversationId]);

    /*
     * Take the anchor again once the spinner is on screen. It was taken at the
     * scroll, before the spinner pushed the messages down, and the prepend
     * removes the spinner in the same update that adds the older messages: an
     * anchor from before it put the first message one spinner's height away
     * from where the reader last saw it.
     */
    useLayoutEffect(() => {
        const viewport = scrollRef.current;

        if (
            state.loadingOlder &&
            viewport !== null &&
            anchorRef.current !== null
        ) {
            anchorRef.current = {
                height: viewport.scrollHeight,
                top: viewport.scrollTop,
            };
        }
    }, [state.loadingOlder]);

    // Keep the view where it should be after every change to the messages.
    useLayoutEffect(() => {
        const viewport = scrollRef.current;

        if (viewport === null || !state.loaded) {
            return;
        }

        if (anchorRef.current !== null) {
            viewport.scrollTop = anchorAfterPrepend({
                previousScrollHeight: anchorRef.current.height,
                previousScrollTop: anchorRef.current.top,
                nextScrollHeight: viewport.scrollHeight,
            });
            anchorRef.current = null;

            return;
        }

        if (stickToBottomRef.current) {
            viewport.scrollTop = viewport.scrollHeight;
        }
    }, [state.loaded, state.messages, state.pending, thread.typing]);

    // Reading: whatever is at the bottom while the reader is there counts as read.
    useEffect(() => {
        if (
            state.loaded &&
            stickToBottomRef.current &&
            document.visibilityState === 'visible'
        ) {
            void thread.read();
        }
    }, [state.loaded, state.messages, thread]);

    const handleScroll = () => {
        const viewport = scrollRef.current;

        if (viewport === null) {
            return;
        }

        const near = isNearBottom(viewport);
        stickToBottomRef.current = near;

        if (near && unseen > 0) {
            setUnseen(0);
            void thread.read();
        }

        if (
            isNearTop(viewport.scrollTop) &&
            state.hasMore &&
            !state.loadingOlder &&
            state.loaded
        ) {
            anchorRef.current = {
                height: viewport.scrollHeight,
                top: viewport.scrollTop,
            };
            void thread.loadOlder();
        }
    };

    const view = assignmentView({
        isSupport: conversation?.isSupport ?? false,
        isClient,
        assignment: state.assignment,
        assignee: conversation?.assignee ?? null,
    });

    const items = useMemo(
        () => buildThreadItems(state.messages),
        [state.messages],
    );
    const ownLatest = latestOwnMessageId(state.messages, currentUserId);
    const receipt = receiptFor({
        messageId: ownLatest,
        pointers: state.pointers,
        currentUserId,
        isDirect:
            conversation?.type === 'direct' ||
            (conversation?.isSupport ?? false),
    });

    const claim = async () => {
        setClaiming(true);

        try {
            await thread.claim();
        } catch (error) {
            toast.error(
                error instanceof ApiError
                    ? error.message
                    : 'This conversation could not be assigned.',
            );
            void thread.catchUp();
        } finally {
            setClaiming(false);
        }
    };

    const release = async () => {
        try {
            await thread.release();
            toast.success('Released to the team');
        } catch (error) {
            toast.error(
                error instanceof ApiError
                    ? error.message
                    : 'This conversation could not be released.',
            );
        }
    };

    const jumpTo = (messageId: number) => {
        const element = scrollRef.current?.querySelector(
            `[data-message-id="${messageId}"]`,
        );

        if (element) {
            element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            setHighlighted(messageId);
            window.setTimeout(
                () =>
                    setHighlighted((current) =>
                        current === messageId ? null : current,
                    ),
                1600,
            );
        } else {
            toast.info('That message is further back in the conversation.');
        }
    };

    const confirmRemove = async () => {
        if (removeTarget === null) {
            return;
        }

        setRemoving(true);

        try {
            await thread.remove(removeTarget.id);
            setRemoveTarget(null);
        } catch (error) {
            toast.error(
                error instanceof ApiError
                    ? error.message
                    : 'The message could not be removed.',
            );
        } finally {
            setRemoving(false);
        }
    };

    const loading = threadLoadingState({
        hasLoaded: state.loaded,
        isLoadingOlder: state.loadingOlder,
        hasMore: state.hasMore,
    });

    if (conversation === null) {
        return (
            <section className="flex min-w-0 flex-1 flex-col overflow-hidden">
                <ThreadSkeleton />
            </section>
        );
    }

    /*
     * Files dragged anywhere over the thread, as in Messenger: an overlay while
     * they are over it, and on drop they join the composer's chips, to be sent
     * with the next message. Only while the viewer can send here and is not
     * editing; otherwise the overlay says why, and the drop is swallowed so the
     * browser does not open the file in place of the page.
     */
    const canDrop =
        state.loaded &&
        state.error === null &&
        view.composer.mode === 'enabled' &&
        editing === null;

    const dropRefusal =
        view.composer.mode !== 'enabled'
            ? view.composer.note
            : 'Finish editing your message first.';

    const dropHandlers = {
        onDragEnter: (event: DragEvent<HTMLElement>) => {
            if (!isFileDrag([...event.dataTransfer.types])) {
                return;
            }

            event.preventDefault();
            dragDepthRef.current = nextDragDepth(dragDepthRef.current, 'enter');
            setDragging(true);
        },
        onDragOver: (event: DragEvent<HTMLElement>) => {
            if (!isFileDrag([...event.dataTransfer.types])) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = canDrop ? 'copy' : 'none';
        },
        onDragLeave: (event: DragEvent<HTMLElement>) => {
            if (!isFileDrag([...event.dataTransfer.types])) {
                return;
            }

            dragDepthRef.current = nextDragDepth(dragDepthRef.current, 'leave');

            if (dragDepthRef.current === 0) {
                setDragging(false);
            }
        },
        onDrop: (event: DragEvent<HTMLElement>) => {
            if (!isFileDrag([...event.dataTransfer.types])) {
                return;
            }

            event.preventDefault();
            dragDepthRef.current = nextDragDepth(dragDepthRef.current, 'drop');
            setDragging(false);

            if (canDrop) {
                composerRef.current?.addFiles([...event.dataTransfer.files]);
            }
        },
    };

    const showsNames = (message: ChatMessage): boolean =>
        message.sender !== null &&
        message.sender.id !== currentUserId &&
        (conversation.type === 'group' ||
            (conversation.isSupport &&
                view.showStaffNames &&
                message.sender.id !== conversation.supportClient?.id));

    return (
        <section
            aria-label={`Conversation with ${conversation.name}`}
            className="relative flex min-w-0 flex-1 flex-col overflow-hidden"
            data-testid="thread"
            {...dropHandlers}
        >
            {dragging ? (
                <div
                    className="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-background/85 p-4"
                    data-testid="drop-overlay"
                >
                    <div className="flex w-full max-w-sm flex-col items-center gap-2 rounded-md border-2 border-dashed border-primary px-6 py-10 text-center">
                        <Upload className="size-8 text-primary" aria-hidden />
                        <p className="text-base font-bold">
                            {canDrop
                                ? 'Drop files to attach'
                                : "Files can't be attached here right now"}
                        </p>
                        <p className="text-xs text-secondary-foreground">
                            {canDrop
                                ? 'Photos (JPG, PNG, WebP), PDF or Word · up to 5 files, 50 MB each'
                                : dropRefusal}
                        </p>
                    </div>
                </div>
            ) : null}

            <ThreadHeader
                conversation={conversation}
                subtitle={view.subtitle}
                canRelease={view.canRelease}
                canReassign={view.canReassign && conversation.isSupport}
                onBack={onBack}
                onRelease={() => void release()}
                onReassign={() => setReassignOpen(true)}
                onAddMembers={() => setAddMembersOpen(true)}
            />

            {view.showCard && state.error === null ? (
                <AssignmentCard
                    onClaim={() => void claim()}
                    claiming={claiming}
                />
            ) : null}

            {state.error !== null ? (
                <p className="m-auto px-4 text-center text-sm font-semibold text-secondary-foreground">
                    {state.error}
                </p>
            ) : loading === 'skeleton' ? (
                <ThreadSkeleton />
            ) : (
                <div
                    ref={scrollRef}
                    onScroll={handleScroll}
                    className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain bg-cover bg-center bg-no-repeat p-4 md:p-5"
                    style={{ backgroundImage: 'url(/images/convo_bg.jpg)' }}
                    data-testid="thread-scroll"
                >
                    {loading === 'olderSpinner' ? (
                        <div
                            className="flex justify-center py-1"
                            data-testid="older-spinner"
                        >
                            <Spinner className="motion-reduce:animate-none" />
                        </div>
                    ) : null}

                    {state.messages.length === 0 &&
                    state.pending.length === 0 ? (
                        <p
                            className="m-auto px-4 text-center text-sm font-semibold text-secondary-foreground"
                            data-testid="thread-empty"
                        >
                            {conversation.isOwnSupport
                                ? SUPPORT_EMPTY_TEXT
                                : view.composer.mode === 'enabled'
                                  ? 'No messages yet — say hello'
                                  : 'No messages yet'}
                        </p>
                    ) : null}

                    {items.map((item) => {
                        if (item.kind === 'separator') {
                            return (
                                <DateSeparator
                                    key={item.key}
                                    label={item.label}
                                />
                            );
                        }

                        const message = item.message;

                        if (message.type === 'inquiry_card') {
                            return message.inquiryCard ? (
                                <InquiryCard
                                    key={item.key}
                                    card={message.inquiryCard}
                                />
                            ) : null;
                        }

                        if (message.type === 'schedule_card') {
                            return message.scheduleCard ? (
                                <ScheduleCard
                                    key={item.key}
                                    card={message.scheduleCard}
                                    isStaff={!isClient}
                                />
                            ) : (
                                <p
                                    key={item.key}
                                    className="text-center text-xs text-secondary-foreground"
                                >
                                    A visit was updated
                                </p>
                            );
                        }

                        if (message.type === 'system') {
                            return (
                                <SystemEvent key={item.key} message={message} />
                            );
                        }

                        const isOwn = message.sender?.id === currentUserId;
                        const canChange = isOwn && !message.isRemoved;

                        return (
                            <div key={item.key}>
                                <MessageBubble
                                    message={message}
                                    isOwn={isOwn}
                                    showName={
                                        item.isRunStart && showsNames(message)
                                    }
                                    showAvatar={item.isRunEnd}
                                    isTouch={isTouch}
                                    highlighted={highlighted === message.id}
                                    onReply={
                                        view.composer.mode === 'enabled'
                                            ? () => {
                                                  setEditing(null);
                                                  setReplyTo(message);
                                              }
                                            : undefined
                                    }
                                    onEdit={
                                        canChange
                                            ? () => {
                                                  setReplyTo(null);
                                                  setEditing(message);
                                              }
                                            : undefined
                                    }
                                    onRemove={
                                        canChange
                                            ? () => setRemoveTarget(message)
                                            : undefined
                                    }
                                    onJumpTo={jumpTo}
                                />
                                {message.id === ownLatest &&
                                state.pending.length === 0 ? (
                                    <Receipts receipt={receipt} />
                                ) : null}
                            </div>
                        );
                    })}

                    {state.pending.map((pending) => (
                        <MessageBubble
                            key={pending.clientKey}
                            message={null}
                            pending={pending}
                            isOwn
                            showName={false}
                            showAvatar={false}
                            isTouch={isTouch}
                            onRetry={() => thread.retry(pending.clientKey)}
                            onDiscard={() => thread.discard(pending.clientKey)}
                            onJumpTo={jumpTo}
                        />
                    ))}

                    <TypingIndicator
                        names={thread.typing.map((person) => person.name)}
                    />
                </div>
            )}

            {unseen > 0 ? (
                <Button
                    type="button"
                    size="sm"
                    onClick={() => scrollToBottom('smooth')}
                    className="absolute bottom-24 left-1/2 z-10 -translate-x-1/2 rounded-full shadow-md"
                    data-testid="new-messages-pill"
                >
                    <ArrowDown aria-hidden />
                    {unseen} new {unseen === 1 ? 'message' : 'messages'}
                </Button>
            ) : null}

            {state.loaded && state.error === null ? (
                <Composer
                    ref={composerRef}
                    placeholder={`Message ${conversation.name}`}
                    state={view.composer}
                    isTouch={isTouch}
                    replyTo={replyTo}
                    editing={editing}
                    onSend={(body, files) => {
                        stickToBottomRef.current = true;
                        thread.send(body, replyTo, files);
                        setReplyTo(null);
                    }}
                    onSaveEdit={async (body) => {
                        if (editing === null) {
                            return;
                        }

                        try {
                            await thread.edit(editing.id, body);
                            setEditing(null);
                        } catch (error) {
                            toast.error(
                                error instanceof ApiError
                                    ? error.message
                                    : 'The message could not be edited.',
                            );
                            throw error;
                        }
                    }}
                    onCancelReply={() => setReplyTo(null)}
                    onCancelEdit={() => setEditing(null)}
                    onTyping={() => thread.signalTyping(currentUserName)}
                />
            ) : null}

            <Dialog
                open={removeTarget !== null}
                onOpenChange={(open) =>
                    !open && !removing ? setRemoveTarget(null) : undefined
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            Remove this message?
                        </DialogTitle>
                        <DialogDescription>
                            Everyone in the conversation will see “This message
                            was removed” instead.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRemoveTarget(null)}
                            disabled={removing}
                        >
                            Keep message
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void confirmRemove()}
                            disabled={removing}
                        >
                            {removing ? (
                                <Spinner className="motion-reduce:animate-none" />
                            ) : null}
                            Remove
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {view.canReassign ? (
                <ReassignDialog
                    open={reassignOpen}
                    onOpenChange={setReassignOpen}
                    onReassign={async (userId) => {
                        try {
                            await thread.reassign(userId);
                            setReassignOpen(false);
                            toast.success('Conversation reassigned');
                        } catch (error) {
                            toast.error(
                                error instanceof ApiError
                                    ? error.message
                                    : 'The conversation could not be reassigned.',
                            );
                        }
                    }}
                />
            ) : null}

            {conversation.canAddMembers ? (
                <AddMembersDialog
                    open={addMembersOpen}
                    conversationId={conversation.id}
                    onOpenChange={setAddMembersOpen}
                    onAdded={(summary) => {
                        onSummaryChange(summary);
                        toast.success('Added to the group');
                    }}
                />
            ) : null}
        </section>
    );
};

export default Thread;

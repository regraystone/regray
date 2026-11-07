import {
    useEffect,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {
    MessageActionSheet,
    MessageToolbar,
} from '@/components/conversations/message-actions';
import {
    MessageAttachments,
    PendingAttachments,
} from '@/components/conversations/message-attachments';
import Countdown from '@/components/countdown';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import {
    LONG_PRESS_DELAY_MS,
    LONG_PRESS_IDLE,
    longPress,
    type LongPressEvent,
    type LongPressState,
} from '@/lib/long-press';
import { ATTACHMENT_SNIPPET } from '@/lib/message-attachments';
import { retryLabel } from '@/lib/rate-limit';
import { cn } from '@/lib/utils';
import type { ChatMessage, PendingMessage } from '@/types/message';

export const REMOVED_TEXT = 'This message was removed';

type BubbleProps = {
    /** A confirmed message, or one of the viewer's that is still on its way. */
    message: ChatMessage | null;
    pending?: PendingMessage;
    isOwn: boolean;
    /** The sender's name above the first bubble of a run. */
    showName: boolean;
    /** The sender's avatar beside the last bubble of a run. */
    showAvatar: boolean;
    isTouch: boolean;
    highlighted?: boolean;
    onReply?: () => void;
    onEdit?: () => void;
    onRemove?: () => void;
    onRetry?: () => void;
    onDiscard?: () => void;
    onJumpTo?: (messageId: number) => void;
};

/**
 * One text message: the bubble, its quoted reply, its files, "Edited", and the actions —
 * a hover toolbar on desktop and press-and-hold on touch.
 *
 * The press is ignored if the finger moves more than 10 px or the page
 * scrolls, and while it is held the bubble suppresses the browser's own
 * context menu and text selection, which would otherwise steal the gesture.
 */
const MessageBubble = ({
    message,
    pending,
    isOwn,
    showName,
    showAvatar,
    isTouch,
    highlighted = false,
    onReply,
    onEdit,
    onRemove,
    onRetry,
    onDiscard,
    onJumpTo,
}: BubbleProps) => {
    const getInitials = useInitials();
    const [sheetOpen, setSheetOpen] = useState(false);
    const [holding, setHolding] = useState(false);
    const pressRef = useRef<LongPressState>(LONG_PRESS_IDLE);
    const timerRef = useRef<number | null>(null);

    const removed = message?.isRemoved ?? false;
    const body = pending?.body ?? message?.body ?? '';
    const replyTo = pending?.replyTo ?? message?.replyTo ?? null;
    const attachments = message?.attachments ?? [];
    const pendingFiles = pending?.files ?? [];
    const hasFiles = attachments.length > 0 || pendingFiles.length > 0;
    // Photos sit almost edge to edge, in a thin frame of the bubble's colour;
    // the words and the quote keep their usual inset.
    const hasPhotos =
        !removed && attachments.some((attachment) => attachment.isImage);
    const createdAt =
        pending?.createdAt ?? message?.createdAt ?? new Date().toISOString();
    const canAct = message !== null && !removed && onReply !== undefined;

    const step = (event: LongPressEvent) => {
        const result = longPress(pressRef.current, event);
        pressRef.current = result.state;
        setHolding(result.state.phase === 'pressing');

        if (result.state.phase !== 'pressing' && timerRef.current !== null) {
            window.clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        if (result.open) {
            setSheetOpen(true);
        }
    };

    useEffect(() => {
        if (!holding) {
            return;
        }

        const cancel = () => step({ type: 'scroll' });
        window.addEventListener('scroll', cancel, true);

        return () => window.removeEventListener('scroll', cancel, true);
    }, [holding]);

    useEffect(
        () => () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
            }
        },
        [],
    );

    const touchHandlers =
        isTouch && canAct
            ? {
                  onPointerDown: (event: ReactPointerEvent) => {
                      if (event.pointerType === 'mouse') {
                          return;
                      }

                      step({
                          type: 'down',
                          x: event.clientX,
                          y: event.clientY,
                      });
                      timerRef.current = window.setTimeout(
                          () => step({ type: 'timer' }),
                          LONG_PRESS_DELAY_MS,
                      );
                  },
                  onPointerMove: (event: ReactPointerEvent) =>
                      step({
                          type: 'move',
                          x: event.clientX,
                          y: event.clientY,
                      }),
                  onPointerUp: () => step({ type: 'up' }),
                  onPointerCancel: () => step({ type: 'scroll' }),
                  onContextMenu: (event: React.MouseEvent) =>
                      event.preventDefault(),
              }
            : {};

    return (
        <div
            className={cn(
                'grid grid-cols-[2rem_1fr] items-end gap-x-2',
                isOwn && 'grid-cols-[1fr]',
            )}
            data-message-id={message?.id}
            data-client-key={pending?.clientKey}
        >
            {isOwn ? null : (
                <div className="size-8 shrink-0">
                    {showAvatar && message?.sender ? (
                        <Avatar className="size-8 border border-border">
                            <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">
                                {getInitials(message.sender.name)}
                            </AvatarFallback>
                        </Avatar>
                    ) : null}
                </div>
            )}

            <div
                className={cn(
                    'flex min-w-0 flex-col',
                    isOwn ? 'items-end' : 'items-start',
                )}
            >
                {showName && message?.sender ? (
                    <span
                        className="mb-1 text-xs font-bold text-secondary-foreground"
                        data-testid="sender-name"
                    >
                        {message.sender.name}
                    </span>
                ) : null}

                <div
                    className={cn(
                        'group/bubble relative max-w-[85%] md:max-w-[65%]',
                        holding && 'select-none [-webkit-touch-callout:none]',
                    )}
                    tabIndex={canAct && !isTouch ? 0 : undefined}
                    {...touchHandlers}
                    data-testid="message-bubble"
                >
                    {canAct && !isTouch ? (
                        <MessageToolbar
                            createdAt={createdAt}
                            isOwn={isOwn}
                            onReply={onReply}
                            onEdit={onEdit}
                            onRemove={onRemove}
                        />
                    ) : null}

                    <div
                        className={cn(
                            'rounded-md text-sm break-words whitespace-pre-wrap transition-shadow',
                            hasPhotos ? 'p-1' : 'px-3 py-2',
                            removed
                                ? 'border border-dashed border-sidebar-border bg-transparent text-secondary-foreground italic'
                                : isOwn
                                  ? 'border border-sidebar-border bg-background text-foreground'
                                  : 'bg-secondary text-foreground',
                            pending?.state === 'sending' && 'opacity-70',
                            highlighted && 'ring-2 ring-ring',
                        )}
                    >
                        {replyTo !== null && !removed ? (
                            <button
                                type="button"
                                onClick={() => onJumpTo?.(replyTo.id)}
                                className={cn(
                                    'mb-1.5 block w-full cursor-pointer border-l-2 border-secondary-foreground/60 pl-2 text-left text-xs opacity-80',
                                    hasPhotos &&
                                        'mx-2 mt-1 w-[calc(100%-1rem)]',
                                )}
                                data-testid="reply-quote"
                            >
                                <span className="block font-bold">
                                    {replyTo.senderName ?? 'Message'}
                                </span>
                                <span className="line-clamp-2 block">
                                    {replyTo.isRemoved
                                        ? REMOVED_TEXT
                                        : replyTo.snippet}
                                </span>
                            </button>
                        ) : null}

                        {!removed && attachments.length > 0 ? (
                            <MessageAttachments
                                attachments={attachments}
                                isOwn={isOwn}
                            />
                        ) : null}
                        {!removed && pendingFiles.length > 0 ? (
                            <PendingAttachments files={pendingFiles} />
                        ) : null}
                        {removed ? (
                            REMOVED_TEXT
                        ) : body === '' ? null : (
                            <span
                                className={cn(
                                    'block',
                                    hasFiles && 'mt-1.5',
                                    hasPhotos && 'mt-1 px-2 pb-1',
                                )}
                            >
                                {body}
                            </span>
                        )}
                    </div>

                    {message?.isEdited && !removed ? (
                        <span
                            className="mt-0.5 block text-[0.7rem] text-secondary-foreground"
                            data-testid="edited-label"
                        >
                            Edited
                        </span>
                    ) : null}
                </div>

                {pending ? (
                    <div
                        className="mt-1 flex items-center gap-2 text-[0.7rem] text-secondary-foreground"
                        data-testid="send-state"
                    >
                        {pending.state === 'sending' ? (
                            <span>Sending…</span>
                        ) : (
                            <>
                                <span className="text-destructive">
                                    {pending.error ?? 'Failed'}
                                </span>
                                {/* Held until a 429's wait is over. */}
                                <Countdown
                                    key={pending.retryAt ?? 'now'}
                                    until={pending.retryAt ?? null}
                                >
                                    {(retryIn) => (
                                        <Button
                                            type="button"
                                            variant="link"
                                            size="sm"
                                            className="h-auto p-0 text-[0.7rem]"
                                            onClick={onRetry}
                                            disabled={retryIn > 0}
                                        >
                                            {retryLabel(retryIn, 'Retry')}
                                        </Button>
                                    )}
                                </Countdown>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    className="h-auto p-0 text-[0.7rem]"
                                    onClick={onDiscard}
                                >
                                    Discard
                                </Button>
                            </>
                        )}
                    </div>
                ) : null}
            </div>

            {canAct && isTouch ? (
                <MessageActionSheet
                    open={sheetOpen}
                    onOpenChange={setSheetOpen}
                    createdAt={createdAt}
                    preview={body || ATTACHMENT_SNIPPET}
                    onReply={onReply}
                    onEdit={onEdit}
                    onRemove={onRemove}
                />
            ) : null}
        </div>
    );
};

export default MessageBubble;

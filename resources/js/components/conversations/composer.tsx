import { Paperclip, Pencil, Reply, Send, UserCheck, X } from 'lucide-react';
import {
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
    type KeyboardEvent,
} from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ComposerState } from '@/lib/assignment';
import type { ChatMessage } from '@/types/message';

/** About five lines of text before the box starts to scroll. */
const MAX_HEIGHT_PX = 128;

type ComposerProps = {
    placeholder: string;
    state: ComposerState;
    /** Touch devices: Enter adds a line and only the button sends. */
    isTouch: boolean;
    replyTo: ChatMessage | null;
    editing: ChatMessage | null;
    claiming: boolean;
    onSend: (body: string) => void;
    onSaveEdit: (body: string) => Promise<void>;
    onCancelReply: () => void;
    onCancelEdit: () => void;
    onClaim: () => void;
    onTyping: () => void;
};

/**
 * The box at the foot of a thread.
 *
 * Desktop: Enter sends and Shift+Enter adds a line. Touch: Enter adds a line
 * and the send button sends. The box grows with what is written, up to about
 * five lines.
 *
 * Above the box sits a bar for whatever the next send is: a reply (with the
 * quoted message and a close button) or an edit ("Editing", where Enter saves
 * and Escape cancels).
 *
 * On a Regray Team thread nobody has claimed, a pool member cannot send: the
 * box is replaced by the note and the same "Assign to me" button the card has.
 */
const Composer = ({
    placeholder,
    state,
    isTouch,
    replyTo,
    editing,
    claiming,
    onSend,
    onSaveEdit,
    onCancelReply,
    onCancelEdit,
    onClaim,
    onTyping,
}: ComposerProps) => {
    const [draft, setDraft] = useState('');
    const [saving, setSaving] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    // Entering edit mode loads the message; leaving it clears the box.
    useEffect(() => {
        setDraft(editing?.body ?? '');

        if (editing !== null) {
            textareaRef.current?.focus();
        }
    }, [editing]);

    useEffect(() => {
        if (replyTo !== null) {
            textareaRef.current?.focus();
        }
    }, [replyTo]);

    useLayoutEffect(() => {
        const textarea = textareaRef.current;

        if (textarea === null) {
            return;
        }

        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, MAX_HEIGHT_PX)}px`;
    }, [draft]);

    if (state.mode !== 'enabled') {
        return (
            <div
                className="flex shrink-0 flex-col items-center gap-2 border-t border-sidebar-border p-3 text-center sm:flex-row sm:justify-between sm:text-left"
                data-testid="composer-locked"
            >
                <p className="text-sm text-secondary-foreground">
                    {state.note}
                </p>
                {state.mode === 'claim' ? (
                    <Button
                        type="button"
                        size="sm"
                        onClick={onClaim}
                        disabled={claiming}
                    >
                        {claiming ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : (
                            <UserCheck aria-hidden />
                        )}
                        Assign to me
                    </Button>
                ) : null}
            </div>
        );
    }

    const submit = async () => {
        const body = draft.trim();

        if (body === '' || saving) {
            return;
        }

        if (editing !== null) {
            setSaving(true);

            try {
                await onSaveEdit(body);
                setDraft('');
            } finally {
                setSaving(false);
            }

            return;
        }

        onSend(body);
        setDraft('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Escape' && editing !== null) {
            event.preventDefault();
            onCancelEdit();

            return;
        }

        if (
            event.key === 'Enter' &&
            !event.shiftKey &&
            !isTouch &&
            !event.nativeEvent.isComposing
        ) {
            event.preventDefault();
            void submit();
        }
    };

    return (
        <div className="shrink-0 border-t border-sidebar-border">
            {replyTo !== null || editing !== null ? (
                <div
                    className="flex items-center gap-2 border-b border-sidebar-border bg-secondary px-3 py-2 text-xs"
                    data-testid={editing ? 'editing-bar' : 'reply-bar'}
                >
                    {editing ? (
                        <Pencil className="size-3.5 shrink-0" aria-hidden />
                    ) : (
                        <Reply className="size-3.5 shrink-0" aria-hidden />
                    )}
                    <span className="min-w-0 flex-1 truncate">
                        {editing ? (
                            <span className="font-bold">Editing</span>
                        ) : (
                            <>
                                Replying to{' '}
                                <span className="font-bold">
                                    {replyTo?.sender?.name ?? 'message'}
                                </span>
                                {': '}
                                {replyTo?.body}
                            </>
                        )}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 rounded-md"
                        onClick={editing ? onCancelEdit : onCancelReply}
                        aria-label={editing ? 'Cancel editing' : 'Cancel reply'}
                    >
                        <X className="size-3.5" aria-hidden />
                    </Button>
                </div>
            ) : null}

            <div className="flex items-end gap-2 p-3">
                {/* TODO(attachments): `message_attachments` exists; uploads are out of scope for now. */}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <span
                            tabIndex={0}
                            className="shrink-0 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                disabled
                                aria-label="Attach a file"
                                className="size-11 rounded-md"
                            >
                                <Paperclip className="size-4" aria-hidden />
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>Attachments are coming soon</TooltipContent>
                </Tooltip>

                <textarea
                    ref={textareaRef}
                    value={draft}
                    rows={1}
                    onChange={(event) => {
                        setDraft(event.target.value);
                        onTyping();
                    }}
                    onKeyDown={handleKeyDown}
                    aria-label={placeholder}
                    placeholder={placeholder}
                    enterKeyHint={isTouch ? 'enter' : 'send'}
                    className="min-h-11 flex-1 resize-none rounded-none border border-input bg-transparent px-3 py-2.5 text-base leading-6 outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                    data-testid="composer-input"
                />

                <Button
                    type="button"
                    size="icon"
                    onClick={() => void submit()}
                    disabled={draft.trim() === '' || saving}
                    aria-label={editing ? 'Save edit' : 'Send message'}
                    className="size-11 shrink-0 rounded-md"
                >
                    {saving ? (
                        <Spinner className="motion-reduce:animate-none" />
                    ) : (
                        <Send className="size-4" aria-hidden />
                    )}
                </Button>
            </div>
        </div>
    );
};

export default Composer;

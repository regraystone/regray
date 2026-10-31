import {
    FileText,
    Image as ImageIcon,
    Pencil,
    Plus,
    Reply,
    Send,
    UserCheck,
    X,
} from 'lucide-react';
import {
    useEffect,
    useImperativeHandle,
    useLayoutEffect,
    useRef,
    useState,
    type ChangeEvent,
    type KeyboardEvent,
    type ReactNode,
    type Ref,
} from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ComposerState } from '@/lib/assignment';
import {
    ATTACHMENT_ACCEPT,
    ATTACHMENT_SNIPPET,
    addAttachments,
    formatFileSize,
} from '@/lib/message-attachments';
import type { ChatMessage } from '@/types/message';

/** About five lines of text before the box starts to scroll. */
const MAX_HEIGHT_PX = 128;

/** What the thread can ask of the composer: files dropped on the conversation. */
export type ComposerHandle = {
    addFiles: (files: File[]) => void;
};

type ComposerProps = {
    ref?: Ref<ComposerHandle>;
    placeholder: string;
    state: ComposerState;
    /** Touch devices: Enter adds a line and only the button sends. */
    isTouch: boolean;
    replyTo: ChatMessage | null;
    editing: ChatMessage | null;
    claiming: boolean;
    onSend: (body: string, files: File[]) => void;
    onSaveEdit: (body: string) => Promise<void>;
    onCancelReply: () => void;
    onCancelEdit: () => void;
    onClaim: () => void;
    onTyping: () => void;
};

/** The solid plus at the start of the field that opens the file picker. */
const AttachButton = ({
    onClick,
    disabled,
}: {
    onClick: () => void;
    disabled: boolean;
}) => (
    <Button
        type="button"
        size="icon"
        onClick={onClick}
        disabled={disabled}
        aria-label="Attach a file"
        className="size-9 shrink-0 cursor-pointer rounded-md p-0"
    >
        <Plus className="size-5" aria-hidden />
    </Button>
);

/** Send, or Save while editing. */
const SendButton = ({
    onClick,
    disabled,
    editing,
    saving,
}: {
    onClick: () => void;
    disabled: boolean;
    editing: boolean;
    saving: boolean;
}) => (
    <Button
        type="button"
        size="icon"
        onClick={onClick}
        disabled={disabled}
        aria-label={editing ? 'Save edit' : 'Send message'}
        className="size-9 shrink-0 rounded-md p-0"
    >
        {saving ? (
            <Spinner className="motion-reduce:animate-none" />
        ) : (
            <Send className="size-4" aria-hidden />
        )}
    </Button>
);

/*
 * Where send sits on touch: it grows in from nothing when the box is in use
 * and folds away again, so the box widens back rather than jumping. The fade
 * and the scale sit on this wrapper rather than the button, whose own disabled
 * opacity would otherwise win and leave a hidden send showing through.
 */
const SendSlot = ({
    shown,
    children,
}: {
    shown: boolean;
    children: ReactNode;
}) => (
    <span
        inert={!shown}
        className={`grid shrink-0 place-items-center overflow-hidden transition-[width,opacity,transform] duration-200 ease-out motion-reduce:transition-none ${
            shown
                ? 'w-9 scale-100 opacity-100'
                : 'pointer-events-none w-0 scale-50 opacity-0'
        }`}
    >
        {children}
    </span>
);

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
 * The box and its buttons share one field. The plus at its start adds
 * photos, PDFs and Word documents (see lib/message-attachments for the
 * limits), and so does dropping files anywhere on the thread (`addFiles`).
 * They show as chips above the box, and a message may be files alone. An edit
 * changes only the words, so the plus rests while editing.
 *
 * Desktop always shows send at the end of the field, disabled until there is
 * something to send. On touch the end of the field is empty while the box is
 * idle, and send grows in once it is in use (focused, holding words or files,
 * or editing). In use does not hang on focus alone, so send is still there
 * when tapping it takes the focus away from the box.
 *
 * On a Regray Team thread nobody has claimed, a pool member cannot send: the
 * box is replaced by the note and the same "Assign to me" button the card has.
 */
const Composer = ({
    ref,
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
    const [files, setFiles] = useState<File[]>([]);
    const [fileError, setFileError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [focused, setFocused] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

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

        // scrollHeight leaves out the border, which the box's height includes;
        // without it the text overflows by a pixel or two and a scrollbar shows.
        const border = textarea.offsetHeight - textarea.clientHeight;
        textarea.style.height = 'auto';
        const needed = textarea.scrollHeight + border;
        textarea.style.height = `${Math.min(needed, MAX_HEIGHT_PX)}px`;
        textarea.style.overflowY = needed > MAX_HEIGHT_PX ? 'auto' : 'hidden';
    }, [draft]);

    /** Stage files to send, with the same checks as the paperclip. */
    const attachFiles = (picked: File[]) => {
        const result = addAttachments(files, picked);
        setFiles(result.files);
        setFileError(result.error);
        textareaRef.current?.focus();
    };

    useImperativeHandle(ref, () => ({ addFiles: attachFiles }));

    if (state.mode !== 'enabled') {
        return (
            <div
                className="flex shrink-0 flex-col items-center gap-2 border-t border-sidebar-border p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] text-center sm:flex-row sm:justify-between sm:text-left"
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

    const canSubmit =
        editing !== null
            ? draft.trim() !== '' && !saving
            : draft.trim() !== '' || files.length > 0;

    const inUse = focused || canSubmit || editing !== null || saving;

    const submit = async () => {
        const body = draft.trim();

        if (!canSubmit) {
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

        onSend(body, files);
        setDraft('');
        setFiles([]);
        setFileError(null);
    };

    const pickFiles = (event: ChangeEvent<HTMLInputElement>) => {
        const picked = [...(event.target.files ?? [])];
        // Cleared so picking the same file again still fires a change.
        event.target.value = '';
        attachFiles(picked);
    };

    const unpickFile = (index: number) => {
        setFiles((current) => current.filter((_, at) => at !== index));
        setFileError(null);
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
                                {replyTo?.body || ATTACHMENT_SNIPPET}
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

            {editing === null && (files.length > 0 || fileError !== null) ? (
                <div
                    className="flex flex-wrap items-center gap-2 border-b border-sidebar-border px-3 py-2"
                    data-testid="composer-files"
                >
                    {files.map((file, index) => (
                        <span
                            key={`${file.name}-${file.size}-${index}`}
                            className="flex max-w-full min-w-0 items-center gap-1.5 rounded-md bg-secondary py-1 pr-1 pl-2 text-xs"
                            data-testid="composer-file"
                        >
                            {file.type.startsWith('image/') ? (
                                <ImageIcon
                                    className="size-3.5 shrink-0"
                                    aria-hidden
                                />
                            ) : (
                                <FileText
                                    className="size-3.5 shrink-0"
                                    aria-hidden
                                />
                            )}
                            <span className="min-w-0 truncate">
                                {file.name}
                            </span>
                            <span className="shrink-0 text-secondary-foreground">
                                {formatFileSize(file.size)}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-6 shrink-0 rounded-md"
                                onClick={() => unpickFile(index)}
                                aria-label={`Remove ${file.name}`}
                            >
                                <X className="size-3" aria-hidden />
                            </Button>
                        </span>
                    ))}
                    {fileError !== null ? (
                        <p
                            role="alert"
                            className="w-full text-xs text-destructive"
                            data-testid="composer-file-error"
                        >
                            {fileError}
                        </p>
                    ) : null}
                </div>
            ) : null}

            <div className="p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    accept={ATTACHMENT_ACCEPT}
                    onChange={pickFiles}
                    className="hidden"
                    tabIndex={-1}
                    aria-hidden
                    data-testid="composer-file-input"
                />

                <div className="flex items-end gap-2 rounded-md border border-input bg-background p-1 transition-[border-color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                    <AttachButton
                        onClick={() => fileInputRef.current?.click()}
                        disabled={editing !== null}
                    />

                    <textarea
                        ref={textareaRef}
                        value={draft}
                        rows={1}
                        onChange={(event) => {
                            setDraft(event.target.value);
                            onTyping();
                        }}
                        onKeyDown={handleKeyDown}
                        onFocus={() => setFocused(true)}
                        onBlur={() => setFocused(false)}
                        aria-label={placeholder}
                        placeholder={placeholder}
                        enterKeyHint={isTouch ? 'enter' : 'send'}
                        className="min-h-9 min-w-0 flex-1 resize-none bg-transparent py-1.5 text-base leading-6 outline-none placeholder:text-muted-foreground md:text-sm"
                        data-testid="composer-input"
                    />

                    {isTouch ? (
                        <SendSlot shown={inUse}>
                            <SendButton
                                onClick={() => void submit()}
                                disabled={!canSubmit}
                                editing={editing !== null}
                                saving={saving}
                            />
                        </SendSlot>
                    ) : (
                        <SendButton
                            onClick={() => void submit()}
                            disabled={!canSubmit}
                            editing={editing !== null}
                            saving={saving}
                        />
                    )}
                </div>
            </div>
        </div>
    );
};

export default Composer;

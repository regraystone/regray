import {
    Camera,
    Image as ImageIcon,
    MessageCircleQuestion,
    Paperclip,
    Pencil,
    Reply,
    Send,
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
import CommonQuestions, {
    COMMON_QUESTIONS_LIST_ID,
} from '@/components/conversations/common-questions';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ComposerState } from '@/lib/assignment';
import {
    ATTACHMENT_ACCEPT,
    ATTACHMENT_SNIPPET,
    addAttachments,
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
    /** Questions to send with one press, above the box. Clients only. */
    commonQuestions?: readonly string[];
    commonQuestionsOpen?: boolean;
    onCommonQuestionsOpenChange?: (open: boolean) => void;
    onSend: (body: string, files: File[]) => void;
    onSaveEdit: (body: string) => Promise<void>;
    onCancelReply: () => void;
    onCancelEdit: () => void;
    onTyping: () => void;
};

/** Camera button on the left inside a container slot (boxed like send). */
const CameraButton = ({
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
        aria-label="Take a photo"
        className="size-10 shrink-0 cursor-pointer rounded-md p-0"
    >
        <Camera className="size-5" aria-hidden />
    </Button>
);

/** Photo upload button on the right. */
const PhotoButton = ({
    onClick,
    disabled,
}: {
    onClick: () => void;
    disabled: boolean;
}) => (
    <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onClick}
        disabled={disabled}
        aria-label="Attach photos"
        className="size-10 shrink-0 rounded-md p-0 text-secondary-foreground hover:bg-secondary hover:text-foreground"
    >
        <ImageIcon className="size-5" aria-hidden />
    </Button>
);

/** File upload button on the right. */
const AttachButton = ({
    onClick,
    disabled,
}: {
    onClick: () => void;
    disabled: boolean;
}) => (
    <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onClick}
        disabled={disabled}
        aria-label="Attach a file"
        className="size-10 shrink-0 cursor-pointer rounded-md p-0 text-secondary-foreground hover:bg-secondary hover:text-foreground"
    >
        <Paperclip className="size-5" aria-hidden />
    </Button>
);

/** Opens the common questions, above the box. */
const CommonQuestionsButton = ({
    isOpen,
    onClick,
    disabled,
}: {
    isOpen: boolean;
    onClick: () => void;
    disabled: boolean;
}) => (
    <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={onClick}
        disabled={disabled}
        aria-label="Frequently Asked Questions"
        aria-expanded={isOpen}
        aria-controls={COMMON_QUESTIONS_LIST_ID}
        className="size-10 shrink-0 cursor-pointer rounded-md p-0 text-secondary-foreground hover:bg-secondary hover:text-foreground"
        data-testid="common-questions-toggle"
    >
        <MessageCircleQuestion className="size-5" aria-hidden />
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
        className="size-10 shrink-0 rounded-md p-0"
    >
        {saving ? (
            <Spinner className="motion-reduce:animate-none" />
        ) : (
            <Send className="size-5" aria-hidden />
        )}
    </Button>
);

/** Camera slot container on the left. */
const CameraSlot = ({
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
                ? 'w-10 scale-100 opacity-100'
                : 'pointer-events-none w-0 scale-50 opacity-0'
        }`}
    >
        {children}
    </span>
);

/** Right attachment icons slot (photos, files) that shows when idle and hides when active. */
const RightIconsSlot = ({
    visible,
    children,
}: {
    visible: boolean;
    children: ReactNode;
}) => (
    <span
        inert={!visible}
        className={`flex shrink-0 items-center overflow-hidden transition-[width,opacity,transform] duration-200 ease-out motion-reduce:transition-none ${
            visible
                ? 'w-auto scale-100 opacity-100'
                : 'pointer-events-none w-0 scale-50 opacity-0'
        }`}
    >
        {children}
    </span>
);

/** Send slot container on the right. */
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
                ? 'w-10 scale-100 opacity-100'
                : 'pointer-events-none w-0 scale-50 opacity-0'
        }`}
    >
        {children}
    </span>
);

/**
 * The box at the foot of a thread. When the viewer may not write here, the same
 * box shows with everything disabled and the reason as its placeholder, so
 * nothing can be typed, uploaded or sent.
 */
const Composer = ({
    ref,
    placeholder,
    state,
    isTouch,
    replyTo,
    editing,
    commonQuestions,
    commonQuestionsOpen = false,
    onCommonQuestionsOpenChange,
    onSend,
    onSaveEdit,
    onCancelReply,
    onCancelEdit,
    onTyping,
}: ComposerProps) => {
    const [draft, setDraft] = useState('');
    const [fileError, setFileError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [focused, setFocused] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const cameraInputRef = useRef<HTMLInputElement>(null);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const isLocked = state.mode !== 'enabled';

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

        const border = textarea.offsetHeight - textarea.clientHeight;
        textarea.style.height = 'auto';
        const needed = textarea.scrollHeight + border;
        textarea.style.height = `${Math.min(needed, MAX_HEIGHT_PX)}px`;
        textarea.style.overflowY = needed > MAX_HEIGHT_PX ? 'auto' : 'hidden';
    }, [draft]);

    /** Immediately send picked files (camera, photos, files). */
    const handleImmediateUpload = (picked: File[]) => {
        if (isLocked || picked.length === 0) {
            return;
        }
        const result = addAttachments([], picked);
        if (result.error !== null) {
            setFileError(result.error);
            textareaRef.current?.focus();
            return;
        }
        if (result.files.length > 0) {
            onSend('', result.files);
            setFileError(null);
            textareaRef.current?.focus();
        }
    };

    useImperativeHandle(ref, () => ({
        addFiles: (files: File[]) => handleImmediateUpload(files),
    }));

    const canSubmit =
        !isLocked &&
        (editing !== null
            ? draft.trim() !== '' && !saving
            : draft.trim() !== '');

    const inUse = focused || draft.trim() !== '' || editing !== null || saving;

    const showsCommon =
        commonQuestions !== undefined &&
        commonQuestionsOpen &&
        !isLocked &&
        editing === null;

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

        onSend(body, []);
        setDraft('');
    };

    const pickCamera = (event: ChangeEvent<HTMLInputElement>) => {
        const picked = [...(event.target.files ?? [])];
        event.target.value = '';
        handleImmediateUpload(picked);
    };

    const pickPhotos = (event: ChangeEvent<HTMLInputElement>) => {
        const picked = [...(event.target.files ?? [])];
        event.target.value = '';
        handleImmediateUpload(picked);
    };

    const pickFiles = (event: ChangeEvent<HTMLInputElement>) => {
        const picked = [...(event.target.files ?? [])];
        event.target.value = '';
        handleImmediateUpload(picked);
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
        <div
            className="shrink-0 border-t border-sidebar-border"
            data-testid={isLocked ? 'composer-locked' : undefined}
        >
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

            {fileError !== null ? (
                <div
                    className="flex items-center border-b border-sidebar-border px-3 py-2"
                    data-testid="composer-files"
                >
                    <p
                        role="alert"
                        className="w-full text-xs text-destructive"
                        data-testid="composer-file-error"
                    >
                        {fileError}
                    </p>
                </div>
            ) : null}

            {commonQuestions === undefined ? null : (
                <CommonQuestions
                    questions={commonQuestions}
                    shown={showsCommon}
                    onSend={(question) => onSend(question, [])}
                    onHide={() => onCommonQuestionsOpenChange?.(false)}
                />
            )}

            <div className="flex items-end gap-1.5 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <input
                    ref={cameraInputRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    onChange={pickCamera}
                    className="hidden"
                    tabIndex={-1}
                    aria-hidden
                    data-testid="composer-camera-input"
                />

                <input
                    ref={photoInputRef}
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    onChange={pickPhotos}
                    className="hidden"
                    tabIndex={-1}
                    aria-hidden
                    data-testid="composer-photo-input"
                />

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

                <div className="shrink-0 pb-0.5">
                    <CameraSlot shown={!inUse && editing === null}>
                        <CameraButton
                            onClick={() => cameraInputRef.current?.click()}
                            disabled={isLocked || editing !== null}
                        />
                    </CameraSlot>
                </div>

                <div
                    className={`flex min-h-10 min-w-0 flex-1 items-end rounded-md border border-input px-3 py-1.5 transition-[border-color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 ${
                        isLocked ? 'bg-muted' : 'bg-background'
                    }`}
                >
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
                        disabled={isLocked}
                        aria-label={
                            state.mode === 'enabled' ? placeholder : state.note
                        }
                        placeholder={
                            state.mode === 'enabled' ? placeholder : state.note
                        }
                        enterKeyHint={isTouch ? 'enter' : 'send'}
                        className="min-h-[1.5rem] w-full min-w-0 resize-none bg-transparent py-0 text-base leading-6 outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed md:text-sm"
                        data-testid="composer-input"
                    />
                </div>

                <div className="flex shrink-0 items-center gap-0.5 pb-0.5">
                    <RightIconsSlot visible={!inUse}>
                        <div className="flex items-center gap-0.5">
                            <PhotoButton
                                onClick={() => photoInputRef.current?.click()}
                                disabled={isLocked || editing !== null}
                            />
                            <AttachButton
                                onClick={() => fileInputRef.current?.click()}
                                disabled={isLocked || editing !== null}
                            />
                            {commonQuestions === undefined ? null : (
                                <CommonQuestionsButton
                                    isOpen={showsCommon}
                                    onClick={() =>
                                        onCommonQuestionsOpenChange?.(
                                            !commonQuestionsOpen,
                                        )
                                    }
                                    disabled={isLocked || editing !== null}
                                />
                            )}
                        </div>
                    </RightIconsSlot>
                    <SendSlot shown={inUse}>
                        <SendButton
                            onClick={() => void submit()}
                            disabled={!canSubmit}
                            editing={editing !== null}
                            saving={saving}
                        />
                    </SendSlot>
                </div>
            </div>
        </div>
    );
};

export default Composer;

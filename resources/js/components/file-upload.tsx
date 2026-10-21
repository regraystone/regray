import { Check, FileText, Upload, X } from 'lucide-react';
import {
    useEffect,
    useRef,
    useState,
    type ChangeEvent,
    type DragEvent,
    type ReactNode,
} from 'react';
import { Spinner } from '@/components/ui/spinner';
import type { InquiryAttachment } from '@/data/inquiries';
import {
    ATTACHMENT_EXTENSIONS,
    ATTACHMENT_MIME_TYPES,
    MAX_ATTACHMENTS,
    MAX_ATTACHMENT_BYTES,
} from '@/lib/inquiry-schema';

/** Both the hint and the `accept` attribute, so the picker filters the same way. */
const ACCEPT = `${ATTACHMENT_EXTENSIONS.map(
    (extension) => `.${extension}`,
).join(',')},${ATTACHMENT_MIME_TYPES.join(',')}`;

const UPLOAD_MIN_MS = 1200;
const UPLOAD_MAX_MS = 2500;
const TICK_MS = 100;

/*
 * Attachment ids only have to be unique within the current list, so a sequence
 * does the job and avoids leaning on `crypto.randomUUID`, which is missing on
 * plain-HTTP origins outside localhost.
 */
let attachmentSequence = 0;

const nextAttachmentId = (file: File): string => {
    attachmentSequence += 1;

    return `${attachmentSequence}-${file.name}`;
};

/** Exported so the Review step sizes a file the same way this list does. */
export const formatSize = (bytes: number): string =>
    bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/*
 * Stands in for a real upload.
 *
 * The project has no upload endpoint yet, so the whole exchange is faked in the
 * browser: a file takes somewhere between 1.2 and 2.5 seconds, scaled by its
 * size, reporting eased progress the whole way. It is deliberately shaped like
 * the real thing — a cancel function, a progress callback, a done callback —
 * so that replacing this one function with a request that reads
 * `xhr.upload.onprogress` is the only change a real endpoint requires. The
 * component around it already speaks only in those three things.
 */
const startUpload = (
    file: File,
    onProgress: (progress: number) => void,
    onDone: () => void,
): (() => void) => {
    const megabytes = file.size / (1024 * 1024);
    const duration = Math.min(UPLOAD_MAX_MS, UPLOAD_MIN_MS + megabytes * 300);

    let elapsed = 0;
    let timer = 0;

    const tick = () => {
        elapsed = Math.min(elapsed + TICK_MS, duration);
        const ratio = elapsed / duration;

        /*
         * Eased out, so the bar races ahead and then settles. A linear bar
         * reads as a clock rather than as a transfer, and looks stuck on the
         * big files.
         */
        onProgress(Math.round((1 - Math.pow(1 - ratio, 2.2)) * 100));

        if (elapsed >= duration) {
            onDone();
            return;
        }

        timer = window.setTimeout(tick, TICK_MS);
    };

    timer = window.setTimeout(tick, TICK_MS);

    return () => window.clearTimeout(timer);
};

type FileUploadProps = {
    files: InquiryAttachment[];
    onFilesChange: (files: InquiryAttachment[]) => void;
};

/**
 * The attachments field of the requirements step.
 *
 * The list itself is held by the page, not here, because stepping back to
 * change a stone and forward again must not cost the reader their uploads. What
 * stays local is everything tied to the browser's side of an upload: the object
 * URLs behind the image previews and the timers driving the progress bars, both
 * of which have to be cleaned up rather than left to the next render.
 */
const FileUpload = ({ files, onFilesChange }: FileUploadProps) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [messages, setMessages] = useState<string[]>([]);

    /*
     * Mirrors the list so the upload callbacks, which fire long after the click
     * that started them, always patch the current version instead of the one
     * captured when the file was added.
     */
    const filesRef = useRef(files);
    const previewsRef = useRef(new Map<string, string>());
    const cancelsRef = useRef(new Map<string, () => void>());

    useEffect(() => {
        filesRef.current = files;
    }, [files]);

    /*
     * Object URLs outlive the component unless they are revoked, and the ones
     * for files still in the list have to survive a re-render. So URLs are
     * dropped only once their file has actually left the list, and everything
     * left over is dropped on unmount.
     */
    useEffect(() => {
        const live = new Set(files.map((entry) => entry.id));

        for (const [id, url] of previewsRef.current) {
            if (!live.has(id)) {
                URL.revokeObjectURL(url);
                previewsRef.current.delete(id);
            }
        }
    }, [files]);

    useEffect(
        () => () => {
            for (const cancel of cancelsRef.current.values()) {
                cancel();
            }

            cancelsRef.current.clear();

            for (const url of previewsRef.current.values()) {
                URL.revokeObjectURL(url);
            }

            previewsRef.current.clear();
        },
        [],
    );

    const patchFile = (id: string, patch: Partial<InquiryAttachment>) => {
        const next = filesRef.current.map((entry) =>
            entry.id === id ? { ...entry, ...patch } : entry,
        );

        filesRef.current = next;
        onFilesChange(next);
    };

    const startPendingUploads = (pending: InquiryAttachment[]) => {
        for (const entry of pending) {
            if (entry.file.type.startsWith('image/')) {
                previewsRef.current.set(
                    entry.id,
                    URL.createObjectURL(entry.file),
                );
            }

            cancelsRef.current.set(
                entry.id,
                startUpload(
                    entry.file,
                    (progress) => patchFile(entry.id, { progress }),
                    () => {
                        cancelsRef.current.delete(entry.id);
                        patchFile(entry.id, { status: 'done', progress: 100 });
                    },
                ),
            );
        }
    };

    /*
     * Files are checked one at a time so a drop of four is not lost because of
     * the fourth: each is taken or refused on its own merits, and every refusal
     * gets its own sentence in the live region. The count limit is the one
     * exception, because once it is reached nothing after it could be added.
     */
    const addFiles = (incoming: File[]) => {
        if (incoming.length === 0) {
            return;
        }

        const accepted: InquiryAttachment[] = [];
        const problems: string[] = [];
        const alreadyAttached = filesRef.current;

        for (const file of incoming) {
            if (alreadyAttached.length + accepted.length >= MAX_ATTACHMENTS) {
                problems.push(`You can attach up to ${MAX_ATTACHMENTS} files.`);
                break;
            }

            const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

            if (
                !ATTACHMENT_EXTENSIONS.includes(extension) ||
                !ATTACHMENT_MIME_TYPES.includes(file.type)
            ) {
                problems.push(
                    `${file.name} isn't a supported type. Use PDF, PNG, JPG or JPEG.`,
                );
                continue;
            }

            if (file.size > MAX_ATTACHMENT_BYTES) {
                problems.push(`${file.name} is over 10 MB.`);
                continue;
            }

            const isDuplicate = [...alreadyAttached, ...accepted].some(
                (entry) =>
                    entry.file.name === file.name &&
                    entry.file.size === file.size,
            );

            if (isDuplicate) {
                problems.push(`${file.name} is already attached.`);
                continue;
            }

            accepted.push({
                id: nextAttachmentId(file),
                file,
                status: 'uploading',
                progress: 0,
            });
        }

        if (accepted.length > 0) {
            const next = [...alreadyAttached, ...accepted];

            filesRef.current = next;
            onFilesChange(next);
            startPendingUploads(accepted);
        }

        setMessages(problems);
    };

    const removeFile = (entry: InquiryAttachment) => {
        /*
         * A removed upload stops immediately. Leaving the timer running would
         * have it report progress into a row that is no longer on screen, and
         * would keep the component's state churning behind the reader's back.
         */
        cancelsRef.current.get(entry.id)?.();
        cancelsRef.current.delete(entry.id);

        const next = filesRef.current.filter(
            (candidate) => candidate.id !== entry.id,
        );

        filesRef.current = next;
        onFilesChange(next);
        setMessages((previous) => [...previous, `${entry.file.name} removed`]);
    };

    const handlePick = (event: ChangeEvent<HTMLInputElement>) => {
        addFiles(Array.from(event.target.files ?? []));

        // Cleared so re-picking a file already in the list is still a change.
        event.target.value = '';
    };

    const stopDragging = (event: DragEvent<HTMLElement>) => {
        // Leaving for a child is not leaving the drop zone.
        if (event.currentTarget.contains(event.relatedTarget as Node)) {
            return;
        }

        setIsDragging(false);
    };

    const rows: ReactNode[] = files.map((entry) => {
        const isUploading = entry.status === 'uploading';
        const preview = previewsRef.current.get(entry.id);
        const isPdf = entry.file.type === 'application/pdf';

        return (
            <li
                key={entry.id}
                className="rounded-md border border-sidebar-border bg-background p-3"
            >
                <div className="flex items-center gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-md border border-sidebar-border bg-background text-secondary-foreground">
                        {isPdf || !preview ? (
                            <FileText className="size-5" aria-hidden />
                        ) : (
                            <img
                                src={preview}
                                alt=""
                                className="size-full object-cover"
                            />
                        )}
                    </span>

                    <span className="min-w-0 flex-1">
                        <span
                            className="block truncate text-sm font-bold text-foreground"
                            title={entry.file.name}
                        >
                            {entry.file.name}
                        </span>
                        <span className="block text-xs text-secondary-foreground">
                            {formatSize(entry.file.size)}
                        </span>
                    </span>

                    <span className="flex shrink-0 items-center gap-1.5 text-xs text-secondary-foreground">
                        {isUploading ? (
                            <>
                                <Spinner className="size-3.5 motion-reduce:animate-none" />
                                <span>{entry.progress}%</span>
                            </>
                        ) : (
                            <>
                                <Check className="size-3.5" aria-hidden />
                                <span>Uploaded</span>
                            </>
                        )}
                    </span>

                    <button
                        type="button"
                        onClick={() => removeFile(entry)}
                        aria-label={`Remove ${entry.file.name}`}
                        className="grid size-9 shrink-0 cursor-pointer place-items-center rounded-md border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <X className="size-4" aria-hidden />
                    </button>
                </div>

                {isUploading ? (
                    <div className="mt-3 h-1 bg-sidebar-border">
                        <div
                            className="h-1 bg-primary transition-[width] motion-reduce:transition-none"
                            style={{ width: `${entry.progress}%` }}
                        />
                    </div>
                ) : null}
            </li>
        );
    });

    return (
        <div
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => event.preventDefault()}
        >
            {/*
             * A button rather than a label around the input, because a label is
             * not focusable and so cannot be reached by keyboard. Clicking it
             * opens the hidden input, which covers both the click and the drag.
             */}
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                onDragOver={(event) => {
                    event.preventDefault();
                    /*
                     * Set on both, not just `dragenter`: a drag that starts on a
                     * child of the zone can reach the zone with an `dragover` and
                     * no `dragenter` of its own, and the highlight has to come on
                     * either way. `preventDefault` is what marks the element as
                     * a valid drop target at all.
                     */
                    setIsDragging(true);
                }}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={stopDragging}
                onDrop={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setIsDragging(false);
                    addFiles(Array.from(event.dataTransfer.files));
                }}
                className={`flex w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-md border border-dashed px-6 py-10 text-center transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none ${
                    isDragging
                        ? 'border-primary bg-muted'
                        : 'border-sidebar-border bg-background'
                }`}
            >
                <Upload
                    className="size-6 text-secondary-foreground"
                    aria-hidden
                />
                <span className="text-sm font-bold text-foreground">
                    Drag files here or click to browse
                </span>
                <span className="text-xs text-secondary-foreground">
                    PDF, PNG, JPG or JPEG. Up to 10 MB each, 10 files.
                </span>
            </button>

            {/*
             * Named for whoever reaches it, and out of the tab order: the
             * button above is the way in by keyboard, so a second stop on the
             * same action would only be a stop with nothing new to say.
             */}
            <input
                ref={inputRef}
                type="file"
                multiple
                accept={ACCEPT}
                aria-label="Attach files"
                tabIndex={-1}
                className="sr-only"
                onChange={handlePick}
            />

            <div
                aria-live="polite"
                className={messages.length > 0 ? 'mt-2 space-y-1' : ''}
            >
                {messages.map((message, index) => (
                    <p
                        key={`${index}-${message}`}
                        className="text-xs text-secondary-foreground"
                    >
                        {message}
                    </p>
                ))}
            </div>

            {files.length > 0 ? (
                <ul className="mt-4 space-y-2">{rows}</ul>
            ) : null}
        </div>
    );
};

export default FileUpload;

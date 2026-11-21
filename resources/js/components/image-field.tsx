import { ImagePlus, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState, type DragEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { formatFileSize } from '@/lib/message-attachments';
import { cn } from '@/lib/utils';

/**
 * One image on a form, such as a brand's logo: pick it, drop it in, replace
 * it or take it off, with a preview.
 *
 * It holds a `File` the parent keeps in its form state, and shows it from an
 * object URL that is revoked when the file changes or the field goes away.
 * With no new file it shows the image already saved (`currentUrl`), if any.
 * Taking a new file off goes back to the saved one; taking the saved one off
 * is `onRemoveCurrent`, offered only when the image is optional. Whether the
 * file is the right type and size is the form's to say, through `error`.
 */
const ImageField = ({
    id,
    label,
    required = false,
    hint,
    error,
    file,
    currentUrl,
    currentName,
    onFileChange,
    onRemoveCurrent,
    accept,
    shape = 'wide',
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    file: File | null;
    /** The saved image, kept unless a new one is picked or it is removed. */
    currentUrl: string | null;
    /** The saved image's own name, to show beside it. */
    currentName?: string | null;
    onFileChange: (file: File | null) => void;
    /** Take the saved image off; left out when the image is required. */
    onRemoveCurrent?: () => void;
    accept: string;
    /** A logo is shown whole on white; a cover photo fills a wide frame. */
    shape?: 'logo' | 'wide';
}) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);

    useEffect(() => {
        if (file === null) {
            setPreviewUrl(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreviewUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = previewUrl ?? currentUrl;
    const pick = () => inputRef.current?.click();

    const take = (picked: File | undefined | null) => {
        if (picked) {
            onFileChange(picked);
        }
    };

    const drop = (event: DragEvent<HTMLElement>) => {
        event.preventDefault();
        setDragging(false);
        take(event.dataTransfer.files?.[0]);
    };

    const dragHandlers = {
        onDragOver: (event: DragEvent<HTMLElement>) => {
            event.preventDefault();
            setDragging(true);
        },
        onDragLeave: () => setDragging(false),
        onDrop: drop,
    };

    return (
        <FormField
            id={id}
            label={label}
            required={required}
            hint={hint}
            error={error}
        >
            {({ describedBy, invalid }) => (
                <div className="mt-2">
                    <input
                        ref={inputRef}
                        type="file"
                        accept={accept}
                        className="sr-only"
                        tabIndex={-1}
                        aria-hidden
                        onChange={(event) => {
                            take(event.target.files?.[0]);
                            // The same file can be picked again after a remove.
                            event.target.value = '';
                        }}
                    />

                    {shown ? (
                        <div
                            className={cn(
                                'flex flex-col gap-3 border border-sidebar-border p-3 sm:flex-row sm:items-center',
                                dragging && 'border-primary bg-muted/40',
                                invalid && 'border-destructive',
                            )}
                            {...dragHandlers}
                        >
                            <div
                                className={cn(
                                    'flex shrink-0 items-center justify-center overflow-hidden border border-sidebar-border',
                                    shape === 'logo'
                                        ? 'size-24 bg-white p-2'
                                        : 'aspect-[16/9] w-full bg-muted sm:w-48',
                                )}
                            >
                                <img
                                    src={shown}
                                    alt=""
                                    className={cn(
                                        'size-full',
                                        shape === 'logo'
                                            ? 'object-contain'
                                            : 'object-cover',
                                    )}
                                />
                            </div>
                            <div className="min-w-0 flex-1 space-y-2">
                                <p
                                    className="truncate text-sm"
                                    title={
                                        file?.name ?? currentName ?? undefined
                                    }
                                >
                                    {file ? (
                                        <>
                                            <span className="font-medium">
                                                {file.name}
                                            </span>{' '}
                                            <span className="text-secondary-foreground">
                                                · {formatFileSize(file.size)} ·
                                                new
                                            </span>
                                        </>
                                    ) : (
                                        <span className="text-secondary-foreground">
                                            {currentName ?? 'Current image'}
                                        </span>
                                    )}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        id={id}
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={pick}
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                    >
                                        <RefreshCw aria-hidden />
                                        Replace
                                        <span className="sr-only">
                                            {' '}
                                            {label.toLowerCase()}
                                        </span>
                                    </Button>
                                    {file !== null || onRemoveCurrent ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                file !== null
                                                    ? onFileChange(null)
                                                    : onRemoveCurrent?.()
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                            {file !== null && currentUrl
                                                ? 'Undo'
                                                : 'Remove'}
                                            <span className="sr-only">
                                                {' '}
                                                {label.toLowerCase()}
                                            </span>
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    ) : (
                        <button
                            id={id}
                            type="button"
                            onClick={pick}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            className={cn(
                                'flex w-full flex-col items-center justify-center gap-2 border border-dashed border-sidebar-border px-4 py-8 text-center transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none',
                                dragging && 'border-primary bg-muted/40',
                                invalid && 'border-destructive',
                            )}
                            {...dragHandlers}
                        >
                            <ImagePlus
                                className="size-6 text-secondary-foreground"
                                aria-hidden
                            />
                            <span className="text-sm font-medium">
                                Choose an image, or drop one here
                            </span>
                        </button>
                    )}
                </div>
            )}
        </FormField>
    );
};

export default ImageField;

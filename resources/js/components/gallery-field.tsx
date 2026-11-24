import { ArrowDown, ArrowUp, ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

/** One photo in a gallery field: a saved one (`url`) or a new one (`file`). */
export type GalleryFieldItem = {
    /** The field array's own key for the row. */
    key: string;
    url: string | null;
    /** The new file's name; a saved photo shows its number. */
    name: string | null;
    file: File | null;
    error?: string;
};

/** A photo's picture: the saved one, or a preview of the new file. */
const GalleryPreview = ({ item }: { item: GalleryFieldItem }) => {
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (item.file === null) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(item.file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [item.file]);

    const src = preview ?? item.url;

    return (
        <div className="aspect-square w-full overflow-hidden bg-muted sm:w-20">
            {src ? (
                <img src={src} alt="" className="size-full object-cover" />
            ) : null}
        </div>
    );
};

/**
 * A form's gallery: its photos in order, each with its picture, moved up or
 * down or taken off, and Add photos for more (several at once), up to `max`.
 * The page holds the rows (a react-hook-form field array) and says what each
 * button does.
 */
const GalleryField = ({
    id,
    label,
    hint,
    items,
    max,
    accept,
    error,
    onAdd,
    onMove,
    onRemove,
}: {
    id: string;
    label: string;
    hint: string;
    items: GalleryFieldItem[];
    max: number;
    accept: string;
    error?: string;
    onAdd: (files: File[]) => void;
    onMove: (from: number, to: number) => void;
    onRemove: (index: number) => void;
}) => {
    const input = useRef<HTMLInputElement>(null);

    return (
        <div>
            <p className="text-sm font-bold">{label}</p>
            <p
                id={`${id}-hint`}
                className="mt-1 text-sm text-secondary-foreground"
            >
                {hint}
            </p>
            {error ? (
                <p className="mt-1 text-sm text-destructive">{error}</p>
            ) : null}

            {items.length > 0 ? (
                <ol className="mt-3 space-y-3" data-testid={`${id}-photos`}>
                    {items.map((item, index) => {
                        const number = index + 1;

                        return (
                            <li
                                key={item.key}
                                className="grid grid-cols-1 gap-3 border border-sidebar-border p-3 sm:grid-cols-[5rem_minmax(0,1fr)_auto] sm:items-center"
                            >
                                <GalleryPreview item={item} />
                                <div className="min-w-0">
                                    <p
                                        className="truncate text-sm"
                                        title={item.name ?? undefined}
                                    >
                                        {item.name ?? `Photo ${number}`}
                                        {item.file ? (
                                            <span className="ml-2 text-xs text-secondary-foreground">
                                                New
                                            </span>
                                        ) : null}
                                    </p>
                                    {item.error ? (
                                        <p className="mt-1 text-sm text-destructive">
                                            {item.error}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9"
                                        disabled={index === 0}
                                        onClick={() => onMove(index, index - 1)}
                                        aria-label={`Move photo ${number} up`}
                                    >
                                        <ArrowUp aria-hidden />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9"
                                        disabled={index === items.length - 1}
                                        onClick={() => onMove(index, index + 1)}
                                        aria-label={`Move photo ${number} down`}
                                    >
                                        <ArrowDown aria-hidden />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-9 text-destructive"
                                        onClick={() => onRemove(index)}
                                        aria-label={`Remove photo ${number}`}
                                    >
                                        <Trash2 aria-hidden />
                                    </Button>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            ) : null}

            {items.length < max ? (
                <>
                    <input
                        ref={input}
                        type="file"
                        accept={accept}
                        multiple
                        className="sr-only"
                        tabIndex={-1}
                        aria-hidden
                        onChange={(event) => {
                            onAdd(
                                Array.from(event.target.files ?? []).slice(
                                    0,
                                    max - items.length,
                                ),
                            );
                            event.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="mt-3"
                        onClick={() => input.current?.click()}
                        aria-describedby={`${id}-hint`}
                    >
                        <ImagePlus aria-hidden />
                        Add photos
                    </Button>
                </>
            ) : null}
        </div>
    );
};

export default GalleryField;

import { Pencil, Reply, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { fullTimestamp } from '@/lib/message-grouping';
import { cn } from '@/lib/utils';

export type MessageActionHandlers = {
    onReply: () => void;
    /** Present only on the viewer's own text messages. */
    onEdit?: () => void;
    /** Present only on the viewer's own messages. */
    onRemove?: () => void;
};

/**
 * The desktop toolbar on a bubble: Reply, Edit and Remove, and the full date
 * and time. Shown on hover and on keyboard focus within the bubble.
 */
export const MessageToolbar = ({
    createdAt,
    isOwn,
    onReply,
    onEdit,
    onRemove,
}: MessageActionHandlers & { createdAt: string; isOwn: boolean }) => (
    <div
        className={cn(
            'pointer-events-none absolute -top-9 z-10 flex items-center gap-1 rounded-md border border-sidebar-border bg-background px-1.5 py-1 opacity-0 shadow-sm transition-opacity group-focus-within/bubble:pointer-events-auto group-focus-within/bubble:opacity-100 group-hover/bubble:pointer-events-auto group-hover/bubble:opacity-100',
            isOwn ? 'right-0' : 'left-0',
        )}
        data-testid="message-toolbar"
    >
        <time
            dateTime={createdAt}
            className="px-1 text-[0.7rem] whitespace-nowrap text-secondary-foreground"
            data-testid="message-timestamp"
        >
            {fullTimestamp(createdAt)}
        </time>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-7 rounded-md"
            onClick={onReply}
            aria-label="Reply"
        >
            <Reply className="size-3.5" aria-hidden />
        </Button>
        {onEdit ? (
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7 rounded-md"
                onClick={onEdit}
                aria-label="Edit"
            >
                <Pencil className="size-3.5" aria-hidden />
            </Button>
        ) : null}
        {onRemove ? (
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-7 rounded-md"
                onClick={onRemove}
                aria-label="Remove"
            >
                <Trash2 className="size-3.5" aria-hidden />
            </Button>
        ) : null}
    </div>
);

/**
 * The touch version: a bottom sheet with the same actions and the time,
 * opened by pressing and holding a bubble.
 */
export const MessageActionSheet = ({
    open,
    onOpenChange,
    createdAt,
    preview,
    onReply,
    onEdit,
    onRemove,
}: MessageActionHandlers & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    createdAt: string;
    preview: string;
}) => {
    const run = (action: () => void) => () => {
        onOpenChange(false);
        action();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="top-auto bottom-0 left-0 w-full max-w-none translate-x-0 translate-y-0 gap-2 rounded-t-md rounded-b-none p-4 pb-6 sm:max-w-none"
                data-testid="message-action-sheet"
            >
                <DialogTitle className="text-sm">
                    <time dateTime={createdAt}>{fullTimestamp(createdAt)}</time>
                </DialogTitle>
                <DialogDescription className="truncate">
                    {preview}
                </DialogDescription>
                <div className="mt-2 flex flex-col gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-start"
                        onClick={run(onReply)}
                    >
                        <Reply aria-hidden /> Reply
                    </Button>
                    {onEdit ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="justify-start"
                            onClick={run(onEdit)}
                        >
                            <Pencil aria-hidden /> Edit
                        </Button>
                    ) : null}
                    {onRemove ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="justify-start"
                            onClick={run(onRemove)}
                        >
                            <Trash2 aria-hidden /> Remove
                        </Button>
                    ) : null}
                </div>
            </DialogContent>
        </Dialog>
    );
};

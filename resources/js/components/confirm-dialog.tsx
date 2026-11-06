import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Ask before doing something, in the app's own dialog rather than the browser's
 * `confirm()`.
 *
 * Both buttons say what they do, so the choice reads without the question above
 * it. Focus starts on the safe one. While `processing`, nothing can be pressed or
 * closed, so the answer can't be given twice or walked away from halfway.
 *
 * With no `cancelLabel` there is nothing to choose, so it only tells the reader
 * something and has the one button to say they have read it.
 */
const ConfirmDialog = ({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    onConfirm,
    destructive = false,
    processing = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel: string;
    /** What staying means, such as "Keep inquiry". */
    cancelLabel?: string;
    onConfirm: () => void;
    /** For something that can't be undone. */
    destructive?: boolean;
    processing?: boolean;
}) => (
    <Dialog
        open={open}
        onOpenChange={(next) => {
            if (!processing) {
                onOpenChange(next);
            }
        }}
    >
        <DialogContent showCloseButton={false}>
            <DialogHeader>
                <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                    {title}
                </DialogTitle>
                <DialogDescription>{description}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                {cancelLabel === undefined ? null : (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        {cancelLabel}
                    </Button>
                )}
                <Button
                    type="button"
                    variant={destructive ? 'destructive' : 'default'}
                    onClick={onConfirm}
                    disabled={processing}
                >
                    {processing ? (
                        <Spinner className="motion-reduce:animate-none" />
                    ) : null}
                    {confirmLabel}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
);

export default ConfirmDialog;

import { useRef, useState, type ReactNode } from 'react';
import { ResultBadge } from '@/components/result-badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ResultDialogContent = {
    status: 'success' | 'error';
    title: string;
    description: ReactNode;
    actions: ReactNode;
};

/**
 * How something the reader sent turned out: sending an inquiry, booking an
 * appointment. Open while there is a `result`.
 *
 * Focus lands on the title, so the result is read out and a stray Enter cannot
 * press one of its buttons. While `locked`, a retry is in flight and it can't be
 * closed. The last result stays drawn while the dialog fades out.
 */
const ResultDialog = ({
    result,
    onClose,
    locked = false,
}: {
    result: ResultDialogContent | null;
    onClose: () => void;
    locked?: boolean;
}) => {
    const titleRef = useRef<HTMLHeadingElement>(null);
    const [shown, setShown] = useState(result);

    if (result !== null && result !== shown) {
        setShown(result);
    }

    return (
        <Dialog
            open={result !== null}
            onOpenChange={(open) => {
                if (!open && !locked) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="sm:max-w-md"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    titleRef.current?.focus();
                }}
            >
                {shown === null ? null : (
                    <>
                        <div className="flex flex-col items-center text-center">
                            <ResultBadge status={shown.status} />
                            <DialogHeader className="mt-6 sm:text-center">
                                <DialogTitle
                                    ref={titleRef}
                                    tabIndex={-1}
                                    className="outline-none"
                                >
                                    {shown.title}
                                </DialogTitle>
                                <DialogDescription className="mt-2">
                                    {shown.description}
                                </DialogDescription>
                            </DialogHeader>
                        </div>
                        <DialogFooter className="mt-8 flex-col gap-3 sm:flex-row sm:justify-center">
                            {shown.actions}
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
};

export default ResultDialog;

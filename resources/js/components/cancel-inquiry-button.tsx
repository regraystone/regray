import { router } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { cancel as quoteCancel } from '@/routes/quote';

/**
 * The question asked before a client cancels an inquiry, opened from a card's
 * ⋯ menu on the Quotations page. Cancelling cannot be undone, so it says so,
 * and the safe choice (keep it) is the one that closes the dialog.
 */
const CancelInquiryDialog = ({
    reference,
    open,
    onOpenChange,
}: {
    reference: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) => {
    const [isCancelling, setIsCancelling] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Cancel this inquiry?"
            description={`We will stop working on ${reference}. You can't undo this, but you can send a new inquiry at any time.`}
            confirmLabel="Cancel inquiry"
            cancelLabel="Keep inquiry"
            destructive
            processing={isCancelling}
            onConfirm={() => {
                setIsCancelling(true);
                router.patch(
                    quoteCancel.url({ reference }),
                    {},
                    {
                        onFinish: () => {
                            setIsCancelling(false);
                            onOpenChange(false);
                        },
                    },
                );
            }}
        />
    );
};

export default CancelInquiryDialog;

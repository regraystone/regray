import { router } from '@inertiajs/react';
import { useState, type ComponentProps } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cancel as quoteCancel } from '@/routes/quote';

/** A client's Cancel button on an inquiry, which asks before it cancels. */
const CancelInquiryButton = ({
    reference,
    label,
    variant,
    className,
}: {
    reference: string;
    label: string;
    variant: ComponentProps<typeof Button>['variant'];
    className?: string;
}) => {
    const [isAsking, setIsAsking] = useState(false);
    const [isCancelling, setIsCancelling] = useState(false);

    return (
        <>
            <Button
                type="button"
                variant={variant}
                className={className}
                onClick={() => setIsAsking(true)}
            >
                {label}
            </Button>

            <ConfirmDialog
                open={isAsking}
                onOpenChange={setIsAsking}
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
                                setIsAsking(false);
                            },
                        },
                    );
                }}
            />
        </>
    );
};

export default CancelInquiryButton;

import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import type { z } from 'zod';
import FormField from '@/components/form-field';
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
import { Textarea } from '@/components/ui/textarea';
import { cancelScheduleSchema } from '@/lib/booking-schema';
import type { ScheduleItem } from '@/lib/schedule';
import { sendScheduleChange } from '@/lib/schedule-changes';
import { cancel as cancelSchedule } from '@/routes/schedule';

type CancelValues = z.input<typeof cancelScheduleSchema>;

/**
 * Cancel an appointment, saying why. The reason is shown to the client and to
 * staff, on the appointment and on the card in the client's Regray chat.
 */
const CancelScheduleDialog = ({
    item,
    onClose,
    onSaved,
}: {
    item: ScheduleItem | null;
    onClose: () => void;
    onSaved: () => void;
}) => {
    const [refusal, setRefusal] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const { register, handleSubmit, setError, reset, formState } =
        useForm<CancelValues>({
            resolver: zodResolver(cancelScheduleSchema),
            mode: 'onTouched',
            defaultValues: { reason: '' },
        });

    useEffect(() => {
        if (item !== null) {
            reset({ reason: '' });
            setRefusal(null);
        }
    }, [item, reset]);

    const save = async (values: CancelValues) => {
        if (item === null) {
            return;
        }

        setIsSaving(true);
        setRefusal(null);

        const outcome = await sendScheduleChange(
            'post',
            cancelSchedule.url(item.id),
            { reason: values.reason },
        );

        setIsSaving(false);

        if (outcome.kind === 'saved') {
            onSaved();

            return;
        }

        if (outcome.kind === 'invalid') {
            if (outcome.errors.reason) {
                setError('reason', {
                    type: 'server',
                    message: outcome.errors.reason,
                });
            }

            setRefusal(outcome.errors.schedule ?? null);

            return;
        }

        setRefusal('The appointment could not be cancelled. Try again.');
    };

    return (
        <Dialog
            open={item !== null}
            onOpenChange={(open) => {
                if (!open && !isSaving) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        Cancel appointment
                    </DialogTitle>
                    <DialogDescription>
                        {item ? `${item.title}. ` : ''}The client and the team
                        will see the reason.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="cancel-schedule-form"
                    noValidate
                    onSubmit={handleSubmit(save)}
                    className="space-y-4"
                >
                    <FormField
                        id="cancel-reason"
                        label="Reason for cancelling *"
                        error={formState.errors.reason?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Textarea
                                id="cancel-reason"
                                rows={3}
                                {...register('reason')}
                                className="rounded-none"
                                aria-invalid={invalid}
                                aria-describedby={describedBy}
                            />
                        )}
                    </FormField>

                    {refusal ? (
                        <p role="alert" className="text-sm text-destructive">
                            {refusal}
                        </p>
                    ) : null}
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isSaving}
                    >
                        Keep appointment
                    </Button>
                    <Button
                        type="submit"
                        form="cancel-schedule-form"
                        variant="destructive"
                        disabled={isSaving}
                    >
                        {isSaving ? (
                            <>
                                <Spinner />
                                Cancelling...
                            </>
                        ) : (
                            'Cancel appointment'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default CancelScheduleDialog;

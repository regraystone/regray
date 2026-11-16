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
import {
    cancelScheduleSchema,
    declineScheduleSchema,
} from '@/lib/booking-schema';
import type { ScheduleItem } from '@/lib/schedule';
import { sendScheduleChange } from '@/lib/schedule-changes';
import {
    cancel as cancelSchedule,
    decline as declineSchedule,
} from '@/routes/schedule';

type CancelValues = z.input<typeof cancelScheduleSchema>;

/** What each kind of reason dialog says and where it sends. */
const KINDS = {
    cancel: {
        schema: cancelScheduleSchema,
        route: cancelSchedule,
        title: 'Cancel appointment',
        audience: 'The client and the team will see the reason.',
        field: 'Reason for cancelling *',
        keep: 'Keep appointment',
        submit: 'Cancel appointment',
        saving: 'Cancelling...',
        failed: 'The appointment could not be cancelled. Try again.',
        destructive: true,
    },
    decline: {
        schema: declineScheduleSchema,
        route: declineSchedule,
        title: 'Decline appointment',
        audience:
            'It goes back to marketing to assign to someone else. Marketing sees the reason; the client does not.',
        field: 'Reason for declining *',
        keep: 'Not now',
        submit: 'Decline appointment',
        saving: 'Declining...',
        failed: 'The appointment could not be declined. Try again.',
        destructive: false,
    },
} as const;

/**
 * Cancel an appointment, saying why. The reason is shown to the client and to
 * staff, on the appointment and on the card in the client's Regray chat.
 *
 * With `kind` "decline", the sales member an appointment is assigned to sends
 * it back to marketing, saying why: the same form, a different audience.
 */
const CancelScheduleDialog = ({
    item,
    onClose,
    onSaved,
    kind = 'cancel',
}: {
    item: ScheduleItem | null;
    onClose: () => void;
    onSaved: () => void;
    kind?: keyof typeof KINDS;
}) => {
    const copy = KINDS[kind];
    const [refusal, setRefusal] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    const { register, handleSubmit, setError, reset, formState } =
        useForm<CancelValues>({
            resolver: zodResolver(copy.schema),
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
            copy.route.url(item.id),
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

        setRefusal(copy.failed);
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
                        {copy.title}
                    </DialogTitle>
                    <DialogDescription>
                        {item ? `${item.title}. ` : ''}
                        {copy.audience}
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
                        label={copy.field}
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
                        {copy.keep}
                    </Button>
                    <Button
                        type="submit"
                        form="cancel-schedule-form"
                        variant={copy.destructive ? 'destructive' : 'default'}
                        disabled={isSaving}
                    >
                        {isSaving ? (
                            <>
                                <Spinner />
                                {copy.saving}
                            </>
                        ) : (
                            copy.submit
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default CancelScheduleDialog;

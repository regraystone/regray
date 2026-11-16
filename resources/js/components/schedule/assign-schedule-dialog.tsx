import { useEffect, useState } from 'react';
import SalesPicker from '@/components/schedule/sales-picker';
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
import { SALES_REQUIRED } from '@/lib/booking-schema';
import type { ScheduleItem } from '@/lib/schedule';
import type { AssignableSales } from '@/lib/schedule-sales-api';
import { sendScheduleChange } from '@/lib/schedule-changes';
import { assign as assignSchedule } from '@/routes/schedule';

/**
 * Assign an appointment to a sales member, who confirms or declines it.
 * Marketing does this to a client's booking, to one a sales member declined,
 * and to one still waiting on a sales member who has not answered. The
 * client still reads it as pending until it is confirmed.
 */
const AssignScheduleDialog = ({
    item,
    onClose,
    onSaved,
}: {
    item: ScheduleItem | null;
    onClose: () => void;
    onSaved: (sales: AssignableSales) => void;
}) => {
    const [sales, setSales] = useState<AssignableSales | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [refusal, setRefusal] = useState<string | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (item !== null) {
            setSales(null);
            setError(undefined);
            setRefusal(null);
        }
    }, [item]);

    const save = async () => {
        if (item === null) {
            return;
        }

        if (sales === null) {
            setError(SALES_REQUIRED);

            return;
        }

        setIsSaving(true);
        setRefusal(null);

        const outcome = await sendScheduleChange(
            'post',
            assignSchedule.url(item.id),
            { salesId: String(sales.id) },
        );

        setIsSaving(false);

        if (outcome.kind === 'saved') {
            onSaved(sales);

            return;
        }

        if (outcome.kind === 'invalid') {
            setError(outcome.errors.salesId);
            setRefusal(outcome.errors.schedule ?? null);

            return;
        }

        setRefusal('The appointment could not be assigned. Try again.');
    };

    const isReassign = item?.status === 'awaiting_sales';

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
                        {isReassign ? 'Reassign to sales' : 'Assign to sales'}
                    </DialogTitle>
                    <DialogDescription>
                        {item ? `${item.title}. ` : ''}
                        {isReassign && item?.assignedSalesName
                            ? `It is waiting on ${item.assignedSalesName}. `
                            : ''}
                        The sales member you choose confirms it, and only then
                        is the client told it is confirmed.
                    </DialogDescription>
                </DialogHeader>

                {item?.declineReason ? (
                    <p
                        className="rounded-md border border-sidebar-border px-3 py-2 text-sm"
                        data-testid="assign-decline-reason"
                    >
                        <span className="font-bold">
                            Declined by {item.declinedByName ?? 'sales'}:
                        </span>{' '}
                        {item.declineReason}
                    </p>
                ) : null}

                <SalesPicker
                    value={sales}
                    onChange={(next) => {
                        setSales(next);
                        setError(undefined);
                    }}
                    error={error}
                />

                {refusal ? (
                    <p role="alert" className="text-sm text-destructive">
                        {refusal}
                    </p>
                ) : null}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={isSaving}
                    >
                        Not now
                    </Button>
                    <Button type="button" onClick={save} disabled={isSaving}>
                        {isSaving ? (
                            <>
                                <Spinner />
                                Assigning...
                            </>
                        ) : (
                            'Assign'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default AssignScheduleDialog;

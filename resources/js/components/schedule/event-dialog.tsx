import { ScheduleStatusBadge } from '@/components/schedule/schedule-status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ScheduleItem, ScheduleWalkIn } from '@/lib/schedule';
import { CONTACT_KIND_LABELS } from '@/lib/staff-inquire';
import { formatFullDate, formatTime } from '@/lib/date';

/** A link that calls or writes to a walk-in's contact, where one fits. */
const contactHref = (
    contact: ScheduleWalkIn['contacts'][number],
): string | null => {
    if (contact.kind === 'phone') {
        return `tel:${contact.value}`;
    }

    if (contact.kind === 'email') {
        return `mailto:${contact.value}`;
    }

    return null;
};

/** What the reader chose to do to the visit from its dialog. */
export type ScheduleDialogAction =
    | 'update'
    | 'cancel'
    | 'assign'
    | 'confirm'
    | 'decline'
    | 'serve'
    | 'noShow';

/**
 * One visit.
 *
 * Everything a client was told when they booked it, in the order they would
 * have been told it: what it is for, when they are expected, where it has got
 * to, and anything the studio wrote down for the day, with the reason it was
 * last moved or cancelled. Staff also see whose it is and who booked it.
 *
 * Staff also see the sales member it is assigned to, and while it waits for
 * marketing again, who declined it and why.
 *
 * Its buttons are the changes this reader may make to it now (`item.can`, as
 * the server works it out): Update and Cancel for the client on their own and
 * for marketing; Assign (or Reassign) for marketing; Confirm and Decline for
 * the sales member it is assigned to; Mark as attended and Did not attend for
 * sales alone. An inquiry's visit has none: it follows its inquiry.
 */
const EventDialog = ({
    item,
    onClose,
    onAction,
}: {
    item: ScheduleItem | null;
    onClose: () => void;
    onAction: (action: ScheduleDialogAction, item: ScheduleItem) => void;
}) => (
    <Dialog
        open={item !== null}
        onOpenChange={(open) => {
            if (!open) {
                onClose();
            }
        }}
    >
        <DialogContent aria-describedby={undefined}>
            {item ? (
                <>
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            {item.title}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="overflow-hidden rounded-md border border-sidebar-border">
                        <div className="flex items-center justify-between gap-3 border-b border-sidebar-border px-4 py-3">
                            <span className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                Status
                            </span>
                            <ScheduleStatusBadge status={item.status} />
                        </div>

                        <dl className="divide-y divide-sidebar-border">
                            <div className="flex items-baseline justify-between gap-4 px-4 py-3">
                                <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                    Date
                                </dt>
                                <dd className="text-right text-sm font-bold">
                                    {formatFullDate.format(item.arrival)}
                                </dd>
                            </div>

                            <div className="flex items-baseline justify-between gap-4 px-4 py-3">
                                <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                    Expected
                                </dt>
                                <dd className="text-right text-sm font-bold tabular-nums">
                                    {formatTime.format(item.arrival)}
                                </dd>
                            </div>

                            {item.inquiryReference ? (
                                <div className="flex items-baseline justify-between gap-4 px-4 py-3">
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Inquiry
                                    </dt>
                                    <dd className="text-right text-sm font-bold tabular-nums">
                                        {item.inquiryReference}
                                    </dd>
                                </div>
                            ) : null}

                            {item.clientName ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-client"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Client
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.clientName}
                                        {item.walkIn ? ' (walk-in)' : ''}
                                    </dd>
                                </div>
                            ) : null}
                            {item.walkIn ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-walk-in"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Reach them
                                    </dt>
                                    <dd className="mt-1.5 space-y-1 text-sm text-secondary-foreground">
                                        <p className="text-xs">
                                            A walk-in has no Regray account and
                                            is not told in the app.
                                        </p>
                                        {item.walkIn.contacts.map((contact) => (
                                            <p
                                                key={`${contact.kind}-${contact.value}`}
                                            >
                                                <span className="font-bold text-foreground">
                                                    {
                                                        CONTACT_KIND_LABELS[
                                                            contact.kind
                                                        ]
                                                    }
                                                    :
                                                </span>{' '}
                                                {contactHref(contact) ? (
                                                    <a
                                                        href={
                                                            contactHref(
                                                                contact,
                                                            ) ?? undefined
                                                        }
                                                        className="underline underline-offset-2"
                                                    >
                                                        {contact.value}
                                                    </a>
                                                ) : (
                                                    contact.value
                                                )}
                                            </p>
                                        ))}
                                    </dd>
                                </div>
                            ) : null}
                            {item.assignedSalesName ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-assigned-sales"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Sales
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.assignedSalesName}
                                        {item.status === 'awaiting_sales'
                                            ? ' (has not confirmed yet)'
                                            : ''}
                                    </dd>
                                </div>
                            ) : null}
                            {item.declineReason ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-decline-reason"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Declined by{' '}
                                        {item.declinedByName ?? 'sales'}
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.declineReason}
                                    </dd>
                                </div>
                            ) : null}
                            {item.bookedByName ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-booked-by"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Booked by
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.bookedByName}
                                    </dd>
                                </div>
                            ) : null}
                            {item.changeReason ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-change-reason"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Reason for change
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.changeReason}
                                    </dd>
                                </div>
                            ) : null}
                            {item.status === 'cancelled' &&
                            item.cancelReason ? (
                                <div
                                    className="px-4 py-3"
                                    data-testid="schedule-cancel-reason"
                                >
                                    <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                        Reason for cancelling
                                    </dt>
                                    <dd className="mt-1.5 text-sm text-secondary-foreground">
                                        {item.cancelReason}
                                    </dd>
                                </div>
                            ) : null}
                            <div className="px-4 py-3">
                                <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                    Notes
                                </dt>
                                <dd className="mt-1.5 text-sm text-secondary-foreground">
                                    {item.notes ?? 'None'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <p className="text-xs text-muted-foreground">
                        This is the hour the studio expects you, not a slot held
                        for you. Arrival can be a little either side.
                    </p>
                </>
            ) : null}

            <DialogFooter className="flex-wrap gap-2">
                {item?.can.confirm ? (
                    <Button
                        type="button"
                        onClick={() => onAction('confirm', item)}
                    >
                        Confirm
                    </Button>
                ) : null}
                {item?.can.decline ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onAction('decline', item)}
                    >
                        Decline
                    </Button>
                ) : null}
                {item?.can.assign ? (
                    <Button
                        type="button"
                        variant={
                            item.status === 'awaiting_sales'
                                ? 'outline'
                                : 'default'
                        }
                        onClick={() => onAction('assign', item)}
                    >
                        {item.status === 'awaiting_sales'
                            ? 'Reassign'
                            : 'Assign to sales'}
                    </Button>
                ) : null}
                {item?.can.serve ? (
                    <Button
                        type="button"
                        onClick={() => onAction('serve', item)}
                    >
                        Mark as attended
                    </Button>
                ) : null}
                {item?.can.noShow ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onAction('noShow', item)}
                    >
                        Did not attend
                    </Button>
                ) : null}
                {item?.can.update ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onAction('update', item)}
                    >
                        Update
                    </Button>
                ) : null}
                {item?.can.cancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onAction('cancel', item)}
                    >
                        Cancel
                    </Button>
                ) : null}
                <DialogClose asChild>
                    <Button type="button" variant="outline">
                        Close
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
);

export default EventDialog;

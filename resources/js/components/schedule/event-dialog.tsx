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
import type { ScheduleItem } from '@/data/schedule';
import { formatFullDate, formatTime } from '@/lib/date';

/**
 * One visit, read only.
 *
 * Everything a client was told when they booked it, in the order they would
 * have been told it: what it is for, when they are expected, where it has got
 * to, and anything the studio wrote down for the day. Read only for now, since
 * there is nothing behind it to change — the actions live on the list beside
 * the calendar, where they can be wired up one at a time.
 */
const EventDialog = ({
    item,
    onClose,
}: {
    item: ScheduleItem | null;
    onClose: () => void;
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

                            <div className="px-4 py-3">
                                <dt className="text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                    Notes
                                </dt>
                                <dd className="mt-1.5 text-sm text-secondary-foreground">
                                    {item.notes}
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

            <DialogFooter>
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

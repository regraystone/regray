import { Building2, UserRound } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    STAFF_INQUIRE_OPTIONS,
    type StaffInquireKind,
} from '@/lib/staff-inquire';

const ICONS = { platform: UserRound, direct: Building2 } as const;

/**
 * What staff are asked when they press Inquire: does the client have a Regray
 * account? Platform goes on to find the account and its lead; Direct (walk-in)
 * to take the client down. Never shown to a client.
 */
const StaffInquireDialog = ({
    open,
    onOpenChange,
    onChoose,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onChoose: (kind: StaffInquireKind) => void;
}) => (
    <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent data-testid="staff-inquire-dialog">
            <DialogHeader>
                <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                    Who is this inquiry for?
                </DialogTitle>
                <DialogDescription>
                    Choose whether the client has a Regray account.
                </DialogDescription>
            </DialogHeader>
            <div className="grid gap-2 sm:grid-cols-2">
                {STAFF_INQUIRE_OPTIONS.map((option) => {
                    const Icon = ICONS[option.kind];

                    return (
                        <Button
                            key={option.kind}
                            type="button"
                            variant="outline"
                            onClick={() => onChoose(option.kind)}
                            className="flex h-auto flex-col items-start gap-1.5 rounded-md p-4 text-left whitespace-normal normal-case"
                            data-staff-inquire-kind={option.kind}
                        >
                            <span className="flex items-center gap-2 text-sm font-bold">
                                <Icon aria-hidden className="size-4" />
                                {option.label}
                            </span>
                            <span className="text-xs font-normal tracking-normal text-secondary-foreground">
                                {option.description}
                            </span>
                        </Button>
                    );
                })}
            </div>
        </DialogContent>
    </Dialog>
);

export default StaffInquireDialog;

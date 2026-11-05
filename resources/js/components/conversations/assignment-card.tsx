import { UserCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The staff-only strip under the header of an unassigned Regray Team thread,
 * and the one place to claim it. Pinned outside the scrolling messages so it is
 * in view on a long thread, which opens at its newest message. It is part of the
 * page, never a stored message, and is never sent to a client.
 */
const AssignmentCard = ({
    onClaim,
    claiming,
}: {
    onClaim: () => void;
    claiming: boolean;
}) => (
    <div
        className="flex shrink-0 items-center justify-between gap-3 border-b border-sidebar-border bg-secondary px-4 py-2.5"
        data-testid="assignment-card"
    >
        <div className="min-w-0">
            <p className="text-sm font-bold">
                Do you want to be assigned to this conversation?
            </p>
            <p className="mt-0.5 text-xs text-secondary-foreground">
                Once you are, only you can reply to this client.
            </p>
        </div>
        <Button
            type="button"
            size="sm"
            className="shrink-0"
            onClick={onClaim}
            disabled={claiming}
        >
            {claiming ? (
                <Spinner className="motion-reduce:animate-none" />
            ) : (
                <UserCheck aria-hidden />
            )}
            Assign to me
        </Button>
    </div>
);

export default AssignmentCard;

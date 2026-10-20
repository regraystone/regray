import { UserCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The staff-only card at the top of an unassigned Regray Team thread. It is
 * part of the page, never a stored message, and is never sent to a client.
 */
const AssignmentCard = ({
    onClaim,
    claiming,
}: {
    onClaim: () => void;
    claiming: boolean;
}) => (
    <div
        className="mx-auto w-full max-w-sm rounded-md border border-sidebar-border bg-secondary p-4 text-center"
        data-testid="assignment-card"
    >
        <p className="text-sm font-bold">
            Do you want to be assigned to this conversation?
        </p>
        <p className="mt-1 text-xs text-secondary-foreground">
            Once you are, only you can reply to this client.
        </p>
        <Button
            type="button"
            size="sm"
            className="mt-3"
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

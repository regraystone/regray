import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { Receipt } from '@/lib/receipts';

/**
 * Under the viewer's latest message: "Sent", "Delivered" or "Seen" in a
 * direct chat, and small avatars of who has read it in a group.
 */
const Receipts = ({ receipt }: { receipt: Receipt }) => {
    const getInitials = useInitials();

    if (receipt.kind === 'none') {
        return null;
    }

    if (receipt.kind === 'status') {
        return (
            <p
                className="mt-1 text-right text-[0.7rem] text-secondary-foreground"
                data-testid="receipt"
            >
                {receipt.text}
            </p>
        );
    }

    return (
        <div
            className="mt-1 flex justify-end -space-x-1.5"
            aria-label={`Seen by ${receipt.readers.map((reader) => reader.name).join(', ')}`}
            data-testid="receipt"
        >
            {receipt.readers.slice(0, 5).map((reader) => (
                <Avatar
                    key={reader.userId}
                    className="size-4 border border-background"
                    title={reader.name}
                >
                    <AvatarFallback className="bg-primary text-[0.45rem] font-bold text-primary-foreground">
                        {getInitials(reader.name)}
                    </AvatarFallback>
                </Avatar>
            ))}
            {receipt.readers.length > 5 ? (
                <span className="pl-2 text-[0.7rem] text-secondary-foreground">
                    +{receipt.readers.length - 5}
                </span>
            ) : null}
        </div>
    );
};

export default Receipts;

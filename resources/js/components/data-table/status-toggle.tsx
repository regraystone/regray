import { useState } from 'react';
import { toast } from 'sonner';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';

/** What the page's request reports back to the toggle. */
export type StatusToggleVisit = {
    /** The server refused: its messages, keyed by field. */
    onError: (errors: Record<string, string>) => void;
    /** It is over, either way. */
    onFinish: () => void;
};

/**
 * A row's on/off switch, such as whether an account may sign in.
 *
 * It flips at once and asks the server through `onToggle`; while that runs it
 * is locked, with a spinner beside it. A refusal flips it back and says why in
 * a toast; a success is the server's own toast. `label` names what a press
 * does, as "Deactivate Juan Dela Cruz". The state is written beside it
 * ("Active" / "Inactive"), so an off switch, or one that is locked, is never
 * read by its colour alone.
 */
const StatusToggle = ({
    checked,
    label,
    disabled = false,
    onToggle,
}: {
    checked: boolean;
    label: string;
    disabled?: boolean;
    onToggle: (next: boolean, visit: StatusToggleVisit) => void;
}) => {
    const [optimistic, setOptimistic] = useState<boolean | null>(null);
    const [pending, setPending] = useState(false);

    const toggle = (next: boolean) => {
        setOptimistic(next);
        setPending(true);
        onToggle(next, {
            onError: (errors) => {
                setOptimistic(null);
                toast.error(
                    Object.values(errors)[0] ??
                        'That could not be changed. Try again.',
                );
            },
            onFinish: () => {
                // The reloaded row is the truth from here.
                setOptimistic(null);
                setPending(false);
            },
        });
    };

    const on = optimistic ?? checked;

    return (
        <span className="inline-flex items-center gap-2">
            <Switch
                checked={on}
                onCheckedChange={toggle}
                disabled={disabled || pending}
                aria-label={label}
                aria-busy={pending}
            />
            <span
                aria-hidden="true"
                className={
                    on
                        ? 'w-14 text-sm text-foreground'
                        : 'w-14 text-sm text-muted-foreground'
                }
            >
                {on ? 'Active' : 'Inactive'}
            </span>
            {pending ? (
                <Spinner className="size-3.5 text-secondary-foreground motion-reduce:animate-none" />
            ) : null}
        </span>
    );
};

export default StatusToggle;

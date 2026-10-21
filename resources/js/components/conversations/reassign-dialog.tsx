import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { searchPeople, type Person } from '@/lib/message-api';

/**
 * The super-admin hands a Regray Team thread to another staff member. The
 * server checks the person may handle Regray Team threads.
 */
const ReassignDialog = ({
    open,
    onOpenChange,
    onReassign,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onReassign: (userId: number) => Promise<void>;
}) => {
    const [search, setSearch] = useState('');
    const [staff, setStaff] = useState<Person[]>([]);
    const [selected, setSelected] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            searchPeople(search)
                .then((people) => setStaff(people.staff))
                .catch(() => setStaff([]));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, search]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        Reassign conversation
                    </DialogTitle>
                    <DialogDescription>
                        Choose who handles this client from now on.
                    </DialogDescription>
                </DialogHeader>
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search staff"
                    aria-label="Search staff"
                    className="rounded-none"
                />
                <ul
                    className="max-h-60 overflow-y-auto"
                    role="radiogroup"
                    aria-label="Staff"
                >
                    {staff.map((person) => (
                        <li key={person.id}>
                            <label className="flex cursor-pointer items-center gap-2 px-1 py-2 text-sm">
                                <input
                                    type="radio"
                                    name="reassign-to"
                                    checked={selected === person.id}
                                    onChange={() => setSelected(person.id)}
                                />
                                {person.name}
                            </label>
                        </li>
                    ))}
                </ul>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={selected === null || saving}
                        onClick={async () => {
                            if (selected === null) {
                                return;
                            }

                            setSaving(true);

                            try {
                                await onReassign(selected);
                            } finally {
                                setSaving(false);
                            }
                        }}
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Reassign
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default ReassignDialog;

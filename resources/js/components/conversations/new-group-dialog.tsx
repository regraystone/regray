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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    ApiError,
    createGroup,
    searchPeople,
    type Person,
} from '@/lib/message-api';
import type { ConversationSummary } from '@/types/message';

/**
 * Start a group chat: a name, one or more staff, and exactly one client. The
 * creator is added as the group's admin by the server.
 */
const NewGroupDialog = ({
    open,
    onOpenChange,
    onCreated,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (conversation: ConversationSummary) => void;
}) => {
    const [name, setName] = useState('');
    const [search, setSearch] = useState('');
    const [people, setPeople] = useState<{
        staff: Person[];
        clients: Person[];
    }>({ staff: [], clients: [] });
    const [staffIds, setStaffIds] = useState<number[]>([]);
    const [clientId, setClientId] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            searchPeople(search)
                .then(setPeople)
                .catch(() => setPeople({ staff: [], clients: [] }));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, search]);

    useEffect(() => {
        if (!open) {
            setName('');
            setSearch('');
            setStaffIds([]);
            setClientId(null);
            setErrors({});
        }
    }, [open]);

    const toggleStaff = (id: number) =>
        setStaffIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );

    const submit = async () => {
        setSaving(true);
        setErrors({});

        try {
            const { conversation } = await createGroup({
                name,
                staffIds,
                clientId: clientId ?? 0,
            });
            onCreated(conversation);
            onOpenChange(false);
        } catch (error) {
            setErrors(
                error instanceof ApiError
                    ? error.errors
                    : { name: ['The group could not be created.'] },
            );
        } finally {
            setSaving(false);
        }
    };

    const error = (field: string) =>
        errors[field]?.[0] ? (
            <p className="text-xs text-destructive">{errors[field][0]}</p>
        ) : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        New group
                    </DialogTitle>
                    <DialogDescription>
                        A name, the staff who work on it, and one client.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-1.5">
                    <Label htmlFor="group-name">Group name</Label>
                    <Input
                        id="group-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        maxLength={150}
                        className="rounded-none"
                    />
                    {error('name')}
                </div>

                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search people"
                    aria-label="Search people"
                    className="rounded-none"
                />

                <fieldset className="space-y-1">
                    <legend className="text-sm font-bold">Staff</legend>
                    <ul className="max-h-40 overflow-y-auto">
                        {people.staff.map((person) => (
                            <li key={person.id}>
                                <label className="flex cursor-pointer items-center gap-2 py-1.5 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={staffIds.includes(person.id)}
                                        onChange={() => toggleStaff(person.id)}
                                    />
                                    {person.name}
                                </label>
                            </li>
                        ))}
                    </ul>
                    {error('staffIds')}
                </fieldset>

                <fieldset className="space-y-1">
                    <legend className="text-sm font-bold">Client</legend>
                    <ul className="max-h-40 overflow-y-auto">
                        {people.clients.map((person) => (
                            <li key={person.id}>
                                <label className="flex cursor-pointer items-center gap-2 py-1.5 text-sm">
                                    <input
                                        type="radio"
                                        name="group-client"
                                        checked={clientId === person.id}
                                        onChange={() => setClientId(person.id)}
                                    />
                                    {person.name}
                                </label>
                            </li>
                        ))}
                    </ul>
                    {error('clientId')}
                </fieldset>

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
                        onClick={() => void submit()}
                        disabled={
                            saving ||
                            name.trim() === '' ||
                            staffIds.length === 0 ||
                            clientId === null
                        }
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Create group
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default NewGroupDialog;

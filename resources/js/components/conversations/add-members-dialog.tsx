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
import {
    addGroupMembers,
    ApiError,
    searchAddableMembers,
    type Person,
} from '@/lib/message-api';
import type { ConversationSummary } from '@/types/message';

/**
 * A sales member adds accounting and logistics to an inquiry's group chat. The
 * server offers only those two roles, and only people not in the group yet.
 */
const AddMembersDialog = ({
    open,
    conversationId,
    onOpenChange,
    onAdded,
}: {
    open: boolean;
    conversationId: number;
    onOpenChange: (open: boolean) => void;
    onAdded: (conversation: ConversationSummary) => void;
}) => {
    const [search, setSearch] = useState('');
    const [people, setPeople] = useState<Person[]>([]);
    const [selected, setSelected] = useState<number[]>([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            setSearch('');
            setSelected([]);
            setError(null);

            return;
        }

        const timer = window.setTimeout(() => {
            searchAddableMembers(conversationId, search)
                .then((response) => setPeople(response.people))
                .catch(() => setPeople([]));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, search, conversationId]);

    const toggle = (id: number) =>
        setSelected((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );

    const submit = async () => {
        setSaving(true);
        setError(null);

        try {
            const { conversation } = await addGroupMembers(
                conversationId,
                selected,
            );
            onAdded(conversation);
            onOpenChange(false);
        } catch (failure) {
            setError(
                failure instanceof ApiError
                    ? (failure.errors.userIds?.[0] ?? failure.message)
                    : 'They could not be added.',
            );
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                        Add accounting or logistics
                    </DialogTitle>
                    <DialogDescription>
                        Bring in the people who handle payment and delivery for
                        this inquiry.
                    </DialogDescription>
                </DialogHeader>
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Search accounting and logistics"
                    aria-label="Search accounting and logistics"
                    className="rounded-none"
                />
                {people.length === 0 ? (
                    <p className="text-sm text-secondary-foreground">
                        Nobody left to add.
                    </p>
                ) : (
                    <ul className="max-h-52 overflow-y-auto">
                        {people.map((person) => (
                            <li key={person.id}>
                                <label className="flex cursor-pointer items-center gap-2 py-1.5 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(person.id)}
                                        onChange={() => toggle(person.id)}
                                    />
                                    {person.name}
                                </label>
                            </li>
                        ))}
                    </ul>
                )}
                {error !== null ? (
                    <p className="text-xs text-destructive">{error}</p>
                ) : null}
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
                        disabled={saving || selected.length === 0}
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Add
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};

export default AddMembersDialog;

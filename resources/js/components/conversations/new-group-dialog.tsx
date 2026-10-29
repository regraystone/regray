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
    type GroupPeople,
} from '@/lib/message-api';
import type { ConversationSummary } from '@/types/message';

const NO_PEOPLE: GroupPeople = {
    staff: [],
    clients: [],
    owners: [],
    inquiries: [],
};

/** A client and inquiry the dialog opens with, from the Leads page. */
export type GroupPreset = {
    client: { id: number; name: string };
    inquiry: { id: number; reference: string; name: string };
};

/**
 * Start a group chat for one client's inquiry: a name, the client, the
 * inquiry the group is bound to, and the staff who work on it. Marketing is
 * offered sales and technical only; every owner is always added, and shown as
 * such. The creator is added as the group's admin by the server.
 *
 * `preset` fills in the client and the inquiry (the Leads page's "Create
 * group chat"), and they cannot be changed there.
 */
const NewGroupDialog = ({
    open,
    onOpenChange,
    onCreated,
    preset = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (conversation: ConversationSummary) => void;
    preset?: GroupPreset | null;
}) => {
    const [name, setName] = useState('');
    const [search, setSearch] = useState('');
    const [people, setPeople] = useState<GroupPeople>(NO_PEOPLE);
    const [staffIds, setStaffIds] = useState<number[]>([]);
    const [clientId, setClientId] = useState<number | null>(null);
    const [inquiryId, setInquiryId] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (!open) {
            return;
        }

        const timer = window.setTimeout(() => {
            searchPeople(search, clientId)
                .then(setPeople)
                .catch(() => setPeople(NO_PEOPLE));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, search, clientId]);

    useEffect(() => {
        if (open) {
            setName(preset?.inquiry.name ?? '');
            setClientId(preset?.client.id ?? null);
            setInquiryId(preset?.inquiry.id ?? null);
        } else {
            setName('');
            setSearch('');
            setStaffIds([]);
            setClientId(null);
            setInquiryId(null);
            setErrors({});
        }
    }, [open, preset]);

    const toggleStaff = (id: number) =>
        setStaffIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );

    const chooseClient = (id: number) => {
        setClientId(id);
        setInquiryId(null);
    };

    const submit = async () => {
        setSaving(true);
        setErrors({});

        try {
            const { conversation } = await createGroup({
                name,
                staffIds,
                clientId: clientId ?? 0,
                inquiryId: inquiryId ?? 0,
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
                        A group chat for one client&rsquo;s inquiry: its status
                        updates are posted here.
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

                {preset === null ? (
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search people"
                        aria-label="Search people"
                        className="rounded-none"
                    />
                ) : null}

                {preset === null ? (
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
                                            onChange={() =>
                                                chooseClient(person.id)
                                            }
                                        />
                                        {person.name}
                                    </label>
                                </li>
                            ))}
                        </ul>
                        {error('clientId')}
                    </fieldset>
                ) : (
                    <p className="text-sm" data-testid="group-preset">
                        <span className="font-bold">{preset.client.name}</span>{' '}
                        · {preset.inquiry.reference} · {preset.inquiry.name}
                    </p>
                )}

                {preset === null && clientId !== null ? (
                    <fieldset className="space-y-1">
                        <legend className="text-sm font-bold">Inquiry</legend>
                        {people.inquiries.length === 0 ? (
                            <p className="text-xs text-secondary-foreground">
                                This client has no inquiries yet.
                            </p>
                        ) : (
                            <ul className="max-h-40 overflow-y-auto">
                                {people.inquiries.map((inquiry) => (
                                    <li key={inquiry.id}>
                                        <label className="flex cursor-pointer items-center gap-2 py-1.5 text-sm has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                                            <input
                                                type="radio"
                                                name="group-inquiry"
                                                checked={
                                                    inquiryId === inquiry.id
                                                }
                                                disabled={inquiry.hasGroup}
                                                onChange={() =>
                                                    setInquiryId(inquiry.id)
                                                }
                                            />
                                            <span>
                                                {inquiry.reference} ·{' '}
                                                {inquiry.name}
                                                {inquiry.hasGroup
                                                    ? ' (has a group)'
                                                    : ''}
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </fieldset>
                ) : null}
                {error('inquiryId')}

                <fieldset className="space-y-1">
                    <legend className="text-sm font-bold">Staff</legend>
                    {preset !== null ? (
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search staff"
                            aria-label="Search staff"
                            className="mb-1 rounded-none"
                        />
                    ) : null}
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

                {people.owners.length > 0 ? (
                    <div className="space-y-1" data-testid="group-owners">
                        <p className="text-sm font-bold">Always included</p>
                        <ul>
                            {people.owners.map((owner) => (
                                <li
                                    key={owner.id}
                                    className="flex items-center gap-2 py-1 text-sm text-secondary-foreground"
                                >
                                    <input
                                        type="checkbox"
                                        checked
                                        disabled
                                        aria-label={`${owner.name} is always included`}
                                    />
                                    {owner.name} (owner)
                                </li>
                            ))}
                        </ul>
                    </div>
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
                        disabled={
                            saving ||
                            name.trim() === '' ||
                            staffIds.length === 0 ||
                            clientId === null ||
                            inquiryId === null
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

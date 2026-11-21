import { useEffect, useState } from 'react';
import StaffPicker from '@/components/conversations/staff-picker';
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
    createWalkInGroup,
    searchPeople,
    searchWalkInInquiries,
    type GroupPeople,
    type WalkInInquiryOption,
} from '@/lib/message-api';
import { cn } from '@/lib/utils';
import type { ConversationSummary } from '@/types/message';

const NO_PEOPLE: GroupPeople = {
    sales: [],
    technical: [],
    clients: [],
    inquiries: [],
};

/** Who the group is for: a lead's client, or a walk-in who has since made an account. */
type GroupSource = 'lead' | 'walk-in';

const SOURCES: { value: GroupSource; label: string }[] = [
    { value: 'lead', label: 'From a lead' },
    { value: 'walk-in', label: 'For a direct inquiry' },
];

/** Why a walk-in inquiry cannot have a group yet, or null when it can. */
const walkInBlocker = (inquiry: WalkInInquiryOption): string | null => {
    if (inquiry.hasGroup) {
        return 'has a group';
    }

    if (!inquiry.isWon) {
        return 'not won yet';
    }

    return inquiry.account === null ? 'no account yet' : null;
};

/** A client and inquiry the dialog opens with, from the Leads page. */
export type GroupPreset = {
    client: { id: number; name: string };
    inquiry: { id: number; reference: string; name: string };
};

/**
 * Start a group chat for one client's inquiry: a name, the client, the
 * inquiry the group is bound to (only those the creator reads), and the staff
 * who work on it: exactly one sales and one technical member, each shown with
 * their active inquiry count. Putting them in the group assigns them to the
 * inquiry. Owners are never added. The creator is added as the group's admin
 * by the server.
 *
 * `preset` fills in the client and the inquiry (the Leads page's "Create
 * group chat"), and they cannot be changed there.
 *
 * Without a preset it asks first who the group is for: **From a lead** (the
 * above), or **For a walk-in inquiry**: one of the walk-in inquiries the
 * creator reads, once sales has won it and the walk-in has made a Regray
 * account with the same phone or email. The client is that account, found
 * by the server; the sales member is the inquiry's owner, fixed; the
 * creator picks the technical member.
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
    const [salesId, setSalesId] = useState<number | null>(null);
    const [technicalId, setTechnicalId] = useState<number | null>(null);
    const [clientId, setClientId] = useState<number | null>(null);
    const [inquiryId, setInquiryId] = useState<number | null>(null);
    const [source, setSource] = useState<GroupSource>('lead');
    const [walkInSearch, setWalkInSearch] = useState('');
    const [walkIns, setWalkIns] = useState<WalkInInquiryOption[]>([]);
    const [walkIn, setWalkIn] = useState<WalkInInquiryOption | null>(null);
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
        if (!open || source !== 'walk-in') {
            return;
        }

        const timer = window.setTimeout(() => {
            searchWalkInInquiries(walkInSearch)
                .then((response) => setWalkIns(response.inquiries))
                .catch(() => setWalkIns([]));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [open, source, walkInSearch]);

    useEffect(() => {
        if (open) {
            setName(preset?.inquiry.name ?? '');
            setClientId(preset?.client.id ?? null);
            setInquiryId(preset?.inquiry.id ?? null);
        } else {
            setName('');
            setSearch('');
            setSalesId(null);
            setTechnicalId(null);
            setClientId(null);
            setInquiryId(null);
            setSource('lead');
            setWalkInSearch('');
            setWalkIn(null);
            setErrors({});
        }
    }, [open, preset]);

    const chooseSource = (next: GroupSource) => {
        setSource(next);
        setSalesId(null);
        setClientId(null);
        setInquiryId(null);
        setWalkIn(null);
        setErrors({});
    };

    const chooseWalkIn = (inquiry: WalkInInquiryOption) => {
        setWalkIn(inquiry);
        setName((current) => (current.trim() === '' ? inquiry.name : current));
    };

    const isWalkIn = preset === null && source === 'walk-in';

    const chooseClient = (id: number) => {
        setClientId(id);
        setInquiryId(null);
    };

    const submit = async () => {
        setSaving(true);
        setErrors({});

        try {
            const { conversation } = isWalkIn
                ? await createWalkInGroup({
                      name,
                      staffIds: [walkIn?.ownerId ?? null, technicalId].filter(
                          (id): id is number => id !== null,
                      ),
                      inquiryId: walkIn?.id ?? 0,
                  })
                : await createGroup({
                      name,
                      staffIds: [salesId, technicalId].filter(
                          (id): id is number => id !== null,
                      ),
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

                {preset === null ? (
                    <div
                        role="group"
                        aria-label="Who the group is for"
                        className="grid grid-cols-2 border border-sidebar-border"
                        data-testid="group-source"
                    >
                        {SOURCES.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                aria-pressed={source === option.value}
                                onClick={() => chooseSource(option.value)}
                                className={cn(
                                    'px-3 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring',
                                    source === option.value
                                        ? 'bg-foreground text-background'
                                        : 'bg-background text-foreground hover:bg-muted',
                                )}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                ) : null}

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

                {isWalkIn ? (
                    <fieldset className="space-y-1">
                        <legend className="text-sm font-bold">
                            Direct inquiry
                        </legend>
                        <Input
                            type="search"
                            value={walkInSearch}
                            onChange={(event) =>
                                setWalkInSearch(event.target.value)
                            }
                            placeholder="Search by reference, project or name"
                            aria-label="Search direct inquiries"
                            className="mb-1 rounded-none"
                        />
                        {walkIns.length === 0 ? (
                            <p className="text-xs text-secondary-foreground">
                                No direct inquiries you can see match.
                            </p>
                        ) : (
                            <ul
                                className="max-h-48 overflow-y-auto"
                                data-testid="group-walk-in-inquiries"
                            >
                                {walkIns.map((inquiry) => {
                                    const blocker = walkInBlocker(inquiry);

                                    return (
                                        <li key={inquiry.id}>
                                            <label className="flex cursor-pointer items-start gap-2 py-1.5 text-sm has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
                                                <input
                                                    type="radio"
                                                    name="group-walk-in"
                                                    className="mt-1"
                                                    checked={
                                                        walkIn?.id ===
                                                        inquiry.id
                                                    }
                                                    disabled={blocker !== null}
                                                    onChange={() =>
                                                        chooseWalkIn(inquiry)
                                                    }
                                                />
                                                <span className="min-w-0">
                                                    <span className="block">
                                                        {inquiry.reference} ·{' '}
                                                        {inquiry.name}
                                                        {blocker !== null
                                                            ? ` (${blocker})`
                                                            : ''}
                                                    </span>
                                                    <span className="block text-xs text-secondary-foreground">
                                                        {inquiry.client}
                                                    </span>
                                                </span>
                                            </label>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        {error('inquiryId')}
                    </fieldset>
                ) : null}

                {isWalkIn && walkIn !== null && walkIn.account !== null ? (
                    <dl
                        className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm"
                        data-testid="group-walk-in-people"
                    >
                        <dt className="font-bold">Client</dt>
                        <dd className="break-words">
                            {walkIn.account.name}{' '}
                            <span className="text-secondary-foreground">
                                ({walkIn.account.email})
                            </span>
                        </dd>
                        <dt className="font-bold">Sales</dt>
                        <dd>{walkIn.owner ?? 'Its owner'} (owner)</dd>
                    </dl>
                ) : null}

                {preset === null && !isWalkIn ? (
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search people"
                        aria-label="Search people"
                        className="rounded-none"
                    />
                ) : null}

                {isWalkIn ? null : preset === null ? (
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
                                        {person.label}
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

                {preset === null && !isWalkIn && clientId !== null ? (
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
                                                disabled={
                                                    inquiry.hasGroup ||
                                                    !inquiry.isWon
                                                }
                                                onChange={() =>
                                                    setInquiryId(inquiry.id)
                                                }
                                            />
                                            <span>
                                                {inquiry.reference} ·{' '}
                                                {inquiry.name}
                                                {inquiry.hasGroup
                                                    ? ' (has a group)'
                                                    : !inquiry.isWon
                                                      ? ' (not won yet)'
                                                      : ''}
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </fieldset>
                ) : null}
                {isWalkIn ? null : error('inquiryId')}

                {preset !== null || isWalkIn ? (
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search staff"
                        aria-label="Search staff"
                        className="rounded-none"
                    />
                ) : null}
                {isWalkIn ? null : (
                    <StaffPicker
                        name="group-sales"
                        legend="Sales"
                        people={people.sales}
                        selected={salesId}
                        onSelect={setSalesId}
                        empty="Nobody from sales matches."
                    />
                )}
                <StaffPicker
                    name="group-technical"
                    legend="Technical"
                    people={people.technical}
                    selected={technicalId}
                    onSelect={setTechnicalId}
                    empty="Nobody from technical matches."
                />
                {error('staffIds')}

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
                            technicalId === null ||
                            (isWalkIn
                                ? walkIn === null
                                : salesId === null ||
                                  clientId === null ||
                                  inquiryId === null)
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

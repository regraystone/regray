import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { decision as decisionRoute } from '@/routes/inquiries';
import { store as storeHandler } from '@/routes/inquiries/handlers';
import type { WalkInDetails, WalkInRole } from '@/types/quote';

const ROLE_LABELS: Record<WalkInRole, string> = {
    technical: 'Technical',
    accounting: 'Accounting',
    logistics: 'Logistics',
};

const ROLES: WalkInRole[] = ['technical', 'accounting', 'logistics'];

/**
 * One role's Assign control: the people this reader may add from it, and the
 * button that adds the one chosen. The server sends a role only where this
 * reader may assign from it now, so the control is shown only there.
 */
const AssignRole = ({
    reference,
    role,
    people,
}: {
    reference: string;
    role: WalkInRole;
    people: Array<{ id: number; name: string }>;
}) => {
    const [userId, setUserId] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const id = `walk-in-assign-${role}`;

    const assign = () => {
        setSaving(true);
        setError(null);
        router.post(
            storeHandler.url(reference),
            { userId: Number(userId) },
            {
                preserveScroll: true,
                onSuccess: () => setUserId(''),
                onError: (errors) =>
                    setError(errors.userId ?? 'They could not be assigned.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-1.5" data-testid={`walk-in-assign-${role}`}>
            <Label htmlFor={id}>{ROLE_LABELS[role]}</Label>
            <div className="flex gap-2">
                <Select value={userId} onValueChange={setUserId}>
                    <SelectTrigger id={id} className="min-w-0 flex-1">
                        <SelectValue
                            placeholder={
                                people.length === 0
                                    ? 'No one left to assign'
                                    : 'Choose someone'
                            }
                        />
                    </SelectTrigger>
                    <SelectContent>
                        {people.map((person) => (
                            <SelectItem
                                key={person.id}
                                value={String(person.id)}
                            >
                                {person.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    onClick={assign}
                    disabled={saving || userId === ''}
                >
                    Assign
                </Button>
            </div>
            {error !== null ? (
                <p className="text-xs text-destructive" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
};

/** Marketing's decision on the walk-in's inquiry, with the note it needs. */
const Decide = ({ reference }: { reference: string }) => {
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const decide = (to: 'won' | 'lost') => {
        setSaving(true);
        setErrors({});
        router.post(
            decisionRoute.url(reference),
            { to, note },
            {
                preserveScroll: true,
                onSuccess: () => setNote(''),
                onError: setErrors,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="space-y-1.5" data-testid="walk-in-decision">
            <Label htmlFor="walk-in-decision-note">Marketing decision</Label>
            <Textarea
                id="walk-in-decision-note"
                value={note}
                onChange={(event) => setNote(event.target.value)}
                maxLength={500}
                placeholder="Why it is won or lost"
                className="rounded-none"
            />
            {errors.note || errors.to ? (
                <p className="text-xs text-destructive" role="alert">
                    {errors.note ?? errors.to}
                </p>
            ) : null}
            <div className="flex gap-2">
                <Button
                    type="button"
                    onClick={() => decide('won')}
                    disabled={saving || note.trim() === ''}
                >
                    Mark won
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => decide('lost')}
                    disabled={saving || note.trim() === ''}
                >
                    Mark lost
                </Button>
            </div>
        </div>
    );
};

/**
 * A walk-in's inquiry, for the staff who read it: the walk-in's name, phone
 * and email, its sales owner, and the controls this reader may use: Assign
 * for each role they may add from now (technical for the owner and the
 * marketing member who opened it; accounting and logistics for the owner once
 * sales has won it), and marketing's decision for the one who opened it.
 */
const WalkInInquiryCard = ({
    reference,
    walkIn,
}: {
    reference: string;
    walkIn: WalkInDetails;
}) => {
    const roles = ROLES.filter((role) => walkIn.assignable[role] !== undefined);

    return (
        <section
            aria-labelledby="inquiry-walk-in-title"
            className="overflow-hidden rounded-md border border-sidebar-border bg-background"
            data-testid="inquiry-walk-in"
        >
            <h2 className="px-4 py-3 sm:px-5">
                <span
                    id="inquiry-walk-in-title"
                    className="block truncate text-sm font-bold tracking-[0.08em] uppercase"
                >
                    Direct client
                </span>
            </h2>

            <div className="space-y-4 border-t border-sidebar-border px-4 py-4 sm:px-5">
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                    <dt className="text-secondary-foreground">Name</dt>
                    <dd className="font-bold break-words">
                        {walkIn.client.name}
                    </dd>
                    <dt className="text-secondary-foreground">Phone</dt>
                    <dd className="break-words">
                        {walkIn.client.phone ?? 'Not provided'}
                    </dd>
                    {walkIn.client.email !== null ? (
                        <>
                            <dt className="text-secondary-foreground">Email</dt>
                            <dd className="break-words">
                                {walkIn.client.email}
                            </dd>
                        </>
                    ) : null}
                    <dt className="text-secondary-foreground">Owner</dt>
                    <dd className="break-words">{walkIn.owner ?? '—'}</dd>
                    {walkIn.marketingStatus !== null ? (
                        <>
                            <dt className="text-secondary-foreground">
                                Marketing
                            </dt>
                            <dd className="capitalize">
                                {walkIn.marketingStatus}
                            </dd>
                        </>
                    ) : null}
                </dl>

                {roles.map((role) => (
                    <AssignRole
                        key={role}
                        reference={reference}
                        role={role}
                        people={walkIn.assignable[role] ?? []}
                    />
                ))}

                {walkIn.canDecide ? <Decide reference={reference} /> : null}
            </div>
        </section>
    );
};

export default WalkInInquiryCard;

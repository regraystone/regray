import { Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ApiError } from '@/lib/message-api';
import {
    createDirectClient,
    type DirectClientInput,
    type DirectClientResult,
} from '@/lib/staff-clients-api';
import {
    CONTACT_KIND_LABELS,
    CONTACT_KINDS,
    type ContactKind,
} from '@/lib/staff-inquire';

/** A further way to reach the client; the first contact is always the phone. */
const NEW_CONTACT = { kind: 'email' as ContactKind, value: '' };

/** A contact kind mid-sentence: "this email", but "this Viber" and "this WhatsApp". */
const contactWord = (kind: ContactKind): string =>
    kind === 'phone' || kind === 'email'
        ? CONTACT_KIND_LABELS[kind].toLowerCase()
        : CONTACT_KIND_LABELS[kind];

/**
 * Take down a direct (walk-in) client: a name, an optional company and
 * address, and how to reach them (a phone number first, then up to seven
 * more). When one of the contacts is already another client's, nothing is
 * created and that client is offered instead (`use`), so the same client is
 * not added twice.
 *
 * Shared by the staff Inquire flow (Direct) and Book an appointment (Walk-in).
 * `header` draws the heading above it, told whether the duplicate is being
 * offered. Its submit never reaches a form it is drawn inside, as when it
 * opens in a dialog over the booking form.
 */
const DirectClientForm = ({
    onUse,
    header,
    useFor,
}: {
    onUse: (result: DirectClientResult) => void;
    header?: (offeringExisting: boolean) => ReactNode;
    /** What the duplicate is to be used for: "Open the inquiry for them". */
    useFor: string;
}) => {
    const [form, setForm] = useState<DirectClientInput>({
        fullName: '',
        company: '',
        address: '',
        contacts: [{ kind: 'phone', value: '' }],
    });
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [saving, setSaving] = useState(false);
    const [existing, setExisting] = useState<DirectClientResult | null>(null);

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setSaving(true);
        setErrors({});

        try {
            const result = await createDirectClient(form);

            if (result.existing) {
                setExisting(result);
            } else {
                onUse(result);
            }
        } catch (error) {
            setErrors(
                error instanceof ApiError
                    ? error.errors
                    : { fullName: ['The client could not be saved.'] },
            );
        } finally {
            setSaving(false);
        }
    };

    const setContact = (
        index: number,
        change: Partial<DirectClientInput['contacts'][number]>,
    ) =>
        setForm((current) => ({
            ...current,
            contacts: current.contacts.map((contact, at) =>
                at === index ? { ...contact, ...change } : contact,
            ),
        }));

    const error = (field: string) =>
        errors[field]?.[0] ? (
            <p className="text-xs text-destructive">{errors[field][0]}</p>
        ) : null;

    if (existing !== null) {
        return (
            <div>
                {header?.(true)}
                <div
                    className="space-y-4 rounded-md border border-sidebar-border p-4"
                    data-testid="direct-client-duplicate"
                >
                    <p className="text-sm">
                        <span className="font-bold">
                            {existing.client.fullName}
                        </span>
                        {existing.client.company
                            ? ` · ${existing.client.company}`
                            : ''}{' '}
                        already has this{' '}
                        {existing.matched
                            ? `${contactWord(existing.matched.kind)} (${existing.matched.value})`
                            : 'contact'}
                        . {useFor} instead of adding the same client twice.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => onUse(existing)}>
                            Use {existing.client.fullName}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setExisting(null)}
                        >
                            Change the details
                        </Button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <form
            onSubmit={(event) => void submit(event)}
            noValidate
            data-testid="direct-client-form"
        >
            {header?.(false)}

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5 sm:col-span-2">
                    <Label htmlFor="direct-full-name">Full name</Label>
                    <Input
                        id="direct-full-name"
                        value={form.fullName}
                        onChange={(event) =>
                            setForm({ ...form, fullName: event.target.value })
                        }
                        maxLength={150}
                        className="rounded-none"
                    />
                    {error('fullName')}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="direct-company">Company (optional)</Label>
                    <Input
                        id="direct-company"
                        value={form.company}
                        onChange={(event) =>
                            setForm({ ...form, company: event.target.value })
                        }
                        maxLength={150}
                        className="rounded-none"
                    />
                    {error('company')}
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="direct-address">Address (optional)</Label>
                    <Input
                        id="direct-address"
                        value={form.address}
                        onChange={(event) =>
                            setForm({ ...form, address: event.target.value })
                        }
                        maxLength={255}
                        className="rounded-none"
                    />
                    {error('address')}
                </div>
            </div>

            <fieldset className="mt-6 space-y-2">
                <legend className="mb-2 text-sm font-bold">
                    How to reach them
                </legend>
                {form.contacts.map((contact, index) => (
                    <div key={index} className="space-y-1">
                        {index === 0 ? (
                            <Label htmlFor="direct-contact-0">
                                Phone number
                                <span
                                    className="text-destructive"
                                    aria-label="required"
                                >
                                    {' '}
                                    *
                                </span>
                            </Label>
                        ) : null}
                        <div className="flex items-center gap-2">
                            {/* The first contact is always the phone number, which is required. */}
                            {index === 0 ? null : (
                                <Select
                                    value={contact.kind}
                                    onValueChange={(kind) =>
                                        setContact(index, {
                                            kind: kind as ContactKind,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        aria-label={`Contact ${index + 1} kind`}
                                        className="w-32 shrink-0 rounded-none text-base data-[size=default]:h-11"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CONTACT_KINDS.map((kind) => (
                                            <SelectItem
                                                key={kind}
                                                value={kind}
                                                className="rounded-none"
                                            >
                                                {CONTACT_KIND_LABELS[kind]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            <Input
                                id={`direct-contact-${index}`}
                                value={contact.value}
                                onChange={(event) =>
                                    setContact(index, {
                                        value: event.target.value,
                                    })
                                }
                                type={
                                    contact.kind === 'email' ? 'email' : 'tel'
                                }
                                aria-label={
                                    index === 0
                                        ? 'Phone number'
                                        : `Contact ${index + 1}`
                                }
                                maxLength={255}
                                className="min-w-0 rounded-none"
                            />
                            {index > 0 ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove contact ${index + 1}`}
                                    onClick={() =>
                                        setForm({
                                            ...form,
                                            contacts: form.contacts.filter(
                                                (_, at) => at !== index,
                                            ),
                                        })
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            ) : null}
                        </div>
                        {error(`contacts.${index}.value`)}
                        {error(`contacts.${index}.kind`)}
                    </div>
                ))}
                {error('contacts')}
                {form.contacts.length < 8 ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setForm({
                                ...form,
                                contacts: [
                                    ...form.contacts,
                                    { ...NEW_CONTACT },
                                ],
                            })
                        }
                    >
                        <Plus aria-hidden />
                        Add another contact
                    </Button>
                ) : null}
            </fieldset>

            <div className="mt-8 flex justify-end">
                <Button type="submit" disabled={saving}>
                    {saving ? (
                        <Spinner className="motion-reduce:animate-none" />
                    ) : null}
                    Continue
                </Button>
            </div>
        </form>
    );
};

export default DirectClientForm;

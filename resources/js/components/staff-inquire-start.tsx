import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import EmptyState from '@/components/empty-state';
import InquiryLeadPicker from '@/components/inquiry-lead-picker';
import PageHeading from '@/components/page-heading';
import StaffInquireDialog from '@/components/staff-inquire-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ApiError } from '@/lib/message-api';
import {
    createDirectClient,
    searchClientAccounts,
    type ClientAccount,
    type DirectClientInput,
    type DirectClientResult,
} from '@/lib/staff-clients-api';
import {
    CONTACT_KIND_LABELS,
    CONTACT_KINDS,
    type ContactKind,
    type StaffInquireKind,
} from '@/lib/staff-inquire';
import { index as inquiryIndex } from '@/routes/inquiry';
import { store as directInquiriesStore } from '@/routes/inquiry/direct-clients/inquiries';
import { store as leadInquiriesStore } from '@/routes/leads/inquiries';
import type { InquiryLeadOption, StaffInquireTarget } from '@/types/inquiry';

/** A lead, as the stepper's target: sent under that lead. */
export const leadTarget = (lead: InquiryLeadOption): StaffInquireTarget => ({
    kind: 'platform',
    name: lead.client,
    detail: lead.reference,
    storeUrl: leadInquiriesStore.url(lead.reference),
});

/**
 * What staff answer before the stepper: who the inquiry is for.
 *
 * - No branch yet: the Platform or Direct (walk-in) question.
 * - **Platform**: find the client's account, then pick one of that client's
 *   leads this reader may use (the server sends only those: sales won leads,
 *   marketing its own idle or won leads).
 * - **Direct**: take the client down. When one of the contacts is already
 *   another client's, that client is offered instead of a second one.
 *
 * The branch is kept in the URL (`?kind=`) like the lead (`?lead=`).
 */
const StaffInquireStart = ({
    kind,
    leads,
    onKind,
    onTarget,
}: {
    kind: StaffInquireKind | null;
    leads: InquiryLeadOption[];
    onKind: (kind: StaffInquireKind | null) => void;
    onTarget: (target: StaffInquireTarget) => void;
}) => {
    if (kind === null) {
        return (
            <div className="mx-auto w-full max-w-3xl">
                <PageHeading
                    title="Inquire"
                    description="Who is this inquiry for?"
                />
                <StaffInquireDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            router.visit(inquiryIndex.url());
                        }
                    }}
                    onChoose={onKind}
                />
            </div>
        );
    }

    // Back from a branch leaves for the list, as closing the question does:
    // the question is already answered, so asking it again would trap the reader.
    const leave = () => router.visit(inquiryIndex.url());

    return kind === 'platform' ? (
        <PlatformStart leads={leads} onBack={leave} onTarget={onTarget} />
    ) : (
        <DirectStart onBack={leave} onTarget={onTarget} />
    );
};

const BackButton = ({ onClick }: { onClick: () => void }) => (
    <Button type="button" variant="outline" onClick={onClick}>
        <ArrowLeft aria-hidden />
        Back
    </Button>
);

const PlatformStart = ({
    leads,
    onBack,
    onTarget,
}: {
    leads: InquiryLeadOption[];
    onBack: () => void;
    onTarget: (target: StaffInquireTarget) => void;
}) => {
    const [search, setSearch] = useState('');
    const [accounts, setAccounts] = useState<ClientAccount[]>([]);
    const [account, setAccount] = useState<ClientAccount | null>(null);

    useEffect(() => {
        if (account !== null) {
            return;
        }

        const timer = window.setTimeout(() => {
            searchClientAccounts(search)
                .then((response) => setAccounts(response.accounts))
                .catch(() => setAccounts([]));
        }, 200);

        return () => window.clearTimeout(timer);
    }, [search, account]);

    const accountLeads = useMemo(
        () =>
            account === null
                ? []
                : leads.filter((lead) => lead.clientId === account.id),
        [leads, account],
    );

    if (account !== null) {
        return (
            <InquiryLeadPicker
                leads={accountLeads}
                description={`Choose ${account.name}'s lead`}
                emptyDescription={`${account.name} has no lead you can open an inquiry from. Sales can use won leads; marketing its own idle or won leads.`}
                action={<BackButton onClick={() => setAccount(null)} />}
                onPick={(lead) => onTarget(leadTarget(lead))}
            />
        );
    }

    return (
        <div className="mx-auto w-full max-w-3xl">
            <PageHeading
                title="Inquire"
                description="Find the client's Regray account"
                action={<BackButton onClick={onBack} />}
            />
            <Input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by name or email"
                aria-label="Search client accounts"
                className="mb-4 rounded-none"
            />
            {accounts.length === 0 ? (
                <EmptyState
                    title="No client accounts found"
                    description="Try another name or email. A client with no account is a Direct (walk-in) client."
                />
            ) : (
                <ul
                    className="divide-y divide-sidebar-border rounded-md border border-sidebar-border"
                    data-testid="client-account-picker"
                >
                    {accounts.map((item) => (
                        <li key={item.id}>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setAccount(item)}
                                className="flex h-auto w-full items-center justify-between gap-3 rounded-none px-4 py-3 text-left normal-case"
                            >
                                <span className="min-w-0">
                                    <span className="block truncate font-semibold">
                                        {item.name}
                                    </span>
                                    <span className="text-xs font-normal tracking-normal text-secondary-foreground">
                                        {item.email}
                                    </span>
                                </span>
                                <ArrowRight aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
};

/** A further way to reach the client; the first contact is always the phone. */
const NEW_CONTACT = { kind: 'email' as ContactKind, value: '' };

/** A contact kind mid-sentence: "this email", but "this Viber" and "this WhatsApp". */
const contactWord = (kind: ContactKind): string =>
    kind === 'phone' || kind === 'email'
        ? CONTACT_KIND_LABELS[kind].toLowerCase()
        : CONTACT_KIND_LABELS[kind];

const DirectStart = ({
    onBack,
    onTarget,
}: {
    onBack: () => void;
    onTarget: (target: StaffInquireTarget) => void;
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

    const use = (result: DirectClientResult) =>
        onTarget({
            kind: 'direct',
            name: result.client.fullName,
            detail: 'Direct (walk-in)',
            storeUrl: directInquiriesStore.url(result.client.id),
        });

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setSaving(true);
        setErrors({});

        try {
            const result = await createDirectClient(form);

            if (result.existing) {
                setExisting(result);
            } else {
                use(result);
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
            <div className="mx-auto w-full max-w-3xl">
                <PageHeading
                    title="Inquire"
                    description="This client is already on file"
                />
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
                        . Open the inquiry for them instead of adding the same
                        client twice.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => use(existing)}>
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
            className="mx-auto w-full max-w-3xl"
            onSubmit={(event) => void submit(event)}
            noValidate
            data-testid="direct-client-form"
        >
            <PageHeading
                title="Inquire"
                description="Take down the walk-in client"
                action={<BackButton onClick={onBack} />}
            />

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
                                <select
                                    value={contact.kind}
                                    onChange={(event) =>
                                        setContact(index, {
                                            kind: event.target
                                                .value as ContactKind,
                                        })
                                    }
                                    aria-label={`Contact ${index + 1} kind`}
                                    className="h-9 shrink-0 border border-input bg-background px-2 text-sm"
                                >
                                    {CONTACT_KINDS.map((kind) => (
                                        <option key={kind} value={kind}>
                                            {CONTACT_KIND_LABELS[kind]}
                                        </option>
                                    ))}
                                </select>
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

export default StaffInquireStart;

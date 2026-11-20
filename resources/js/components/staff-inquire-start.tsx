import { router } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import DirectClientForm from '@/components/direct-client-form';
import EmptyState from '@/components/empty-state';
import InquiryLeadPicker from '@/components/inquiry-lead-picker';
import PageHeading from '@/components/page-heading';
import StaffInquireDialog from '@/components/staff-inquire-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    searchClientAccounts,
    type ClientAccount,
} from '@/lib/staff-clients-api';
import type { StaffInquireKind } from '@/lib/staff-inquire';
import { index as inquiryIndex } from '@/routes/inquiry';
import { store as directInquiriesStore } from '@/routes/inquiry/direct-clients/inquiries';
import { store as leadInquiriesStore } from '@/routes/leads/inquiries';
import type {
    InquiryLeadOption,
    StaffInquireTarget,
    WalkInOwnerOption,
} from '@/types/inquiry';

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
 * - **Platform** (marketing's; sales is never offered it): find the client's
 *   account, then pick one of that client's leads this reader may use (the
 *   server sends only those: marketing's own idle or won leads).
 * - **Direct**: take the client down. When one of the contacts is already
 *   a walk-in's on file, that walk-in is offered instead of a second one,
 *   and the inquiry is opened on it. Staff who are not sales then name the
 *   sales member who owns the inquiry (`walkInOwners`); sales owns what it
 *   opens and is not asked.
 *
 * The branch is kept in the URL (`?kind=`) like the lead (`?lead=`).
 */
const StaffInquireStart = ({
    kind,
    leads,
    walkInOwners = null,
    onKind,
    onTarget,
}: {
    kind: StaffInquireKind | null;
    leads: InquiryLeadOption[];
    walkInOwners?: WalkInOwnerOption[] | null;
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
        <DirectStart owners={walkInOwners} onBack={leave} onTarget={onTarget} />
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
                emptyDescription={`${account.name} has no lead you can open an inquiry from. You can use your own idle or won leads.`}
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
                    description="Try another name or email. A client with no account is a direct client."
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

/**
 * Direct (walk-in): take the client down, then, for staff who are not sales,
 * name its sales owner, then send the inquiry for them. The owner goes with
 * the inquiry as `ownerId`.
 */
const DirectStart = ({
    owners,
    onBack,
    onTarget,
}: {
    owners: WalkInOwnerOption[] | null;
    onBack: () => void;
    onTarget: (target: StaffInquireTarget) => void;
}) => {
    const [client, setClient] = useState<{ id: number; name: string } | null>(
        null,
    );

    const open = (
        chosen: { id: number; name: string },
        owner: WalkInOwnerOption | null,
    ) =>
        onTarget({
            kind: 'direct',
            name: chosen.name,
            detail: owner === null ? 'Direct' : `Direct · Owner: ${owner.name}`,
            storeUrl: directInquiriesStore.url(
                chosen.id,
                owner === null
                    ? undefined
                    : { query: { ownerId: String(owner.id) } },
            ),
        });

    if (client !== null && owners !== null) {
        return (
            <div className="mx-auto w-full max-w-3xl">
                <PageHeading
                    title="Inquire"
                    description={`Who in sales owns ${client.name}'s inquiry?`}
                    action={<BackButton onClick={() => setClient(null)} />}
                />
                {owners.length === 0 ? (
                    <EmptyState
                        title="No one in sales"
                        description="A direct client's inquiry is owned by a sales member. Ask an admin to add one."
                    />
                ) : (
                    <ul
                        className="divide-y divide-sidebar-border rounded-md border border-sidebar-border"
                        data-testid="walk-in-owner-picker"
                    >
                        {owners.map((owner) => (
                            <li key={owner.id}>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => open(client, owner)}
                                    className="flex h-auto w-full items-center justify-between gap-3 rounded-none px-4 py-3 text-left normal-case"
                                >
                                    <span className="block truncate font-semibold">
                                        {owner.name}
                                    </span>
                                    <ArrowRight aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-3xl">
            <DirectClientForm
                useFor="Open the inquiry for them"
                onUse={(result) => {
                    const chosen = {
                        id: result.client.id,
                        name: result.client.fullName,
                    };

                    if (owners === null) {
                        open(chosen, null);
                    } else {
                        setClient(chosen);
                    }
                }}
                header={(offeringExisting) =>
                    offeringExisting ? (
                        <PageHeading
                            title="Inquire"
                            description="This client is already on file"
                        />
                    ) : (
                        <PageHeading
                            title="Inquire"
                            description="Take down the direct client"
                            action={<BackButton onClick={onBack} />}
                        />
                    )
                }
            />
        </div>
    );
};

export default StaffInquireStart;

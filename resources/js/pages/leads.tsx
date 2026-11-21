import { Link as InertiaLink, router, usePage } from '@inertiajs/react';
import { FilePlus, MessageCirclePlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import NewGroupDialog, {
    type GroupPreset,
} from '@/components/conversations/new-group-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilters,
    type DataTableFilterOption,
} from '@/components/data-table/data-table';
import RowActions from '@/components/data-table/row-actions';
import {
    LeadStatusBadge,
    LeadTallyBadges,
} from '@/components/lead-status-badge';
import PageHeader from '@/components/page-header';
import StatusUpdateDialog from '@/components/status-update-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { TableMeta } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import {
    leadStatusLabel,
    LEAD_STATUSES,
    type LeadStatus,
    type LeadTally,
} from '@/lib/lead-statuses';
import { inquire as inquiryInquire } from '@/routes/inquiry';
import { status as leadInquiryStatusRoute } from '@/routes/leads/inquiries';
import { index as inboxIndex } from '@/routes/inbox';

type LeadInquiry = {
    id: number;
    reference: string;
    name: string;
    /** Marketing's decision on it; idle while it waits for one. */
    marketingStatus: LeadStatus;
    hasGroup: boolean;
    /** Whether this reader may mark it won or lost now. */
    canDecide: boolean;
    /** Whether this reader may make its group chat: only once it is won, and while it has none. */
    canCreateGroup: boolean;
};

/** One lead as a row of the Leads page, as `LeadResource` sends it. */
type LeadRow = {
    reference: string;
    /** The tally of marketing's decisions on its inquiries. */
    tally: LeadTally;
    createdAt: string;
    client: { id: number; name: string };
    /** The client's phone number. */
    contact: string | null;
    email: string | null;
    marketingUser: string;
    inquiries: LeadInquiry[];
    /** The lead opened (no inquiry), then each decision, naming its inquiry. */
    history: Array<{
        status: LeadStatus;
        inquiry: string | null;
        note: string | null;
        at: string;
    }>;
    /** The actions this reader has on this row, decided by the server. */
    actions: string[];
    canCreateInquiry: boolean;
};

type LeadsPageProps = {
    leads: { data: LeadRow[]; meta: TableMeta };
    filters: DataTableFilters & { status?: string };
    canCreateGroup: boolean;
};

/**
 * The Leads page: marketing's own clients, newest first, each with the tally
 * of its inquiries' decisions. View opens the lead: its log, and its
 * inquiries, each with marketing's decision, Won and Lost (each with a
 * required note) on one still waiting, and a way to open a group chat for it
 * or a new inquiry for the client.
 */
const Leads = () => {
    const { props } = usePage<LeadsPageProps>();
    const [viewing, setViewing] = useState<LeadRow | null>(null);
    const [editing, setEditing] = useState<LeadRow | null>(null);
    const [deciding, setDeciding] = useState<{
        lead: LeadRow;
        inquiry: LeadInquiry;
        to: 'won' | 'lost';
    } | null>(null);
    const [groupPreset, setGroupPreset] = useState<GroupPreset | null>(null);
    const statusOptions: DataTableFilterOption[] = [
        { value: 'all', label: 'All statuses' },
        ...LEAD_STATUSES.map((status) => ({
            value: status,
            label: leadStatusLabel(status),
        })),
    ];

    const columns: DataTableColumn<LeadRow>[] = [
        {
            key: 'date',
            header: 'Date',
            cell: (row) => formatDate(row.createdAt),
        },
        {
            key: 'client',
            header: 'Client',
            cell: (row) => row.client.name,
        },
        {
            key: 'contact',
            header: 'Contact',
            cell: (row) => row.contact ?? '—',
        },
        {
            key: 'email',
            header: 'Email',
            cell: (row) => (
                <span className="block truncate" title={row.email ?? undefined}>
                    {row.email ?? '—'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => <LeadTallyBadges tally={row.tally} />,
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            cell: (row) => (
                <RowActions
                    actions={row.actions}
                    subject={row.client.name}
                    handlers={{
                        view: () => setViewing(row),
                        edit: () => setEditing(row),
                    }}
                />
            ),
        },
    ];

    return (
        <div className="w-full min-w-0">
            <PageHeader
                title="Leads"
                subtitle="Your clients, and where each one stands"
            />

            <DataTable
                rows={props.leads.data}
                meta={props.leads.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(row) => row.reference}
                only={['leads', 'filters']}
                searchPlaceholder="Search by name, email or inquiry no."
                emptyTitle="No leads found"
                emptyDescription="A lead is created when you take a client's Regray conversation."
                statusOptions={statusOptions}
            />

            {/* View Modal - Redesigned */}
            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewing(null);
                    }
                }}
            >
                <DialogContent
                    className="max-h-[90svh] overflow-y-auto sm:max-w-2xl"
                    data-testid="lead-details"
                    showCloseButton={false}
                >
                    {viewing !== null ? (
                        <div className="space-y-6">
                            <div>
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <h2 className="text-2xl font-bold tracking-[-0.03em]">
                                        {viewing.reference}
                                    </h2>
                                    <LeadTallyBadges tally={viewing.tally} />
                                </div>
                                <p className="text-sm text-secondary-foreground">
                                    {viewing.client.name}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-4">
                                <div>
                                    <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                        Contact
                                    </p>
                                    <p className="text-sm">
                                        {viewing.contact ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                        Email
                                    </p>
                                    <p className="text-sm break-all">
                                        {viewing.email ?? '—'}
                                    </p>
                                </div>
                                <div className="col-span-2">
                                    <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                        Held by
                                    </p>
                                    <p className="text-sm">
                                        {viewing.marketingUser}
                                    </p>
                                </div>
                            </div>

                            {viewing.inquiries.length > 0 && (
                                <section className="space-y-3">
                                    <h3 className="text-sm font-bold tracking-[0.05em] uppercase">
                                        Inquiries ({viewing.inquiries.length})
                                    </h3>
                                    <ul className="space-y-2">
                                        {viewing.inquiries.map((inquiry) => (
                                            <li
                                                key={inquiry.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded border border-sidebar-border bg-muted/30 p-3 text-sm"
                                            >
                                                <span className="flex min-w-0 flex-wrap items-center gap-2">
                                                    <span>
                                                        <span className="font-bold">
                                                            {inquiry.reference}
                                                        </span>{' '}
                                                        · {inquiry.name}
                                                    </span>
                                                    <LeadStatusBadge
                                                        status={
                                                            inquiry.marketingStatus
                                                        }
                                                    />
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    {inquiry.canDecide ? (
                                                        <>
                                                            {(
                                                                [
                                                                    'won',
                                                                    'lost',
                                                                ] as const
                                                            ).map((to) => (
                                                                <Button
                                                                    key={to}
                                                                    type="button"
                                                                    size="sm"
                                                                    variant={
                                                                        to ===
                                                                        'won'
                                                                            ? 'default'
                                                                            : 'outline'
                                                                    }
                                                                    onClick={() => {
                                                                        setDeciding(
                                                                            {
                                                                                lead: viewing,
                                                                                inquiry,
                                                                                to,
                                                                            },
                                                                        );
                                                                        setViewing(
                                                                            null,
                                                                        );
                                                                    }}
                                                                >
                                                                    {leadStatusLabel(
                                                                        to,
                                                                    )}
                                                                </Button>
                                                            ))}
                                                        </>
                                                    ) : null}
                                                    {inquiry.canCreateGroup && (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-9 w-9"
                                                                    aria-label={`Create a group chat for ${inquiry.reference}`}
                                                                    onClick={() => {
                                                                        setGroupPreset(
                                                                            {
                                                                                client: viewing.client,
                                                                                inquiry,
                                                                            },
                                                                        );
                                                                        setViewing(
                                                                            null,
                                                                        );
                                                                    }}
                                                                >
                                                                    <MessageCirclePlus
                                                                        aria-hidden
                                                                        className="size-4"
                                                                    />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Create group
                                                                chat
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            )}

                            {viewing.history.length > 0 && (
                                <section className="space-y-3">
                                    <h3 className="text-sm font-bold tracking-[0.05em] uppercase">
                                        Status History
                                    </h3>
                                    <ul className="space-y-2">
                                        {[...viewing.history]
                                            .reverse()
                                            .map((entry, idx) => (
                                                <li
                                                    key={`${entry.status}-${entry.at}-${idx}`}
                                                    className="rounded border border-sidebar-border bg-muted/30 p-3 text-sm"
                                                >
                                                    <div className="mb-1 flex items-center gap-2">
                                                        <span className="font-bold">
                                                            {entry.inquiry ===
                                                            null
                                                                ? 'Lead opened'
                                                                : `${entry.inquiry} ${leadStatusLabel(entry.status).toLowerCase()}`}
                                                        </span>
                                                        <span className="text-xs text-secondary-foreground">
                                                            {formatDate(
                                                                entry.at,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {entry.note && (
                                                        <p className="text-xs text-secondary-foreground">
                                                            {entry.note}
                                                        </p>
                                                    )}
                                                </li>
                                            ))}
                                    </ul>
                                </section>
                            )}

                            <div className="flex gap-2">
                                {viewing.canCreateInquiry && (
                                    <Button asChild className="flex-1">
                                        <InertiaLink
                                            href={inquiryInquire.url({
                                                query: {
                                                    lead: viewing.reference,
                                                },
                                            })}
                                        >
                                            <FilePlus
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Inquiry
                                        </InertiaLink>
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setViewing(null)}
                                    className="flex-1"
                                >
                                    Close
                                </Button>
                            </div>
                        </div>
                    ) : null}
                </DialogContent>
            </Dialog>

            {/* Edit Modal */}
            {editing !== null && (
                <EditLeadDialog
                    lead={editing}
                    onClose={() => setEditing(null)}
                    onSuccess={() => {
                        setEditing(null);
                        router.reload({ only: ['leads', 'filters'] });
                    }}
                />
            )}

            {/* Status Update Modal - Redesigned */}
            {deciding !== null ? (
                <StatusUpdateDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setDeciding(null);
                        }
                    }}
                    current={
                        <LeadStatusBadge
                            status={deciding.inquiry.marketingStatus}
                        />
                    }
                    targets={[
                        {
                            value: deciding.to,
                            label: leadStatusLabel(deciding.to),
                        },
                    ]}
                    initialTarget={deciding.to}
                    fileGroups={() => []}
                    extraFieldFor={() => null}
                    submitUrl={leadInquiryStatusRoute.url({
                        reference: deciding.lead.reference,
                        inquiry: deciding.inquiry.reference,
                    })}
                />
            ) : null}

            {props.canCreateGroup ? (
                <NewGroupDialog
                    open={groupPreset !== null}
                    preset={groupPreset}
                    onOpenChange={(open) => {
                        if (!open) {
                            setGroupPreset(null);
                        }
                    }}
                    onCreated={(conversation) =>
                        router.visit(
                            inboxIndex.url({
                                query: {
                                    conversation: String(conversation.id),
                                },
                            }),
                        )
                    }
                />
            ) : null}
        </div>
    );
};

/** Edit lead modal: the client's phone, beside their name and email, read-only. */
const EditLeadDialog = ({
    lead,
    onClose,
    onSuccess,
}: {
    lead: LeadRow;
    onClose: () => void;
    onSuccess: () => void;
}) => {
    const [phone, setPhone] = useState(lead.contact || '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        setPhone(lead.contact || '');
        setErrors({});
    }, [lead]);

    const handleSubmit = async () => {
        setSaving(true);
        setErrors({});

        router.post(
            `/leads/${lead.reference}/update`,
            { phone },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onSuccess();
                },
                onError: (errorBag) => {
                    setErrors(errorBag as Record<string, string>);
                    setSaving(false);
                },
            },
        );
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => !saving && open === false && onClose()}
        >
            <DialogContent className="sm:max-w-md" showCloseButton={false}>
                <DialogHeader>
                    <DialogTitle>Edit Lead Details</DialogTitle>
                    <DialogDescription>
                        Update the phone number for {lead.reference}. The name
                        and email are the client's own to change.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            value={lead.client.name}
                            readOnly
                            disabled
                            className="rounded-none"
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="edit-phone">Phone</Label>
                        <Input
                            id="edit-phone"
                            type="tel"
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="Phone number"
                            disabled={saving}
                            className="rounded-none"
                        />
                        {errors.phone && (
                            <p className="text-xs text-destructive">
                                {errors.phone}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="edit-email">Email</Label>
                        <Input
                            id="edit-email"
                            type="email"
                            value={lead.email ?? ''}
                            placeholder="—"
                            readOnly
                            disabled
                            className="rounded-none"
                        />
                    </div>

                    {errors.general && (
                        <p className="text-xs text-destructive">
                            {errors.general}
                        </p>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={saving}
                    >
                        {saving ? (
                            <Spinner className="motion-reduce:animate-none" />
                        ) : null}
                        Save changes
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
};

export default Leads;

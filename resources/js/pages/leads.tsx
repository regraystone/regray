import { Link as InertiaLink, router, usePage } from '@inertiajs/react';
import { MessageCirclePlus, Plus } from 'lucide-react';
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
import { LeadStatusBadge } from '@/components/lead-status-badge';
import PageHeading from '@/components/page-heading';
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
import type { TableMeta } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import {
    leadStatusLabel,
    LEAD_STATUSES,
    type LeadStatus,
} from '@/lib/lead-statuses';
import { inquire as inquiryInquire } from '@/routes/inquiry';
import { status as leadStatusRoute } from '@/routes/leads';
import { index as messageIndex } from '@/routes/message';

type LeadInquiry = {
    id: number;
    reference: string;
    name: string;
    hasGroup: boolean;
};

/** One lead as a row of the Leads page, as `LeadResource` sends it. */
type LeadRow = {
    reference: string;
    status: LeadStatus;
    createdAt: string;
    client: { id: number; name: string };
    /** The client's phone number. */
    contact: string | null;
    email: string | null;
    marketingUser: string;
    inquiries: LeadInquiry[];
    history: Array<{ status: LeadStatus; note: string | null; at: string }>;
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
 * The Leads page: marketing's own clients, newest first. View opens the lead
 * (its status, history and inquiries, with a way to open an inquiry or a
 * group chat for one); Won and Lost, each with a required note, only while
 * the lead is idle.
 */
const Leads = () => {
    const { props } = usePage<LeadsPageProps>();
    const [viewing, setViewing] = useState<LeadRow | null>(null);
    const [editing, setEditing] = useState<LeadRow | null>(null);
    const [deciding, setDeciding] = useState<{
        lead: LeadRow;
        to: 'won' | 'lost';
    } | null>(null);
    const [groupPreset, setGroupPreset] = useState<GroupPreset | null>(null);
    const [statusFilter, setStatusFilter] = useState(
        props.filters.status || 'all',
    );

    const handleStatusFilterChange = (value: string) => {
        setStatusFilter(value);
        router.get(
            window.location.pathname,
            {
                ...(props.filters.search.trim() !== '' && {
                    search: props.filters.search,
                }),
                ...(value !== 'all' && { status: value }),
                page: 1,
            },
            {
                only: ['leads', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

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
            className: 'w-[12%]',
        },
        {
            key: 'client',
            header: 'Client',
            cell: (row) => row.client.name,
            className: 'w-[18%]',
        },
        {
            key: 'contact',
            header: 'Contact',
            cell: (row) => row.contact ?? '—',
            className: 'w-[18%]',
        },
        {
            key: 'email',
            header: 'Email',
            cell: (row) => (
                <span className="block truncate" title={row.email ?? undefined}>
                    {row.email ?? '—'}
                </span>
            ),
            className: 'w-[34%]',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => <LeadStatusBadge status={row.status} />,
            className: 'w-[9%]',
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'w-[9%] text-right',
            cell: (row) => (
                <RowActions
                    actions={row.actions}
                    subject={row.client.name}
                    handlers={{
                        view: () => setViewing(row),
                        edit: () => setEditing(row),
                        won: () => setDeciding({ lead: row, to: 'won' }),
                        lost: () => setDeciding({ lead: row, to: 'lost' }),
                    }}
                />
            ),
        },
    ];

    return (
        <div className="mx-auto w-full max-w-7xl min-w-0">
            <PageHeading
                title="Leads"
                description="Your clients, and where each one stands"
            />

            <DataTable
                rows={props.leads.data}
                meta={props.leads.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(row) => row.reference}
                only={['leads', 'filters']}
                searchPlaceholder="Search leads"
                emptyTitle="No leads found"
                emptyDescription="A lead is created when you take a client's Regray Team conversation."
                statusFilter={statusFilter}
                statusOptions={statusOptions}
                onStatusFilterChange={handleStatusFilterChange}
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
                >
                    {viewing !== null ? (
                        <div className="space-y-6">
                            <div>
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <h2 className="text-2xl font-bold tracking-[-0.03em]">
                                        {viewing.reference}
                                    </h2>
                                    <LeadStatusBadge status={viewing.status} />
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
                                                <span>
                                                    <span className="font-bold">
                                                        {inquiry.reference}
                                                    </span>{' '}
                                                    · {inquiry.name}
                                                </span>
                                                {!inquiry.hasGroup && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => {
                                                            setGroupPreset({
                                                                client: viewing.client,
                                                                inquiry,
                                                            });
                                                            setViewing(null);
                                                        }}
                                                    >
                                                        <MessageCirclePlus
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                        Group chat
                                                    </Button>
                                                )}
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
                                                            {leadStatusLabel(
                                                                entry.status,
                                                            )}
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
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="flex-1"
                                    >
                                        <InertiaLink
                                            href={inquiryInquire.url({
                                                query: {
                                                    lead: viewing.reference,
                                                },
                                            })}
                                        >
                                            <Plus
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Create inquiry
                                        </InertiaLink>
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
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
                    current={<LeadStatusBadge status={deciding.lead.status} />}
                    targets={[
                        {
                            value: deciding.to,
                            label: leadStatusLabel(deciding.to),
                        },
                    ]}
                    initialTarget={deciding.to}
                    fileGroups={() => []}
                    extraFieldFor={() => null}
                    submitUrl={leadStatusRoute.url(deciding.lead.reference)}
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
                            messageIndex.url({
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

/** Edit lead modal with form for updating phone, email, and name. */
const EditLeadDialog = ({
    lead,
    onClose,
    onSuccess,
}: {
    lead: LeadRow;
    onClose: () => void;
    onSuccess: () => void;
}) => {
    const [name, setName] = useState(lead.client.name);
    const [phone, setPhone] = useState(lead.contact || '');
    const [email, setEmail] = useState(lead.email || '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        setName(lead.client.name);
        setPhone(lead.contact || '');
        setEmail(lead.email || '');
        setErrors({});
    }, [lead]);

    const handleSubmit = async () => {
        setSaving(true);
        setErrors({});

        router.post(
            `/leads/${lead.reference}/update`,
            { name, phone, email },
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

    const isValid = name.trim().length > 0;

    return (
        <Dialog
            open
            onOpenChange={(open) => !saving && open === false && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Lead Details</DialogTitle>
                    <DialogDescription>
                        Update contact information for {lead.reference}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="edit-name">Name</Label>
                        <Input
                            id="edit-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Client name"
                            disabled={saving}
                            className="rounded-none"
                        />
                        {errors.name && (
                            <p className="text-xs text-destructive">
                                {errors.name}
                            </p>
                        )}
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
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="Email address"
                            disabled={saving}
                            className="rounded-none"
                        />
                        {errors.email && (
                            <p className="text-xs text-destructive">
                                {errors.email}
                            </p>
                        )}
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
                        disabled={!isValid || saving}
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

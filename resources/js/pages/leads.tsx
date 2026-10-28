import { Link, router, usePage } from '@inertiajs/react';
import { MessageCirclePlus, Plus } from 'lucide-react';
import { useState } from 'react';
import NewGroupDialog, {
    type GroupPreset,
} from '@/components/conversations/new-group-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilters,
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
import type { TableMeta } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import { leadStatusLabel, type LeadStatus } from '@/lib/lead-statuses';
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
    filters: DataTableFilters;
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
    const [deciding, setDeciding] = useState<{
        lead: LeadRow;
        to: 'won' | 'lost';
    } | null>(null);
    const [groupPreset, setGroupPreset] = useState<GroupPreset | null>(null);

    const columns: DataTableColumn<LeadRow>[] = [
        {
            key: 'date',
            header: 'Date',
            cell: (row) => formatDate(row.createdAt),
            className: 'whitespace-nowrap',
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
            className: 'whitespace-nowrap',
        },
        {
            key: 'email',
            header: 'Email',
            cell: (row) => (
                <span
                    className="block max-w-56 truncate"
                    title={row.email ?? undefined}
                >
                    {row.email ?? '—'}
                </span>
            ),
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
                        won: () => setDeciding({ lead: row, to: 'won' }),
                        lost: () => setDeciding({ lead: row, to: 'lost' }),
                    }}
                />
            ),
        },
    ];

    return (
        <div className="mx-auto w-full max-w-6xl min-w-0">
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
            />

            <Dialog
                open={viewing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewing(null);
                    }
                }}
            >
                <DialogContent
                    className="max-h-[90svh] overflow-y-auto"
                    data-testid="lead-details"
                >
                    {viewing !== null ? (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2 text-lg font-bold tracking-[-0.03em]">
                                    {viewing.reference}
                                    <LeadStatusBadge status={viewing.status} />
                                </DialogTitle>
                                <DialogDescription>
                                    {viewing.client.name}
                                    {[viewing.contact, viewing.email]
                                        .filter(Boolean)
                                        .map((value) => ` · ${value}`)
                                        .join('')}{' '}
                                    · held by {viewing.marketingUser}
                                </DialogDescription>
                            </DialogHeader>

                            <section className="space-y-2">
                                <h3 className="text-sm font-bold">Inquiries</h3>
                                {viewing.inquiries.length === 0 ? (
                                    <p className="text-sm text-secondary-foreground">
                                        No inquiries yet.
                                    </p>
                                ) : (
                                    <ul className="space-y-1.5">
                                        {viewing.inquiries.map((inquiry) => (
                                            <li
                                                key={inquiry.id}
                                                className="flex flex-wrap items-center justify-between gap-2 text-sm"
                                            >
                                                <span>
                                                    <span className="font-bold">
                                                        {inquiry.reference}
                                                    </span>{' '}
                                                    · {inquiry.name}
                                                </span>
                                                {props.canCreateGroup &&
                                                !inquiry.hasGroup ? (
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
                                                        />
                                                        Group chat
                                                    </Button>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </section>

                            <section className="space-y-2">
                                <h3 className="text-sm font-bold">History</h3>
                                <ul className="space-y-1.5 text-sm">
                                    {[...viewing.history]
                                        .reverse()
                                        .map((entry) => (
                                            <li
                                                key={`${entry.status}-${entry.at}`}
                                            >
                                                <span className="font-bold">
                                                    {leadStatusLabel(
                                                        entry.status,
                                                    )}
                                                </span>{' '}
                                                · {formatDate(entry.at)}
                                                {entry.note ? (
                                                    <span className="block text-secondary-foreground">
                                                        {entry.note}
                                                    </span>
                                                ) : null}
                                            </li>
                                        ))}
                                </ul>
                            </section>

                            {viewing.canCreateInquiry ? (
                                <Button asChild variant="outline">
                                    <Link
                                        href={inquiryInquire.url({
                                            query: { lead: viewing.reference },
                                        })}
                                    >
                                        <Plus aria-hidden />
                                        Create inquiry
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    ) : null}
                </DialogContent>
            </Dialog>

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

export default Leads;

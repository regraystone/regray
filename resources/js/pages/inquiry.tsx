import { router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import DataTable, {
    type DataTableColumn,
    type DataTableFilterOption,
    type DataTableFilters,
} from '@/components/data-table/data-table';
import RowActions from '@/components/data-table/row-actions';
import { StatusBadge } from '@/components/inquiry-status-badge';
import PageHeading from '@/components/page-heading';
import StatusUpdateDialog from '@/components/status-update-dialog';
import { Button } from '@/components/ui/button';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';
import type { TableMeta } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import {
    statusLabel,
    type InquiryStatus,
    type PaymentStatus,
    type ProcessStatus,
    type SalesStatus,
} from '@/lib/inquiry-statuses';
import {
    allowedTargets,
    extraField,
    JOB_ORDER_GROUP,
    requiredFileGroups,
    type StatusTrack,
} from '@/lib/status-rules';
import { edit as inquiryEdit, show as inquiryShow } from '@/routes/inquiry';
import {
    jobOrder as inquiryJobOrderRoute,
    status as inquiryStatusRoute,
} from '@/routes/inquiries';

/** One inquiry as a row of the Inquiry page, as `InquiryRowResource` sends it. */
type InquiryRow = {
    reference: string;
    createdAt: string;
    client: string;
    /** The client's phone number. */
    contact: string | null;
    status: InquiryStatus;
    salesStatus: SalesStatus | null;
    paymentStatus: PaymentStatus | null;
    processStatus: ProcessStatus | null;
    /** The actions this reader has on this row, decided by the server. */
    actions: string[];
};

type InquiryPageProps = {
    inquiries: { data: InquiryRow[]; meta: TableMeta };
    filters: DataTableFilters;
    statusOptions: DataTableFilterOption[];
};

const currentOf = (row: InquiryRow, track: StatusTrack): string | null =>
    track === 'sales'
        ? row.salesStatus
        : track === 'payment'
          ? row.paymentStatus
          : row.processStatus;

/**
 * The Inquiry page: every inquiry, the same columns for every staff role, and
 * the row actions the server says this reader has (view, edit, and the status
 * of the tracks they own). Add, for those who may open an inquiry, asks
 * Platform or Direct first.
 */
const Inquiry = () => {
    const { props } = usePage<InquiryPageProps>();
    const { inquire, onInquireClick, draftPrompt } = useInquiryEntry();
    const [editing, setEditing] = useState<{
        row: InquiryRow;
        track: StatusTrack;
    } | null>(null);
    /** The quoted inquiry whose job order sales is uploading, which starts logistics. */
    const [uploadingJobOrder, setUploadingJobOrder] =
        useState<InquiryRow | null>(null);
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
                only: ['inquiries', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const columns: DataTableColumn<InquiryRow>[] = [
        {
            key: 'date',
            header: 'Date',
            cell: (row) => formatDate(row.createdAt),
            className: 'whitespace-nowrap',
        },
        {
            key: 'reference',
            header: 'Inquiry No.',
            cell: (row) => <span className="font-bold">{row.reference}</span>,
            className: 'whitespace-nowrap',
        },
        { key: 'client', header: 'Client', cell: (row) => row.client },
        {
            key: 'contact',
            header: 'Contact',
            cell: (row) => row.contact ?? '—',
            className: 'whitespace-nowrap',
        },
        {
            key: 'status',
            header: 'Status',
            // The payment status beside the inquiry status, once it has started.
            cell: (row) => (
                <div className="flex flex-row items-center gap-1.5 whitespace-nowrap">
                    <StatusBadge group="inquiry" status={row.status} />
                    <StatusBadge group="payment" status={row.paymentStatus} />
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            cell: (row) => (
                <RowActions
                    actions={row.actions}
                    subject={row.reference}
                    handlers={{
                        view: () =>
                            router.visit(inquiryShow.url(row.reference)),
                        edit: () =>
                            router.visit(inquiryEdit.url(row.reference)),
                        'status.sales': () =>
                            setEditing({ row, track: 'sales' }),
                        'status.payment': () =>
                            setEditing({ row, track: 'payment' }),
                        'status.process': () =>
                            setEditing({ row, track: 'process' }),
                        job_order: () => setUploadingJobOrder(row),
                    }}
                />
            ),
        },
    ];

    const track = editing?.track ?? null;
    const current =
        editing === null || track === null
            ? null
            : currentOf(editing.row, track);

    return (
        <div className="mx-auto w-full max-w-6xl min-w-0">
            <PageHeading
                title="Inquiry"
                description="Every inquiry, where it stands, and what you can do with it"
                action={
                    inquire ? (
                        <Button type="button" onClick={onInquireClick}>
                            <Plus aria-hidden />
                            Add inquiry
                        </Button>
                    ) : undefined
                }
            />

            <DataTable
                rows={props.inquiries.data}
                meta={props.inquiries.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(row) => row.reference}
                only={['inquiries', 'filters']}
                searchPlaceholder="Search inquiries"
                emptyTitle="No inquiries found"
                emptyDescription="No inquiry matches this search."
                statusFilter={statusFilter}
                statusOptions={props.statusOptions}
                onStatusFilterChange={handleStatusFilterChange}
            />

            {editing !== null && track !== null ? (
                <StatusUpdateDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditing(null);
                        }
                    }}
                    current={
                        current === null ? null : (
                            <StatusBadge
                                group={track}
                                status={current as never}
                            />
                        )
                    }
                    targets={allowedTargets(track, current).map((value) => ({
                        value,
                        label: statusLabel(track, value as never),
                    }))}
                    fileGroups={(to) => requiredFileGroups(track, to)}
                    extraFieldFor={(to) => extraField(track, to)}
                    submitUrl={inquiryStatusRoute.url(editing.row.reference)}
                    payload={{ track }}
                />
            ) : null}

            {uploadingJobOrder !== null ? (
                <StatusUpdateDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setUploadingJobOrder(null);
                        }
                    }}
                    current={null}
                    targets={[
                        {
                            value: 'order_received',
                            label: statusLabel('process', 'order_received'),
                        },
                    ]}
                    initialTarget="order_received"
                    fileGroups={() => [JOB_ORDER_GROUP]}
                    extraFieldFor={() => null}
                    submitUrl={inquiryJobOrderRoute.url(
                        uploadingJobOrder.reference,
                    )}
                />
            ) : null}

            {draftPrompt}
        </div>
    );
};

export default Inquiry;

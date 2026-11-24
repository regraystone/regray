import { Link, router, usePage } from '@inertiajs/react';
import { Layers, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Detail, Section } from '@/components/admin-drawer-parts';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilterOption,
} from '@/components/data-table/data-table';
import DataTableFilter from '@/components/data-table/data-table-filter';
import RowActions from '@/components/data-table/row-actions';
import StatusToggle from '@/components/data-table/status-toggle';
import { useDataTableQuery } from '@/components/data-table/use-data-table-query';
import EmptyState from '@/components/empty-state';
import FormDrawer from '@/components/form-drawer';
import PageHeader from '@/components/page-header';
import ServiceForm, { SERVICE_PROPS } from '@/components/services/service-form';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format-date';
import {
    archiveServiceDescription,
    fulfillmentLabel,
    notOfferedReason,
    questionHint,
    serviceMeta,
} from '@/lib/service-admin';
import {
    archive as archiveService,
    status as serviceStatus,
} from '@/routes/dashboard/services';
import { index as serviceListIndex } from '@/routes/dashboard/services/lists';
import type {
    ServiceRow,
    ServicesPageProps,
    ServiceTypeOption,
} from '@/types/service-admin';

const STATUS_OPTIONS: DataTableFilterOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Visible' },
    { value: 'inactive', label: 'Hidden' },
];

type Drawer =
    | { mode: 'add' }
    | { mode: 'view'; service: ServiceRow }
    | { mode: 'edit'; service: ServiceRow };

/**
 * The admin's All services page: what clients can ask for on the Inquire
 * page, as one table like Products', by service type then display order,
 * hidden ones too; archived ones are gone. Searched by name or description
 * and filtered by service type and status, on the server.
 *
 * Add, Edit and View open in the drawer; Archive asks first. A row's switch
 * shows or hides it. Service types and questions have their own pages under
 * Services. What a row offers comes from the server (`actions`).
 */
const Services = () => {
    const { props } = usePage<ServicesPageProps>();
    const { serviceTypes, stoneTypes, questions, limits, canCreate, filters } =
        props;
    const { update } = useDataTableQuery(SERVICE_PROPS);
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<ServiceRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const typeOf = (service: ServiceRow): ServiceTypeOption | undefined =>
        serviceTypes.find((type) => type.id === service.serviceTypeId);

    // A service open in View shows its saved state after an edit.
    const current = (service: ServiceRow): ServiceRow =>
        props.services.data.find((other) => other.id === service.id) ?? service;

    const viewing = drawer?.mode === 'view' ? current(drawer.service) : null;
    const editing = drawer?.mode === 'edit' ? drawer.service : null;
    // A new service starts under the type filtered to, or the first.
    const defaultTypeId =
        serviceTypes.find((type) => type.slug === filters.type)?.id ??
        serviceTypes[0]?.id ??
        null;

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            archiveService.url(archiving.id),
            {},
            {
                only: SERVICE_PROPS,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setArchiving(null);
                    setDrawer(null);
                },
                onError: (errors) => {
                    setArchiving(null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'The service could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const typeFilterOptions: DataTableFilterOption[] = [
        { value: 'all', label: 'All service types' },
        ...serviceTypes.map((type) => ({ value: type.slug, label: type.name })),
    ];

    const columns: DataTableColumn<ServiceRow>[] = [
        {
            key: 'service',
            header: 'Service',
            cell: (row) => {
                const reason = notOfferedReason(
                    row,
                    typeOf(row)?.isActive ?? false,
                );

                return (
                    <span className="block min-w-0">
                        <span className="flex items-center gap-2">
                            <span className="block max-w-64 truncate font-medium">
                                {row.name}
                            </span>
                            {reason ? (
                                <Badge
                                    variant="outline"
                                    className="rounded-none"
                                    title={reason}
                                >
                                    Not offered
                                    <span className="sr-only">: {reason}</span>
                                </Badge>
                            ) : null}
                        </span>
                        <span className="block max-w-64 truncate text-xs text-secondary-foreground">
                            {row.description}
                        </span>
                    </span>
                );
            },
        },
        {
            key: 'type',
            header: 'Service type',
            cell: (row) => typeOf(row)?.name ?? '—',
        },
        {
            key: 'details',
            header: 'Details',
            cell: (row) => serviceMeta(row),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <StatusToggle
                    checked={row.isActive}
                    disabled={!row.canToggle}
                    label={`${row.isActive ? 'Hide' : 'Show'} ${row.name}`}
                    onToggle={(active, visit) =>
                        router.patch(
                            serviceStatus.url(row.id),
                            { active },
                            {
                                only: SERVICE_PROPS,
                                preserveScroll: true,
                                preserveState: true,
                                onError: visit.onError,
                                onFinish: visit.onFinish,
                            },
                        )
                    }
                />
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            cell: (row) => (
                <RowActions
                    actions={row.actions}
                    subject={row.name}
                    maxInline={3}
                    handlers={{
                        view: () => setDrawer({ mode: 'view', service: row }),
                        edit: () => setDrawer({ mode: 'edit', service: row }),
                        archive: () => setArchiving(row),
                    }}
                />
            ),
        },
    ];

    const addButton = (
        <Button
            type="button"
            onClick={() => setDrawer({ mode: 'add' })}
            disabled={serviceTypes.length === 0}
        >
            <Plus aria-hidden />
            Add service
        </Button>
    );

    return (
        <div className="w-full min-w-0">
            {canCreate ? (
                <PageHeader
                    variant="action"
                    title="Services"
                    subtitle="Manage what clients can ask for on the Inquire page"
                    action={addButton}
                />
            ) : (
                <PageHeader
                    title="Services"
                    subtitle="Manage what clients can ask for on the Inquire page"
                />
            )}

            {serviceTypes.length === 0 ? (
                <EmptyState
                    title="No service types yet"
                    description="Add a service type first, such as Natural Stone & Applications, then the services under it."
                    action={
                        <Button asChild variant="outline">
                            <Link href={serviceListIndex.url('service-types')}>
                                <Layers aria-hidden />
                                Go to Service types
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <DataTable
                    rows={props.services.data}
                    meta={props.services.meta}
                    filters={filters}
                    columns={columns}
                    rowKey={(row) => row.id}
                    only={SERVICE_PROPS}
                    searchPlaceholder="Search by service name"
                    emptyTitle="No services yet"
                    emptyDescription="Add a service to offer it on the Inquire page."
                    filteredEmptyTitle="No services match that"
                    filteredEmptyDescription="Try another name, or a different service type or status."
                    statusOptions={STATUS_OPTIONS}
                    extraFiltered={filters.type !== 'all'}
                    extraFilters={
                        <DataTableFilter
                            label="Service type"
                            options={typeFilterOptions}
                            value={filters.type}
                            onChange={(value) => update({ type: value })}
                        />
                    }
                />
            )}

            {/* View: read-only, with Edit to switch the same drawer over. */}
            <FormDrawer
                open={viewing !== null}
                onOpenChange={(open) => (open ? null : setDrawer(null))}
                title={viewing?.name ?? 'Service'}
                description={
                    viewing ? (typeOf(viewing)?.name ?? undefined) : undefined
                }
                size="lg"
                footer={
                    viewing?.actions.includes('edit') ? (
                        <Button
                            type="button"
                            onClick={() =>
                                setDrawer({ mode: 'edit', service: viewing })
                            }
                        >
                            <Pencil aria-hidden />
                            Edit
                        </Button>
                    ) : undefined
                }
            >
                {viewing !== null ? (
                    <ServiceDetails service={viewing} type={typeOf(viewing)} />
                ) : null}
            </FormDrawer>

            <ServiceForm
                open={drawer?.mode === 'add' || editing !== null}
                service={editing}
                defaultTypeId={drawer?.mode === 'add' ? defaultTypeId : null}
                serviceTypes={serviceTypes}
                stoneTypes={stoneTypes}
                questions={questions}
                limits={limits}
                onClose={() => setDrawer(null)}
            />

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => (open ? null : setArchiving(null))}
                title={`Archive ${archiving?.name ?? 'this service'}?`}
                description={
                    archiving
                        ? archiveServiceDescription(archiving.inquiriesCount)
                        : ''
                }
                confirmLabel="Archive service"
                cancelLabel="Keep service"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

/** What the View drawer shows of a service, as the Inquire page uses it. */
const ServiceDetails = ({
    service,
    type,
}: {
    service: ServiceRow;
    type: ServiceTypeOption | undefined;
}) => {
    const { props } = usePage<ServicesPageProps>();
    const reason = notOfferedReason(service, type?.isActive ?? false);

    return (
        <div className="space-y-6" data-testid="service-details">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <p className="min-w-0 flex-1 text-sm leading-relaxed">
                    {service.description}
                </p>
                <StatusBadge
                    active={service.isActive}
                    labels={['Visible', 'Hidden']}
                />
            </div>
            {reason ? (
                <p className="border border-sidebar-border bg-muted/40 p-3 text-sm">
                    {reason}
                </p>
            ) : null}

            <Section title="What it includes">
                {service.features.length > 0 ? (
                    <ul className="list-disc space-y-1 pl-5 text-sm">
                        {service.features.map((feature) => (
                            <li key={feature}>{feature}</li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-secondary-foreground">
                        Nothing listed
                    </p>
                )}
            </Section>

            <Section title="Questions">
                {service.questions.length > 0 ? (
                    <ol className="space-y-2">
                        {service.questions.map((question, index) => {
                            const option = props.questions.find(
                                (other) => other.id === question.id,
                            );

                            return (
                                <li
                                    key={question.id}
                                    className="flex items-start gap-3 border border-sidebar-border p-3"
                                >
                                    <span className="w-5 shrink-0 text-sm text-secondary-foreground">
                                        {index + 1}.
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold">
                                            {question.name}
                                        </p>
                                        {option ? (
                                            <p className="mt-0.5 text-xs text-secondary-foreground">
                                                {questionHint(option)}
                                            </p>
                                        ) : null}
                                    </div>
                                    <span className="text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                        {question.required
                                            ? 'Required'
                                            : 'Optional'}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                ) : (
                    <p className="text-sm text-secondary-foreground">
                        It asks nothing besides the stones.
                    </p>
                )}
            </Section>

            <Section title="Details">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <Detail label="Service type">{type?.name ?? '—'}</Detail>
                    <Detail label="How it reaches the client">
                        {fulfillmentLabel(service.fulfillment)}
                    </Detail>
                    <Detail label="Stones">
                        {service.requiresStones
                            ? service.stoneTypes.length > 0
                                ? service.stoneTypes
                                      .map((stoneType) => stoneType.name)
                                      .join(', ')
                                : 'Needs stones, but no stone type is chosen'
                            : 'Needs none'}
                    </Detail>
                    <Detail label="Inquiries">
                        {service.inquiriesCount === 1
                            ? '1 inquiry'
                            : `${service.inquiriesCount} inquiries`}
                    </Detail>
                    <Detail label="Link name">
                        <span className="font-mono">{service.slug}</span>
                    </Detail>
                    <Detail label="Display order">{service.sortOrder}</Detail>
                    <Detail label="Added">
                        {formatDate(service.createdAt)}
                    </Detail>
                    <Detail label="Last updated">
                        {formatDate(service.updatedAt)}
                    </Detail>
                </div>
            </Section>
        </div>
    );
};

export default Services;

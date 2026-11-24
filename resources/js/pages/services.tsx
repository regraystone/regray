import { Link, router, usePage } from '@inertiajs/react';
import { Layers, Pencil, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import AdminCard from '@/components/admin-card';
import { Detail, Section } from '@/components/admin-drawer-parts';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTableSearch from '@/components/data-table/data-table-search';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import EmptyState from '@/components/empty-state';
import FormDrawer from '@/components/form-drawer';
import PageHeader from '@/components/page-header';
import ServiceForm, { SERVICE_PROPS } from '@/components/services/service-form';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format-date';
import {
    archiveServiceDescription,
    fulfillmentLabel,
    matchesSearch,
    notOfferedReason,
    questionHint,
    serviceMeta,
} from '@/lib/service-admin';
import { archive as archiveService } from '@/routes/dashboard/services';
import { index as serviceListIndex } from '@/routes/dashboard/services/lists';
import type {
    ServiceRow,
    ServicesPageProps,
    ServiceTypeRow,
} from '@/types/service-admin';

type Drawer =
    | { mode: 'add'; typeId: number | null }
    | { mode: 'view'; service: ServiceRow }
    | { mode: 'edit'; service: ServiceRow };

/**
 * The admin's Services page: what clients can ask for on the Inquire page.
 * Each service type is a heading over its services, in their display order,
 * hidden ones too; archived ones are gone. Search narrows by a service's
 * name or description.
 *
 * Add, Edit and View open in the drawer; Archive asks first. Service types
 * and questions have their own pages under Services. What a card offers
 * comes from the server (`actions`).
 */
const Services = () => {
    const { props } = usePage<ServicesPageProps>();
    const { serviceTypes, stoneTypes, questions, limits, canCreate } = props;
    const [search, setSearch] = useState('');
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<ServiceRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const typeOf = (service: ServiceRow): ServiceTypeRow | undefined =>
        serviceTypes.find((type) => type.id === service.serviceTypeId);

    // A service open in View shows its saved state after an edit.
    const current = (service: ServiceRow): ServiceRow =>
        serviceTypes
            .flatMap((type) => type.services)
            .find((other) => other.id === service.id) ?? service;

    const viewing = drawer?.mode === 'view' ? current(drawer.service) : null;
    const editing = drawer?.mode === 'edit' ? drawer.service : null;

    const groups = useMemo(
        () =>
            serviceTypes.map((type) => ({
                type,
                services: type.services.filter((service) =>
                    matchesSearch(service, search),
                ),
            })),
        [serviceTypes, search],
    );
    const isFiltered = search.trim() !== '';
    const shown = groups.filter(
        (group) => !isFiltered || group.services.length > 0,
    );

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

    const addButton = (
        <Button
            type="button"
            onClick={() =>
                setDrawer({ mode: 'add', typeId: serviceTypes[0]?.id ?? null })
            }
            disabled={serviceTypes.length === 0}
        >
            <Plus aria-hidden />
            Add service
        </Button>
    );
    const typesLink = (
        <Button asChild variant="outline">
            <Link href={serviceListIndex.url('service-types')}>
                <Layers aria-hidden />
                Go to Service types
            </Link>
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

            <div className="space-y-8">
                <DataTableToolbar
                    search={
                        <DataTableSearch
                            value={search}
                            placeholder="Search by service name"
                            onSearch={setSearch}
                        />
                    }
                />

                {serviceTypes.length === 0 ? (
                    <EmptyState
                        title="No service types yet"
                        description="Add a service type first, such as Natural Stone & Applications, then the services under it."
                        action={typesLink}
                    />
                ) : shown.length === 0 ? (
                    <EmptyState
                        title="No services match that search"
                        description="Try another service name."
                    />
                ) : (
                    shown.map(({ type, services }) => (
                        <section
                            key={type.id}
                            aria-labelledby={`service-type-${type.id}`}
                            data-testid="service-type-group"
                        >
                            <div className="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h2
                                    id={`service-type-${type.id}`}
                                    className="text-xl font-bold tracking-[-0.03em]"
                                >
                                    {type.name}
                                </h2>
                                <StatusBadge
                                    active={type.isActive}
                                    labels={['Visible', 'Hidden']}
                                />
                                <span className="text-sm text-secondary-foreground">
                                    {type.services.length === 1
                                        ? '1 service'
                                        : `${type.services.length} services`}
                                </span>
                                {canCreate && !isFiltered ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="ml-auto"
                                        onClick={() =>
                                            setDrawer({
                                                mode: 'add',
                                                typeId: type.id,
                                            })
                                        }
                                    >
                                        <Plus aria-hidden />
                                        Add service
                                        <span className="sr-only">
                                            {' '}
                                            to {type.name}
                                        </span>
                                    </Button>
                                ) : null}
                            </div>
                            {!type.isActive ? (
                                <p className="-mt-2 mb-4 text-sm text-secondary-foreground">
                                    This type is hidden, so none of its services
                                    is offered on the Inquire page.
                                </p>
                            ) : null}

                            {services.length === 0 ? (
                                <p className="border border-dashed border-sidebar-border p-6 text-center text-sm text-secondary-foreground">
                                    No services under this type yet.
                                </p>
                            ) : (
                                <div
                                    className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6"
                                    data-testid="service-grid"
                                >
                                    {services.map((service) => (
                                        <AdminCard
                                            key={service.id}
                                            testId="service-card"
                                            title={service.name}
                                            subtitle={service.description}
                                            subtitlePlaceholder="No description yet"
                                            status={{
                                                active: service.isActive,
                                                labels: ['Visible', 'Hidden'],
                                            }}
                                            meta={serviceMeta(service)}
                                            actions={service.actions}
                                            onView={() =>
                                                setDrawer({
                                                    mode: 'view',
                                                    service,
                                                })
                                            }
                                            onEdit={() =>
                                                setDrawer({
                                                    mode: 'edit',
                                                    service,
                                                })
                                            }
                                            onArchive={() =>
                                                setArchiving(service)
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </section>
                    ))
                )}
            </div>

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
                defaultTypeId={drawer?.mode === 'add' ? drawer.typeId : null}
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
    type: ServiceTypeRow | undefined;
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

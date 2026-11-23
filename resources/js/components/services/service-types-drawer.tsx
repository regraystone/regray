import { zodResolver } from '@hookform/resolvers/zod';
import { router } from '@inertiajs/react';
import { ArrowLeft, Archive, Pencil, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Controller, useForm, type FieldPath } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import ConfirmDialog from '@/components/confirm-dialog';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { typeArchiveBlocker } from '@/lib/service-admin';
import {
    archive as archiveType,
    store as storeType,
    update as updateType,
} from '@/routes/dashboard/service-types';
import type { ServiceTypeRow } from '@/types/service-admin';
import { SERVICE_PROPS } from './service-form';

type TypeValues = { name: string; is_active: boolean; sort_order: string };

const EMPTY: TypeValues = { name: '', is_active: true, sort_order: '0' };

const typeSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, "Enter the service type's name.")
        .max(150, 'Use 150 characters or fewer.'),
    is_active: z.boolean(),
    sort_order: z
        .string()
        .trim()
        .regex(/^\d{1,5}$/, 'Enter 0 or a whole number above it.')
        .refine((value) => Number(value) <= 32767, 'Enter 32767 or less.'),
});

type Mode =
    | { form: 'list' }
    | { form: 'add' }
    | { form: 'edit'; type: ServiceTypeRow };

/**
 * The service types, the first choice of "What do you need?" on the Inquire
 * page: the list, and adding, changing and archiving one, in one drawer. A
 * type is archived only once it has no services left.
 */
const ServiceTypesDrawer = ({
    open,
    onClose,
    serviceTypes,
    canCreate,
}: {
    open: boolean;
    onClose: () => void;
    serviceTypes: ServiceTypeRow[];
    canCreate: boolean;
}) => {
    const [mode, setMode] = useState<Mode>({ form: 'list' });
    const [archiving, setArchiving] = useState<ServiceTypeRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const form = useForm<TypeValues>({
        resolver: zodResolver(typeSchema),
        mode: 'onTouched',
        defaultValues: EMPTY,
    });
    const { register, handleSubmit, setError, reset, control, formState } =
        form;

    useEffect(() => {
        if (open) {
            setMode({ form: 'list' });
        }
    }, [open]);

    useEffect(() => {
        if (mode.form === 'edit') {
            reset({
                name: mode.type.name,
                is_active: mode.type.isActive,
                sort_order: String(mode.type.sortOrder),
            });
        } else if (mode.form === 'add') {
            reset(EMPTY);
        }
    }, [mode, reset]);

    const save = handleSubmit((values) => {
        const payload = { ...values, sort_order: Number(values.sort_order) };
        const options = {
            only: SERVICE_PROPS,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setMode({ form: 'list' }),
            onError: (errors: Record<string, string>) => {
                for (const [key, message] of Object.entries(errors)) {
                    if (key in EMPTY) {
                        setError(key as FieldPath<TypeValues>, {
                            type: 'server',
                            message,
                        });
                    } else {
                        toast.error(message);
                    }
                }
            },
        };

        if (mode.form === 'edit') {
            router.put(updateType.url(mode.type.id), payload, options);
        } else {
            router.post(storeType.url(), payload, options);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            archiveType.url(archiving.id),
            {},
            {
                only: SERVICE_PROPS,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setArchiving(null),
                onError: (errors) => {
                    setArchiving(null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'The service type could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const inForm = mode.form !== 'list';

    return (
        <>
            <FormDrawer
                open={open}
                onOpenChange={(next) => (next ? null : onClose())}
                title={
                    mode.form === 'add'
                        ? 'Add service type'
                        : mode.form === 'edit'
                          ? `Edit ${mode.type.name}`
                          : 'Service types'
                }
                description={
                    inForm
                        ? 'The first choice of “What do you need?” on the Inquire page.'
                        : 'The first choice of “What do you need?” on the Inquire page, which decides the services offered after it.'
                }
                {...(inForm
                    ? {
                          submitLabel:
                              mode.form === 'edit'
                                  ? 'Save changes'
                                  : 'Add service type',
                          onSubmit: save,
                          processing,
                      }
                    : {})}
            >
                {inForm ? (
                    <div className="space-y-5">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="-ml-2"
                            onClick={() => setMode({ form: 'list' })}
                        >
                            <ArrowLeft aria-hidden />
                            All service types
                        </Button>
                        <FormField
                            id="service-type-name"
                            label="Name"
                            required
                            error={formState.errors.name?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="service-type-name"
                                    autoComplete="off"
                                    maxLength={150}
                                    placeholder="Custom Stone Furniture"
                                    className="mt-2 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('name')}
                                />
                            )}
                        </FormField>
                        <Controller
                            control={control}
                            name="is_active"
                            render={({ field }) => (
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <label
                                            htmlFor="service-type-active"
                                            className="text-sm font-bold"
                                        >
                                            Visible on Inquire
                                        </label>
                                        <p
                                            id="service-type-active-hint"
                                            className="mt-1 text-sm text-secondary-foreground"
                                        >
                                            While hidden, none of its services
                                            is offered.
                                        </p>
                                    </div>
                                    <Switch
                                        id="service-type-active"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        aria-describedby="service-type-active-hint"
                                    />
                                </div>
                            )}
                        />
                        <FormField
                            id="service-type-sort-order"
                            label="Display order"
                            error={formState.errors.sort_order?.message}
                            hint="Lower comes first; types with the same number go by name."
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="service-type-sort-order"
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={32767}
                                    step={1}
                                    className="mt-2 w-32 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('sort_order')}
                                />
                            )}
                        </FormField>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {serviceTypes.length === 0 ? (
                            <p className="text-sm text-secondary-foreground">
                                No service types yet.
                            </p>
                        ) : (
                            <ul
                                className="divide-y divide-sidebar-border border border-sidebar-border"
                                data-testid="service-type-list"
                            >
                                {serviceTypes.map((type) => (
                                    <li key={type.id} className="space-y-2 p-3">
                                        <div className="min-w-0">
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-sm font-bold break-words">
                                                    {type.name}
                                                </p>
                                                <StatusBadge
                                                    active={type.isActive}
                                                    labels={[
                                                        'Visible',
                                                        'Hidden',
                                                    ]}
                                                />
                                            </div>
                                            <p className="mt-0.5 text-xs text-secondary-foreground">
                                                {type.services.length === 1
                                                    ? '1 service'
                                                    : `${type.services.length} services`}{' '}
                                                · Order {type.sortOrder}
                                            </p>
                                        </div>
                                        <div className="-ml-2 flex gap-1">
                                            {type.actions.includes('edit') ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setMode({
                                                            form: 'edit',
                                                            type,
                                                        })
                                                    }
                                                >
                                                    <Pencil aria-hidden />
                                                    Edit
                                                    <span className="sr-only">
                                                        {' '}
                                                        {type.name}
                                                    </span>
                                                </Button>
                                            ) : null}
                                            {type.actions.includes(
                                                'archive',
                                            ) ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:bg-destructive/10"
                                                    onClick={() =>
                                                        setArchiving(type)
                                                    }
                                                >
                                                    <Archive aria-hidden />
                                                    Archive
                                                    <span className="sr-only">
                                                        {' '}
                                                        {type.name}
                                                    </span>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {canCreate ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setMode({ form: 'add' })}
                            >
                                <Plus aria-hidden />
                                Add service type
                            </Button>
                        ) : null}
                    </div>
                )}
            </FormDrawer>

            {archiving !== null &&
            typeArchiveBlocker(archiving.services.length) !== null ? (
                <ConfirmDialog
                    open
                    onOpenChange={(next) => (next ? null : setArchiving(null))}
                    title={`${archiving.name} still has services`}
                    description={`${typeArchiveBlocker(archiving.services.length)} Then it can be archived.`}
                    confirmLabel="OK"
                    onConfirm={() => setArchiving(null)}
                />
            ) : (
                <ConfirmDialog
                    open={archiving !== null}
                    onOpenChange={(next) => (next ? null : setArchiving(null))}
                    title={`Archive ${archiving?.name ?? 'this service type'}?`}
                    description="It leaves this page and the Inquire page."
                    confirmLabel="Archive service type"
                    cancelLabel="Keep service type"
                    onConfirm={archive}
                    destructive
                    processing={processing}
                />
            )}
        </>
    );
};

export default ServiceTypesDrawer;

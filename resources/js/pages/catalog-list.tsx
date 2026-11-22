import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import { ImageOff, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import ChoiceSelect from '@/components/choice-select';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilterOption,
} from '@/components/data-table/data-table';
import RowActions from '@/components/data-table/row-actions';
import StatusToggle from '@/components/data-table/status-toggle';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import ImageField from '@/components/image-field';
import MultiSelect from '@/components/multi-select';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    imageAccept,
    imageFileSchema,
    imageHint,
    JPG,
    PNG,
    WEBP,
} from '@/lib/image-upload';
import { SLUG_PATTERN, slugify } from '@/lib/slug';
import * as listRoutes from '@/routes/dashboard/products/lists';
import type {
    CatalogField,
    CatalogItem,
    CatalogListPageProps,
} from '@/types/catalog-list';

const ONLY = ['items', 'filters'];

const IMAGE_TYPES = [PNG, JPG, WEBP];
const IMAGE_ACCEPT = imageAccept(IMAGE_TYPES);

const STATUS_OPTIONS: DataTableFilterOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Offered' },
    { value: 'inactive', label: 'Not offered' },
];

type Values = Record<string, unknown>;

/** A field's empty value: what the Add drawer starts from. */
const emptyValue = (field: CatalogField): unknown => {
    switch (field.type) {
        case 'multiselect':
            return [];
        case 'switch':
            // A new item is offered; it is not an "advise me" choice.
            return field.name === 'is_active';
        case 'image':
            return null;
        default:
            return '';
    }
};

const emptyValues = (fields: CatalogField[]): Values =>
    Object.fromEntries(fields.map((field) => [field.name, emptyValue(field)]));

const fromItem = (fields: CatalogField[], item: CatalogItem): Values =>
    Object.fromEntries(
        fields.map((field) => [
            field.name,
            field.type === 'image'
                ? null
                : (item.values[field.name] ?? emptyValue(field)),
        ]),
    );

const lower = (label: string) => label.charAt(0).toLowerCase() + label.slice(1);

/**
 * The drawer's rules, built from the list's fields: what is required, how
 * long, a slug's shape and a thumbnail's type and size. Whether a name is
 * taken, and a list's own checks, only the server knows. Whether an item is
 * being added (a thumbnail is required then) is `adding`.
 */
const schemaFor = (
    fields: CatalogField[],
    thumbnailMaxKb: number,
    adding: { current: boolean },
) =>
    z
        .object(
            Object.fromEntries(
                fields.map((field): [string, z.ZodType] => {
                    switch (field.type) {
                        case 'multiselect':
                            return [field.name, z.array(z.number())];
                        case 'switch':
                            return [field.name, z.boolean()];
                        case 'image':
                            return [
                                field.name,
                                imageFileSchema(
                                    IMAGE_TYPES,
                                    thumbnailMaxKb,
                                    'thumbnail',
                                ),
                            ];
                        case 'select':
                            return [
                                field.name,
                                field.required
                                    ? z
                                          .string()
                                          .min(
                                              1,
                                              `Choose the ${lower(field.label)}.`,
                                          )
                                    : z.string(),
                            ];
                        default: {
                            let text = z.string().trim();

                            if (field.required) {
                                text = text.min(
                                    1,
                                    `Enter the ${lower(field.label)}.`,
                                );
                            }

                            if (field.maxLength) {
                                text = text.max(
                                    field.maxLength,
                                    `Use ${field.maxLength.toLocaleString('en-PH')} characters or fewer.`,
                                );
                            }

                            if (field.type === 'slug') {
                                text = text.regex(
                                    SLUG_PATTERN,
                                    'Use lower-case letters, numbers and single hyphens.',
                                );
                            }

                            if (field.type === 'number') {
                                return [
                                    field.name,
                                    text.refine(
                                        (value) =>
                                            value === '' ||
                                            (Number.isFinite(Number(value)) &&
                                                Number(value) >= 0),
                                        'Enter a number.',
                                    ),
                                ];
                            }

                            return [field.name, text];
                        }
                    }
                }),
            ),
        )
        .superRefine((values, ctx) => {
            for (const field of fields) {
                if (
                    field.type === 'image' &&
                    field.required &&
                    adding.current &&
                    values[field.name] == null
                ) {
                    ctx.addIssue({
                        code: 'custom',
                        path: [field.name],
                        message: `Add the ${lower(field.label)}.`,
                    });
                }
            }
        });

type Drawer = { mode: 'add' } | { mode: 'edit'; item: CatalogItem };

/**
 * A list's page under Products, one page for all eight (Stone types, Sizes,
 * Thicknesses, Finishes, Edge profiles, Furniture types, Applications, Care
 * tips): its items in the list's order, searched, and filtered by whether
 * they are offered where the list has a switch.
 *
 * The drawer's fields are the list's (`list.fields`, from its definition on
 * the server). Archive asks first; an item still in use is refused, and the
 * server says why.
 */
const CatalogListPage = () => {
    const { props } = usePage<CatalogListPageProps>();
    const { list, limits } = props;
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<CatalogItem | null>(null);
    const [processing, setProcessing] = useState(false);
    const [slugEdited, setSlugEdited] = useState(false);
    const adding = useRef(true);

    const editing = drawer?.mode === 'edit' ? drawer.item : null;
    const hasImage = list.fields.some((field) => field.type === 'image');
    const hasSlug = list.fields.some((field) => field.type === 'slug');
    const singular = list.singular;
    const Singular = singular.charAt(0).toUpperCase() + singular.slice(1);

    const schema = useMemo(
        () => schemaFor(list.fields, limits.thumbnailMaxKb, adding),
        [list.fields, limits.thumbnailMaxKb],
    );
    const form = useForm<Values>({
        resolver: zodResolver(schema),
        mode: 'onTouched',
        defaultValues: emptyValues(list.fields),
    });
    const { register, handleSubmit, setError, setValue, reset, control } = form;
    const errors = form.formState.errors;
    const name = useWatch({ control, name: 'name' });

    useEffect(() => {
        if (drawer?.mode === 'edit') {
            adding.current = false;
            setSlugEdited(true);
            reset(fromItem(list.fields, drawer.item));
        } else if (drawer?.mode === 'add') {
            adding.current = true;
            setSlugEdited(false);
            reset(emptyValues(list.fields));
        }
    }, [drawer, list.fields, reset]);

    // While adding, the slug follows the name until it is typed by hand.
    useEffect(() => {
        if (hasSlug && drawer?.mode === 'add' && !slugEdited) {
            setValue('slug', slugify(typeof name === 'string' ? name : ''));
        }
    }, [hasSlug, drawer, slugEdited, name, setValue]);

    const close = () => setDrawer(null);
    const message = (field: string) => {
        const error = errors[field];

        return typeof error?.message === 'string' ? error.message : undefined;
    };

    const showErrors = (serverErrors: Record<string, string>) => {
        const names = new Set(list.fields.map((field) => field.name));
        let unplaced: string | null = null;

        for (const [key, text] of Object.entries(serverErrors)) {
            // `finishes.2` is the Finishes field's.
            const field = key.split('.')[0];

            if (names.has(field)) {
                setError(field, { type: 'server', message: text });
            } else {
                unplaced ??= text;
            }
        }

        if (unplaced) {
            toast.error(unplaced);
        }
    };

    const save = handleSubmit((values) => {
        const payload: Record<string, File | string | boolean | number[]> = {};

        for (const field of list.fields) {
            const value = values[field.name];

            if (field.type === 'image') {
                if (value instanceof File) {
                    payload[field.name] = value;
                }
            } else {
                payload[field.name] = value as string | boolean | number[];
            }
        }

        const options = {
            only: ONLY,
            preserveScroll: true,
            preserveState: true,
            forceFormData: hasImage,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: showErrors,
        };

        if (editing) {
            router.post(
                listRoutes.update.url({ list: list.slug, id: editing.id }),
                { ...payload, _method: 'put' },
                options,
            );
        } else {
            router.post(listRoutes.store.url(list.slug), payload, options);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            listRoutes.archive.url({ list: list.slug, id: archiving.id }),
            {},
            {
                only: ONLY,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setArchiving(null),
                onError: (serverErrors) => {
                    setArchiving(null);
                    toast.error(
                        Object.values(serverErrors)[0] ??
                            `The ${singular} could not be archived. Try again.`,
                    );
                },
            },
        );
    };

    const columns: DataTableColumn<CatalogItem>[] = [
        {
            key: 'name',
            header: Singular,
            cell: (item) => (
                <span className="flex items-center gap-3">
                    {hasImage ? (
                        <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden bg-muted">
                            {item.thumbnail ? (
                                <img
                                    src={item.thumbnail}
                                    alt=""
                                    loading="lazy"
                                    className="size-full object-cover"
                                />
                            ) : (
                                <ImageOff
                                    aria-hidden
                                    className="size-4 text-secondary-foreground"
                                />
                            )}
                        </span>
                    ) : null}
                    <span className="block max-w-80 truncate font-medium">
                        {item.name}
                    </span>
                </span>
            ),
        },
        ...list.columns.map((column): DataTableColumn<CatalogItem> => ({
            key: column.key,
            header: column.header,
            cell: (item) => item.cells[column.key] || '—',
        })),
        ...(list.hasStatus
            ? [
                  {
                      key: 'status',
                      header: 'Offered',
                      cell: (item: CatalogItem) => (
                          <StatusToggle
                              checked={item.isActive === true}
                              disabled={!item.canToggle}
                              label={`${item.isActive ? 'Stop offering' : 'Offer'} ${item.name}`}
                              onToggle={(active, visit) =>
                                  router.patch(
                                      listRoutes.status.url({
                                          list: list.slug,
                                          id: item.id,
                                      }),
                                      { active },
                                      {
                                          only: ONLY,
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
              ]
            : []),
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            cell: (item) => (
                <RowActions
                    actions={item.actions}
                    subject={item.name}
                    maxInline={2}
                    handlers={{
                        edit: () => setDrawer({ mode: 'edit', item }),
                        archive: () => setArchiving(item),
                    }}
                />
            ),
        },
    ];

    const renderField = (field: CatalogField) => {
        const id = `catalog-${field.name}`;

        if (field.type === 'image') {
            return (
                <Controller
                    key={field.name}
                    control={control}
                    name={field.name}
                    render={({ field: input, fieldState }) => (
                        <ImageField
                            id={id}
                            label={field.label}
                            required={field.required && editing === null}
                            accept={IMAGE_ACCEPT}
                            hint={imageHint(
                                IMAGE_TYPES,
                                limits.thumbnailMaxKb,
                                editing
                                    ? `${field.hint ?? ''} Leave it to keep the current one.`.trim()
                                    : field.hint,
                            )}
                            error={fieldState.error?.message}
                            file={
                                input.value instanceof File ? input.value : null
                            }
                            currentUrl={editing?.thumbnail ?? null}
                            onFileChange={(file) => {
                                input.onChange(file);
                                input.onBlur();
                            }}
                        />
                    )}
                />
            );
        }

        if (field.type === 'switch') {
            return (
                <Controller
                    key={field.name}
                    control={control}
                    name={field.name}
                    render={({ field: input }) => (
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <label
                                    htmlFor={id}
                                    className="text-sm font-bold"
                                >
                                    {field.label}
                                </label>
                                {field.hint ? (
                                    <p
                                        id={`${id}-hint`}
                                        className="mt-1 text-sm text-secondary-foreground"
                                    >
                                        {field.hint}
                                    </p>
                                ) : null}
                            </div>
                            <Switch
                                id={id}
                                checked={input.value === true}
                                onCheckedChange={input.onChange}
                                aria-describedby={
                                    field.hint ? `${id}-hint` : undefined
                                }
                            />
                        </div>
                    )}
                />
            );
        }

        return (
            <FormField
                key={field.name}
                id={id}
                label={field.label}
                required={field.required}
                error={message(field.name)}
                hint={field.hint}
            >
                {({ describedBy, invalid }) => {
                    switch (field.type) {
                        case 'textarea':
                            return (
                                <Textarea
                                    id={id}
                                    rows={4}
                                    maxLength={field.maxLength}
                                    className="mt-2"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register(field.name)}
                                />
                            );
                        case 'select':
                            return (
                                <Controller
                                    control={control}
                                    name={field.name}
                                    render={({ field: input }) => (
                                        <ChoiceSelect
                                            id={id}
                                            value={
                                                typeof input.value === 'string'
                                                    ? input.value
                                                    : ''
                                            }
                                            onChange={input.onChange}
                                            onBlur={input.onBlur}
                                            options={(field.options ?? []).map(
                                                (option) => ({
                                                    value: option.value ?? '',
                                                    label: option.label,
                                                }),
                                            )}
                                            placeholder={`Choose the ${lower(field.label)}`}
                                            listLabel={field.label}
                                            describedBy={describedBy}
                                            invalid={invalid}
                                        />
                                    )}
                                />
                            );
                        case 'multiselect':
                            return (
                                <Controller
                                    control={control}
                                    name={field.name}
                                    render={({ field: input }) => (
                                        <div className="mt-2">
                                            <MultiSelect
                                                id={id}
                                                value={
                                                    Array.isArray(input.value)
                                                        ? (input.value as number[])
                                                        : []
                                                }
                                                onChange={input.onChange}
                                                onBlur={input.onBlur}
                                                options={(
                                                    field.options ?? []
                                                ).map((option) => ({
                                                    id: option.id ?? 0,
                                                    label: option.label,
                                                }))}
                                                placeholder={`Search the ${lower(field.label)}`}
                                                listLabel={field.label}
                                                emptyMessage="Nothing matches that."
                                                describedBy={describedBy}
                                                invalid={invalid}
                                            />
                                        </div>
                                    )}
                                />
                            );
                        default:
                            return (
                                <Input
                                    id={id}
                                    type={
                                        field.type === 'number'
                                            ? 'number'
                                            : 'text'
                                    }
                                    inputMode={
                                        field.type === 'number'
                                            ? 'decimal'
                                            : undefined
                                    }
                                    step={field.step}
                                    min={
                                        field.type === 'number' ? 0 : undefined
                                    }
                                    autoComplete="off"
                                    spellCheck={
                                        field.type === 'slug'
                                            ? false
                                            : undefined
                                    }
                                    maxLength={field.maxLength}
                                    className={`mt-2 rounded-none${field.type === 'slug' ? ' font-mono' : ''}`}
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register(field.name, {
                                        onChange:
                                            field.type === 'slug'
                                                ? () => setSlugEdited(true)
                                                : undefined,
                                    })}
                                />
                            );
                    }
                }}
            </FormField>
        );
    };

    const addButton = (
        <Button type="button" onClick={() => setDrawer({ mode: 'add' })}>
            <Plus aria-hidden />
            Add {singular}
        </Button>
    );

    return (
        <div className="w-full min-w-0">
            {props.canCreate ? (
                <PageHeader
                    variant="action"
                    title={list.title}
                    subtitle={list.subtitle}
                    action={addButton}
                />
            ) : (
                <PageHeader title={list.title} subtitle={list.subtitle} />
            )}

            <DataTable
                key={list.slug}
                rows={props.items.data}
                meta={props.items.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(item) => item.id}
                only={ONLY}
                searchPlaceholder={`Search the ${lower(list.title)}`}
                emptyTitle={`No ${lower(list.title)} yet`}
                emptyDescription={`Add a ${singular} for products to use.`}
                filteredEmptyTitle={`No ${lower(list.title)} match that`}
                filteredEmptyDescription="Try another search or status."
                statusOptions={list.hasStatus ? STATUS_OPTIONS : undefined}
            />

            <FormDrawer
                open={drawer !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={editing ? `Edit ${editing.name}` : `Add ${singular}`}
                description={
                    editing
                        ? `Changes show everywhere this ${singular} is used.`
                        : list.subtitle
                }
                submitLabel={editing ? 'Save changes' : `Add ${singular}`}
                onSubmit={save}
                processing={processing}
                size={list.fields.length > 4 ? 'lg' : 'md'}
            >
                <div className="space-y-6">{list.fields.map(renderField)}</div>
            </FormDrawer>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => (open ? null : setArchiving(null))}
                title={`Archive ${archiving?.name ?? `this ${singular}`}?`}
                description={`It leaves this list and can no longer be chosen; past inquiries keep it. A ${singular} a product or stone type still uses can't be archived.`}
                confirmLabel="Archive"
                cancelLabel="Keep it"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

export default CatalogListPage;

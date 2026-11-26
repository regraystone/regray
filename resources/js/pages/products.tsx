import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import { Armchair, ChevronDown, Gem, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Controller,
    useFieldArray,
    useForm,
    useWatch,
    type FieldPath,
} from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { Section } from '@/components/admin-drawer-parts';
import ChoiceSelect from '@/components/choice-select';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTable, {
    type DataTableColumn,
    type DataTableFilterOption,
} from '@/components/data-table/data-table';
import DataTableFilter from '@/components/data-table/data-table-filter';
import RowActions from '@/components/data-table/row-actions';
import StatusToggle from '@/components/data-table/status-toggle';
import { useDataTableQuery } from '@/components/data-table/use-data-table-query';
import FormDrawer from '@/components/form-drawer';
import FormField, { fieldMessage } from '@/components/form-field';
import GalleryField from '@/components/gallery-field';
import ImageField from '@/components/image-field';
import MultiSelect from '@/components/multi-select';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import {
    archiveDescription,
    KIND_LABELS,
    productDetail,
    productUrl,
} from '@/lib/product-admin';
import { SLUG_PATTERN, slugify } from '@/lib/slug';
import * as furnitureRoutes from '@/routes/dashboard/products/furniture';
import * as stoneRoutes from '@/routes/dashboard/products/stones';
import type {
    ProductKind,
    ProductLimits,
    ProductOption,
    ProductRow,
    ProductsPageProps,
    TableChoiceColumn,
} from '@/types/product-admin';

const ONLY = ['products', 'filters'];

/** The image types a thumbnail and a gallery photo may be (config/products.php). */
const IMAGE_TYPES = [PNG, JPG, WEBP];
const IMAGE_ACCEPT = imageAccept(IMAGE_TYPES);

/** A size row's value for a stone sold without set sizes. */
const NO_SIZE = 'none';

const ROUTES = { stone: stoneRoutes, furniture: furnitureRoutes };

const KIND_OPTIONS: DataTableFilterOption[] = [
    { value: 'all', label: 'All kinds' },
    { value: 'stone', label: 'Stones' },
    { value: 'furniture', label: 'Furniture' },
];

const STATUS_OPTIONS: DataTableFilterOption[] = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

/** One photo of the gallery in the form: a saved one (`photo_id`) or a new file. */
type GalleryRow = {
    photo_id: number | null;
    url: string | null;
    name: string | null;
    file: File | null;
};

/** One size a stone comes in: a size's id (or NO_SIZE) and one thickness's. */
type SizeRow = { size_id: string; thickness_id: string };

type ProductValues = {
    brand_id: string;
    type_id: string;
    name: string;
    slug: string;
    is_active: boolean;
    origin: string;
    size_note: string;
    types: number[];
    sizes: SizeRow[];
    description: string;
    /** A table's own shape and base design, by option id, or NO_CHOICE. */
    table_shape_option_id: string;
    table_base_design_option_id: string;
    thumbnail: File | null;
    gallery: GalleryRow[];
};

/** A table's shape or base design left unset (a select can't hold ''). */
const NO_CHOICE = 'none';

/** The two table questions a piece can answer for its Inquire button, as the drawer labels them. */
const TABLE_CHOICES: { column: TableChoiceColumn; label: string }[] = [
    { column: 'table_shape_option_id', label: 'Table shape' },
    { column: 'table_base_design_option_id', label: 'Table base design' },
];

const emptyValues = (kind: ProductKind): ProductValues => ({
    brand_id: '',
    type_id: '',
    name: '',
    slug: '',
    is_active: true,
    origin: '',
    size_note: '',
    types: [],
    sizes: kind === 'stone' ? [{ size_id: '', thickness_id: '' }] : [],
    description: '',
    table_shape_option_id: NO_CHOICE,
    table_base_design_option_id: NO_CHOICE,
    thumbnail: null,
    gallery: [],
});

const fromRow = (row: ProductRow): ProductValues => ({
    brand_id: String(row.brand.id),
    type_id: String(row.type.id),
    name: row.name,
    slug: row.slug,
    is_active: row.isActive,
    origin: row.origin ?? '',
    size_note: row.sizeNote ?? '',
    types: row.types.map((type) => type.id),
    sizes: row.sizes.map((size) => ({
        size_id: size.sizeId === null ? NO_SIZE : String(size.sizeId),
        thickness_id: String(size.thicknessId),
    })),
    description: row.description ?? '',
    table_shape_option_id: row.tableShape
        ? String(row.tableShape.id)
        : NO_CHOICE,
    table_base_design_option_id: row.tableBaseDesign
        ? String(row.tableBaseDesign.id)
        : NO_CHOICE,
    thumbnail: null,
    gallery: row.gallery.map((photo) => ({
        photo_id: photo.id,
        url: photo.url,
        name: null,
        file: null,
    })),
});

/**
 * The form's rules, the server's own (StoneValidationRules,
 * FurnitureValidationRules), so the drawer says what is wrong before it sends
 * anything. Whether a name or slug is taken only the server knows. What the
 * form is for (a stone or a piece, added or changed) is `context`.
 */
const productSchema = (
    limits: ProductLimits,
    reserved: string[],
    context: { current: { kind: ProductKind; adding: boolean } },
) =>
    z
        .object({
            brand_id: z.string().min(1, 'Choose the brand.'),
            type_id: z.string().min(1, 'Choose the type.'),
            name: z
                .string()
                .trim()
                .min(1, 'Enter the name.')
                .max(150, 'Use 150 characters or fewer.'),
            slug: z
                .string()
                .trim()
                .min(1, 'Enter the slug for its link.')
                .max(100, 'Use 100 characters or fewer.')
                .regex(
                    SLUG_PATTERN,
                    'Use lower-case letters, numbers and single hyphens, such as bianco-carrara.',
                )
                .refine(
                    (slug) => !reserved.includes(slug),
                    "That slug is kept for one of Regray's own pages. Choose another.",
                ),
            is_active: z.boolean(),
            origin: z.string().trim().max(255, 'Use 255 characters or fewer.'),
            size_note: z
                .string()
                .trim()
                .max(100, 'Use 100 characters or fewer.'),
            types: z.array(z.number()),
            sizes: z.array(
                z.object({
                    size_id: z.string(),
                    thickness_id: z.string(),
                }),
            ),
            description: z
                .string()
                .trim()
                .max(
                    limits.descriptionMax,
                    `Keep the description to ${limits.descriptionMax.toLocaleString('en-PH')} characters.`,
                ),
            table_shape_option_id: z.string(),
            table_base_design_option_id: z.string(),
            thumbnail: imageFileSchema(
                IMAGE_TYPES,
                limits.thumbnailMaxKb,
                'thumbnail',
            ),
            gallery: z
                .array(
                    z.object({
                        photo_id: z.number().nullable(),
                        url: z.string().nullable(),
                        name: z.string().nullable(),
                        file: imageFileSchema(
                            IMAGE_TYPES,
                            limits.photoMaxKb,
                            'photo',
                        ),
                    }),
                )
                .max(
                    limits.galleryMax,
                    `A gallery can have ${limits.galleryMax} photos at most.`,
                ),
        })
        .superRefine((values, ctx) => {
            const { kind, adding } = context.current;

            if (kind !== 'stone') {
                return;
            }

            if (adding && values.thumbnail === null) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['thumbnail'],
                    message: "Add the stone's thumbnail.",
                });
            }

            if (values.origin.trim() === '') {
                ctx.addIssue({
                    code: 'custom',
                    path: ['origin'],
                    message: 'Enter where the stone comes from.',
                });
            }

            if (values.sizes.length === 0) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['sizes'],
                    message: 'Add at least one size the stone comes in.',
                });
            } else if (values.sizes.length > limits.sizesMax) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['sizes'],
                    message: `A stone can list ${limits.sizesMax} sizes at most.`,
                });
            }

            if (
                values.sizes.some((row) => row.size_id === NO_SIZE) &&
                values.size_note.trim() === ''
            ) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['size_note'],
                    message: 'Say how big the pieces run.',
                });
            }

            const seen = new Set<string>();

            values.sizes.forEach((row, index) => {
                if (row.size_id === '') {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['sizes', index, 'size_id'],
                        message: 'Choose a size, or "No set size".',
                    });
                }

                if (row.thickness_id === '') {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['sizes', index, 'thickness_id'],
                        message: 'Choose the thickness.',
                    });
                } else if (row.size_id !== '') {
                    const pair = `${row.size_id}x${row.thickness_id}`;

                    if (seen.has(pair)) {
                        ctx.addIssue({
                            code: 'custom',
                            path: ['sizes', index, 'thickness_id'],
                            message:
                                'That size and thickness are already listed.',
                        });
                    }

                    seen.add(pair);
                }
            });
        });

type Drawer =
    | { mode: 'add'; kind: ProductKind }
    | { mode: 'edit'; row: ProductRow };

/** A row's thumbnail, or its kind's placeholder. */
const ProductThumbnail = ({ row }: { row: ProductRow }) => (
    <span className="flex size-10 shrink-0 items-center justify-center overflow-hidden bg-muted">
        {row.thumbnail ? (
            <img
                src={row.thumbnail}
                alt=""
                loading="lazy"
                className="size-full object-cover"
            />
        ) : row.kind === 'stone' ? (
            <Gem aria-hidden className="size-4 text-secondary-foreground" />
        ) : (
            <Armchair
                aria-hidden
                className="size-4 text-secondary-foreground"
            />
        )}
    </span>
);

/**
 * The admin's Products page: every stone and piece of furniture on Regray but
 * the archived, inactive ones too, as one table by name, searched by name and
 * filtered by kind, brand and status.
 *
 * Add product asks which kind, then opens the drawer for it; Edit opens the
 * same drawer filled in, and Archive asks first. A row's switch turns it on
 * or off. What a row offers comes from the server (`actions`).
 */
const Products = () => {
    const { props } = usePage<ProductsPageProps>();
    const { limits, options } = props;
    const { update } = useDataTableQuery(ONLY);
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<ProductRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const [slugEdited, setSlugEdited] = useState(false);
    const context = useRef<{ kind: ProductKind; adding: boolean }>({
        kind: 'stone',
        adding: true,
    });

    const editing = drawer?.mode === 'edit' ? drawer.row : null;
    const adding = drawer?.mode === 'add';
    const kind: ProductKind =
        drawer?.mode === 'add' ? drawer.kind : (editing?.kind ?? 'stone');
    const isStone = kind === 'stone';

    const schema = useMemo(
        () => productSchema(limits, props.reservedSlugs, context),
        [limits, props.reservedSlugs],
    );

    const form = useForm<ProductValues>({
        resolver: zodResolver(schema),
        mode: 'onTouched',
        defaultValues: emptyValues('stone'),
    });
    const {
        register,
        handleSubmit,
        setError,
        setValue,
        getValues,
        reset,
        control,
        formState,
    } = form;
    const gallery = useFieldArray({ control, name: 'gallery' });
    const sizes = useFieldArray({ control, name: 'sizes' });
    const galleryRows = useWatch({ control, name: 'gallery' }) ?? [];
    const sizeRows = useWatch({ control, name: 'sizes' }) ?? [];
    const [name, slug, brandId, typeId] = useWatch({
        control,
        name: ['name', 'slug', 'brand_id', 'type_id'],
    });
    // The shapes and base designs a table of the chosen type may be given.
    const tableChoices: Partial<Record<TableChoiceColumn, ProductOption[]>> =
        isStone ? {} : (options.tableChoices[typeId ?? ''] ?? {});

    // The form starts from the product being edited, or empty to add one.
    useEffect(() => {
        if (drawer?.mode === 'edit') {
            context.current = { kind: drawer.row.kind, adding: false };
            setSlugEdited(true);
            reset(fromRow(drawer.row));
        } else if (drawer?.mode === 'add') {
            context.current = { kind: drawer.kind, adding: true };
            setSlugEdited(false);
            reset(emptyValues(drawer.kind));
        }
    }, [drawer, reset]);

    // While adding, the slug follows the name until it is typed by hand.
    useEffect(() => {
        if (adding && !slugEdited) {
            setValue('slug', slugify(name ?? ''), {
                shouldValidate: formState.touchedFields.slug === true,
            });
        }
    }, [adding, slugEdited, name, setValue, formState.touchedFields.slug]);

    /** A new type; a shape or base it doesn't offer (nor the piece already has) goes. */
    const chooseType = (value: string) => {
        setValue('type_id', value, { shouldValidate: true, shouldDirty: true });

        const offered = isStone ? {} : (options.tableChoices[value] ?? {});

        for (const { column } of TABLE_CHOICES) {
            const current = getValues(column);
            const held =
                column === 'table_shape_option_id'
                    ? editing?.tableShape
                    : editing?.tableBaseDesign;
            const kept =
                current === NO_CHOICE ||
                String(held?.id) === current ||
                (offered[column] ?? []).some(
                    ({ id }) => String(id) === current,
                );

            if (!kept) {
                setValue(column, NO_CHOICE);
            }
        }
    };

    const close = () => setDrawer(null);

    // The server's field names, as the form calls them.
    const FIELD_NAMES: Record<string, FieldPath<ProductValues>> = {
        stone_type_id: 'type_id',
        furniture_type_id: 'type_id',
        variants: 'sizes',
    };

    const showErrors = (errors: Record<string, string>) => {
        const fields = new Set<string>(Object.keys(emptyValues('stone')));
        let unplaced: string | null = null;

        for (const [key, message] of Object.entries(errors)) {
            const photo = /^gallery\.(\d+)\.(id|file)$/.exec(key);
            const size = /^variants\.(\d+)\.(size_id|thickness_id)$/.exec(key);
            const field = FIELD_NAMES[key] ?? key;

            if (fields.has(field)) {
                setError(field as FieldPath<ProductValues>, {
                    type: 'server',
                    message,
                });
            } else if (photo) {
                setError(`gallery.${Number(photo[1])}.file`, {
                    type: 'server',
                    message,
                });
            } else if (size) {
                setError(
                    `sizes.${Number(size[1])}.${size[2] === 'size_id' ? 'size_id' : 'thickness_id'}`,
                    { type: 'server', message },
                );
            } else if (key.startsWith('gallery')) {
                setError('gallery', { type: 'server', message });
            } else if (key.startsWith('types')) {
                setError('types', { type: 'server', message });
            } else {
                unplaced ??= message;
            }
        }

        if (unplaced) {
            toast.error(unplaced);
        }
    };

    const save = handleSubmit((values) => {
        const shared = {
            brand_id: values.brand_id,
            name: values.name,
            slug: values.slug,
            is_active: values.is_active,
            // Saved photos by id, new ones as files, in the gallery's order.
            gallery: values.gallery.map((row) =>
                row.photo_id !== null
                    ? { id: row.photo_id }
                    : { file: row.file },
            ),
            ...(values.thumbnail ? { thumbnail: values.thumbnail } : {}),
        };
        const payload = isStone
            ? {
                  ...shared,
                  stone_type_id: values.type_id,
                  types: values.types,
                  origin: values.origin,
                  size_note: values.size_note,
                  variants: values.sizes.map((row) => ({
                      size_id: row.size_id === NO_SIZE ? '' : row.size_id,
                      thickness_id: row.thickness_id,
                  })),
              }
            : {
                  ...shared,
                  furniture_type_id: values.type_id,
                  description: values.description,
                  // Only what the type offers is sent; '' is none.
                  ...Object.fromEntries(
                      TABLE_CHOICES.map(({ column }) => [
                          column,
                          tableChoices[column] && values[column] !== NO_CHOICE
                              ? values[column]
                              : '',
                      ]),
                  ),
              };
        const requestOptions = {
            only: ONLY,
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: showErrors,
        };
        const routes = ROUTES[kind];

        if (editing) {
            // A PUT can't carry files, so it is a POST that says it is a PUT.
            router.post(
                routes.update.url(editing.id),
                { ...payload, _method: 'put' },
                requestOptions,
            );
        } else {
            router.post(routes.store.url(), payload, requestOptions);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            ROUTES[archiving.kind].archive.url(archiving.id),
            {},
            {
                only: ONLY,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setArchiving(null),
                onError: (errors) => {
                    setArchiving(null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'The product could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const brandOptions = options.brands.map((brand) => ({
        value: String(brand.id),
        label: brand.name,
    }));
    const typeOptions = (
        isStone ? options.stoneTypes : options.furnitureTypes
    ).map((type) => ({ value: String(type.id), label: type.name }));
    // The type a product being edited has, even once it is no longer offered.
    if (
        editing &&
        !typeOptions.some((o) => o.value === String(editing.type.id))
    ) {
        typeOptions.push({
            value: String(editing.type.id),
            label: editing.type.name,
        });
    }
    // A table question's choices, plus the one a piece being edited has, even once no longer offered.
    const tableChoiceOptions = (column: TableChoiceColumn) => {
        const held =
            column === 'table_shape_option_id'
                ? editing?.tableShape
                : editing?.tableBaseDesign;
        const offered = tableChoices[column] ?? [];
        const listed =
            held && !offered.some(({ id }) => id === held.id)
                ? [...offered, held]
                : offered;

        return [
            { value: NO_CHOICE, label: 'None' },
            ...listed.map((choice) => ({
                value: String(choice.id),
                label: choice.name,
            })),
        ];
    };
    const sizeOptions = [
        ...options.sizes.map((size) => ({
            value: String(size.id),
            label: size.label,
        })),
        { value: NO_SIZE, label: 'No set size' },
    ];
    const thicknessOptions = options.thicknesses.map((thickness) => ({
        value: String(thickness.id),
        label: thickness.label,
    }));
    const stoneTypeOptions = options.stoneTypes.map((type) => ({
        id: type.id,
        label: type.name,
    }));
    const extraTypes = Object.fromEntries(
        (editing?.types ?? []).map((type) => [type.id, type.name]),
    );
    const brandSlug = options.brands.find(
        (brand) => String(brand.id) === brandId,
    )?.slug;
    const brandFilterOptions: DataTableFilterOption[] = [
        { value: 'all', label: 'All brands' },
        ...options.brands.map((brand) => ({
            value: brand.slug,
            label: brand.name,
        })),
    ];
    const usesNoSize = sizeRows.some((row) => row.size_id === NO_SIZE);

    const columns: DataTableColumn<ProductRow>[] = [
        {
            key: 'product',
            header: 'Product',
            cell: (row) => (
                <span className="flex items-center gap-3">
                    <ProductThumbnail row={row} />
                    <span className="min-w-0">
                        <span className="block max-w-56 truncate font-medium">
                            {row.name}
                        </span>
                        <span className="block max-w-56 truncate text-xs text-secondary-foreground">
                            {row.slug}
                        </span>
                    </span>
                </span>
            ),
        },
        {
            key: 'kind',
            header: 'Kind',
            cell: (row) => (
                <Badge variant="outline" className="rounded-none">
                    {KIND_LABELS[row.kind]}
                </Badge>
            ),
        },
        { key: 'brand', header: 'Brand', cell: (row) => row.brand.name },
        { key: 'type', header: 'Type', cell: (row) => row.type.name },
        {
            key: 'details',
            header: 'Details',
            cell: (row) =>
                productDetail(row.kind, row.sizes, row.gallery.length),
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
                            ROUTES[row.kind].status.url(row.id),
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
                        view: () =>
                            row.publicUrl &&
                            window.open(row.publicUrl, '_blank', 'noopener'),
                        edit: () => setDrawer({ mode: 'edit', row }),
                        archive: () => setArchiving(row),
                    }}
                />
            ),
        },
    ];

    const addButton = (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button">
                    <Plus aria-hidden />
                    Add product
                    <ChevronDown aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-(--radix-dropdown-menu-trigger-width) min-w-0 rounded-none"
            >
                <DropdownMenuItem
                    onSelect={() => setDrawer({ mode: 'add', kind: 'stone' })}
                >
                    <Gem aria-hidden />
                    Stone
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={() =>
                        setDrawer({ mode: 'add', kind: 'furniture' })
                    }
                >
                    <Armchair aria-hidden />
                    Furniture
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
    const noun = isStone ? 'stone' : 'piece';

    return (
        <div className="w-full min-w-0">
            {props.canCreate ? (
                <PageHeader
                    variant="action"
                    title="Products"
                    subtitle="Manage the stones and furniture shown across Regray"
                    action={addButton}
                />
            ) : (
                <PageHeader
                    title="Products"
                    subtitle="Manage the stones and furniture shown across Regray"
                />
            )}

            <DataTable
                rows={props.products.data}
                meta={props.products.meta}
                filters={props.filters}
                columns={columns}
                rowKey={(row) => row.key}
                only={ONLY}
                searchPlaceholder="Search by product name"
                emptyTitle="No products yet"
                emptyDescription="Add a stone or a piece of furniture to show it on Regray."
                filteredEmptyTitle="No products match that"
                filteredEmptyDescription="Try another name, or a different kind, brand or status."
                statusOptions={STATUS_OPTIONS}
                extraFiltered={
                    props.filters.kind !== 'all' ||
                    props.filters.brand !== 'all'
                }
                extraFilters={
                    <>
                        <DataTableFilter
                            label="Kind"
                            options={KIND_OPTIONS}
                            value={props.filters.kind}
                            onChange={(value) => update({ kind: value })}
                        />
                        <DataTableFilter
                            label="Brand"
                            options={brandFilterOptions}
                            value={props.filters.brand}
                            onChange={(value) => update({ brand: value })}
                        />
                    </>
                }
            />

            {/* Add and Edit: one form, a stone's or a piece's. */}
            <FormDrawer
                open={drawer !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={
                    editing
                        ? `Edit ${editing.name}`
                        : isStone
                          ? 'Add stone'
                          : 'Add furniture'
                }
                description={
                    editing
                        ? `Change what Regray shows about this ${noun}.`
                        : isStone
                          ? 'A stone with its own page on Regray, under its brand.'
                          : 'A piece of furniture with its own page on Regray, under its brand.'
                }
                submitLabel={
                    editing
                        ? 'Save changes'
                        : isStone
                          ? 'Add stone'
                          : 'Add furniture'
                }
                onSubmit={save}
                processing={processing}
                size="lg"
            >
                <div className="space-y-6">
                    <Section title="Identity" as="fieldset">
                        <FormField
                            id="product-brand"
                            label="Brand"
                            required
                            error={formState.errors.brand_id?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="brand_id"
                                    render={({ field }) => (
                                        <ChoiceSelect
                                            id="product-brand"
                                            value={field.value}
                                            onChange={field.onChange}
                                            onBlur={field.onBlur}
                                            options={brandOptions}
                                            placeholder="Choose a brand"
                                            describedBy={describedBy}
                                            invalid={invalid}
                                        />
                                    )}
                                />
                            )}
                        </FormField>

                        <FormField
                            id="product-name"
                            label="Name"
                            required
                            error={formState.errors.name?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="product-name"
                                    autoComplete="off"
                                    maxLength={150}
                                    className="mt-2 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('name')}
                                />
                            )}
                        </FormField>

                        <FormField
                            id="product-slug"
                            label="Slug"
                            required
                            error={formState.errors.slug?.message}
                            hint={`${productUrl(props.publicUrls[kind], brandSlug, slug)}${
                                editing
                                    ? `. Changing it changes the ${noun}’s link.`
                                    : ''
                            }`}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="product-slug"
                                    autoComplete="off"
                                    maxLength={100}
                                    spellCheck={false}
                                    className="mt-2 rounded-none font-mono"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('slug', {
                                        onChange: () => setSlugEdited(true),
                                    })}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Details" as="fieldset">
                        <FormField
                            id="product-type"
                            label={isStone ? 'Stone type' : 'Furniture type'}
                            required
                            error={formState.errors.type_id?.message}
                            hint={
                                isStone
                                    ? 'Its page shows the type’s description, finishes, uses and care.'
                                    : undefined
                            }
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="type_id"
                                    render={({ field }) => (
                                        <ChoiceSelect
                                            id="product-type"
                                            value={field.value}
                                            onChange={chooseType}
                                            onBlur={field.onBlur}
                                            options={typeOptions}
                                            placeholder="Choose a type"
                                            describedBy={describedBy}
                                            invalid={invalid}
                                        />
                                    )}
                                />
                            )}
                        </FormField>

                        {TABLE_CHOICES.filter(
                            ({ column }) => tableChoices[column] !== undefined,
                        ).map(({ column, label }) => (
                            <FormField
                                key={column}
                                id={`product-${column}`}
                                label={label}
                                error={formState.errors[column]?.message}
                                hint="Picked for the client when they inquire from its page. Optional."
                            >
                                {({ describedBy, invalid }) => (
                                    <Controller
                                        control={control}
                                        name={column}
                                        render={({ field }) => (
                                            <ChoiceSelect
                                                id={`product-${column}`}
                                                value={field.value}
                                                onChange={field.onChange}
                                                onBlur={field.onBlur}
                                                options={tableChoiceOptions(
                                                    column,
                                                )}
                                                placeholder="None"
                                                describedBy={describedBy}
                                                invalid={invalid}
                                            />
                                        )}
                                    />
                                )}
                            </FormField>
                        ))}

                        {isStone ? (
                            <>
                                <FormField
                                    id="product-types"
                                    label="Also listed under"
                                    error={
                                        formState.errors.types?.message ??
                                        formState.errors.types?.root?.message
                                    }
                                    hint="Other types its brand page lists it under. Optional."
                                >
                                    {({ describedBy, invalid }) => (
                                        <Controller
                                            control={control}
                                            name="types"
                                            render={({ field }) => (
                                                <div className="mt-2">
                                                    <MultiSelect
                                                        id="product-types"
                                                        value={field.value}
                                                        onChange={
                                                            field.onChange
                                                        }
                                                        onBlur={field.onBlur}
                                                        options={
                                                            stoneTypeOptions
                                                        }
                                                        extraLabels={extraTypes}
                                                        placeholder="Search the stone types"
                                                        listLabel="Also listed under"
                                                        emptyMessage="No stone type matches that."
                                                        describedBy={
                                                            describedBy
                                                        }
                                                        invalid={invalid}
                                                    />
                                                </div>
                                            )}
                                        />
                                    )}
                                </FormField>

                                <FormField
                                    id="product-origin"
                                    label="Origin"
                                    required
                                    error={formState.errors.origin?.message}
                                    hint="Such as Italy."
                                >
                                    {({ describedBy, invalid }) => (
                                        <Input
                                            id="product-origin"
                                            autoComplete="off"
                                            maxLength={255}
                                            className="mt-2 rounded-none"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            {...register('origin')}
                                        />
                                    )}
                                </FormField>
                            </>
                        ) : (
                            <FormField
                                id="product-description"
                                label="Description"
                                error={formState.errors.description?.message}
                                hint="Shown on its page. Optional."
                            >
                                {({ describedBy, invalid }) => (
                                    <Textarea
                                        id="product-description"
                                        rows={5}
                                        className="mt-2"
                                        aria-describedby={describedBy}
                                        aria-invalid={invalid}
                                        {...register('description')}
                                    />
                                )}
                            </FormField>
                        )}
                    </Section>

                    {isStone ? (
                        <Section title="Sizes" as="fieldset">
                            <p className="text-sm text-secondary-foreground">
                                Each size the stone comes in and a thickness it
                                is cut in at that size, one pair a row. A size
                                cut in two thicknesses is two rows. Its page
                                lists them in this order.
                            </p>
                            {formState.errors.sizes?.message ||
                            formState.errors.sizes?.root?.message ? (
                                <p className="text-sm text-destructive">
                                    {formState.errors.sizes.message ??
                                        formState.errors.sizes.root?.message}
                                </p>
                            ) : null}

                            <ol className="space-y-3" data-testid="stone-sizes">
                                {sizes.fields.map((row, index) => {
                                    const errors =
                                        formState.errors.sizes?.[index];
                                    const number = index + 1;

                                    return (
                                        <li
                                            key={row.id}
                                            className="grid grid-cols-1 gap-3 border border-sidebar-border p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-start"
                                        >
                                            <FormField
                                                id={`product-size-${index}`}
                                                label={`Size ${number}`}
                                                required
                                                error={fieldMessage(
                                                    errors?.size_id,
                                                )}
                                            >
                                                {({ describedBy, invalid }) => (
                                                    <Controller
                                                        control={control}
                                                        name={`sizes.${index}.size_id`}
                                                        render={({ field }) => (
                                                            <ChoiceSelect
                                                                id={`product-size-${index}`}
                                                                value={
                                                                    field.value
                                                                }
                                                                onChange={
                                                                    field.onChange
                                                                }
                                                                onBlur={
                                                                    field.onBlur
                                                                }
                                                                options={
                                                                    sizeOptions
                                                                }
                                                                placeholder="Choose a size"
                                                                listLabel="Slab sizes"
                                                                describedBy={
                                                                    describedBy
                                                                }
                                                                invalid={
                                                                    invalid
                                                                }
                                                            />
                                                        )}
                                                    />
                                                )}
                                            </FormField>
                                            <FormField
                                                id={`product-thickness-${index}`}
                                                label="Thickness"
                                                required
                                                error={fieldMessage(
                                                    errors?.thickness_id,
                                                )}
                                            >
                                                {({ describedBy, invalid }) => (
                                                    <Controller
                                                        control={control}
                                                        name={`sizes.${index}.thickness_id`}
                                                        render={({ field }) => (
                                                            <ChoiceSelect
                                                                id={`product-thickness-${index}`}
                                                                value={
                                                                    field.value
                                                                }
                                                                onChange={
                                                                    field.onChange
                                                                }
                                                                onBlur={
                                                                    field.onBlur
                                                                }
                                                                options={
                                                                    thicknessOptions
                                                                }
                                                                placeholder="Choose a thickness"
                                                                listLabel="Thicknesses"
                                                                describedBy={
                                                                    describedBy
                                                                }
                                                                invalid={
                                                                    invalid
                                                                }
                                                            />
                                                        )}
                                                    />
                                                )}
                                            </FormField>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 text-destructive sm:mt-7"
                                                onClick={() =>
                                                    sizes.remove(index)
                                                }
                                                aria-label={`Remove size ${number}`}
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ol>

                            {sizes.fields.length < limits.sizesMax ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="self-start"
                                    onClick={() =>
                                        sizes.append({
                                            size_id: '',
                                            thickness_id: '',
                                        })
                                    }
                                >
                                    <Plus aria-hidden />
                                    Add a size and thickness
                                </Button>
                            ) : null}

                            {usesNoSize ? (
                                <FormField
                                    id="product-size-note"
                                    label="How big the pieces run"
                                    error={formState.errors.size_note?.message}
                                    hint='For "No set size", such as Diameter 15–40 cm.'
                                >
                                    {({ describedBy, invalid }) => (
                                        <Input
                                            id="product-size-note"
                                            autoComplete="off"
                                            maxLength={100}
                                            className="mt-2 rounded-none"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            {...register('size_note')}
                                        />
                                    )}
                                </FormField>
                            ) : null}
                        </Section>
                    ) : null}

                    <Section title="Images" as="fieldset">
                        <Controller
                            control={control}
                            name="thumbnail"
                            render={({ field, fieldState }) => (
                                <ImageField
                                    id="product-thumbnail"
                                    label="Thumbnail"
                                    required={isStone && editing === null}
                                    accept={IMAGE_ACCEPT}
                                    hint={imageHint(
                                        IMAGE_TYPES,
                                        limits.thumbnailMaxKb,
                                        editing
                                            ? 'The photo on its card and page. Leave it to keep the current one.'
                                            : isStone
                                              ? 'The photo on its card and page, best in portrait, such as 1200 × 1500.'
                                              : 'The photo on its card and page, best square. Optional.',
                                    )}
                                    error={fieldState.error?.message}
                                    file={field.value}
                                    currentUrl={editing?.thumbnail ?? null}
                                    onFileChange={(file) => {
                                        field.onChange(file);
                                        field.onBlur();
                                    }}
                                />
                            )}
                        />

                        <GalleryField
                            id="product-gallery"
                            label="Other photos"
                            hint={`Shown below the thumbnail on its page, in this order. ${imageHint(IMAGE_TYPES, limits.photoMaxKb)} ${limits.galleryMax} photos at most.`}
                            items={gallery.fields.map((photo, index) => {
                                const row = galleryRows[index] ?? photo;

                                return {
                                    key: photo.id,
                                    url: row.url,
                                    name: row.name,
                                    file: row.file,
                                    error: formState.errors.gallery?.[index]
                                        ?.file?.message,
                                };
                            })}
                            max={limits.galleryMax}
                            accept={IMAGE_ACCEPT}
                            error={
                                formState.errors.gallery?.message ??
                                formState.errors.gallery?.root?.message
                            }
                            onAdd={(files) =>
                                files.forEach((file) =>
                                    gallery.append({
                                        photo_id: null,
                                        url: null,
                                        name: file.name,
                                        file,
                                    }),
                                )
                            }
                            onMove={gallery.move}
                            onRemove={gallery.remove}
                        />
                    </Section>

                    <Section title="Display" as="fieldset">
                        <Controller
                            control={control}
                            name="is_active"
                            render={({ field }) => (
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <label
                                            htmlFor="product-active"
                                            className="text-sm font-bold"
                                        >
                                            Shown on Regray
                                        </label>
                                        <p
                                            id="product-active-hint"
                                            className="mt-1 text-sm text-secondary-foreground"
                                        >
                                            A hidden {noun} stays here, with no
                                            page on Regray.
                                        </p>
                                    </div>
                                    <Switch
                                        id="product-active"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        aria-describedby="product-active-hint"
                                    />
                                </div>
                            )}
                        />
                    </Section>
                </div>
            </FormDrawer>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => (open ? null : setArchiving(null))}
                title={`Archive ${archiving?.name ?? 'this product'}?`}
                description={archiveDescription(archiving?.kind ?? 'stone')}
                confirmLabel="Archive"
                cancelLabel="Keep it"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

export default Products;

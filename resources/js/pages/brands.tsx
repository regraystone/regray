import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Link2,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
    Controller,
    useFieldArray,
    useForm,
    useWatch,
    type FieldPath,
} from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import BrandAdminCard, {
    BrandAdminCardSkeleton,
    BrandCover,
} from '@/components/brand-admin-card';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableSearch from '@/components/data-table/data-table-search';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import { useDataTableQuery } from '@/components/data-table/use-data-table-query';
import EmptyState from '@/components/empty-state';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import ImageField from '@/components/image-field';
import SocialIcon, { SOCIAL_LABELS } from '@/components/social-icon';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import TagInput from '@/components/tag-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    archiveDescription,
    catalogueCount,
    isSocialLinkFor,
    SLUG_PATTERN,
    slugify,
    websiteDomain,
} from '@/lib/brand-admin';
import { DEFAULT_PAGE_SIZE } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import { formatFileSize } from '@/lib/message-attachments';
import {
    archive as archiveBrand,
    store as storeBrand,
    update as updateBrand,
} from '@/routes/dashboard/brands';
import type {
    BrandImageFile,
    BrandLimits,
    BrandRow,
    BrandsPageProps,
} from '@/types/brand-admin';
import type { SocialPlatform } from '@/types/home';

const ONLY = ['brands', 'filters'];

/** The image types a logo and a thumbnail may be (config/brands.php). */
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
const IMAGE_ACCEPT = '.png,.jpg,.jpeg,.webp,.svg,' + IMAGE_TYPES.join(',');

type BrandValues = {
    name: string;
    slug: string;
    tagline: string;
    description: string;
    website_url: string;
    logo: File | null;
    thumbnail: File | null;
    remove_thumbnail: boolean;
    facts: { label: string; value: string }[];
    makes: string[];
    socials: { platform: SocialPlatform | ''; url: string }[];
    is_active: boolean;
    sort_order: string;
};

const EMPTY: BrandValues = {
    name: '',
    slug: '',
    tagline: '',
    description: '',
    website_url: '',
    logo: null,
    thumbnail: null,
    remove_thumbnail: false,
    facts: [],
    makes: [],
    socials: [],
    is_active: true,
    sort_order: '0',
};

const fromRow = (brand: BrandRow): BrandValues => ({
    name: brand.name,
    slug: brand.slug,
    tagline: brand.tagline ?? '',
    description: brand.description ?? '',
    website_url: brand.websiteUrl ?? '',
    logo: null,
    thumbnail: null,
    remove_thumbnail: false,
    facts: brand.facts.map((fact) => ({ ...fact })),
    makes: [...brand.makes],
    socials: brand.socials.map((social) => ({ ...social })),
    is_active: brand.isActive,
    sort_order: String(brand.sortOrder),
});

/** Whether what was typed reads as a web address, with or without https://. */
const isWebsite = (value: string) => {
    try {
        const url = new URL(value.includes('://') ? value : `https://${value}`);

        return (
            ['http:', 'https:'].includes(url.protocol) &&
            url.hostname.includes('.')
        );
    } catch {
        return false;
    }
};

const imageSchema = (maxKb: number, what: string) =>
    z
        .custom<File | null>((value) => value === null || value instanceof File)
        .superRefine((file, ctx) => {
            if (file === null) {
                return;
            }

            if (!IMAGE_TYPES.includes(file.type)) {
                ctx.addIssue({
                    code: 'custom',
                    message: `The ${what} has to be a PNG, JPG, WebP or SVG.`,
                });
            } else if (file.size > maxKb * 1024) {
                ctx.addIssue({
                    code: 'custom',
                    message: `The ${what} can be ${formatFileSize(maxKb * 1024)} at most.`,
                });
            }
        });

/**
 * The form's rules, the server's own (BrandValidationRules) so the drawer
 * says what is wrong before it sends anything. The logo is required only
 * while adding: `needsLogo` says which.
 */
const brandSchema = (
    limits: BrandLimits,
    reserved: string[],
    needsLogo: { current: boolean },
) =>
    z
        .object({
            name: z
                .string()
                .trim()
                .min(1, "Enter the brand's name.")
                .max(150, 'Use 150 characters or fewer.'),
            slug: z
                .string()
                .trim()
                .min(1, 'Enter the slug for its link.')
                .max(100, 'Use 100 characters or fewer.')
                .regex(
                    SLUG_PATTERN,
                    'Use lower-case letters, numbers and single hyphens, such as cucine-lube.',
                )
                .refine(
                    (slug) => !reserved.includes(slug),
                    "That slug is kept for one of Regray's own pages. Choose another.",
                ),
            tagline: z
                .string()
                .trim()
                .max(120, 'Keep the tagline to 120 characters.'),
            description: z
                .string()
                .max(5000, 'Keep the description to 5,000 characters.'),
            website_url: z
                .string()
                .trim()
                .max(255, 'Use 255 characters or fewer.')
                .refine(
                    (value) => value === '' || isWebsite(value),
                    'Enter a web address, such as www.cucinelube.it.',
                ),
            logo: imageSchema(limits.logoMaxKb, 'logo'),
            thumbnail: imageSchema(limits.thumbnailMaxKb, 'thumbnail'),
            remove_thumbnail: z.boolean(),
            facts: z
                .array(
                    z.object({
                        label: z
                            .string()
                            .trim()
                            .min(1, 'Give this fact a label, or remove it.')
                            .max(
                                limits.factLabelMax,
                                `Use ${limits.factLabelMax} characters or fewer.`,
                            ),
                        value: z
                            .string()
                            .trim()
                            .min(1, 'Give this fact a value, or remove it.')
                            .max(
                                limits.factValueMax,
                                `Use ${limits.factValueMax} characters or fewer.`,
                            ),
                    }),
                )
                .max(limits.factsMax, `${limits.factsMax} facts at most.`),
            makes: z
                .array(z.string().trim().min(1).max(limits.makeMax))
                .max(limits.makesMax, `${limits.makesMax} at most.`),
            socials: z.array(
                z.object({
                    platform: z
                        .enum([
                            '',
                            'facebook',
                            'twitter',
                            'x',
                            'instagram',
                            'tiktok',
                        ])
                        .refine((value) => value !== '', 'Choose the network.'),
                    url: z
                        .string()
                        .trim()
                        .min(1, 'Enter the link, or remove it.')
                        .max(255, 'Use 255 characters or fewer.'),
                }),
            ),
            is_active: z.boolean(),
            sort_order: z
                .string()
                .trim()
                .regex(/^\d{1,5}$/, 'Enter 0 or a whole number above it.')
                .refine(
                    (value) => Number(value) <= 32767,
                    'Enter 32767 or less.',
                ),
        })
        .superRefine((values, ctx) => {
            if (needsLogo.current && values.logo === null) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['logo'],
                    message: "Add the brand's logo.",
                });
            }

            const seen = new Set<string>();

            values.socials.forEach((social, index) => {
                // A row with no network yet has its own error already.
                if (!social.platform) {
                    return;
                }

                if (seen.has(social.platform)) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['socials', index, 'platform'],
                        message: 'That network already has a link.',
                    });
                }

                seen.add(social.platform);
                const platform = social.platform;

                if (
                    social.url.trim() !== '' &&
                    !isSocialLinkFor(platform, social.url.trim())
                ) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['socials', index, 'url'],
                        message: `That link isn't on ${SOCIAL_LABELS[platform]}.`,
                    });
                }
            });
        });

type Drawer =
    | { mode: 'add' }
    | { mode: 'view'; brand: BrandRow }
    | { mode: 'edit'; brand: BrandRow };

/** A label and its value in the View drawer. */
const Detail = ({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) => (
    <div className="min-w-0">
        <p className="mb-1 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
            {label}
        </p>
        <div className="text-sm break-words">{children}</div>
    </div>
);

/** A part of the View drawer or the form, under its own small heading. */
const Section = ({
    title,
    children,
    as: Tag = 'section',
}: {
    title: string;
    children: ReactNode;
    as?: 'section' | 'fieldset';
}) => (
    <Tag className="space-y-4 border-t border-sidebar-border pt-5 first:border-t-0 first:pt-0">
        {Tag === 'fieldset' ? (
            <legend className="mb-4 text-xs font-bold tracking-[0.12em] text-secondary-foreground uppercase">
                {title}
            </legend>
        ) : (
            <h3 className="text-xs font-bold tracking-[0.12em] text-secondary-foreground uppercase">
                {title}
            </h3>
        )}
        {children}
    </Tag>
);

const fileLine = (file: BrandImageFile | null) =>
    file === null
        ? '—'
        : `${file.originalName}${file.sizeBytes ? ` · ${formatFileSize(file.sizeBytes)}` : ''}`;

/**
 * The admin's Brands page: every brand on Regray but the archived, as cards
 * two to a row, by display order then name, searched by name.
 *
 * Add, Edit and View open in the drawer; Archive asks first. What a card
 * offers comes from the server (`actions`). A brand's images, facts, what it
 * makes and its social links are set here and read by its public page.
 */
const Brands = () => {
    const { props } = usePage<BrandsPageProps>();
    const { limits } = props;
    const { update, isLoading } = useDataTableQuery(ONLY);
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<BrandRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const [slugEdited, setSlugEdited] = useState(false);
    const needsLogo = useRef(false);

    const schema = useMemo(
        () => brandSchema(limits, props.reservedSlugs, needsLogo),
        [limits, props.reservedSlugs],
    );

    const form = useForm<BrandValues>({
        resolver: zodResolver(schema),
        mode: 'onTouched',
        defaultValues: EMPTY,
    });
    const {
        register,
        handleSubmit,
        setError,
        setValue,
        reset,
        control,
        formState,
    } = form;
    const facts = useFieldArray({ control, name: 'facts' });
    const socials = useFieldArray({ control, name: 'socials' });
    const chosenPlatforms = useWatch({ control, name: 'socials' }) ?? [];
    const [name, slug, tagline, removeThumbnail] = useWatch({
        control,
        name: ['name', 'slug', 'tagline', 'remove_thumbnail'],
    });

    const editing = drawer?.mode === 'edit' ? drawer.brand : null;
    const viewing = drawer?.mode === 'view' ? drawer.brand : null;
    const adding = drawer?.mode === 'add';

    // The form starts from the brand being edited, or empty to add one.
    useEffect(() => {
        if (drawer?.mode === 'edit') {
            needsLogo.current = false;
            setSlugEdited(true);
            reset(fromRow(drawer.brand));
        } else if (drawer?.mode === 'add') {
            needsLogo.current = true;
            setSlugEdited(false);
            reset(EMPTY);
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

    const close = () => setDrawer(null);

    const showErrors = (errors: Record<string, string>) => {
        const fields = new Set<string>(Object.keys(EMPTY));
        let unplaced: string | null = null;

        for (const [key, message] of Object.entries(errors)) {
            if (fields.has(key)) {
                setError(key as FieldPath<BrandValues>, {
                    type: 'server',
                    message,
                });
            } else if (
                /^facts\.\d+\.(label|value)$/.test(key) ||
                /^socials\.\d+\.(platform|url)$/.test(key)
            ) {
                setError(key as FieldPath<BrandValues>, {
                    type: 'server',
                    message,
                });
            } else if (key.startsWith('makes')) {
                setError('makes', { type: 'server', message });
            } else if (key.startsWith('facts')) {
                setError('facts', { type: 'server', message });
            } else if (key.startsWith('socials')) {
                setError('socials', { type: 'server', message });
            } else {
                unplaced ??= message;
            }
        }

        if (unplaced) {
            toast.error(unplaced);
        }
    };

    const save = handleSubmit((values) => {
        const payload = {
            name: values.name,
            slug: values.slug,
            tagline: values.tagline,
            description: values.description,
            website_url: values.website_url,
            is_active: values.is_active,
            sort_order: Number(values.sort_order),
            facts: values.facts,
            makes: values.makes,
            socials: values.socials,
            ...(values.logo ? { logo: values.logo } : {}),
            ...(values.thumbnail ? { thumbnail: values.thumbnail } : {}),
            remove_thumbnail: values.remove_thumbnail,
        };
        const options = {
            only: ONLY,
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: close,
            onError: showErrors,
        };

        if (editing) {
            // A PUT can't carry files, so it is a POST that says it is a PUT.
            router.post(
                updateBrand.url(editing.id),
                { ...payload, _method: 'put' },
                options,
            );
        } else {
            router.post(storeBrand.url(), payload, options);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            archiveBrand.url(archiving.id),
            {},
            {
                only: ONLY,
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setArchiving(null);
                    close();
                },
                onError: (errors) => {
                    setArchiving(null);
                    toast.error(
                        Object.values(errors)[0] ??
                            'The brand could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const rows = props.brands.data;
    const isFiltered = props.filters.search.trim() !== '';
    const addButton = (
        <Button type="button" onClick={() => setDrawer({ mode: 'add' })}>
            <Plus aria-hidden />
            Add brand
        </Button>
    );
    const publicUrlFor = (value: string) =>
        props.publicBrandUrl.replace('__slug__', value || 'your-brand');
    const imageHint = (maxKb: number, extra?: string) =>
        `PNG, JPG, WebP or SVG, up to ${formatFileSize(maxKb * 1024)}.${extra ? ` ${extra}` : ''}`;

    return (
        <div className="w-full min-w-0">
            {props.canCreate ? (
                <PageHeader
                    variant="action"
                    title="Brands"
                    subtitle="Manage the brands shown across Regray"
                    action={addButton}
                />
            ) : (
                <PageHeader
                    title="Brands"
                    subtitle="Manage the brands shown across Regray"
                />
            )}

            <div className="space-y-4">
                <DataTableToolbar
                    search={
                        <DataTableSearch
                            value={props.filters.search}
                            placeholder="Search by brand name"
                            onSearch={(search) => update({ search })}
                        />
                    }
                />

                {!isLoading && rows.length === 0 ? (
                    isFiltered ? (
                        <EmptyState
                            title="No brands match that search"
                            description="Try another brand name."
                        />
                    ) : (
                        <EmptyState
                            title="No brands yet"
                            description="Add a brand to show it on Regray."
                            action={props.canCreate ? addButton : undefined}
                        />
                    )
                ) : (
                    <div
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6"
                        aria-busy={isLoading}
                        data-testid="brand-grid"
                    >
                        {isLoading
                            ? Array.from(
                                  {
                                      length: Math.max(
                                          1,
                                          rows.length || DEFAULT_PAGE_SIZE,
                                      ),
                                  },
                                  (_, index) => (
                                      <BrandAdminCardSkeleton
                                          key={`skeleton-${index}`}
                                      />
                                  ),
                              )
                            : rows.map((brand) => (
                                  <BrandAdminCard
                                      key={brand.id}
                                      brand={brand}
                                      onView={() =>
                                          setDrawer({ mode: 'view', brand })
                                      }
                                      onEdit={() =>
                                          setDrawer({ mode: 'edit', brand })
                                      }
                                      onArchive={() => setArchiving(brand)}
                                  />
                              ))}
                    </div>
                )}

                <DataTablePagination
                    meta={props.brands.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            {/* View: read-only, with Edit to switch the same drawer over. */}
            <FormDrawer
                open={viewing !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={viewing?.name ?? 'Brand'}
                description={viewing?.tagline ?? undefined}
                size="lg"
                footer={
                    viewing &&
                    (viewing.publicUrl || viewing.actions.includes('edit')) ? (
                        <>
                            {viewing.publicUrl ? (
                                <Button variant="outline" asChild>
                                    <a
                                        href={viewing.publicUrl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink aria-hidden />
                                        Open brand page
                                        <span className="sr-only">
                                            {' '}
                                            (opens in a new tab)
                                        </span>
                                    </a>
                                </Button>
                            ) : null}
                            {viewing.actions.includes('edit') ? (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        setDrawer({
                                            mode: 'edit',
                                            brand: viewing,
                                        })
                                    }
                                >
                                    <Pencil aria-hidden />
                                    Edit
                                </Button>
                            ) : null}
                        </>
                    ) : undefined
                }
            >
                {viewing !== null ? (
                    <div className="space-y-6" data-testid="brand-details">
                        <div className="-mx-1">
                            <BrandCover
                                brand={viewing}
                                className="border border-sidebar-border"
                            />
                            <div className="mt-4 flex flex-wrap items-start justify-between gap-3 px-1">
                                <div className="min-w-0">
                                    <p className="text-2xl leading-tight font-bold tracking-[-0.03em] break-words">
                                        {viewing.name}
                                    </p>
                                    <p className="mt-1 text-sm text-secondary-foreground">
                                        {viewing.tagline ?? 'No tagline yet'}
                                    </p>
                                </div>
                                <StatusBadge
                                    active={viewing.isActive}
                                    labels={['Visible', 'Hidden']}
                                />
                            </div>
                        </div>

                        <Section title="About">
                            {viewing.description ? (
                                <div className="space-y-3 text-sm leading-relaxed">
                                    {viewing.description
                                        .split(/\n{2,}/)
                                        .map((paragraph, index) => (
                                            <p
                                                key={index}
                                                className="whitespace-pre-line"
                                            >
                                                {paragraph}
                                            </p>
                                        ))}
                                </div>
                            ) : (
                                <p className="text-sm text-secondary-foreground">
                                    No description yet
                                </p>
                            )}
                        </Section>

                        {viewing.facts.length > 0 ||
                        viewing.makes.length > 0 ? (
                            <Section title="Facts">
                                {viewing.facts.length > 0 ? (
                                    <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                                        {viewing.facts.map((fact, index) => (
                                            <div
                                                key={`${fact.label}-${index}`}
                                                className="min-w-0"
                                            >
                                                <dt className="text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                                    {fact.label}
                                                </dt>
                                                <dd className="mt-1 text-sm break-words">
                                                    {fact.value}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                ) : null}
                                {viewing.makes.length > 0 ? (
                                    <div>
                                        <p className="mb-2 text-xs font-bold tracking-[0.05em] text-secondary-foreground uppercase">
                                            What it makes
                                        </p>
                                        <ul className="flex flex-wrap gap-2">
                                            {viewing.makes.map((item) => (
                                                <li
                                                    key={item}
                                                    className="border border-sidebar-border bg-muted/40 px-2.5 py-1 text-sm"
                                                >
                                                    {item}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                            </Section>
                        ) : null}

                        <Section title="Details">
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <Detail label="Public page">
                                    {viewing.publicUrl ? (
                                        <a
                                            href={viewing.publicUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="underline underline-offset-4"
                                        >
                                            /brands/{viewing.slug}
                                            <span className="sr-only">
                                                {' '}
                                                (opens in a new tab)
                                            </span>
                                        </a>
                                    ) : (
                                        <span className="text-secondary-foreground">
                                            Hidden, so it has no page
                                        </span>
                                    )}
                                </Detail>
                                <Detail label="Slug">
                                    <span className="font-mono">
                                        {viewing.slug}
                                    </span>
                                </Detail>
                                <Detail label="Website">
                                    {viewing.websiteUrl ? (
                                        <a
                                            href={viewing.websiteUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="underline underline-offset-4"
                                        >
                                            {websiteDomain(viewing.websiteUrl)}
                                            <span className="sr-only">
                                                {' '}
                                                (opens in a new tab)
                                            </span>
                                        </a>
                                    ) : (
                                        '—'
                                    )}
                                </Detail>
                                <Detail label="Social media">
                                    {viewing.socials.length > 0 ? (
                                        <ul className="flex flex-wrap gap-2">
                                            {viewing.socials.map((social) => (
                                                <li key={social.platform}>
                                                    <a
                                                        href={social.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        title={social.url}
                                                        aria-label={`${viewing.name} on ${SOCIAL_LABELS[social.platform]} (opens in a new tab)`}
                                                        className="flex size-9 items-center justify-center border border-sidebar-border transition-colors hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    >
                                                        <SocialIcon
                                                            platform={
                                                                social.platform
                                                            }
                                                        />
                                                    </a>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        '—'
                                    )}
                                </Detail>
                                <Detail label="Display order">
                                    {viewing.sortOrder}
                                </Detail>
                                <Detail label="In the catalogue">
                                    {catalogueCount(
                                        viewing.stonesCount,
                                        viewing.furnitureCount,
                                    )}
                                    {` · ${viewing.projectsCount} ${viewing.projectsCount === 1 ? 'project' : 'projects'}`}
                                </Detail>
                                <Detail label="Logo file">
                                    {fileLine(viewing.logoFile)}
                                </Detail>
                                <Detail label="Thumbnail file">
                                    {viewing.thumbnail
                                        ? fileLine(viewing.thumbnailFile)
                                        : 'None'}
                                </Detail>
                                <Detail label="Added">
                                    {viewing.createdAt
                                        ? formatDate(viewing.createdAt)
                                        : '—'}
                                </Detail>
                                <Detail label="Last updated">
                                    {viewing.updatedAt
                                        ? formatDate(viewing.updatedAt)
                                        : '—'}
                                </Detail>
                            </div>
                        </Section>
                    </div>
                ) : null}
            </FormDrawer>

            {/* Add and Edit: one form. */}
            <FormDrawer
                open={adding || editing !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={editing ? `Edit ${editing.name}` : 'Add brand'}
                description={
                    editing
                        ? 'Change what Regray shows about this brand.'
                        : 'A new brand, with its own page on Regray.'
                }
                submitLabel={editing ? 'Save changes' : 'Add brand'}
                onSubmit={save}
                processing={processing}
                size="lg"
            >
                <div className="space-y-6">
                    <Section title="Identity" as="fieldset">
                        <FormField
                            id="brand-name"
                            label="Brand name"
                            required
                            error={formState.errors.name?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="brand-name"
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
                            id="brand-slug"
                            label="Slug"
                            required
                            error={formState.errors.slug?.message}
                            hint={`${publicUrlFor(slug)}${
                                editing
                                    ? '. Changing it changes the brand’s public link.'
                                    : ''
                            }`}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="brand-slug"
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

                        <FormField
                            id="brand-tagline"
                            label="Tagline"
                            error={formState.errors.tagline?.message}
                            hint={`${(tagline ?? '').length} of 120 characters`}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="brand-tagline"
                                    autoComplete="off"
                                    maxLength={120}
                                    className="mt-2 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('tagline')}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Images" as="fieldset">
                        <Controller
                            control={control}
                            name="logo"
                            render={({ field, fieldState }) => (
                                <ImageField
                                    id="brand-logo"
                                    label="Logo"
                                    required={editing === null}
                                    shape="logo"
                                    accept={IMAGE_ACCEPT}
                                    hint={imageHint(
                                        limits.logoMaxKb,
                                        editing
                                            ? 'Leave it to keep the current one.'
                                            : undefined,
                                    )}
                                    error={fieldState.error?.message}
                                    file={field.value}
                                    currentUrl={editing?.logo ?? null}
                                    currentName={
                                        editing?.logoFile?.originalName
                                    }
                                    onFileChange={(file) => {
                                        field.onChange(file);
                                        field.onBlur();
                                    }}
                                />
                            )}
                        />

                        <Controller
                            control={control}
                            name="thumbnail"
                            render={({ field, fieldState }) => (
                                <ImageField
                                    id="brand-thumbnail"
                                    label="Thumbnail"
                                    accept={IMAGE_ACCEPT}
                                    hint={imageHint(
                                        limits.thumbnailMaxKb,
                                        'The wide cover photo on its card and page, best at 16:9, such as 1600 × 900.',
                                    )}
                                    error={fieldState.error?.message}
                                    file={field.value}
                                    currentUrl={
                                        removeThumbnail
                                            ? null
                                            : (editing?.thumbnail ?? null)
                                    }
                                    currentName={
                                        editing?.thumbnailFile?.originalName
                                    }
                                    onFileChange={(file) => {
                                        field.onChange(file);
                                        field.onBlur();
                                    }}
                                    onRemoveCurrent={() =>
                                        setValue('remove_thumbnail', true, {
                                            shouldDirty: true,
                                        })
                                    }
                                />
                            )}
                        />
                        {removeThumbnail && editing?.thumbnail ? (
                            <p className="-mt-2 text-sm text-secondary-foreground">
                                The thumbnail comes off when you save.{' '}
                                <button
                                    type="button"
                                    className="font-medium text-foreground underline underline-offset-4"
                                    onClick={() =>
                                        setValue('remove_thumbnail', false)
                                    }
                                >
                                    Keep it
                                </button>
                            </p>
                        ) : null}
                    </Section>

                    <Section title="About" as="fieldset">
                        <FormField
                            id="brand-description"
                            label="Description"
                            error={formState.errors.description?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Textarea
                                    id="brand-description"
                                    rows={5}
                                    className="mt-2"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('description')}
                                />
                            )}
                        </FormField>

                        <FormField
                            id="brand-website"
                            label="Website"
                            error={formState.errors.website_url?.message}
                            hint="Such as www.cucinelube.it. https:// is added if it's left off."
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="brand-website"
                                    type="url"
                                    inputMode="url"
                                    autoComplete="off"
                                    className="mt-2 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('website_url')}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Brand facts" as="fieldset">
                        <div>
                            <p className="text-sm font-bold">Facts</p>
                            <p
                                id="brand-facts-hint"
                                className="mt-1 text-sm text-secondary-foreground"
                            >
                                Shown on its page in this order.{' '}
                                {limits.factsMax} at most.
                            </p>
                            {formState.errors.facts?.message ? (
                                <p className="mt-1 text-sm text-destructive">
                                    {formState.errors.facts.message}
                                </p>
                            ) : null}

                            {facts.fields.length > 0 ? (
                                <ol className="mt-3 space-y-3">
                                    {facts.fields.map((fact, index) => {
                                        const errors =
                                            formState.errors.facts?.[index];
                                        const number = index + 1;

                                        return (
                                            <li
                                                key={fact.id}
                                                className="grid grid-cols-1 gap-2 border border-sidebar-border p-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] sm:items-start"
                                            >
                                                <div>
                                                    <label
                                                        htmlFor={`brand-fact-${index}-label`}
                                                        className="sr-only"
                                                    >
                                                        Fact {number} label
                                                    </label>
                                                    <Input
                                                        id={`brand-fact-${index}-label`}
                                                        placeholder="Made in"
                                                        maxLength={
                                                            limits.factLabelMax
                                                        }
                                                        className="rounded-none"
                                                        aria-invalid={
                                                            errors?.label !==
                                                            undefined
                                                        }
                                                        aria-describedby={
                                                            errors?.label
                                                                ? `brand-fact-${index}-label-error`
                                                                : undefined
                                                        }
                                                        {...register(
                                                            `facts.${index}.label`,
                                                        )}
                                                    />
                                                    {errors?.label ? (
                                                        <p
                                                            id={`brand-fact-${index}-label-error`}
                                                            className="mt-1 text-sm text-destructive"
                                                        >
                                                            {
                                                                errors.label
                                                                    .message
                                                            }
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div>
                                                    <label
                                                        htmlFor={`brand-fact-${index}-value`}
                                                        className="sr-only"
                                                    >
                                                        Fact {number} value
                                                    </label>
                                                    <Input
                                                        id={`brand-fact-${index}-value`}
                                                        placeholder="Treia, Italy"
                                                        maxLength={
                                                            limits.factValueMax
                                                        }
                                                        className="rounded-none"
                                                        aria-invalid={
                                                            errors?.value !==
                                                            undefined
                                                        }
                                                        aria-describedby={
                                                            errors?.value
                                                                ? `brand-fact-${index}-value-error`
                                                                : undefined
                                                        }
                                                        {...register(
                                                            `facts.${index}.value`,
                                                        )}
                                                    />
                                                    {errors?.value ? (
                                                        <p
                                                            id={`brand-fact-${index}-value-error`}
                                                            className="mt-1 text-sm text-destructive"
                                                        >
                                                            {
                                                                errors.value
                                                                    .message
                                                            }
                                                        </p>
                                                    ) : null}
                                                </div>
                                                <div className="flex gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-9"
                                                        disabled={index === 0}
                                                        onClick={() =>
                                                            facts.move(
                                                                index,
                                                                index - 1,
                                                            )
                                                        }
                                                        aria-label={`Move fact ${number} up`}
                                                    >
                                                        <ArrowUp aria-hidden />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-9"
                                                        disabled={
                                                            index ===
                                                            facts.fields
                                                                .length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            facts.move(
                                                                index,
                                                                index + 1,
                                                            )
                                                        }
                                                        aria-label={`Move fact ${number} down`}
                                                    >
                                                        <ArrowDown
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-9 text-destructive"
                                                        onClick={() =>
                                                            facts.remove(index)
                                                        }
                                                        aria-label={`Remove fact ${number}`}
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </Button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            ) : null}

                            {facts.fields.length < limits.factsMax ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    onClick={() =>
                                        facts.append({ label: '', value: '' })
                                    }
                                    aria-describedby="brand-facts-hint"
                                >
                                    <Plus aria-hidden />
                                    Add fact
                                </Button>
                            ) : null}
                        </div>

                        <FormField
                            id="brand-makes"
                            label="What it makes"
                            error={
                                formState.errors.makes?.message ??
                                formState.errors.makes?.root?.message
                            }
                            hint={`Type one, then Enter or a comma. ${limits.makesMax} at most.`}
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="makes"
                                    render={({ field }) => (
                                        <TagInput
                                            id="brand-makes"
                                            value={field.value}
                                            onChange={field.onChange}
                                            onBlur={field.onBlur}
                                            max={limits.makesMax}
                                            maxLength={limits.makeMax}
                                            placeholder="Kitchens"
                                            describedBy={describedBy}
                                            invalid={invalid}
                                        />
                                    )}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Social media" as="fieldset">
                        <p
                            id="brand-socials-hint"
                            className="-mt-2 text-sm text-secondary-foreground"
                        >
                            Shown as icons on its page, in this order. One link
                            for each of Facebook, Twitter, X, Instagram and
                            TikTok.
                        </p>
                        {formState.errors.socials?.message ? (
                            <p className="text-sm text-destructive">
                                {formState.errors.socials.message}
                            </p>
                        ) : null}

                        {socials.fields.length > 0 ? (
                            <ol className="space-y-3">
                                {socials.fields.map((social, index) => {
                                    const errors =
                                        formState.errors.socials?.[index];
                                    const number = index + 1;
                                    const taken = chosenPlatforms
                                        .filter((_, other) => other !== index)
                                        .map((other) => other.platform);

                                    return (
                                        <li
                                            key={social.id}
                                            className="grid grid-cols-1 gap-2 border border-sidebar-border p-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-start"
                                        >
                                            <div>
                                                <label
                                                    htmlFor={`brand-social-${index}-platform`}
                                                    className="sr-only"
                                                >
                                                    Link {number} network
                                                </label>
                                                <Controller
                                                    control={control}
                                                    name={`socials.${index}.platform`}
                                                    render={({ field }) => (
                                                        <Select
                                                            value={field.value}
                                                            onValueChange={(
                                                                value,
                                                            ) => {
                                                                field.onChange(
                                                                    value,
                                                                );
                                                                field.onBlur();
                                                            }}
                                                        >
                                                            <SelectTrigger
                                                                id={`brand-social-${index}-platform`}
                                                                className="w-full rounded-none text-base data-[size=default]:h-11"
                                                                aria-invalid={
                                                                    errors?.platform !==
                                                                    undefined
                                                                }
                                                                aria-describedby={
                                                                    errors?.platform
                                                                        ? `brand-social-${index}-platform-error`
                                                                        : undefined
                                                                }
                                                            >
                                                                <SelectValue placeholder="Network" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {props.socialPlatforms.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option.value
                                                                            }
                                                                            value={
                                                                                option.value
                                                                            }
                                                                            disabled={taken.includes(
                                                                                option.value,
                                                                            )}
                                                                        >
                                                                            <SocialIcon
                                                                                platform={
                                                                                    option.value
                                                                                }
                                                                                className="size-4"
                                                                            />
                                                                            {
                                                                                option.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    )}
                                                />
                                                {errors?.platform ? (
                                                    <p
                                                        id={`brand-social-${index}-platform-error`}
                                                        className="mt-1 text-sm text-destructive"
                                                    >
                                                        {
                                                            errors.platform
                                                                .message
                                                        }
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div>
                                                <label
                                                    htmlFor={`brand-social-${index}-url`}
                                                    className="sr-only"
                                                >
                                                    Link {number} address
                                                </label>
                                                <Input
                                                    id={`brand-social-${index}-url`}
                                                    type="url"
                                                    inputMode="url"
                                                    autoComplete="off"
                                                    placeholder="https://www.instagram.com/regraystoneph"
                                                    maxLength={255}
                                                    className="rounded-none"
                                                    aria-invalid={
                                                        errors?.url !==
                                                        undefined
                                                    }
                                                    aria-describedby={
                                                        errors?.url
                                                            ? `brand-social-${index}-url-error`
                                                            : undefined
                                                    }
                                                    {...register(
                                                        `socials.${index}.url`,
                                                    )}
                                                />
                                                {errors?.url ? (
                                                    <p
                                                        id={`brand-social-${index}-url-error`}
                                                        className="mt-1 text-sm text-destructive"
                                                    >
                                                        {errors.url.message}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-9 text-destructive"
                                                onClick={() =>
                                                    socials.remove(index)
                                                }
                                                aria-label={`Remove link ${number}`}
                                            >
                                                <Trash2 aria-hidden />
                                            </Button>
                                        </li>
                                    );
                                })}
                            </ol>
                        ) : null}

                        {socials.fields.length <
                        props.socialPlatforms.length ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    const used = chosenPlatforms.map(
                                        (other) => other.platform,
                                    );

                                    socials.append({
                                        platform:
                                            props.socialPlatforms.find(
                                                (option) =>
                                                    !used.includes(
                                                        option.value,
                                                    ),
                                            )?.value ?? '',
                                        url: '',
                                    });
                                }}
                                aria-describedby="brand-socials-hint"
                            >
                                <Link2 aria-hidden />
                                Add link
                            </Button>
                        ) : null}
                    </Section>

                    <Section title="Display" as="fieldset">
                        <Controller
                            control={control}
                            name="is_active"
                            render={({ field }) => (
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <label
                                            htmlFor="brand-active"
                                            className="text-sm font-bold"
                                        >
                                            Visible on Regray
                                        </label>
                                        <p
                                            id="brand-active-hint"
                                            className="mt-1 text-sm text-secondary-foreground"
                                        >
                                            A hidden brand keeps its products
                                            but has no page.
                                        </p>
                                    </div>
                                    <Switch
                                        id="brand-active"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        aria-describedby="brand-active-hint"
                                    />
                                </div>
                            )}
                        />

                        <FormField
                            id="brand-sort-order"
                            label="Display order"
                            error={formState.errors.sort_order?.message}
                            hint="Lower comes first; brands with the same number go by name."
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="brand-sort-order"
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
                    </Section>
                </div>
            </FormDrawer>

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => (open ? null : setArchiving(null))}
                title={`Archive ${archiving?.name ?? 'this brand'}?`}
                description={
                    archiving
                        ? archiveDescription(
                              archiving.stonesCount,
                              archiving.furnitureCount,
                          )
                        : ''
                }
                confirmLabel="Archive brand"
                cancelLabel="Keep brand"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

export default Brands;

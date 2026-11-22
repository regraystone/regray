import { zodResolver } from '@hookform/resolvers/zod';
import { router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    ImagePlus,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
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
import AdminCard, {
    AdminCardCover,
    AdminCardSkeleton,
    type AdminCardCoverData,
} from '@/components/admin-card';
import { Detail, Section } from '@/components/admin-drawer-parts';
import ConfirmDialog from '@/components/confirm-dialog';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableSearch from '@/components/data-table/data-table-search';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import { useDataTableQuery } from '@/components/data-table/use-data-table-query';
import EmptyState from '@/components/empty-state';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import ImageField from '@/components/image-field';
import MultiSelect from '@/components/multi-select';
import PageHeader from '@/components/page-header';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_PAGE_SIZE } from '@/lib/data-table';
import { formatDate } from '@/lib/format-date';
import {
    fileLine,
    imageAccept,
    imageFileSchema,
    imageHint,
    JPG,
    PNG,
    WEBP,
} from '@/lib/image-upload';
import { initials } from '@/lib/initials';
import {
    archiveDescription,
    projectCount,
    projectSubtitle,
} from '@/lib/project-admin';
import { SLUG_PATTERN, slugify } from '@/lib/slug';
import {
    archive as archiveProject,
    store as storeProject,
    update as updateProject,
} from '@/routes/dashboard/projects';
import type {
    ProjectLimits,
    ProjectRow,
    ProjectsPageProps,
} from '@/types/project-admin';

const ONLY = ['projects', 'filters'];

/** The image types a thumbnail and a gallery photo may be (config/projects.php). */
const IMAGE_TYPES = [PNG, JPG, WEBP];
const IMAGE_ACCEPT = imageAccept(IMAGE_TYPES);

/**
 * One photo of the gallery in the form: a saved one (`photo_id`, `url`) or a
 * new one (`file`). Not `id`: the field array keeps that name for its own.
 */
type GalleryRow = {
    photo_id: number | null;
    url: string | null;
    name: string | null;
    file: File | null;
};

type ProjectValues = {
    name: string;
    slug: string;
    location: string;
    completed_year: string;
    overview: string;
    thumbnail: File | null;
    gallery: GalleryRow[];
    stones: number[];
    categories: number[];
    is_published: boolean;
};

const EMPTY: ProjectValues = {
    name: '',
    slug: '',
    location: '',
    completed_year: String(new Date().getFullYear()),
    overview: '',
    thumbnail: null,
    gallery: [],
    stones: [],
    categories: [],
    is_published: false,
};

const fromRow = (project: ProjectRow): ProjectValues => ({
    name: project.name,
    slug: project.slug,
    location: project.location,
    completed_year: String(project.completedYear),
    overview: project.overview,
    thumbnail: null,
    gallery: project.images.map((image) => ({
        photo_id: image.id,
        url: image.url,
        name: image.file?.originalName ?? null,
        file: null,
    })),
    stones: project.stones.map((stone) => stone.id),
    categories: project.categories.map((category) => category.id),
    is_published: project.isPublished,
});

/**
 * The form's rules, the server's own (ProjectValidationRules) so the drawer
 * says what is wrong before it sends anything. Whether a slug is a stone's
 * or another project's only the server knows. The thumbnail is required only
 * while adding: `needsThumbnail` says which.
 */
const projectSchema = (
    limits: ProjectLimits,
    reserved: string[],
    needsThumbnail: { current: boolean },
) =>
    z
        .object({
            name: z
                .string()
                .trim()
                .min(1, "Enter the project's name.")
                .max(150, 'Use 150 characters or fewer.'),
            slug: z
                .string()
                .trim()
                .min(1, 'Enter the slug for its link.')
                .max(100, 'Use 100 characters or fewer.')
                .regex(
                    SLUG_PATTERN,
                    'Use lower-case letters, numbers and single hyphens, such as ginza-one.',
                )
                .refine(
                    (slug) => !reserved.includes(slug),
                    "That slug is kept for one of Regray's own pages. Choose another.",
                ),
            location: z
                .string()
                .trim()
                .min(1, 'Enter where the project is.')
                .max(255, 'Use 255 characters or fewer.'),
            completed_year: z
                .string()
                .trim()
                .regex(/^\d{4}$/, 'Enter a year, such as 2026.')
                .refine(
                    (value) => Number(value) >= 1900 && Number(value) <= 2100,
                    'Enter a year from 1900 to 2100.',
                ),
            overview: z
                .string()
                .trim()
                .min(1, 'Write a short overview of the project.')
                .max(
                    limits.overviewMax,
                    `Keep the overview to ${limits.overviewMax.toLocaleString('en-PH')} characters.`,
                ),
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
            stones: z
                .array(z.number())
                .min(1, 'Choose at least one stone used in the project.'),
            categories: z.array(z.number()),
            is_published: z.boolean(),
        })
        .superRefine((values, ctx) => {
            if (needsThumbnail.current && values.thumbnail === null) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['thumbnail'],
                    message: "Add the project's thumbnail.",
                });
            }
        });

type Drawer =
    | { mode: 'add' }
    | { mode: 'view'; project: ProjectRow }
    | { mode: 'edit'; project: ProjectRow };

/** A project's cover: its thumbnail, or its initials. */
const projectCover = (project: ProjectRow): AdminCardCoverData => ({
    image: project.thumbnail,
    initials: initials(project.name),
});

/** A gallery photo in the form: the saved one's picture, or a preview of the new file. */
const PhotoPreview = ({ row }: { row: GalleryRow }) => {
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (row.file === null) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(row.file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [row.file]);

    const src = preview ?? row.url;

    return (
        <div className="aspect-[4/3] w-full overflow-hidden bg-muted sm:w-28">
            {src ? (
                <img src={src} alt="" className="size-full object-cover" />
            ) : null}
        </div>
    );
};

/**
 * The admin's Projects page: every project on Regray but the archived, drafts
 * too, as cards two to a row, newest completed first, searched by name.
 *
 * Add, Edit and View open in the drawer; Archive asks first. What a card
 * offers comes from the server (`actions`). A project's details, thumbnail,
 * gallery, stones and categories are set here and read by its public page.
 */
const Projects = () => {
    const { props } = usePage<ProjectsPageProps>();
    const { limits } = props;
    const { update, isLoading } = useDataTableQuery(ONLY);
    const [drawer, setDrawer] = useState<Drawer | null>(null);
    const [archiving, setArchiving] = useState<ProjectRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const [slugEdited, setSlugEdited] = useState(false);
    const needsThumbnail = useRef(false);
    const photoInput = useRef<HTMLInputElement>(null);

    const schema = useMemo(
        () => projectSchema(limits, props.reservedSlugs, needsThumbnail),
        [limits, props.reservedSlugs],
    );

    const form = useForm<ProjectValues>({
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
    const gallery = useFieldArray({ control, name: 'gallery' });
    const galleryRows = useWatch({ control, name: 'gallery' }) ?? [];
    const [name, slug] = useWatch({ control, name: ['name', 'slug'] });

    const editing = drawer?.mode === 'edit' ? drawer.project : null;
    const viewing = drawer?.mode === 'view' ? drawer.project : null;
    const adding = drawer?.mode === 'add';

    // The form starts from the project being edited, or empty to add one.
    useEffect(() => {
        if (drawer?.mode === 'edit') {
            needsThumbnail.current = false;
            setSlugEdited(true);
            reset(fromRow(drawer.project));
        } else if (drawer?.mode === 'add') {
            needsThumbnail.current = true;
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
            const photo = /^gallery\.(\d+)\.(id|file)$/.exec(key);

            if (fields.has(key)) {
                setError(key as FieldPath<ProjectValues>, {
                    type: 'server',
                    message,
                });
            } else if (photo) {
                // A saved photo's own error goes where a new one's would.
                setError(`gallery.${Number(photo[1])}.file`, {
                    type: 'server',
                    message,
                });
            } else if (key.startsWith('gallery')) {
                setError('gallery', { type: 'server', message });
            } else if (key.startsWith('stones')) {
                setError('stones', { type: 'server', message });
            } else if (key.startsWith('categories')) {
                setError('categories', { type: 'server', message });
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
            location: values.location,
            completed_year: Number(values.completed_year),
            overview: values.overview,
            is_published: values.is_published,
            stones: values.stones,
            categories: values.categories,
            // Saved photos by id, new ones as files, in the gallery's order.
            gallery: values.gallery.map((row) =>
                row.photo_id !== null
                    ? { id: row.photo_id }
                    : { file: row.file },
            ),
            ...(values.thumbnail ? { thumbnail: values.thumbnail } : {}),
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
                updateProject.url(editing.id),
                { ...payload, _method: 'put' },
                options,
            );
        } else {
            router.post(storeProject.url(), payload, options);
        }
    });

    const archive = () => {
        if (archiving === null) {
            return;
        }

        router.post(
            archiveProject.url(archiving.id),
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
                            'The project could not be archived. Try again.',
                    );
                },
            },
        );
    };

    const addPhotos = (files: FileList | null) => {
        const room = limits.galleryMax - gallery.fields.length;

        Array.from(files ?? [])
            .slice(0, Math.max(0, room))
            .forEach((file) =>
                gallery.append({
                    photo_id: null,
                    url: null,
                    name: file.name,
                    file,
                }),
            );
    };

    const rows = props.projects.data;
    const isFiltered = props.filters.search.trim() !== '';
    const addButton = (
        <Button type="button" onClick={() => setDrawer({ mode: 'add' })}>
            <Plus aria-hidden />
            Add project
        </Button>
    );
    const publicUrlFor = (value: string) =>
        props.publicProjectUrl.replace('__slug__', value || 'your-project');
    const stoneOptions = props.stoneOptions.map((stone) => ({
        id: stone.id,
        label: `${stone.name} · ${stone.brand}`,
    }));
    const categoryOptions = props.categoryOptions.map((category) => ({
        id: category.id,
        label: category.name,
    }));
    // What a project being edited has that is no longer offered, so it still shows.
    const extraStones = Object.fromEntries(
        (editing?.stones ?? []).map((stone) => [stone.id, stone.name]),
    );
    const extraCategories = Object.fromEntries(
        (editing?.categories ?? []).map((category) => [
            category.id,
            category.name,
        ]),
    );

    return (
        <div className="w-full min-w-0">
            {props.canCreate ? (
                <PageHeader
                    variant="action"
                    title="Projects"
                    subtitle="Manage the projects shown across Regray"
                    action={addButton}
                />
            ) : (
                <PageHeader
                    title="Projects"
                    subtitle="Manage the projects shown across Regray"
                />
            )}

            <div className="space-y-4">
                <DataTableToolbar
                    search={
                        <DataTableSearch
                            value={props.filters.search}
                            placeholder="Search by project name"
                            onSearch={(search) => update({ search })}
                        />
                    }
                />

                {!isLoading && rows.length === 0 ? (
                    isFiltered ? (
                        <EmptyState
                            title="No projects match that search"
                            description="Try another project name."
                        />
                    ) : (
                        <EmptyState
                            title="No projects yet"
                            description="Add a project to show it on Regray."
                            action={props.canCreate ? addButton : undefined}
                        />
                    )
                ) : (
                    <div
                        className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6"
                        aria-busy={isLoading}
                        data-testid="project-grid"
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
                                      <AdminCardSkeleton
                                          key={`skeleton-${index}`}
                                          testId="project-card"
                                      />
                                  ),
                              )
                            : rows.map((project) => (
                                  <AdminCard
                                      key={project.id}
                                      testId="project-card"
                                      title={project.name}
                                      subtitle={projectSubtitle(
                                          project.location,
                                          project.completedYear,
                                      )}
                                      subtitlePlaceholder="No location yet"
                                      cover={projectCover(project)}
                                      status={{
                                          active: project.isPublished,
                                          labels: ['Published', 'Draft'],
                                      }}
                                      meta={projectCount(
                                          project.stones.length,
                                          project.images.length,
                                      )}
                                      actions={project.actions}
                                      onView={() =>
                                          setDrawer({ mode: 'view', project })
                                      }
                                      onEdit={() =>
                                          setDrawer({ mode: 'edit', project })
                                      }
                                      onArchive={() => setArchiving(project)}
                                  />
                              ))}
                    </div>
                )}

                <DataTablePagination
                    meta={props.projects.meta}
                    onPageChange={(page) => update({ page })}
                />
            </div>

            {/* View: read-only, with Edit to switch the same drawer over. */}
            <FormDrawer
                open={viewing !== null}
                onOpenChange={(open) => (open ? null : close())}
                title={viewing?.name ?? 'Project'}
                description={
                    viewing
                        ? projectSubtitle(
                              viewing.location,
                              viewing.completedYear,
                          )
                        : undefined
                }
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
                                        Open project page
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
                                            project: viewing,
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
                    <div className="space-y-6" data-testid="project-details">
                        <div className="-mx-1">
                            <AdminCardCover
                                cover={projectCover(viewing)}
                                className="border border-sidebar-border"
                            />
                            <div className="mt-4 flex flex-wrap items-start justify-between gap-3 px-1">
                                <div className="min-w-0">
                                    <p className="text-2xl leading-tight font-bold tracking-[-0.03em] break-words">
                                        {viewing.name}
                                    </p>
                                    <p className="mt-1 text-sm text-secondary-foreground">
                                        {projectSubtitle(
                                            viewing.location,
                                            viewing.completedYear,
                                        )}
                                    </p>
                                </div>
                                <StatusBadge
                                    active={viewing.isPublished}
                                    labels={['Published', 'Draft']}
                                />
                            </div>
                        </div>

                        <Section title="Overview">
                            <div className="space-y-3 text-sm leading-relaxed">
                                {viewing.overview
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
                        </Section>

                        {viewing.images.length > 0 ? (
                            <Section title="Gallery">
                                <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {viewing.images.map((image, index) => (
                                        <li
                                            key={image.id}
                                            className="aspect-[4/3] overflow-hidden bg-muted"
                                        >
                                            <img
                                                src={image.url}
                                                alt={`${viewing.name} - ${image.name ?? `Image ${index + 1}`}`}
                                                loading="lazy"
                                                className="size-full object-cover"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        ) : null}

                        {viewing.stones.length > 0 ? (
                            <Section title="Stones">
                                <ul className="flex flex-wrap gap-2">
                                    {viewing.stones.map((stone) => (
                                        <li
                                            key={stone.id}
                                            className="border border-sidebar-border bg-muted/40 px-2.5 py-1 text-sm"
                                        >
                                            {stone.name}
                                        </li>
                                    ))}
                                </ul>
                            </Section>
                        ) : null}

                        {viewing.categories.length > 0 ? (
                            <Section title="Categories">
                                <ul className="flex flex-wrap gap-2">
                                    {viewing.categories.map((category) => (
                                        <li
                                            key={category.id}
                                            className="border border-sidebar-border bg-muted/40 px-2.5 py-1 text-sm"
                                        >
                                            {category.name}
                                        </li>
                                    ))}
                                </ul>
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
                                            /{viewing.slug}
                                            <span className="sr-only">
                                                {' '}
                                                (opens in a new tab)
                                            </span>
                                        </a>
                                    ) : (
                                        <span className="text-secondary-foreground">
                                            A draft, so it has no page
                                        </span>
                                    )}
                                </Detail>
                                <Detail label="Slug">
                                    <span className="font-mono">
                                        {viewing.slug}
                                    </span>
                                </Detail>
                                <Detail label="Thumbnail file">
                                    {fileLine(viewing.thumbnailFile)}
                                </Detail>
                                {viewing.images.length > 0 ? (
                                    <Detail label="Gallery files">
                                        <ol className="space-y-1">
                                            {viewing.images.map((image) => (
                                                <li key={image.id}>
                                                    {fileLine(image.file)}
                                                </li>
                                            ))}
                                        </ol>
                                    </Detail>
                                ) : null}
                                <Detail label="Published on">
                                    {viewing.publishedAt
                                        ? formatDate(viewing.publishedAt)
                                        : 'Not yet'}
                                </Detail>
                                <Detail label="Added">
                                    {formatDate(viewing.createdAt)}
                                </Detail>
                                <Detail label="Last updated">
                                    {formatDate(viewing.updatedAt)}
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
                title={editing ? `Edit ${editing.name}` : 'Add project'}
                description={
                    editing
                        ? 'Change what Regray shows about this project.'
                        : 'A finished project, with its own page on Regray.'
                }
                submitLabel={editing ? 'Save changes' : 'Add project'}
                onSubmit={save}
                processing={processing}
                size="lg"
            >
                <div className="space-y-6">
                    <Section title="Identity" as="fieldset">
                        <FormField
                            id="project-name"
                            label="Project name"
                            required
                            error={formState.errors.name?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="project-name"
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
                            id="project-slug"
                            label="Slug"
                            required
                            error={formState.errors.slug?.message}
                            hint={`${publicUrlFor(slug)}${
                                editing
                                    ? '. Changing it changes the project’s public link.'
                                    : ''
                            }`}
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="project-slug"
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
                            id="project-location"
                            label="Location"
                            required
                            error={formState.errors.location?.message}
                            hint="Such as Makati City."
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="project-location"
                                    autoComplete="off"
                                    maxLength={255}
                                    className="mt-2 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('location')}
                                />
                            )}
                        </FormField>

                        <FormField
                            id="project-year"
                            label="Completed in"
                            required
                            error={formState.errors.completed_year?.message}
                            hint="The year, from 1900 to 2100."
                        >
                            {({ describedBy, invalid }) => (
                                <Input
                                    id="project-year"
                                    type="number"
                                    inputMode="numeric"
                                    min={1900}
                                    max={2100}
                                    step={1}
                                    className="mt-2 w-32 rounded-none"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('completed_year')}
                                />
                            )}
                        </FormField>

                        <FormField
                            id="project-overview"
                            label="Overview"
                            required
                            error={formState.errors.overview?.message}
                            hint="Shown on its page. Leave a blank line between paragraphs."
                        >
                            {({ describedBy, invalid }) => (
                                <Textarea
                                    id="project-overview"
                                    rows={6}
                                    className="mt-2"
                                    aria-describedby={describedBy}
                                    aria-invalid={invalid}
                                    {...register('overview')}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Images" as="fieldset">
                        <Controller
                            control={control}
                            name="thumbnail"
                            render={({ field, fieldState }) => (
                                <ImageField
                                    id="project-thumbnail"
                                    label="Thumbnail"
                                    required={editing === null}
                                    accept={IMAGE_ACCEPT}
                                    hint={imageHint(
                                        IMAGE_TYPES,
                                        limits.thumbnailMaxKb,
                                        editing
                                            ? 'The cover photo on its card and page, best at 16:9. Leave it to keep the current one.'
                                            : 'The cover photo on its card and page, best at 16:9, such as 1600 × 900.',
                                    )}
                                    error={fieldState.error?.message}
                                    file={field.value}
                                    currentUrl={editing?.thumbnail ?? null}
                                    currentName={
                                        editing?.thumbnailFile?.originalName
                                    }
                                    onFileChange={(file) => {
                                        field.onChange(file);
                                        field.onBlur();
                                    }}
                                />
                            )}
                        />

                        <div>
                            <p className="text-sm font-bold">Gallery</p>
                            <p
                                id="project-gallery-hint"
                                className="mt-1 text-sm text-secondary-foreground"
                            >
                                Shown on its page in this order.{' '}
                                {imageHint(IMAGE_TYPES, limits.photoMaxKb)}{' '}
                                {limits.galleryMax} photos at most.
                            </p>
                            {formState.errors.gallery?.message ||
                            formState.errors.gallery?.root?.message ? (
                                <p className="mt-1 text-sm text-destructive">
                                    {formState.errors.gallery.message ??
                                        formState.errors.gallery.root?.message}
                                </p>
                            ) : null}

                            {gallery.fields.length > 0 ? (
                                <ol className="mt-3 space-y-3">
                                    {gallery.fields.map((photo, index) => {
                                        const errors =
                                            formState.errors.gallery?.[index];
                                        const number = index + 1;
                                        const row = galleryRows[index] ?? photo;

                                        return (
                                            <li
                                                key={photo.id}
                                                className="grid grid-cols-1 gap-3 border border-sidebar-border p-3 sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-start"
                                            >
                                                <PhotoPreview row={row} />
                                                <div className="min-w-0">
                                                    <p
                                                        className="truncate text-sm"
                                                        title={
                                                            row.name ??
                                                            undefined
                                                        }
                                                    >
                                                        {row.name ??
                                                            `Photo ${number}`}
                                                        {row.file ? (
                                                            <span className="ml-2 text-xs text-secondary-foreground">
                                                                New
                                                            </span>
                                                        ) : null}
                                                    </p>
                                                    {errors?.file?.message ? (
                                                        <p className="mt-1 text-sm text-destructive">
                                                            {
                                                                errors.file
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
                                                            gallery.move(
                                                                index,
                                                                index - 1,
                                                            )
                                                        }
                                                        aria-label={`Move photo ${number} up`}
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
                                                            gallery.fields
                                                                .length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            gallery.move(
                                                                index,
                                                                index + 1,
                                                            )
                                                        }
                                                        aria-label={`Move photo ${number} down`}
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
                                                            gallery.remove(
                                                                index,
                                                            )
                                                        }
                                                        aria-label={`Remove photo ${number}`}
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </Button>
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ol>
                            ) : null}

                            {gallery.fields.length < limits.galleryMax ? (
                                <>
                                    <input
                                        ref={photoInput}
                                        type="file"
                                        accept={IMAGE_ACCEPT}
                                        multiple
                                        className="sr-only"
                                        tabIndex={-1}
                                        aria-hidden
                                        onChange={(event) => {
                                            addPhotos(event.target.files);
                                            event.target.value = '';
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-3"
                                        onClick={() =>
                                            photoInput.current?.click()
                                        }
                                        aria-describedby="project-gallery-hint"
                                    >
                                        <ImagePlus aria-hidden />
                                        Add photos
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    </Section>

                    <Section title="Stones" as="fieldset">
                        <FormField
                            id="project-stones"
                            label="Stones used"
                            required
                            error={
                                formState.errors.stones?.message ??
                                formState.errors.stones?.root?.message
                            }
                            hint="Its page lists them, and each stone's page shows the project."
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="stones"
                                    render={({ field }) => (
                                        <div className="mt-2">
                                            <MultiSelect
                                                id="project-stones"
                                                value={field.value}
                                                onChange={field.onChange}
                                                onBlur={field.onBlur}
                                                options={stoneOptions}
                                                extraLabels={extraStones}
                                                placeholder="Search the stones"
                                                listLabel="Stones used"
                                                emptyMessage="No stone matches that."
                                                describedBy={describedBy}
                                                invalid={invalid}
                                            />
                                        </div>
                                    )}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Categories" as="fieldset">
                        <FormField
                            id="project-categories"
                            label="Categories"
                            error={
                                formState.errors.categories?.message ??
                                formState.errors.categories?.root?.message
                            }
                            hint="Such as Kitchens or Bathrooms. Optional."
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="categories"
                                    render={({ field }) => (
                                        <div className="mt-2">
                                            <MultiSelect
                                                id="project-categories"
                                                value={field.value}
                                                onChange={field.onChange}
                                                onBlur={field.onBlur}
                                                options={categoryOptions}
                                                extraLabels={extraCategories}
                                                placeholder="Search the categories"
                                                listLabel="Categories"
                                                emptyMessage="No category matches that."
                                                describedBy={describedBy}
                                                invalid={invalid}
                                            />
                                        </div>
                                    )}
                                />
                            )}
                        </FormField>
                    </Section>

                    <Section title="Display" as="fieldset">
                        <Controller
                            control={control}
                            name="is_published"
                            render={({ field }) => (
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <label
                                            htmlFor="project-published"
                                            className="text-sm font-bold"
                                        >
                                            Published on Regray
                                        </label>
                                        <p
                                            id="project-published-hint"
                                            className="mt-1 text-sm text-secondary-foreground"
                                        >
                                            A draft has no page and shows
                                            nowhere on Regray.
                                        </p>
                                    </div>
                                    <Switch
                                        id="project-published"
                                        checked={field.value}
                                        onCheckedChange={field.onChange}
                                        aria-describedby="project-published-hint"
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
                title={`Archive ${archiving?.name ?? 'this project'}?`}
                description={archiveDescription()}
                confirmLabel="Archive project"
                cancelLabel="Keep project"
                onConfirm={archive}
                destructive
                processing={processing}
            />
        </div>
    );
};

export default Projects;

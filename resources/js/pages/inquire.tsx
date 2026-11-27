import { zodResolver } from '@hookform/resolvers/zod';
import { Link, router, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import {
    type ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { FormProvider, useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import ConfirmDialog from '@/components/confirm-dialog';
import EmptyState from '@/components/empty-state';
import { fieldMessage } from '@/components/form-field';
import {
    INQUIRY_ADDRESS_ID,
    InquiryAddressContext,
    projectAddressLine,
    type InquiryAddressContextValue,
} from '@/components/inquiry-address-field';
import InquiryBrandQuestion, {
    INQUIRY_BRANDS_ID,
} from '@/components/inquiry-brand-question';
import { INQUIRY_DELIVERY_METHOD_ID } from '@/components/inquiry-project-details';
import InquiryProjectForm from '@/components/inquiry-project-form';
import InquiryQuestion, {
    InquiryActionBar,
    InquiryActionBarSlot,
    inquiryQuestionId,
} from '@/components/inquiry-question';
import InquiryResult, {
    type InquiryResultState,
} from '@/components/inquiry-result';
import InquiryReview from '@/components/inquiry-review';
import InquiryServiceQuestion from '@/components/inquiry-service-question';
import InquiryStepper from '@/components/inquiry-stepper';
import InputError from '@/components/input-error';
import StaffInquireStart, {
    leadTarget,
} from '@/components/staff-inquire-start';
import {
    staffInquireKindFor,
    staffInquireKindFrom,
    type StaffInquireKind,
} from '@/lib/staff-inquire';
import InquiryStoneGrid, {
    StoneGridSkeleton,
} from '@/components/inquiry-stone-grid';
import StoneBrandFilter from '@/components/stone-brand-filter';
import {
    StoneTypePills,
    StoneTypeSelect,
} from '@/components/stone-type-filter';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    InquiryCatalogProvider,
    useInquiryCatalog,
} from '@/hooks/use-inquiry-catalog';
import { useInquireStoreUrl } from '@/hooks/use-inquire-route';
import { useInquiryDraftAutosave } from '@/hooks/use-inquiry-draft-autosave';
import { usePermissions } from '@/hooks/use-permissions';
import {
    gridPageSizeCookieIs,
    useGridPageSize,
} from '@/hooks/use-grid-page-size';
import { createInquiryCatalog } from '@/lib/inquiry-catalog';
import {
    draftStoneSlugs,
    keepKnownStones,
    readInquiryDraft,
    type StoredInquiryDraft,
} from '@/lib/inquiry-draft-storage';
import { rateLimitFrom } from '@/lib/rate-limit';
import type { RateLimit } from '@/lib/rate-limit';
import {
    ALL_STONE_TYPES,
    autoProjectName,
    BRAND_STEP,
    furthestQuestion,
    buildCommittedProject,
    buildInquiryFormData,
    clampRestoredStep,
    deadlineLocks,
    findService,
    fulfillmentAfterServiceChange,
    HELD_ADDRESS,
    INQUIRY_STEP_LABELS,
    mapInquiryErrors,
    nextQuestion,
    questionProblem,
    readInquiryFlash,
    removedBrandStonesNotice,
    removedStonesNotice,
    REVIEW_STEP,
    seedStoneRows,
    stepperPosition,
    stoneBrandsQuery,
    stoneFilterQuery,
    stonesOutsideBrands,
    stoneTypeOptions,
    toEditFormProjects,
    toggleBrandFilter,
    allowedStoneBrands,
    type AddressSource,
    type InquiryEditProject,
    type InquiryFlash,
    type ServiceTypeOption,
    type StoneFilter,
    type StoneQuery,
} from '@/lib/inquiry-flow';
import {
    createInquirySchemas,
    draftDefaults,
    inquiryDefaults,
    REQUIREMENTS_STEP,
    SERVICE_STEP,
    STEP_FIELDS,
    STONES_STEP,
    type InquiryDraftInput,
    type InquiryDraftValues,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';
import { PROJECT_NAME_MAX } from '@/data/inquiries';
import {
    addressFromPayload,
    addressValueLine,
    type PhAddressPayload,
} from '@/lib/ph-address';
import { index as profileAddresses } from '@/routes/profile/addresses';
import { permissions } from '@/layouts/workspace-layout';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as quoteIndex, update as quoteUpdate } from '@/routes/quote';
import { update as inquiryUpdate } from '@/routes/inquiry';
import type { Auth } from '@/types';
import type {
    InquirePageProps,
    InquiryStoneOption,
    SavedAddress,
    StaffInquireTarget,
} from '@/types/inquiry';

const STEPS = [...INQUIRY_STEP_LABELS];

/**
 * The way on from the last question shown, drawn into the page's bar like
 * every other footer. Its name says which question it shows next.
 */
const NextBar = ({
    nextTitle,
    onNext,
}: {
    nextTitle: string;
    onNext: () => void;
}) => (
    <InquiryActionBar>
        <span className="min-w-0 flex-1" />
        <Button
            type="button"
            onClick={onNext}
            aria-label={`Next: ${nextTitle}`}
            className="w-full shrink-0 sm:w-auto"
        >
            Next
        </Button>
    </InquiryActionBar>
);

const REQUEST_DESCRIPTION =
    'Answer a few simple questions. We save your answers as you go.';

const REVIEW_DESCRIPTION = 'Check your request, then send it';

/**
 * The page fills the screen under the header, and gives back the layout's bottom
 * padding, so the bar at its end sits on the bottom edge of the screen: 3.5rem or
 * 4rem of header and 1.5rem or 2rem of padding, as the layout steps them up.
 */
const FULL_SCREEN_PAGE =
    'flex min-h-[calc(100svh-5rem)] w-full flex-col -mb-6 sm:min-h-[calc(100svh-5.5rem)] sm:-mb-8 lg:min-h-[calc(100svh-6rem)]';

const BOTTOM_BAR = 'sticky bottom-0 z-10';

/**
 * Which step owns which field, read off `STEP_FIELDS` rather than written out
 * again. The whole-form check has to say "change your projects" beside the one
 * project complaint, and a second hand-written list of the same pairing could
 * drift from the one the schema itself is written against.
 */
const STEP_OF_FIELD = new Map<string, number>(
    Object.entries(STEP_FIELDS).flatMap(([step, fields]) =>
        (fields ?? []).map((field) => [field as string, Number(step)]),
    ),
);

/**
 * Said once, in one place, so the project it is about and the list above the
 * sheet cannot offer different advice about the same wait.
 */
const WAITING_FOR_UPLOADS = 'Wait for uploads to finish.';

/**
 * A new project's id.
 *
 * Not an index: Edit and Remove both point at one project rather than at a
 * position, so that removing Project 1 does not silently turn Project 2 into
 * Project 1 under a project that is already saved.
 *
 * A timestamp taken once at load, plus a sequence, rather than
 * `crypto.randomUUID`. That is a secure-context API: it is missing on a
 * plain-HTTP origin outside localhost — a LAN address, a local domain, which is
 * exactly how this app is reached while it is being worked on — and the id is
 * minted on the path that commits a project, so its absence takes the whole
 * commit down and leaves the button looking dead. The timestamp separates two
 * inquiries built in two tabs; the sequence separates the projects in one.
 */
const projectIdPrefix = Date.now().toString(36);

let projectSequence = 0;

const newProjectId = (): string => {
    projectSequence += 1;

    return `${projectIdPrefix}-${projectSequence}`;
};

/**
 * How a send ended, as the page needs to hear it.
 *
 * `flashed` is the backend answering with its own verdict, success or error;
 * `invalid` is the form request refusing the answers, with its errors keyed by
 * field path; `failed` is everything else — the network, a server error page —
 * where the backend never got to say anything.
 */
type SendOutcome =
    | { kind: 'flashed'; flash: InquiryFlash | null }
    | { kind: 'invalid'; errors: Record<string, string> }
    | { kind: 'failed'; rateLimit?: RateLimit };

/**
 * The send, wrapped in a promise so the guard and the reset-on-success logic
 * around it read straight down.
 *
 * Multipart because the attachments are `File` objects. The promise settles on
 * whichever callback fires first; `onFinish` is the net under the other two, for
 * the failures that run neither.
 *
 * `onHttpException` returns false so a server error is answered by the page's
 * own result panel, which keeps the answers and offers a retry, rather than by
 * Inertia's dialog of the raw response laid over it. A 429 carries how long to
 * wait as the failure's message.
 *
 * `patch` is the edit of a sent inquiry. It still goes out as a POST carrying
 * `_method=PATCH`, because PHP does not parse a multipart body sent as PATCH,
 * and it carries each project's id and the stored files it keeps.
 */
const sendInquiryRequest = (
    url: string,
    values: InquiryFormValues,
    serviceTypes: ServiceTypeOption[],
    method: 'post' | 'patch' = 'post',
    addressSource: AddressSource = 'book',
): Promise<SendOutcome> =>
    new Promise((resolve) => {
        const body = buildInquiryFormData(values, {
            withProjectIds: method === 'patch',
            serviceFor: (serviceId) => findService(serviceTypes, serviceId),
            addressSource,
        });

        if (method === 'patch') {
            body.append('_method', 'PATCH');
        }

        let rateLimit: RateLimit | undefined;

        router.post(url, body, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) =>
                resolve({
                    kind: 'flashed',
                    flash: readInquiryFlash(page.flash),
                }),
            onError: (errors) =>
                resolve({
                    kind: 'invalid',
                    errors: errors as Record<string, string>,
                }),
            onHttpException: (response) => {
                rateLimit = rateLimitFrom(response) ?? undefined;

                return false;
            },
            onFinish: () => resolve({ kind: 'failed', rateLimit }),
        });
    });

const SKELETON_MS = 700;

/**
 * The strip above steps 1 and 2 saying which project is being built and how to
 * walk away from it.
 *
 * It is there because steps 1 and 2 look exactly as they did before there could
 * be more than one project: without a line saying so, a reader adding their
 * second project would have no way of telling the two apart, and walking away
 * would look like walking away from the whole inquiry.
 */
/**
 * The page's title in the heading face, what to do under it and, while the
 * questions are open on a new request, a note that the answers are kept as
 * they are given (the draft autosave). Anything else the page puts at the
 * top, such as Change client or the way back from an edit, sits at its end.
 */
const InquireHeading = ({
    title,
    description,
    action,
    showSaved,
}: {
    title: string;
    description: string;
    action?: ReactNode;
    showSaved: boolean;
}) => (
    <div className="mb-7 flex flex-col gap-4 sm:mb-9 md:flex-row md:items-end md:justify-between">
        <div className="min-w-0 space-y-2">
            <h2 className="font-display text-[clamp(1.875rem,2.8vw,2.5rem)] leading-tight font-light tracking-[-0.005em]">
                {title}
            </h2>
            <p className="text-base text-muted-foreground">{description}</p>
        </div>
        {action || showSaved ? (
            <div className="flex shrink-0 flex-wrap items-center gap-x-5 gap-y-2">
                {showSaved ? (
                    <span className="inline-flex items-center gap-2 text-[0.8125rem] text-muted-foreground">
                        <span
                            aria-hidden
                            className="size-1.75 rounded-full bg-success-text"
                        />
                        Answers saved
                    </span>
                ) : null}
                {action}
            </div>
        ) : null}
    </div>
);

const ProjectBanner = ({
    label,
    onCancel,
}: {
    label: string;
    onCancel: () => void;
}) => (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-md border border-sidebar-border bg-background px-4 py-3">
        <p className="text-sm font-bold text-foreground">{label}</p>
        <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onCancel}
            className="h-7 cursor-pointer px-1.5 text-xs"
        >
            Cancel
        </Button>
    </div>
);

/**
 * The page as it looks while the flow is still loading.
 *
 * Shaped from the step the reader lands on rather than from a generic list of
 * blocks, because a placeholder that does not match the thing it stands in for
 * is worse than none: the page jumps the moment the real stones arrive, and the
 * jump is the jarring part — the skeleton is supposed to be what absorbs it. So
 * the heading, the rail, the first question and the footer are the real ones'
 * measurements.
 *
 * Marked busy rather than loading, and paired with the one line a screen reader
 * is given, because a column of empty boxes cannot say anything on its own: it
 * would be read as a page that has finished and simply has no content.
 */
const InquireSkeleton = () => {
    return (
        <div role="status" aria-busy="true" className={FULL_SCREEN_PAGE}>
            {/* Everything below is empty boxes; this is the only thing to hear. */}
            <span className="sr-only">Loading the inquiry</span>

            {/* The page heading: a title and the line under it. */}
            <div className="mb-8 flex flex-col gap-3 border-b border-sidebar-border pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div className="min-w-0 space-y-1.5">
                    <Skeleton className="h-9 w-40" />
                    <Skeleton className="h-5 w-56" />
                </div>
            </div>

            {/*
             * The rail, at its real height: four boxes on a line with their labels
             * beneath it, so nothing about the spacing moves when it arrives. The
             * boxes are the same `size-9` the real ones are, and the labels are given
             * real heights rather than omitted — the labels hang below the boxes and
             * are most of what this band's height is made of.
             */}
            <div className="mx-auto mb-8 w-full max-w-2xl">
                <Skeleton className="mx-auto h-4 w-44 sm:hidden" />

                <div className="hidden items-center px-6 pb-7 sm:flex">
                    {STEPS.map((_, index) => (
                        <div
                            key={index}
                            className="flex min-w-0 flex-1 items-center last:flex-none"
                        >
                            <div className="relative shrink-0">
                                <Skeleton className="size-9 rounded-md" />
                                <Skeleton className="absolute top-full left-1/2 mt-2 h-3 w-16 -translate-x-1/2" />
                            </div>

                            {/* The same gap on both sides of every box. */}
                            {index < STEPS.length - 1 ? (
                                <Skeleton className="mx-2 h-px flex-1" />
                            ) : null}
                        </div>
                    ))}
                </div>
            </div>

            {/* The first question: its number and title, then its first dropdown. */}
            <div className="flex items-center gap-3 border-t border-sidebar-border py-5 first:border-t-0">
                <Skeleton className="size-8 shrink-0 rounded-full" />
                <Skeleton className="h-7 w-44" />
            </div>
            <div className="sm:max-w-md">
                <Skeleton className="h-3 w-24 rounded-none" />
                <Skeleton className="mt-3 h-9 w-full rounded-none" />
            </div>

            {/*
             * The bar the reader finishes on, with the way forward on the right. Pinned the same way, because it is pinned in the real
             * page and a placeholder that scrolls away while the real one does not
             * would move the button a reader was reaching for.
             */}
            <div
                className={`${BOTTOM_BAR} mt-auto flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-end`}
            >
                {/*
                 * 46px, which is what the real Continue comes out at: `py-3` on
                 * both sides of a `text-sm` line, plus the button's own border.
                 * `h-11` and `h-12` both miss it, and this is the one button the
                 * reader is reaching for, so it is the one that must not move.
                 */}
                <Skeleton className="h-[46px] w-full rounded-md sm:w-36" />
            </div>
        </div>
    );
};

/** A sent inquiry being edited: its reference and its projects as stored. */
type InquiryEdit = {
    reference: string;
    projects: InquiryEditProject[];
    /** Where the edit is saved, and the list it was started from. */
    updateUrl: string;
    back: { url: string; label: string };
    /** Staff edit the project only: the client's files stay as they are. */
    allowAttachments: boolean;
};

/**
 * The stepper. `edit` is null when starting a new inquiry; when it is set, the
 * same steps change that inquiry instead (see `reviewActions`).
 *
 * `target` is set when staff open the inquiry for someone: a Platform client's
 * lead or a Direct (walk-in) client. It is sent there rather than for the
 * reader, and takes no attachments.
 *
 * `addresses` is a client's own address book, which a project's address is
 * picked from, the default first and already picked. Staff have none (null):
 * they enter the address with the address picker.
 */
const InquireFlow = ({
    edit,
    target = null,
    onChangeTarget,
    rememberStones,
    addresses = null,
}: {
    edit: InquiryEdit | null;
    target?: StaffInquireTarget | null;
    onChangeTarget?: () => void;
    rememberStones: (found: InquiryStoneOption[]) => void;
    addresses?: SavedAddress[] | null;
}) => {
    const catalog = useInquiryCatalog();
    const addressSource: AddressSource = addresses === null ? 'picker' : 'book';
    // An edit may keep the date it already has, however soon it now is.
    const heldNeededBy = edit?.projects[0]?.neededBy ?? null;
    const { inquirySchema, projectDraftSchema } = useMemo(
        () => createInquirySchemas(catalog, addressSource, heldNeededBy),
        [catalog, addressSource, heldNeededBy],
    );
    const ownStoreUrl = useInquireStoreUrl();
    const inquireStoreUrl = target === null ? ownStoreUrl : target.storeUrl;

    /** A service's name, for the notices about stones it does not offer. */
    const serviceName = useCallback(
        (serviceId: string): string =>
            catalog.serviceTypes
                .flatMap(({ services }) => services)
                .find(({ id }) => id === serviceId)?.name ?? '',
        [catalog],
    );

    /** Whether a service asks for stones; one the page does not know is taken to. */
    const requiresStones = useCallback(
        (serviceId: string): boolean =>
            findService(catalog.serviceTypes, serviceId)?.requiresStones !==
            false,
        [catalog],
    );

    /** What the first question's Next accepts, including a service an edit holds. */
    const offeredServiceIds = useMemo(
        () =>
            new Set(
                catalog.serviceTypes.flatMap(({ services }) =>
                    services.map(({ id }) => id),
                ),
            ),
        [catalog],
    );

    const [isLoading, setIsLoading] = useState(true);
    /**
     * Both start on the first step. An edit opens the inquiry's one project
     * there as soon as it is loaded (see the effect after `openProject`).
     */
    const [step, setStep] = useState<number>(BRAND_STEP);
    /**
     * How many questions are shown: the first alone at the start, and one more
     * each time Next is pressed under the last. Every question shown stays open
     * with all its fields, so an earlier answer is changed where it was given.
     */
    const [shownQuestions, setShownQuestions] = useState<number>(BRAND_STEP);
    /** The question Next has just shown, brought into view once it is drawn. */
    const revealedQuestionRef = useRef<number | null>(null);
    const {
        stones: stonePage,
        stoneTypes,
        stoneFilters,
        furniture: furniturePrefill = null,
    } = usePage<InquirePageProps>().props;
    /** Which stones a change of service, a restore or an edit took off, said on step 2. */
    const [stoneNotice, setStoneNotice] = useState<string | null>(null);
    /** The service the stones were last asked for, so each change asks once. */
    const requestedServiceRef = useRef(stoneFilters.service ?? '');
    const [filter, setFilter] = useState<StoneFilter>(stoneFilters.tab);
    const [stoneType, setStoneType] = useState(
        stoneFilters.type ?? ALL_STONE_TYPES,
    );
    const [search, setSearch] = useState(stoneFilters.search ?? '');
    /** The stones' own Brand filter: none is All brands. */
    const [brandFilter, setBrandFilter] = useState<string[]>(
        stoneFilters.brands ?? [],
    );
    const [isLoadingStones, setIsLoadingStones] = useState(false);
    const searchTimerRef = useRef<number | null>(null);
    /** While a restored draft asks the server about its stones. */
    const [isResolvingDraft, setIsResolvingDraft] = useState(false);

    /** Which committed project the open draft is editing, or null when new. */
    const [editingProjectId, setEditingProjectId] = useState<string | null>(
        null,
    );

    const [result, setResult] = useState<InquiryResultState | null>(null);
    const [isSending, setIsSending] = useState(false);
    const [actionBarSlot, setActionBarSlot] = useState<HTMLDivElement | null>(
        null,
    );
    /** How many files a restored draft could not keep, until the reader has read so. */
    const [lostAttachments, setLostAttachments] = useState(0);
    const [validationAlertErrors, setValidationAlertErrors] = useState<
        string[]
    >([]);
    const [sectionMessages, setSectionMessages] = useState<
        Partial<Record<number, string>>
    >({});

    /*
     * One complaint per project, keyed by its id rather than by its position, so
     * that a stone dropped or a project removed since the request was checked
     * cannot move somebody else's complaint onto this project. Every project now
     * carries its own site, date and files, so most of what the whole-form check
     * has to say is about one of them rather than about the request as a whole.
     */
    const [projectMessages, setProjectMessages] = useState<
        Record<string, string>
    >({});

    /**
     * Whether the stored draft has been put back.
     *
     * The autosave reads this rather than guessing: until the restore has run,
     * both forms hold nothing but their blank defaults, and writing those over
     * the draft the reader is about to be shown would lose it before they had
     * seen it.
     */
    const [isRestored, setIsRestored] = useState(false);

    /*
     * Everything the request will carry. A project is committed to it whole or
     * not at all, so a step change can never cost a finished one.
     *
     * `onTouched` so a field is judged once the reader has left it rather than
     * mid-keystroke, and `onChange` after that so fixing one answer clears its
     * own complaint without waiting for the next blur.
     */
    const methods = useForm<InquiryFormInput, unknown, InquiryFormValues>({
        resolver: zodResolver(inquirySchema),
        mode: 'onTouched',
        reValidateMode: 'onChange',
        defaultValues: inquiryDefaults,
        shouldUnregister: false,
    });

    const { setValue, trigger, getValues, reset } = methods;

    /*
     * Subscribed rather than read with `watch`, for the same reason the review
     * sheet subscribes: the list changes without the step changing — a project
     * taken off, a draft opened for editing — and a bare read would leave the
     * rest of the page answering from the list as it was.
     */
    const projects = useWatch({ control: methods.control, name: 'projects' });

    /** When editing, why each locked project's date and time cannot change. */
    const lockedDeadlines = useMemo(
        () => (edit === null ? {} : deadlineLocks(edit.projects)),
        [edit],
    );

    /*
     * A new project starts on the client's default address: a saved address
     * is the only kind they pick. An id no longer in the book (deleted while
     * the draft waited) is no answer.
     */
    const defaultAddressId = useMemo(() => {
        const saved =
            addresses?.find(({ isDefault }) => isDefault) ?? addresses?.[0];

        return saved === undefined ? '' : String(saved.id);
    }, [addresses]);

    const knownAddressId = useCallback(
        (id: string): string =>
            addresses === null
                ? ''
                : id === HELD_ADDRESS && edit !== null
                  ? id
                  : addresses.some((saved) => String(saved.id) === id)
                    ? id
                    : defaultAddressId,
        [addresses, defaultAddressId, edit],
    );

    const initialDraftDefaults = useMemo(
        () => ({ ...draftDefaults, addressId: defaultAddressId }),
        [defaultAddressId],
    );

    /*
     * The project in progress, as a second form.
     *
     * A second form rather than a corner of the first because a draft is not part
     * of the request: a reader who has picked three stones and not yet said how
     * many is not an inquiry that fails to validate, they are a reader halfway
     * through a step, and mixing the two would make the whole request unsendable
     * for as long as they were still looking at it.
     */
    const draftMethods = useForm<
        InquiryDraftInput,
        unknown,
        InquiryDraftValues
    >({
        resolver: zodResolver(projectDraftSchema),
        mode: 'onTouched',
        reValidateMode: 'onChange',
        defaultValues: initialDraftDefaults,
        shouldUnregister: false,
    });

    const {
        setValue: setDraftValue,
        watch: watchDraft,
        getValues: getDraftValues,
    } = draftMethods;

    const draftStoneIds = watchDraft('stoneIds');
    const draftStoneAdvice = watchDraft('stoneAdvice');
    const draftBrands = watchDraft('brands');
    const draftBrandNoPreference = watchDraft('brandNoPreference');

    /*
     * Whether Continue has been pressed at all, once. A card is checked once there
     * is something to correct, so picking the first stone does not raise an
     * objection the reader has not caused yet.
     */
    const hasDraftContinued = useRef(false);
    const isSendingRef = useRef(false);

    const { can } = usePermissions();

    /*
     * Whose draft this is. The key is per account so two people sharing a
     * browser never read each other's half-built inquiry, which makes the
     * reader's id the one thing both the restore and the autosave need.
     */
    const userId = usePage<{ auth: Auth }>().props.auth.user.id;

    /**
     * Whether the restore has already happened.
     *
     * An effect runs twice under Strict Mode, and restoring twice would only
     * repeat the same work — but the notice about attachments the browser could
     * not keep would be said twice, which is why the guard exists.
     */
    const hasRestoredRef = useRef(false);

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), SKELETON_MS);

        return () => window.clearTimeout(timer);
    }, []);

    /*
     * Put the stored draft back, on mount and behind the skeleton.
     *
     * Deliberately not held until the skeleton has gone down: the point of
     * restoring while the placeholder still stands is that the reader never
     * sees a frame of the blank default where their answers belong — the
     * answers are already in place by the time the real page arrives.
     *
     * Nothing is validated on the way in, on the principle that a reader who
     * was not asked a question should not be told they got it wrong: whatever
     * the draft held comes back as it was, and the step it left off on is
     * checked against what is still there to do rather than believed outright.
     */
    useEffect(() => {
        if (hasRestoredRef.current) {
            return;
        }

        hasRestoredRef.current = true;

        /*
         * An edit starts from the inquiry as stored and never touches the new
         * inquiry's draft. `isRestored` stays false, which keeps the autosave
         * inert for the whole edit: a refresh starts the edit over from the
         * server rather than from anything kept in the browser.
         */
        if (edit !== null) {
            /*
             * The inquiry's own stones stay, even one whose type its service no
             * longer takes or that has left the catalogue since: the server
             * keeps them while the inquiry keeps its service. Choosing another
             * service takes off the ones it does not offer (chooseService).
             */
            reset({
                projects: toEditFormProjects(edit.projects, (address) =>
                    addressFromPayload(address as unknown as PhAddressPayload),
                ),
            });

            return;
        }

        const restore = (stored: StoredInquiryDraft) => {
            /*
             * The files come back empty because a `File` has no form storage
             * can hold; the count of them is what the notice below is about.
             */
            reset({
                projects: stored.projects.map((project) => ({
                    ...project,
                    attachments: [],
                })),
            });
            /*
             * The draft's saved address comes back while it is still in the
             * book; one deleted meanwhile, or none yet (a client back from
             * adding their first), is the default.
             */
            draftMethods.reset({
                ...stored.draft,
                addressId: knownAddressId(stored.draft.addressId),
                address: addresses === null ? stored.draft.address : null,
                attachments: [],
            });

            setEditingProjectId(
                stored.editingProjectId !== null &&
                    stored.projects.some(
                        (project) => project.id === stored.editingProjectId,
                    )
                    ? stored.editingProjectId
                    : null,
            );
            const progress = {
                ...stored.draft,
                requiresStones: requiresStones(stored.draft.serviceId),
            };

            setStep(
                clampRestoredStep(
                    stored.step,
                    stored.projects.length,
                    progress,
                ),
            );
            // Every question the draft's answers reach comes back on show.
            setShownQuestions(furthestQuestion(progress));
            hasDraftContinued.current = false;

            setLostAttachments(stored.droppedAttachments);
            setIsRestored(true);
        };

        const stored = readInquiryDraft(userId, catalog);

        if (stored === null) {
            setIsRestored(true);

            return;
        }

        const slugs = draftStoneSlugs(stored);

        if (slugs.length === 0) {
            restore(stored);

            return;
        }

        /*
         * The page only holds the stones it has been sent, so the draft's own are
         * asked for by slug. One the server no longer offers is left out, and if
         * the question fails the draft still comes back as it was.
         */
        let answered = false;
        /*
         * Asked for under the draft's service, so a stone its service no longer
         * offers comes back missing, and goes the way a stone no longer sold does.
         */
        const draftService =
            stored.draft.serviceId || stored.projects[0]?.serviceId || '';

        setIsResolvingDraft(true);
        router.reload({
            only: ['pickedStones'],
            data: { picked: slugs, stone_service: draftService },
            async: true,
            preserveUrl: true,
            onSuccess: (page) => {
                const found =
                    (page.props as unknown as InquirePageProps).pickedStones ??
                    [];
                const known = new Set(found.map(({ slug }) => slug));
                const dropped = stored.draft.stoneIds.filter(
                    (slug) => !known.has(slug),
                ).length;

                answered = true;
                rememberStones(found);
                restore(keepKnownStones(stored, known));

                if (dropped > 0 && draftService !== '') {
                    setStoneNotice(
                        removedStonesNotice(
                            [dropped === 1 ? '1 stone' : `${dropped} stones`],
                            serviceName(draftService),
                            dropped,
                        ),
                    );
                }
            },
            onHttpException: () => false,
            onFinish: () => {
                if (!answered) {
                    restore(stored);
                }

                setIsResolvingDraft(false);
            },
        });
    }, [
        userId,
        catalog,
        reset,
        draftMethods,
        edit,
        rememberStones,
        serviceName,
        knownAddressId,
        addresses,
    ]);

    /*
     * The draft's own life support: a write a couple of seconds after the last
     * change, flushed on the way out of a step and when the tab goes away.
     *
     * Inert until the restore above has run, for the reason that state is
     * there: the first thing either form holds is the blank default, and a
     * save of that would put the stored draft to death while its owner was
     * still on the skeleton.
     */
    const { discardDraft, saveDraftNow } = useInquiryDraftAutosave({
        userId,
        isRestored,
        step,
        editingProjectId,
        methods,
        draftMethods,
    });

    /*
     * A newly opened question is brought into view and takes focus, because the
     * page is long and the question that changed is the one the reader has to
     * read next. Review, and anything that is not a move between questions, goes
     * to the top. The result panel is left out: it arrives where the reader
     * pressed the button, and jumping would move it off screen.
     */
    const shownStepRef = useRef(step);

    useEffect(() => {
        if (result !== null) {
            return;
        }

        const hasMoved = shownStepRef.current !== step;
        shownStepRef.current = step;

        const question =
            hasMoved && step < REVIEW_STEP
                ? document.getElementById(inquiryQuestionId(step))
                : null;

        if (question === null) {
            window.scrollTo({ top: 0, behavior: 'smooth' });

            return;
        }

        question.scrollIntoView({ block: 'start', behavior: 'smooth' });
        question.querySelector('h2')?.focus({ preventScroll: true });
    }, [step, result]);

    const stoneTypeChoices = useMemo(
        () => stoneTypeOptions(stoneTypes),
        [stoneTypes],
    );
    const visibleStones = stonePage.data;

    /*
     * The Brand answer keeps the stones to the brands it chose: the Brand
     * filter offers only those, and with none of them picked asks for all of
     * them. No preference (or no answer yet) leaves every brand.
     */
    const allowedBrands = allowedStoneBrands(catalog.brands, {
        brands: draftBrands,
        brandNoPreference: draftBrandNoPreference,
    });
    const stoneBrandOptions = catalog.brands.filter(({ id }) =>
        allowedBrands.ids.includes(id),
    );

    const clearSearchTimer = () => {
        if (searchTimerRef.current !== null) {
            window.clearTimeout(searchTimerRef.current);
            searchTimerRef.current = null;
        }
    };

    /**
     * Ask the server for the first page under these filters, in place: the form
     * lives in this page's state, so unlike Our Collection it has to survive the
     * answer. They stay in the address, because each further page reads them there.
     */
    const loadStones = (query: Omit<StoneQuery, 'service'>) => {
        clearSearchTimer();

        const service = getDraftValues('serviceId');
        const url = new URL(window.location.href);

        requestedServiceRef.current = service;

        [...url.searchParams.keys()]
            .filter(
                (name) =>
                    [
                        'stone_service',
                        'stone_tab',
                        'stone_type',
                        'stone_search',
                        'page',
                    ].includes(name) || name.startsWith('stone_brands'),
            )
            .forEach((name) => url.searchParams.delete(name));
        Object.entries(
            stoneFilterQuery({
                ...query,
                // Never outside the Brand answer: with none picked, every brand it chose.
                brands: stoneBrandsQuery(
                    query.brands ?? brandFilter,
                    allowedBrands,
                ),
                service,
            }),
        ).forEach(([name, value]) =>
            Array.isArray(value)
                ? value.forEach((entry) =>
                      url.searchParams.append(`${name}[]`, entry),
                  )
                : url.searchParams.set(name, value),
        );

        /*
         * `stoneTypes` comes back with the stones because it is the service's:
         * it is no longer a once prop, so a later visit cannot show the types
         * of the service before.
         */
        router.get(`${url.pathname}${url.search}`, undefined, {
            only: ['stones', 'stoneFilters', 'stoneTypes'],
            reset: ['stones'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsLoadingStones(true),
            onFinish: () => setIsLoadingStones(false),
        });
    };

    /**
     * Back to stones to choose from: the search and the type first, and the tab
     * only when those were not what emptied the grid.
     */
    const clearStoneFilters = () => {
        const tab =
            search.trim() === '' &&
            stoneType === ALL_STONE_TYPES &&
            brandFilter.length === 0
                ? 'all'
                : filter;

        setFilter(tab);
        setSearch('');
        setStoneType(ALL_STONE_TYPES);
        setBrandFilter([]);
        loadStones({
            tab,
            stoneType: ALL_STONE_TYPES,
            search: '',
            brands: [],
        });
    };

    const changeBrandFilter = (brand: string | null) => {
        const next = toggleBrandFilter(
            brandFilter.filter((id) => allowedBrands.ids.includes(id)),
            brand,
            allowedBrands.ids,
        );

        setBrandFilter(next);
        loadStones({ tab: filter, stoneType, search, brands: next });
    };

    const changeTab = (tab: StoneFilter) => {
        setFilter(tab);
        loadStones({ tab, stoneType, search });
    };

    const changeStoneType = (value: string) => {
        setStoneType(value);
        loadStones({ tab: filter, stoneType: value, search });
    };

    /** Asked for once the reader stops typing, as on a brand's Our Collection. */
    const changeSearch = (value: string) => {
        setSearch(value);
        clearSearchTimer();
        searchTimerRef.current = window.setTimeout(
            () => loadStones({ tab: filter, stoneType, search: value }),
            300,
        );
    };

    const clearSearch = () => {
        setSearch('');
        loadStones({ tab: filter, stoneType, search: '' });
    };

    /*
     * A first visit, or a screen that crossed the tablet width, was answered at
     * the other page size. The cookie is set by now, so asking again corrects it.
     * If the browser refused the cookie, asking again would only repeat forever.
     */
    const gridPageSize = useGridPageSize();

    useEffect(() => {
        if (
            gridPageSize !== null &&
            gridPageSize !== stoneFilters.perPage &&
            gridPageSizeCookieIs(gridPageSize)
        ) {
            loadStones({ tab: filter, stoneType, search });
        }
        // Only a change of size asks again; the filters ask for themselves.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [gridPageSize, stoneFilters.perPage]);

    useEffect(() => clearSearchTimer, []);

    /*
     * The stones are the chosen service's. A new service, and a draft or an
     * edit put back with its own, asks for that service's first page, with the
     * search and the type started again; the tab is kept.
     */
    const draftServiceId = watchDraft('serviceId');
    const hasWatchedServiceRef = useRef(false);

    useEffect(() => {
        /*
         * Not on mount: the draft is still blank then, and the stones the page
         * was sent are the address's, which a restore or an edit corrects next.
         */
        if (!hasWatchedServiceRef.current) {
            hasWatchedServiceRef.current = true;

            return;
        }

        if (draftServiceId === requestedServiceRef.current) {
            return;
        }

        setSearch('');
        setStoneType(ALL_STONE_TYPES);
        loadStones({ tab: filter, stoneType: ALL_STONE_TYPES, search: '' });
        // Only a change of service asks again; the filters ask for themselves.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draftServiceId]);

    /*
     * A new Brand answer: the filter keeps only the brands still allowed, the
     * picked stones of the brands it now keeps out come off (said on step 2,
     * as a change of service says), and the stones are asked for again.
     */
    const allowedBrandsKey = allowedBrands.restricted
        ? allowedBrands.ids.join(',')
        : '';
    const hasWatchedBrandsRef = useRef(false);

    useEffect(() => {
        // Not on mount: the answer is still blank then, as with the service.
        if (!hasWatchedBrandsRef.current) {
            hasWatchedBrandsRef.current = true;

            return;
        }

        const kept = brandFilter.filter((id) => allowedBrands.ids.includes(id));
        const misfits = stonesOutsideBrands(
            getDraftValues('stoneIds'),
            catalog.stones,
            allowedBrands,
        );

        setBrandFilter(kept);

        if (misfits.length > 0) {
            const gone = new Set(misfits.map(({ slug }) => slug));

            setDraftValue(
                'stoneIds',
                getDraftValues('stoneIds').filter((id) => !gone.has(id)),
                { shouldDirty: true },
            );
            setStoneNotice(
                removedBrandStonesNotice(misfits.map(({ name }) => name)),
            );
        }

        if (requiresStones(getDraftValues('serviceId'))) {
            loadStones({ tab: filter, stoneType, search, brands: kept });
        }
        // Only a change of the answer asks again.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [allowedBrandsKey]);

    /*
     * A type the service does not list, from an address kept from another
     * service, is no filter: back to All Type.
     */
    useEffect(() => {
        if (
            stoneType !== ALL_STONE_TYPES &&
            !stoneTypes.some(({ value }) => value === stoneType)
        ) {
            setStoneType(ALL_STONE_TYPES);
            loadStones({ tab: filter, stoneType: ALL_STONE_TYPES, search });
        }
        // Only the service's list of types decides it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [stoneTypes]);

    const toggleIn = (chosen: string[], id: string): string[] =>
        chosen.includes(id)
            ? chosen.filter((entry) => entry !== id)
            : [...chosen, id];

    /** Another type offers other services, so the one chosen under this one goes. */
    const chooseServiceType = (serviceTypeId: string) => {
        setDraftValue('serviceTypeId', serviceTypeId, { shouldDirty: true });
        setDraftValue('serviceId', '', { shouldDirty: true });
        draftMethods.clearErrors('serviceId');
    };

    /**
     * A new service keeps the picked stones it offers and takes off the rest,
     * saying which on step 2. Its stones are then asked for (the effect on
     * `draftServiceId` above).
     */
    const chooseService = (serviceId: string) => {
        const misfits = catalog.stonesNotOfferedFor(
            getDraftValues('stoneIds'),
            serviceId,
        );

        const previous = findService(
            catalog.serviceTypes,
            getDraftValues('serviceId'),
        );
        const next = findService(catalog.serviceTypes, serviceId);
        const fulfillment = fulfillmentAfterServiceChange(
            {
                deliveryMethod: getDraftValues('deliveryMethod'),
                addressId: getDraftValues('addressId'),
                address: getDraftValues('address'),
            },
            previous,
            next,
        );

        setDraftValue('serviceId', serviceId, { shouldDirty: true });
        // What no longer applies to the new service goes: a method it does not ask.
        setDraftValue('deliveryMethod', fulfillment.deliveryMethod, {
            shouldDirty: true,
        });
        draftMethods.clearErrors(['serviceId', 'deliveryMethod']);

        // A service that asks for no stones takes none, and no recommendation either.
        if (!requiresStones(serviceId)) {
            setDraftValue('stoneIds', [], { shouldDirty: true });
            setDraftValue('stones', [], { shouldDirty: true });
            setDraftValue('stoneAdvice', false, { shouldDirty: true });
            draftMethods.clearErrors(['stoneIds', 'stones']);
            setStoneNotice(null);

            return;
        }

        if (misfits.length > 0) {
            const gone = new Set(misfits.map(({ slug }) => slug));

            setDraftValue(
                'stoneIds',
                getDraftValues('stoneIds').filter((id) => !gone.has(id)),
                { shouldDirty: true },
            );
            setStoneNotice(
                removedStonesNotice(
                    misfits.map(({ name }) => name),
                    serviceName(serviceId),
                ),
            );
        } else {
            setStoneNotice(null);
        }
    };

    /*
     * Opened from a furniture page's Inquire: the piece's brand, service and
     * table answers go into the draft once it has been restored, unless the
     * reader is already part way through one (a service picked), which is
     * theirs to finish. Once, and the piece leaves the URL so a refresh
     * doesn't pick it again.
     */
    const furniturePrefilled = useRef(false);

    useEffect(() => {
        if (
            furniturePrefilled.current ||
            !isRestored ||
            edit !== null ||
            furniturePrefill === null
        ) {
            return;
        }

        furniturePrefilled.current = true;

        const url = new URL(window.location.href);
        url.searchParams.delete('furniture');
        window.history.replaceState(window.history.state, '', url);

        const service = findService(
            catalog.serviceTypes,
            furniturePrefill.service,
        );

        if (
            service === undefined ||
            getDraftValues('serviceId') !== '' ||
            editingProjectId !== null
        ) {
            return;
        }

        const brandKnown = catalog.brands.some(
            ({ id }) => id === furniturePrefill.brand,
        );

        if (
            brandKnown &&
            getDraftValues('brands').length === 0 &&
            !getDraftValues('brandNoPreference')
        ) {
            setDraftValue('brands', [furniturePrefill.brand], {
                shouldDirty: true,
            });
        }

        setDraftValue('serviceTypeId', furniturePrefill.serviceType, {
            shouldDirty: true,
        });
        chooseService(furniturePrefill.service);
        setDraftValue(
            'answers',
            { ...getDraftValues('answers'), ...furniturePrefill.answers },
            { shouldDirty: true },
        );

        const draft = getDraftValues();

        setStep(BRAND_STEP);
        setShownQuestions(
            furthestQuestion({
                ...draft,
                requiresStones: requiresStones(draft.serviceId),
            }),
        );
    }, [
        isRestored,
        edit,
        furniturePrefill,
        catalog,
        editingProjectId,
        getDraftValues,
        setDraftValue,
        chooseService,
        requiresStones,
    ]);

    const setStones = (ids: string[]) => {
        setDraftValue('stoneIds', ids, {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        });
        setDraftValue('stoneAdvice', false, { shouldDirty: true });

        if (ids.length > 0) {
            draftMethods.clearErrors('stoneIds');
        }
    };

    const toggleStone = (id: string) => setStones(toggleIn(draftStoneIds, id));

    /** Every question is on one page, so moving to one is a scroll to it. */
    const showQuestion = (question: number) =>
        document
            .getElementById(inquiryQuestionId(question))
            ?.scrollIntoView({ block: 'start', behavior: 'smooth' });

    /**
     * Show a question that is not on the page yet, under the ones that are,
     * and take the reader to it; one already shown is only scrolled to.
     */
    const revealQuestion = (question: number) => {
        if (question <= shownQuestions) {
            showQuestion(question);

            return;
        }

        revealedQuestionRef.current = question;
        setShownQuestions(question);
    };

    /*
     * A question Next has just shown is brought into view, and its heading takes
     * focus, once it is on the page.
     */
    useEffect(() => {
        const question = revealedQuestionRef.current;

        if (question === null || question > shownQuestions) {
            return;
        }

        revealedQuestionRef.current = null;
        const section = document.getElementById(inquiryQuestionId(question));

        section?.scrollIntoView({ block: 'start', behavior: 'smooth' });
        section?.querySelector('h2')?.focus({ preventScroll: true });
    }, [shownQuestions]);

    /**
     * Next, under the last question shown: check that question alone, and keep
     * the reader on it with its complaint inline until it is answered; then
     * show the next one. The questions above stay as they are.
     */
    const goToNextQuestion = (question: number) => {
        const draft = getDraftValues();
        const takesStones = requiresStones(draft.serviceId);
        const problem = questionProblem(
            question,
            { ...draft, requiresStones: takesStones },
            offeredServiceIds,
        );

        if (problem !== null) {
            draftMethods.setError(problem.field, {
                type: 'manual',
                message: problem.message,
            });

            return;
        }

        draftMethods.clearErrors(
            question === BRAND_STEP
                ? 'brands'
                : question === SERVICE_STEP
                  ? 'serviceId'
                  : 'stoneIds',
        );
        revealQuestion(nextQuestion(question, takesStones));
    };

    /**
     * The reader does not know which stone yet, so the team will recommend one.
     * Nothing else about the stones needs answering, so the details are shown
     * and taken to, as Next would.
     */ const askForStoneAdvice = () => {
        setDraftValue('stoneIds', [], { shouldDirty: true });
        setDraftValue('stones', [], { shouldDirty: true });
        setDraftValue('stoneAdvice', true, { shouldDirty: true });
        draftMethods.clearErrors('stoneIds');
        setStoneNotice(null);
        revealQuestion(REQUIREMENTS_STEP);
    };

    /**
     * Empty the draft, whatever part of it is open.
     *
     * `editingProjectId` goes with the answers because it says which committed
     * project the next commit will replace; left standing, cancelling a new
     * project would quietly make the next one overwrite an existing one.
     */
    const clearDraft = () => {
        draftMethods.reset(initialDraftDefaults);
        hasDraftContinued.current = false;
        setEditingProjectId(null);
        setStoneNotice(null);
        setShownQuestions(BRAND_STEP);
    };

    /**
     * Stop changing the project, from the strip above the questions. Back to
     * Review, which is the only screen that shows it.
     */
    const cancelExcursion = () => {
        clearDraft();
        setStep(REVIEW_STEP);
    };

    /**
     * One committed project, reopened in the requirements step, or at
     * `startAt` when an edit opens it from the beginning.
     *
     * The stones come back both as the step 2 picks and as their specifications,
     * because those are two views of one answer: the reader can walk back to the
     * grid and add or remove a material without losing the counts of the ones that
     * stay.
     *
     * The name, the site and the date come back with them, because those are the
     * answers a draft that opened without them would ask all over again, and a
     * reader who has opened a project to correct one thing should not have to
     * retype the rest to do it.
     */
    const openProject = (id: string, startAt: number = REQUIREMENTS_STEP) => {
        const project = getValues('projects').find((entry) => entry.id === id);

        if (project === undefined) {
            return;
        }

        draftMethods.reset({
            name: project.name,
            serviceTypeId: project.serviceTypeId,
            serviceId: project.serviceId,
            stoneAdvice: project.stoneAdvice,
            // Once each: a stone added again with another finish has two rows.
            stoneIds: [
                ...new Set(
                    project.stones.map((selection) => selection.stoneId),
                ),
            ],
            stones: project.stones.map((selection) => ({ ...selection })),
            answers: { ...project.answers },
            brands: [...project.brands],
            brandNoPreference: project.brandNoPreference,
            deliveryMethod: project.deliveryMethod,
            addressId: knownAddressId(project.addressId),
            address: project.address === null ? null : { ...project.address },
            neededBy: project.neededBy,
            notes: project.notes,
            attachments: project.attachments.map((attachment) => ({
                ...attachment,
            })),
        });
        hasDraftContinued.current = false;
        setEditingProjectId(id);
        setStep(startAt);
        // A committed project has every question answered, so all are shown.
        setShownQuestions(REQUIREMENTS_STEP);
    };

    /*
     * An inquiry is one project, so an edit skips Review and opens that project
     * at the first step, with every answer already filled in. It runs once, after
     * the restore effect above has put the stored inquiry into the form, and
     * before the skeleton comes down. Review is still where the change is saved.
     */
    const hasOpenedEditRef = useRef(false);

    useEffect(() => {
        if (edit === null || hasOpenedEditRef.current) {
            return;
        }

        const [project] = getValues('projects');

        if (project === undefined) {
            return;
        }

        hasOpenedEditRef.current = true;
        openProject(project.id, BRAND_STEP);
    });

    /**
     * Give the draft a specification row per chosen stone, keeping duplicate rows
     * and their answers intact, and ordering by the current picks.
     *
     * Rows are made here rather than at step 2 because a count is only asked for
     * once the reader knows they are staying on this step: a material added and
     * removed in the grid never has to be given a count at all.
     *
     * Every new row takes the "advise me" answers, so the reader is only asked
     * about edge and finish if they want to be.
     */
    const seedDraftStones = () => {
        const advisory =
            catalog.advisoryEdge !== null && catalog.advisoryFinish !== null
                ? {
                      edge: catalog.advisoryEdge,
                      finish: catalog.advisoryFinish,
                  }
                : null;

        setDraftValue(
            'stones',
            seedStoneRows(
                getDraftValues('stoneIds'),
                getDraftValues('stones'),
                advisory,
            ),
        );
    };

    /*
     * Every question is on one page, so a stone gets its row in the details the
     * moment it is picked, and loses it the moment it is not.
     */
    useEffect(() => {
        seedDraftStones();
        // Only a change of stones asks for it.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draftStoneIds, draftStoneAdvice]);

    /** What a project is called unless the reader names it on Review: its service. */
    const projectNameFor = (serviceId: string): string =>
        autoProjectName(
            catalog.serviceTypes
                .flatMap(({ services }) => services)
                .find(({ id }) => id === serviceId)?.name ?? '',
            PROJECT_NAME_MAX,
        );

    /**
     * Name the draft before it is checked. A project being reopened keeps a name
     * its reader gave it; one still carrying its automatic name is named again,
     * so that name keeps describing what the project now is.
     */
    const nameDraft = () => {
        const reopened = getValues('projects').find(
            (project) => project.id === editingProjectId,
        );
        const hasOwnName =
            reopened !== undefined &&
            reopened.name !== projectNameFor(reopened.serviceId);

        setDraftValue(
            'name',
            hasOwnName
                ? reopened.name
                : projectNameFor(getDraftValues('serviceId')),
        );
    };

    /**
     * The draft, checked whole and then committed.
     *
     * Checked against the draft's own schema rather than against any one step's,
     * because this is the one press that takes all the steps' answers together
     * and puts them on the inquiry.
     *
     */
    const commitDraft = async () => {
        hasDraftContinued.current = true;

        seedDraftStones();
        nameDraft();

        /*
         * `handleSubmit` rather than `trigger`, because it is what makes a refused
         * field re-check as it changes. With `trigger`, a complaint stayed until
         * the field lost focus, which is the press of this button: it went between
         * the press and the release, moved the button, and the press did nothing.
         */
        let valid = false;

        await draftMethods.handleSubmit(
            () => {
                valid = true;
            },
            /*
             * A complaint about another question has no field on this one, so
             * the reader is taken to where it can be answered.
             */
            (errors) => {
                if (errors.brands !== undefined) {
                    showQuestion(BRAND_STEP);
                    document.getElementById(INQUIRY_BRANDS_ID)?.focus();
                } else if (errors.serviceId !== undefined) {
                    showQuestion(SERVICE_STEP);
                } else if (errors.stoneIds !== undefined) {
                    showQuestion(STONES_STEP);
                } else if (errors.deliveryMethod !== undefined) {
                    document
                        .getElementById(INQUIRY_DELIVERY_METHOD_ID)
                        ?.scrollIntoView({
                            block: 'center',
                            behavior: 'smooth',
                        });
                } else if (
                    errors.addressId !== undefined ||
                    errors.address !== undefined
                ) {
                    document
                        .getElementById(INQUIRY_ADDRESS_ID)
                        ?.scrollIntoView({
                            block: 'center',
                            behavior: 'smooth',
                        });
                }
            },
        )();

        if (!valid) {
            return;
        }

        const draft = getDraftValues();

        const project = buildCommittedProject(draft, {
            id: editingProjectId ?? newProjectId(),
        });

        /*
         * An edit puts the project back where it was rather than at the end of
         * the inquiry: it is the same project as it was, and a sheet that
         * renumbered it every time it was changed would make the positions on it
         * mean nothing.
         */
        const committed =
            editingProjectId === null
                ? [...getValues('projects'), project]
                : getValues('projects').map((entry) =>
                      entry.id === editingProjectId ? project : entry,
                  );

        setValue('projects', committed, { shouldDirty: true });

        setEditingProjectId(null);

        clearDraft();
        setStep(REVIEW_STEP);
    };

    /**
     * Check the whole form, and only then send it.
     *
     * The step-by-step checks cannot stand in for this: the reader can reach back
     * and change an earlier step, so what is about to leave the browser is the
     * whole form, and the whole form is what has to hold. `safeParse` is what
     * says so; `trigger` alongside it is what leaves the complaints on the
     * individual fields for whoever goes back to fix them.
     *
     * Nothing is sent and no panel appears when it fails — this is a fixable
     * answer, not a failure to report — so the reader is left looking at the
     * sheet, with the reasons above it and beside the project each one is about.
     */
    const requestQuote = async () => {
        if (isSendingRef.current) {
            return;
        }

        const values = getValues();

        /*
         * The schema has one rule the form's own field-level messages cannot
         * cover: a file still in flight has no control to point at. It is read
         * from the values being sent rather than re-read from the list, so what
         * is checked is exactly what would have gone out.
         */
        const waitingOn = values.projects.filter((project) =>
            project.attachments.some(
                (attachment) => attachment.status === 'uploading',
            ),
        );

        if (waitingOn.length > 0) {
            /*
             * Said against the projects rather than against the sheet: the file is
             * on one of them, and a reader who has ten projects would otherwise be
             * told to wait without being told which one to wait for.
             */
            setProjectMessages((messages) => ({
                ...messages,
                ...Object.fromEntries(
                    waitingOn.map((project) => [
                        project.id,
                        WAITING_FOR_UPLOADS,
                    ]),
                ),
            }));
            setValidationAlertErrors([WAITING_FOR_UPLOADS]);

            return;
        }

        const parsed = inquirySchema.safeParse(values);

        const valid = await trigger();

        if (!parsed.success || !valid) {
            const issues = parsed.success ? [] : parsed.error.issues;
            const perSection: Partial<Record<number, string>> = {};
            const perProject: Record<string, string> = {};

            /*
             * One short line per section and one per project, each taken from the
             * first complaint about that section or that project. The whole list is
             * said once at the top; repeating every rule under every heading would
             * turn a sheet into a form again.
             *
             * A project is found by the index the complaint carries, read against
             * the same list that is about to be sent, so a complaint about a
             * project that has since been removed falls away rather than being
             * put on whichever one took its place.
             */
            for (const issue of issues) {
                const field = String(issue.path[0] ?? '');
                const owner = STEP_OF_FIELD.get(field);

                if (owner === undefined) {
                    continue;
                }

                const index = issue.path[1];
                const project =
                    typeof index === 'number'
                        ? values.projects[index]
                        : undefined;

                if (project !== undefined) {
                    if (perProject[project.id] === undefined) {
                        perProject[project.id] = issue.message;
                    }
                } else if (perSection[owner] === undefined) {
                    perSection[owner] = issue.message;
                }
            }

            setSectionMessages(perSection);
            setProjectMessages(perProject);
            setValidationAlertErrors(issues.map((issue) => issue.message));

            return;
        }

        setSectionMessages({});
        setProjectMessages({});
        setValidationAlertErrors([]);

        await (edit === null ? sendInquiry() : saveChanges());
    };

    /**
     * The send itself, and what each outcome leaves behind.
     *
     * `isSendingRef` is what guards the double click: the state is what disables
     * the buttons, and by the time it has re-rendered a second click has already
     * been dispatched.
     *
     * On success the form is emptied before the panel is set, in the same tick,
     * so the reader never sees a frame of the old answers under the new heading.
     * The panel names the request by what the backend says it is, not by what the
     * form held.
     *
     * When the backend refuses the answers, the reader stays on the sheet with
     * each complaint beside the project it is about. On any other failure nothing
     * is touched at all — that promise is the reason the panel offers a retry
     * rather than making the reader rebuild the request.
     */
    const sendInquiry = async () => {
        if (isSendingRef.current) {
            return;
        }

        isSendingRef.current = true;
        setIsSending(true);

        const outcome: SendOutcome =
            inquireStoreUrl === null
                ? { kind: 'failed' }
                : await sendInquiryRequest(
                      inquireStoreUrl,
                      getValues(),
                      catalog.serviceTypes,
                      'post',
                      addressSource,
                  );

        isSendingRef.current = false;
        setIsSending(false);

        if (outcome.kind === 'invalid') {
            const mapped = mapInquiryErrors(
                outcome.errors,
                getValues('projects'),
            );

            setProjectMessages(mapped.projectMessages);
            setSectionMessages(mapped.sectionMessages);
            setValidationAlertErrors(mapped.alertErrors);
            setResult(null);

            return;
        }

        if (outcome.kind === 'failed' || outcome.flash?.status !== 'success') {
            setResult({
                status: 'error',
                message:
                    outcome.kind === 'flashed' &&
                    outcome.flash?.status === 'error'
                        ? outcome.flash.message
                        : outcome.kind === 'failed'
                          ? outcome.rateLimit?.message
                          : undefined,
                retryAt:
                    outcome.kind === 'failed'
                        ? outcome.rateLimit?.retryAt
                        : undefined,
            });

            return;
        }

        const { title, reference } = outcome.flash;

        /*
         * A fresh inquiry starts empty rather than from the request just sent.
         * The draft goes with it: a half-built project is the one thing that
         * must not survive into the next inquiry.
         */
        reset(inquiryDefaults);

        clearDraft();

        /*
         * The draft is no longer a draft once it has been sent. Forgetting it
         * here — with any write still waiting behind it cancelled — is what
         * keeps the sent request from being resurrected by the autosave a
         * moment later, and what stops the next visit offering to continue it.
         */
        discardDraft();
        setSearch('');
        setFilter('all');
        setStoneType(ALL_STONE_TYPES);
        loadStones({
            tab: 'all',
            stoneType: ALL_STONE_TYPES,
            search: '',
            brands: [],
        });
        setValidationAlertErrors([]);
        setSectionMessages({});
        setProjectMessages({});
        setBrandFilter([]);
        setStep(BRAND_STEP);
        setResult({ status: 'success', title, reference });
    };

    /**
     * Save the edit of a sent inquiry.
     *
     * On success the server redirects to the inquiry's details page with a
     * toast, so there is nothing left to do here. Refused answers come back
     * against their projects as they do for a new inquiry; anything else keeps
     * the reader on the sheet with their changes, to try again.
     */
    const saveChanges = async () => {
        if (edit === null || isSendingRef.current) {
            return;
        }

        isSendingRef.current = true;
        setIsSending(true);

        const outcome = await sendInquiryRequest(
            edit.updateUrl,
            getValues(),
            catalog.serviceTypes,
            'patch',
            addressSource,
        );

        isSendingRef.current = false;
        setIsSending(false);

        if (outcome.kind === 'invalid') {
            const mapped = mapInquiryErrors(
                outcome.errors,
                getValues('projects'),
            );

            setProjectMessages(mapped.projectMessages);
            setSectionMessages(mapped.sectionMessages);
            setValidationAlertErrors(mapped.alertErrors);

            return;
        }

        if (outcome.kind === 'failed') {
            toast.error(
                outcome.rateLimit?.message ??
                    'Your changes could not be saved. Try again.',
            );
        }
    };

    /**
     * Start again on a blank form. The form was already emptied when the request
     * was sent, so this is a clean start with nothing carried over.
     */
    const inquireAgain = () => {
        setResult(null);
        setStep(BRAND_STEP);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    if (isLoading || isResolvingDraft) {
        return <InquireSkeleton />;
    }

    const hasSelection = draftStoneIds.length > 0;

    /*
     * Where the details question's address comes from. "Manage addresses"
     * saves the draft first: the address book sends the client back here,
     * where it is restored.
     */
    const addressContext: InquiryAddressContextValue = {
        source: addressSource,
        addresses: addresses ?? [],
        heldAddress:
            edit !== null && addresses !== null
                ? ((edit.projects[0]?.address ??
                      null) as PhAddressPayload | null)
                : null,
        onManageAddresses: () => {
            saveDraftNow();
            router.visit(profileAddresses.url({ query: { from: 'inquire' } }));
        },
    };
    const canChat = can(permissions.messagesView);
    const canSeeQuotation = can(permissions.quotationsView);
    const canSeeInquiry = can(permissions.inquiriesView);

    /*
     * Whichever list this reader actually has open, so the panel's fallback
     * button is never a link to a page that would refuse them.
     */
    const inquiriesHref = canSeeQuotation
        ? quoteIndex.url()
        : canSeeInquiry
          ? inquiryIndex.url()
          : null;

    const stoneTabs: Array<{ value: StoneFilter; label: string }> = [
        { value: 'all', label: 'All' },
        { value: 'saved', label: 'Saved' },
        { value: 'bestSellers', label: 'Best Seller' },
    ];

    /*
     * Which project the open draft is going to be, in the words steps 1 and 2
     * need. A draft over an existing project says so rather than counting up to
     * a number it is not going to take.
     */
    const editingProjectNumber =
        editingProjectId === null
            ? -1
            : projects.findIndex((project) => project.id === editingProjectId) +
              1;

    /*
     * Only when coming back from Review, where Cancel returns to. An edit of a
     * sent inquiry starts here instead, and has its own way back at the top.
     */
    const showDraftBanner = edit === null && editingProjectNumber > 0;

    /*
     * The questions this draft is asked, in order, all on the first step. A
     * service that asks for no stones skips the stones question, so the
     * details are its second.
     */
    const draftTakesStones = requiresStones(draftServiceId);
    const questions = draftTakesStones
        ? [BRAND_STEP, SERVICE_STEP, STONES_STEP, REQUIREMENTS_STEP]
        : [BRAND_STEP, SERVICE_STEP, REQUIREMENTS_STEP];

    /**
     * The questions shown so far, every one open with all its fields; the rest
     * wait for Next.
     */
    const question = (questionStep: number) =>
        questionStep > shownQuestions || !questions.includes(questionStep)
            ? null
            : {
                  step: questionStep,
                  number: questions.indexOf(questionStep) + 1,
                  isOpen: true,
                  onChange: () => undefined,
              };

    const brandQuestion = question(BRAND_STEP);
    const serviceQuestion = question(SERVICE_STEP);
    const stonesQuestion = question(STONES_STEP);
    const detailsQuestion = question(REQUIREMENTS_STEP);

    return (
        <FormProvider {...methods}>
            <InquiryAddressContext.Provider value={addressContext}>
                <InquiryActionBarSlot.Provider value={actionBarSlot}>
                    <div className={FULL_SCREEN_PAGE}>
                        <InquireHeading
                            showSaved={edit === null && step !== REVIEW_STEP}
                            title={
                                edit === null
                                    ? 'Inquire'
                                    : `Update ${edit.reference}`
                            }
                            description={
                                edit !== null && step === REVIEW_STEP
                                    ? 'Choose a project to edit, then save your changes'
                                    : step === REVIEW_STEP
                                      ? REVIEW_DESCRIPTION
                                      : REQUEST_DESCRIPTION
                            }
                            action={
                                edit === null ? (
                                    target !== null && onChangeTarget ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className="text-sm"
                                                data-testid="inquire-target"
                                            >
                                                For{' '}
                                                <span className="font-bold">
                                                    {target.name}
                                                </span>{' '}
                                                · {target.detail}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={onChangeTarget}
                                            >
                                                Change client
                                            </Button>
                                        </div>
                                    ) : undefined
                                ) : (
                                    <Button asChild variant="outline">
                                        {/* Back to the list the edit was started from. */}
                                        <Link href={edit.back.url}>
                                            {edit.back.label}
                                        </Link>
                                    </Button>
                                )
                            }
                        />

                        <InquiryStepper
                            steps={STEPS}
                            current={stepperPosition(step)}
                        />

                        {step === REVIEW_STEP ? (
                            <InquiryReview
                                onEditProject={openProject}
                                sectionMessages={sectionMessages}
                                projectMessages={projectMessages}
                                validationErrors={validationAlertErrors}
                                locked={isSending}
                                onRequestQuote={requestQuote}
                                mode={edit === null ? 'create' : 'edit'}
                                addressLineFor={(project) =>
                                    projectAddressLine(
                                        addressContext,
                                        project,
                                        addressValueLine,
                                    )
                                }
                            />
                        ) : (
                            /*
                             * Steps 1 to 3 with a project open all read the draft form, so
                             * they sit inside its own provider. Shadowing the inquiry's
                             * provider for this subtree is the point: neither can reach an
                             * answer the other owns.
                             */
                            <FormProvider {...draftMethods}>
                                {showDraftBanner ? (
                                    <ProjectBanner
                                        label="Changing your request"
                                        onCancel={cancelExcursion}
                                    />
                                ) : null}

                                {brandQuestion !== null ? (
                                    <InquiryQuestion
                                        {...brandQuestion}
                                        title="Which brands would you like?"
                                        summary=""
                                    >
                                        <InquiryBrandQuestion />
                                        {shownQuestions === BRAND_STEP ? (
                                            <NextBar
                                                nextTitle="What do you need?"
                                                onNext={() =>
                                                    goToNextQuestion(BRAND_STEP)
                                                }
                                            />
                                        ) : null}
                                    </InquiryQuestion>
                                ) : null}

                                {serviceQuestion !== null ? (
                                    <InquiryQuestion
                                        {...serviceQuestion}
                                        title="What do you need?"
                                        summary=""
                                    >
                                        <InquiryServiceQuestion
                                            onServiceType={chooseServiceType}
                                            onService={chooseService}
                                        />
                                        {shownQuestions === SERVICE_STEP ? (
                                            <NextBar
                                                nextTitle={
                                                    draftTakesStones
                                                        ? 'Which stones?'
                                                        : 'Other details'
                                                }
                                                onNext={() =>
                                                    goToNextQuestion(
                                                        SERVICE_STEP,
                                                    )
                                                }
                                            />
                                        ) : null}
                                    </InquiryQuestion>
                                ) : null}

                                {stonesQuestion !== null ? (
                                    <InquiryQuestion
                                        {...stonesQuestion}
                                        title="Which stones?"
                                        summary=""
                                    >
                                        <div className="mb-5 flex flex-col gap-3 border border-dashed border-foreground/30 py-3 pr-3.5 pl-4.5 sm:flex-row sm:items-center sm:justify-between">
                                            <div className="min-w-0">
                                                <p className="text-sm font-semibold">
                                                    Not sure which stone yet?
                                                </p>
                                                <p className="mt-0.5 text-[0.8125rem] text-muted-foreground">
                                                    Our team will recommend one
                                                    for you.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant={
                                                    draftStoneAdvice
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                onClick={askForStoneAdvice}
                                                aria-pressed={draftStoneAdvice}
                                                className="h-10 w-full shrink-0 rounded-none border-foreground text-xs font-semibold tracking-[0.08em] uppercase sm:w-auto"
                                            >
                                                Help me choose
                                            </Button>
                                        </div>

                                        <div className="mb-6 border-y border-border py-3.5">
                                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                                <div className="relative w-full sm:max-w-xs">
                                                    <Search
                                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                                                        aria-hidden
                                                    />
                                                    <Input
                                                        type="search"
                                                        value={search}
                                                        onChange={(event) =>
                                                            changeSearch(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        placeholder="Search stones"
                                                        aria-label="Search stones"
                                                        className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                                                    />
                                                    {search ? (
                                                        <button
                                                            type="button"
                                                            onClick={
                                                                clearSearch
                                                            }
                                                            aria-label="Clear search"
                                                            className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                        >
                                                            <X
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                        </button>
                                                    ) : null}
                                                </div>

                                                <div
                                                    className="hidden shrink-0 items-center gap-1.5 whitespace-nowrap sm:flex"
                                                    role="tablist"
                                                    aria-label="Filter stones"
                                                >
                                                    {stoneTabs.map((tab) => {
                                                        const isActive =
                                                            filter ===
                                                            tab.value;

                                                        return (
                                                            <button
                                                                key={tab.value}
                                                                type="button"
                                                                role="tab"
                                                                aria-selected={
                                                                    isActive
                                                                }
                                                                onClick={() =>
                                                                    changeTab(
                                                                        tab.value,
                                                                    )
                                                                }
                                                                className={`h-11 cursor-pointer border px-3.5 text-[0.8125rem] tracking-[0.04em] uppercase transition-colors ${
                                                                    isActive
                                                                        ? 'border-foreground bg-foreground text-background'
                                                                        : 'border-border bg-background text-foreground hover:border-foreground'
                                                                }`}
                                                            >
                                                                {tab.label}
                                                            </button>
                                                        );
                                                    })}
                                                </div>

                                                <div
                                                    className="flex items-center gap-2.5 sm:hidden"
                                                    role="tablist"
                                                    aria-label="Filter stones"
                                                >
                                                    {stoneTabs.map((tab) => {
                                                        const isActive =
                                                            filter ===
                                                            tab.value;

                                                        return (
                                                            <Button
                                                                key={tab.value}
                                                                type="button"
                                                                role="tab"
                                                                aria-selected={
                                                                    isActive
                                                                }
                                                                variant={
                                                                    isActive
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                                size="sm"
                                                                className="px-3 py-1.5"
                                                                onClick={() =>
                                                                    changeTab(
                                                                        tab.value,
                                                                    )
                                                                }
                                                            >
                                                                {tab.label}
                                                            </Button>
                                                        );
                                                    })}
                                                </div>

                                                {stoneBrandOptions.length >
                                                0 ? (
                                                    <StoneBrandFilter
                                                        brands={
                                                            stoneBrandOptions
                                                        }
                                                        value={brandFilter.filter(
                                                            (id) =>
                                                                allowedBrands.ids.includes(
                                                                    id,
                                                                ),
                                                        )}
                                                        restricted={
                                                            allowedBrands.restricted
                                                        }
                                                        onChange={
                                                            changeBrandFilter
                                                        }
                                                    />
                                                ) : null}

                                                {/* The service's own types; with none there is nothing to filter by. */}
                                                {stoneTypes.length > 0 ? (
                                                    <StoneTypeSelect
                                                        options={
                                                            stoneTypeChoices
                                                        }
                                                        value={stoneType}
                                                        onChange={
                                                            changeStoneType
                                                        }
                                                    />
                                                ) : null}
                                            </div>

                                            {stoneTypes.length > 0 ? (
                                                <StoneTypePills
                                                    options={stoneTypeChoices}
                                                    value={stoneType}
                                                    onChange={changeStoneType}
                                                />
                                            ) : null}
                                        </div>

                                        {stoneNotice !== null ? (
                                            <p
                                                role="status"
                                                className="mb-4 rounded-md border border-sidebar-border bg-secondary px-3 py-2 text-sm"
                                                data-testid="stone-notice"
                                            >
                                                {stoneNotice}
                                            </p>
                                        ) : null}

                                        {/*
                                         * What is picked so far, and why Continue was
                                         * refused if it was: a grid of cards is not a
                                         * field, so there is no control to say it on.
                                         */}
                                        <div className="mb-4 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                                            <p
                                                aria-live="polite"
                                                className="text-sm font-bold text-foreground"
                                                data-testid="stones-selected"
                                            >
                                                {draftStoneIds.length}{' '}
                                                {draftStoneIds.length === 1
                                                    ? 'stone'
                                                    : 'stones'}{' '}
                                                selected
                                            </p>
                                            {hasSelection ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setStones([])
                                                    }
                                                    className="h-7 cursor-pointer px-1.5 text-xs"
                                                >
                                                    Clear
                                                </Button>
                                            ) : null}
                                            <InputError
                                                message={fieldMessage(
                                                    draftMethods.formState
                                                        .errors.stoneIds,
                                                )}
                                                role="alert"
                                            />
                                        </div>

                                        {isLoadingStones ? (
                                            <StoneGridSkeleton />
                                        ) : draftServiceId === '' ? (
                                            <EmptyState
                                                title="Choose what you need first"
                                                description="The stones for that service will show here."
                                            />
                                        ) : filter === 'saved' &&
                                          search.trim() === '' &&
                                          stoneType === ALL_STONE_TYPES &&
                                          visibleStones.length === 0 ? (
                                            <EmptyState
                                                title="You haven't saved any stones yet"
                                                description="Save a stone from a brand's collection and it will show up here."
                                                action={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            changeTab('all')
                                                        }
                                                    >
                                                        Browse all stones
                                                    </Button>
                                                }
                                            />
                                        ) : visibleStones.length === 0 &&
                                          allowedBrands.restricted &&
                                          brandFilter.length === 0 &&
                                          search.trim() === '' &&
                                          stoneType === ALL_STONE_TYPES ? (
                                            <EmptyState
                                                title="No stones from the brands you chose"
                                                description="None of them has a stone for this service. Choose other brands above, or press Help me choose and Regray will recommend one."
                                            />
                                        ) : visibleStones.length === 0 &&
                                          brandFilter.length > 0 ? (
                                            <EmptyState
                                                title="No stones from these brands"
                                                description="None of the chosen brands has a stone for this service under these filters."
                                                action={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={
                                                            clearStoneFilters
                                                        }
                                                    >
                                                        Clear filters
                                                    </Button>
                                                }
                                            />
                                        ) : visibleStones.length === 0 &&
                                          stoneTypes.length === 0 ? (
                                            <EmptyState
                                                title="No stones listed for this service yet"
                                                description="Press Help me choose and Regray will recommend one."
                                            />
                                        ) : visibleStones.length === 0 ? (
                                            <EmptyState
                                                title="No stones found"
                                                description="No stone matches these filters."
                                                action={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={
                                                            clearStoneFilters
                                                        }
                                                    >
                                                        Clear filters
                                                    </Button>
                                                }
                                            />
                                        ) : (
                                            <InquiryStoneGrid
                                                stones={visibleStones}
                                                selectedIds={draftStoneIds}
                                                onToggle={toggleStone}
                                            />
                                        )}

                                        {shownQuestions === STONES_STEP ? (
                                            <NextBar
                                                nextTitle="Other details"
                                                onNext={() =>
                                                    goToNextQuestion(
                                                        STONES_STEP,
                                                    )
                                                }
                                            />
                                        ) : null}
                                    </InquiryQuestion>
                                ) : null}

                                {detailsQuestion !== null ? (
                                    <InquiryQuestion
                                        {...detailsQuestion}
                                        title="Other details"
                                        summary=""
                                    >
                                        <InquiryProjectForm
                                            onChooseStones={() =>
                                                showQuestion(STONES_STEP)
                                            }
                                            projectNumber={
                                                editingProjectNumber > 0
                                                    ? editingProjectNumber
                                                    : projects.length + 1
                                            }
                                            allowAttachments={
                                                target === null &&
                                                (edit?.allowAttachments ?? true)
                                            }
                                            onCommit={commitDraft}
                                            deadlineLockReason={
                                                editingProjectId === null
                                                    ? null
                                                    : (lockedDeadlines[
                                                          editingProjectId
                                                      ] ?? null)
                                            }
                                        />
                                    </InquiryQuestion>
                                ) : null}
                            </FormProvider>
                        )}

                        {/* The gap above the bar, and what pushes it down the screen. */}
                        <div aria-hidden className="min-h-6 flex-1" />
                        <div ref={setActionBarSlot} className={BOTTOM_BAR} />
                    </div>
                </InquiryActionBarSlot.Provider>

                <InquiryResult
                    result={result}
                    canChat={canChat}
                    inquiriesHref={inquiriesHref}
                    onTryAgain={sendInquiry}
                    onClose={() => setResult(null)}
                    onInquireAgain={inquireAgain}
                    submitting={isSending}
                />

                <ConfirmDialog
                    open={lostAttachments > 0}
                    onOpenChange={() => setLostAttachments(0)}
                    title="Your draft is back"
                    description={
                        lostAttachments === 1
                            ? 'Its attachment could not be saved here. Attach the file again.'
                            : `${lostAttachments} attachments could not be saved here. Attach the files again.`
                    }
                    confirmLabel="OK"
                    onConfirm={() => setLostAttachments(0)}
                />
            </InquiryAddressContext.Provider>
        </FormProvider>
    );
};

const Inquire = (props: InquirePageProps) => {
    const {
        stones,
        mode,
        reference,
        projects,
        editor = 'client',
        leads = null,
        lead: leadInUrl = null,
        walkInOwners = null,
    } = props;

    /*
     * Staff say who the inquiry is for first: the branch (`?kind=`, Platform or
     * Direct), then the client and, on Platform, the lead (`?lead=`). The URL
     * keeps both, so a reload, or the Leads page's "Create inquiry", lands past
     * what was already answered. A direct client is not in the URL: it is taken
     * down again after a reload, and the duplicate check finds it.
     *
     * Staff who may not use Platform (sales) are never asked: they are always
     * on Direct, whatever the URL says.
     */
    const { can } = usePermissions();
    const canUsePlatform = can(permissions.inquiriesCreatePlatform);
    const [kind, setKind] = useState<StaffInquireKind | null>(() =>
        staffInquireKindFor(
            leadInUrl !== null
                ? 'platform'
                : staffInquireKindFrom(
                      typeof window === 'undefined'
                          ? null
                          : new URLSearchParams(window.location.search).get(
                                'kind',
                            ),
                  ),
            canUsePlatform,
        ),
    );
    const [target, setTarget] = useState<StaffInquireTarget | null>(() => {
        const lead = leads?.find((option) => option.reference === leadInUrl);

        return lead === undefined ? null : leadTarget(lead);
    });

    const keepInUrl = (
        nextKind: StaffInquireKind | null,
        lead: string | null,
    ) => {
        const url = new URL(window.location.href);

        url.searchParams.delete('kind');
        url.searchParams.delete('lead');

        if (nextKind !== null) {
            url.searchParams.set('kind', nextKind);
        }

        if (lead !== null) {
            url.searchParams.set('lead', lead);
        }

        window.history.replaceState(window.history.state, '', url);
    };

    const chooseKind = (requested: StaffInquireKind | null) => {
        const next = staffInquireKindFor(requested, canUsePlatform);

        setKind(next);
        setTarget(null);
        keepInUrl(next, null);
    };

    const chooseTarget = (next: StaffInquireTarget) => {
        setTarget(next);
        keepInUrl(next.kind, next.kind === 'platform' ? next.detail : null);
    };

    /*
     * Every stone the page has been sent, under whichever filter: a stone stays
     * picked, and has to be drawn on Review, after the page it came on has gone.
     */
    const [knownStones, setKnownStones] = useState(
        () =>
            new Map(
                (projects ?? []).flatMap((project) =>
                    project.heldStones.map(
                        (stone) =>
                            [stone.slug, stone as InquiryStoneOption] as const,
                    ),
                ),
            ),
    );
    const rememberStones = useCallback((found: InquiryStoneOption[]) => {
        setKnownStones((current) => {
            const next = new Map(current);

            found.forEach((stone) => next.set(stone.slug, stone));

            return next;
        });
    }, []);

    useEffect(() => rememberStones(stones.data), [rememberStones, stones.data]);

    const edit = useMemo(
        () =>
            mode === 'edit' && reference !== undefined
                ? {
                      reference,
                      projects: projects ?? [],
                      // Staff edit from the Inquiry list; a client from Quote.
                      updateUrl:
                          editor === 'staff'
                              ? inquiryUpdate.url(reference)
                              : quoteUpdate.url({ reference }),
                      back:
                          editor === 'staff'
                              ? {
                                    url: inquiryIndex.url(),
                                    label: 'Back to Inquiry',
                                }
                              : {
                                    url: quoteIndex.url(),
                                    label: 'Back to Quote',
                                },
                      allowAttachments: editor !== 'staff',
                  }
                : null,
        [mode, reference, projects, editor],
    );

    const catalog = useMemo(
        () => createInquiryCatalog(props, [...knownStones.values()]),
        [props, knownStones],
    );

    if (edit === null && leads !== null && target === null) {
        return (
            <StaffInquireStart
                kind={kind}
                leads={leads}
                walkInOwners={walkInOwners}
                onKind={chooseKind}
                onTarget={chooseTarget}
            />
        );
    }

    return (
        <InquiryCatalogProvider value={catalog}>
            <InquireFlow
                edit={edit}
                target={edit === null ? target : null}
                onChangeTarget={() => chooseKind(kind)}
                rememberStones={rememberStones}
                /*
                 * A client's own book, on their new inquiry and their edit;
                 * staff (a lead, a walk-in, an edit from the Inquiry list)
                 * enter the address themselves.
                 */
                addresses={
                    leads === null && editor !== 'staff'
                        ? (props.addresses ?? [])
                        : null
                }
            />
        </InquiryCatalogProvider>
    );
};

export default Inquire;

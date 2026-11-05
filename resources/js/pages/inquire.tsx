import { zodResolver } from '@hookform/resolvers/zod';
import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FormProvider, useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import ApplicationCard from '@/components/application-card';
import EmptyState from '@/components/empty-state';
import { fieldMessage } from '@/components/form-field';
import InquiryProjectForm from '@/components/inquiry-project-form';
import InquiryQuestion, {
    inquiryQuestionId,
} from '@/components/inquiry-question';
import InquiryResult from '@/components/inquiry-result';
import InquiryReview from '@/components/inquiry-review';
import InquiryStepper from '@/components/inquiry-stepper';
import InputError from '@/components/input-error';
import PageHeading from '@/components/page-heading';
import ServiceCard from '@/components/service-card';
import StaffInquireStart, {
    leadTarget,
} from '@/components/staff-inquire-start';
import {
    staffInquireKindFrom,
    type StaffInquireKind,
} from '@/lib/staff-inquire';
import ProductCard from '@/components/product-card';
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
import { useSavedStones } from '@/hooks/use-saved-stones';
import {
    canPickService,
    createInquiryCatalog,
    serviceNameIn,
} from '@/lib/inquiry-catalog';
import { readInquiryDraft } from '@/lib/inquiry-draft-storage';
import { rateLimitFrom } from '@/lib/rate-limit';
import type { RateLimit } from '@/lib/rate-limit';
import {
    APPLICATION_STEP,
    autoProjectName,
    buildCommittedProject,
    buildInquiryFormData,
    clampRestoredStep,
    commitPlan,
    deadlineLocks,
    INQUIRY_STEP_LABELS,
    isStandaloneOnly,
    isStandaloneProject,
    mapInquiryErrors,
    nextQuestion,
    readInquiryFlash,
    REVIEW_STEP,
    seedStoneRows,
    stepperPosition,
    toEditFormProjects,
    type InquiryEditProject,
    type InquiryFlash,
} from '@/lib/inquiry-flow';
import {
    createInquirySchemas,
    draftDefaults,
    inquiryDefaults,
    REQUIREMENTS_STEP,
    SERVICES_STEP,
    STEP_FIELDS,
    STONES_STEP,
    type InquiryDraftInput,
    type InquiryDraftValues,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';
import { PROJECT_NAME_MAX } from '@/data/inquiries';
import { permissions } from '@/layouts/workspace-layout';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as quoteIndex, update as quoteUpdate } from '@/routes/quote';
import { update as inquiryUpdate } from '@/routes/inquiry';
import { show as savedShow } from '@/routes/saved';
import type { Auth } from '@/types';
import type {
    InquirePageProps,
    InquiryStoneOption,
    StaffInquireTarget,
} from '@/types/inquiry';

const STEPS = [...INQUIRY_STEP_LABELS];

const REQUEST_DESCRIPTION =
    'Answer a few simple questions. We save your answers as you go.';

const REVIEW_DESCRIPTION = 'Check your request, then send it';

const neededByFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'long',
});

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

type InquiryResultState =
    | { status: 'success'; title: string; reference: string }
    | { status: 'error'; message?: string; retryAt?: number | null }
    | null;

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
    method: 'post' | 'patch' = 'post',
): Promise<SendOutcome> =>
    new Promise((resolve) => {
        const body = buildInquiryFormData(values, {
            withProjectIds: method === 'patch',
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

type StoneFilter = 'all' | 'bestSellers' | 'saved';

/**
 * The strip above steps 1 and 2 saying which project is being built and how to
 * walk away from it.
 *
 * It is there because steps 1 and 2 look exactly as they did before there could
 * be more than one project: without a line saying so, a reader adding their
 * second project would have no way of telling the two apart, and walking away
 * would look like walking away from the whole inquiry.
 */
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
 * is worse than none: the page jumps the moment the real services arrive, and
 * the jump is the jarring part — the skeleton is supposed to be what absorbs it.
 * So the heading, the rail and the footer are the real ones' measurements, and
 * the rows are the real catalogue's rows, because the count of them is what the
 * reader's eye is about to land on.
 *
 * Marked busy rather than loading, and paired with the one line a screen reader
 * is given, because a column of empty boxes cannot say anything on its own: it
 * would be read as a page that has finished and simply has no content.
 */
const InquireSkeleton = () => {
    const { services } = useInquiryCatalog();

    return (
        <div role="status" aria-busy="true" className="w-full">
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

            {/* The heading over the list, which is the row above it. */}
            <Skeleton className="h-7 w-36" />

            {/*
             * The services themselves, one row each: a square thumbnail, a name and
             * its bullets, and the counter at the far end. Built from the catalogue
             * rather than from a fixed number, so the band is the height it is going
             * to be and adding a service cannot quietly make the page jump again.
             */}
            <div className="mt-4 divide-y divide-sidebar-border border-y border-sidebar-border">
                {services.map((service) => (
                    <div
                        key={service.id}
                        className="flex w-full items-start gap-4 p-4 sm:gap-6 sm:p-5"
                    >
                        <Skeleton className="size-20 shrink-0 rounded-md sm:size-28" />

                        <div className="min-w-0 flex-1">
                            <Skeleton className="h-5 w-2/5 sm:h-6" />

                            <ul className="mt-2 space-y-1.5">
                                {service.features.map((feature) => (
                                    <li
                                        key={feature}
                                        className="flex items-center gap-2.5"
                                    >
                                        <Skeleton className="size-1.5 shrink-0 rounded-full" />
                                        {/*
                                         * 23px, because that is what a real bullet
                                         * line comes out at — `text-sm` on a
                                         * `leading-relaxed` row — and a row's height
                                         * is its bullet count times this. Getting it
                                         * wrong by a few pixels per bullet is enough
                                         * to make every row below it sit higher than
                                         * where the real row will land.
                                         */}
                                        <Skeleton className="h-[23px] w-4/5" />
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <Skeleton className="hidden h-3 w-6 shrink-0 sm:block" />
                    </div>
                ))}
            </div>

            {/*
             * The bar the reader finishes on: the count on the left, the way forward
             * on the right. Pinned the same way, because it is pinned in the real
             * page and a placeholder that scrolls away while the real one does not
             * would move the button a reader was reaching for.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <Skeleton className="h-5 w-32" />

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
 */
const InquireFlow = ({
    edit,
    target = null,
    onChangeTarget,
}: {
    edit: InquiryEdit | null;
    target?: StaffInquireTarget | null;
    onChangeTarget?: () => void;
}) => {
    const catalog = useInquiryCatalog();
    const { stones } = catalog;
    const { inquirySchema, projectDraftSchema } = useMemo(
        () => createInquirySchemas(catalog),
        [catalog],
    );
    const ownStoreUrl = useInquireStoreUrl();
    const inquireStoreUrl = target === null ? ownStoreUrl : target.storeUrl;

    const [isLoading, setIsLoading] = useState(true);
    /**
     * Both start on the first step. An edit opens the inquiry's one project
     * there as soon as it is loaded (see the effect after `openProject`).
     */
    const [step, setStep] = useState<number>(SERVICES_STEP);
    const [filter, setFilter] = useState<StoneFilter>('all');
    const [search, setSearch] = useState('');
    const [applicationSearch, setApplicationSearch] = useState('');

    /** Which committed project the open draft is editing, or null when new. */
    const [editingProjectId, setEditingProjectId] = useState<string | null>(
        null,
    );

    const [result, setResult] = useState<InquiryResultState>(null);
    const [isSending, setIsSending] = useState(false);
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
        defaultValues: draftDefaults,
        shouldUnregister: false,
    });

    const {
        setValue: setDraftValue,
        trigger: triggerDraft,
        watch: watchDraft,
        getValues: getDraftValues,
    } = draftMethods;

    const draftStoneIds = watchDraft('stoneIds');
    const draftServiceIds = watchDraft('serviceIds');
    const draftStandaloneServiceIds = watchDraft('standaloneServiceIds');
    const draftServiceOptions = watchDraft('serviceOptions');
    const draftApplicationIds = watchDraft('applicationIds');
    const draftStoneAdvice = watchDraft('stoneAdvice');
    const draftLocation = watchDraft('location');
    const draftNeededBy = watchDraft('neededBy');
    const draftNeededByTime = watchDraft('neededByTime');

    /*
     * The part of the draft that decides the path through the steps, handed to
     * the flow helpers rather than re-read inside them.
     */
    const flowDraft = {
        serviceIds: draftServiceIds,
        standaloneServiceIds: draftStandaloneServiceIds,
        stoneIds: draftStoneIds,
        applicationIds: draftApplicationIds,
    };

    /*
     * Everything the reader has picked on the services step, both kinds together.
     *
     * The step shows one list of cards, so everything it can say — the count, the
     * "n selected" line, Clear — has to be about what the reader can see rather
     * than about which of the draft's two lists a service happens to be filed
     * under.
     */
    const chosenServiceIds = [...draftServiceIds, ...draftStandaloneServiceIds];

    /*
     * Whether Continue has been pressed at all, once. A card is checked once there
     * is something to correct, so picking the first stone does not raise an
     * objection the reader has not caused yet.
     */
    const hasDraftContinued = useRef(false);
    const isSendingRef = useRef(false);

    const { can } = usePermissions();
    const { savedIds, toggleSave, canSave } = useSavedStones();

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
     * repeat the same work — but the toast about attachments the browser could
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
            reset({ projects: toEditFormProjects(edit.projects) });

            return;
        }

        const stored = readInquiryDraft(userId, catalog);

        if (stored !== null) {
            /*
             * The files come back empty because a `File` has no form storage
             * can hold; the count of them is what the toast below is about.
             */
            reset({
                projects: stored.projects.map((project) => ({
                    ...project,
                    attachments: [],
                })),
            });
            draftMethods.reset({ ...stored.draft, attachments: [] });
            setEditingProjectId(
                stored.editingProjectId !== null &&
                    stored.projects.some(
                        (project) => project.id === stored.editingProjectId,
                    )
                    ? stored.editingProjectId
                    : null,
            );
            setStep(
                clampRestoredStep(
                    stored.step,
                    stored.projects.length,
                    stored.draft,
                ),
            );
            hasDraftContinued.current = false;

            if (stored.droppedAttachments > 0) {
                toast.info(
                    stored.droppedAttachments === 1
                        ? 'Your draft is back. Its attachment could not be saved here — attach the file again.'
                        : `Your draft is back. ${stored.droppedAttachments} attachments could not be saved here — attach the files again.`,
                );
            }
        }

        setIsRestored(true);
    }, [userId, catalog, reset, draftMethods, edit]);

    /*
     * The draft's own life support: a write a couple of seconds after the last
     * change, flushed on the way out of a step and when the tab goes away.
     *
     * Inert until the restore above has run, for the reason that state is
     * there: the first thing either form holds is the blank default, and a
     * save of that would put the stored draft to death while its owner was
     * still on the skeleton.
     */
    const { discardDraft } = useInquiryDraftAutosave({
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

    const visibleStones = useMemo(() => {
        const base = ((): InquiryStoneOption[] => {
            if (filter === 'bestSellers') {
                return stones.filter((stone) => stone.bestSeller);
            }

            if (filter === 'saved') {
                return stones.filter((stone) => savedIds.has(stone.slug));
            }

            return stones;
        })();

        const query = search.trim().toLowerCase();

        if (query === '') {
            return base;
        }

        return base.filter((stone) =>
            [
                stone.name,
                stone.specifications.stoneType,
                stone.specifications.origin,
            ]
                .filter((value): value is string => Boolean(value))
                .some((value) => value.toLowerCase().includes(query)),
        );
    }, [filter, savedIds, search, stones]);

    const toggleIn = (chosen: string[], id: string): string[] =>
        chosen.includes(id)
            ? chosen.filter((entry) => entry !== id)
            : [...chosen, id];

    const toggleStone = (id: string) => {
        setDraftValue('stoneIds', toggleIn(draftStoneIds, id), {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        });
        setDraftValue('stoneAdvice', false, { shouldDirty: true });
    };

    /**
     * The reader does not know which stone yet, so the team will recommend one.
     * Nothing else on this question needs answering, so it moves straight on.
     */
    const askForStoneAdvice = () => {
        setDraftValue('stoneIds', [], { shouldDirty: true });
        setDraftValue('stones', [], { shouldDirty: true });
        setDraftValue('stoneAdvice', true, { shouldDirty: true });
        draftMethods.clearErrors('stoneIds');
        setStep(nextQuestion(STONES_STEP, flowDraft));
    };

    /** Pick or unpick one application, which a project may hold several of. */
    const toggleApplication = (id: string) => {
        setDraftValue('applicationIds', toggleIn(draftApplicationIds, id), {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        });
    };

    /**
     * Pick or unpick one service, filed under whichever of the draft's two lists
     * it belongs to.
     *
     * The split is a fact about the catalogue rather than something the reader
     * chose, so it decides where a service goes: a service that stands on its own
     * can never be one this project is specified for, and the requirements form must not
     * say it is.
     */
    const toggleService = (id: string) => {
        const field = catalog.standaloneServiceIds.has(id)
            ? 'standaloneServiceIds'
            : 'serviceIds';
        const options = {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        };

        const current = getDraftValues(field);

        setDraftValue(field, toggleIn(current, id), options);

        // A service taken off takes the kind of work picked under it too.
        if (current.includes(id)) {
            setDraftValue(
                'serviceOptions',
                Object.fromEntries(
                    Object.entries(getDraftValues('serviceOptions')).filter(
                        ([serviceId]) => serviceId !== id,
                    ),
                ),
                options,
            );
        }
    };

    /** The kind of work picked under one service, from its dropdown. */
    const setServiceOption = (id: string, option: string) => {
        // By its own path, so a revalidation clears this service's error.
        setDraftValue(`serviceOptions.${id}`, option, {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        });
    };

    /**
     * Empty the draft, whatever part of it is open.
     *
     * `editingProjectId` goes with the answers because it says which committed
     * project the next commit will replace; left standing, cancelling a new
     * project would quietly make the next one overwrite an existing one.
     */
    const clearDraft = () => {
        draftMethods.reset(draftDefaults);
        hasDraftContinued.current = false;
        setEditingProjectId(null);
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
     *
     * The applications come back too, so Edit from Review shows the saved
     * choices selected in the Application step.
     *
     * A project that is a service standing on its own goes back into the draft's
     * queue rather than into its services, because that is how the requirements step
     * knows to ask for a site and a date instead of a material: the queue being
     * what is there and this project's own service not being in it is exactly
     * what "this project is the service" means.
     */
    const openProject = (id: string, startAt: number = REQUIREMENTS_STEP) => {
        const project = getValues('projects').find((entry) => entry.id === id);

        if (project === undefined) {
            return;
        }

        /*
         * Read off the services rather than off the stones: a visit may now carry
         * stones as extra information, so having none no longer says it is one.
         */
        const isStandalone = isStandaloneProject(
            project.serviceIds,
            catalog.standaloneServiceIds,
        );

        draftMethods.reset({
            name: project.name,
            stoneAdvice: project.stoneAdvice,
            stoneIds: project.stones.map((selection) => selection.stoneId),
            serviceIds: isStandalone ? [] : [...project.serviceIds],
            standaloneServiceIds: isStandalone ? [...project.serviceIds] : [],
            serviceOptions: { ...project.serviceOptions },
            stones: project.stones.map((selection) => ({ ...selection })),
            applicationIds: [...project.applications],
            location: project.location,
            landmark: project.landmark,
            areaSqm: project.areaSqm,
            neededBy: project.neededBy,
            neededByTime: project.neededByTime,
            notes: project.notes,
            attachments: project.attachments.map((attachment) => ({
                ...attachment,
            })),
        });
        hasDraftContinued.current = false;
        setEditingProjectId(id);
        setStep(startAt);
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
        openProject(project.id, SERVICES_STEP);
    });

    /**
     * Name the draft after its service. A visit has nothing else to be called, so
     * the reader is never asked.
     */
    const seedStandaloneName = (queue: string[]): boolean => {
        const [next] = queue;

        if (next === undefined) {
            return false;
        }

        setDraftValue('name', serviceNameIn(catalog, next), {
            shouldDirty: true,
            shouldValidate: false,
        });

        return true;
    };

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

    /**
     * Open another question from its Change button. The stones get their rows on
     * the way out of their question, however the reader leaves it.
     */
    const openQuestion = (question: number) => {
        if (step === STONES_STEP) {
            seedDraftStones();
        }

        setStep(question);
    };

    /**
     * Step 2 onward. Needs a stone, or the reader asking for a recommendation.
     *
     * The objection is spoken by the bar rather than by refusing the button,
     * because a grid of cards is not a field and there is nothing to send the
     * reader's attention to.
     */
    const continueFromStones = async () => {
        hasDraftContinued.current = true;

        const valid = await triggerDraft('stoneIds', { shouldFocus: true });

        if (valid) {
            seedDraftStones();
            setStep(nextQuestion(STONES_STEP, flowDraft));
        }
    };

    /**
     * Step 1 onward. A draft of only standalone services skips the stones and the
     * application and is named after its service. When a new request changes to
     * one, what was picked for the stone it no longer wants is dropped.
     */
    const continueFromServices = async () => {
        hasDraftContinued.current = true;

        /*
         * Each service's kind of work by its own path: react-hook-form keeps
         * an error only where it carries a message, which the
         * `serviceOptions.<id>` entries do and the map as a whole does not.
         */
        const valid = await triggerDraft(
            [
                'serviceIds',
                'standaloneServiceIds',
                ...chosenServiceIds.map(
                    (id) => `serviceOptions.${id}` as const,
                ),
            ],
            { shouldFocus: true },
        );

        if (!valid) {
            return;
        }

        if (isStandaloneOnly(flowDraft)) {
            if (edit === null) {
                setDraftValue('stoneIds', []);
                setDraftValue('stones', []);
                setDraftValue('applicationIds', []);
                setDraftValue('stoneAdvice', false);
            }

            seedStandaloneName(getDraftValues('standaloneServiceIds'));
            hasDraftContinued.current = false;
        }

        setStep(nextQuestion(SERVICES_STEP, flowDraft));
    };

    /**
     * Step 3 onward. Needs an application.
     */
    const continueFromApplication = async () => {
        hasDraftContinued.current = true;

        const valid = await triggerDraft('applicationIds', {
            shouldFocus: true,
        });

        if (valid) {
            setStep(nextQuestion(APPLICATION_STEP, flowDraft));
        }
    };

    /** What a project is called unless the reader names it on Review. */
    const projectNameFor = (
        applicationIds: string[],
        serviceIds: string[],
    ): string =>
        autoProjectName(
            applicationIds.flatMap((id) => {
                const application = catalog.applications.find(
                    (entry) => entry.id === id,
                );

                return application === undefined ? [] : [application.name];
            }),
            serviceIds.map((id) => serviceNameIn(catalog, id)),
            PROJECT_NAME_MAX,
        );

    /**
     * Name the draft before it is checked. A project being reopened keeps a name
     * its reader gave it; one still carrying its automatic name is named again,
     * so that name keeps describing what the project now is.
     */
    const nameDraft = () => {
        if (getDraftValues('serviceIds').length === 0) {
            return;
        }

        const reopened = getValues('projects').find(
            (project) => project.id === editingProjectId,
        );
        const hasOwnName =
            reopened !== undefined &&
            reopened.name !==
                projectNameFor(reopened.applications, reopened.serviceIds);

        setDraftValue(
            'name',
            hasOwnName
                ? reopened.name
                : projectNameFor(
                      getDraftValues('applicationIds'),
                      getDraftValues('serviceIds'),
                  ),
        );
    };

    /**
     * The draft, checked whole and then committed.
     *
     * Checked against the draft's own schema rather than against any one step's,
     * because this is the one press that takes all the steps' answers together
     * and puts them on the inquiry.
     *
     * Every standalone project is committed with the stones and applications
     * picked before it, as extra information.
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

        await draftMethods.handleSubmit(() => {
            valid = true;
        })();

        if (!valid) {
            return;
        }

        const draft = getDraftValues();
        const { isStandalone, head, remaining } = commitPlan(draft);

        /*
         * An inquiry is one project. Services that would split this one into
         * several (two visits) are refused here rather than sent and refused.
         */
        if (remaining.length > 0) {
            toast.error(
                'An inquiry can only have one project. Keep one of these services here and send the others as a new inquiry.',
            );

            return;
        }

        const project = buildCommittedProject(draft, {
            id: editingProjectId ?? newProjectId(),
            standaloneService:
                isStandalone && head !== undefined
                    ? { id: head, name: serviceNameIn(catalog, head) }
                    : undefined,
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
                : await sendInquiryRequest(inquireStoreUrl, getValues());

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
         * A fresh inquiry starts empty rather than from the request just sent,
         * and the draft goes with it: a half-built project is the one thing that
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
        setApplicationSearch('');
        setValidationAlertErrors([]);
        setSectionMessages({});
        setProjectMessages({});
        setStep(SERVICES_STEP);
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
            'patch',
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
        setStep(SERVICES_STEP);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    if (isLoading) {
        return <InquireSkeleton />;
    }

    const hasSelection = draftStoneIds.length > 0;
    const hasServices = chosenServiceIds.length > 0;
    // A stone that stands alone is read under Saved, so that is what opens
    // one in a new tab.
    const canSeeStoneProfile = can(permissions.savedView);
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
        { value: 'saved', label: 'Saved' },
        { value: 'all', label: 'All' },
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
     * A finished request is past every step, so the rail shows them all done. A
     * failure is not a step of its own, so it leaves the reader where they were:
     * they are looking at the request that failed, not at a new question.
     */
    const stepperCurrent =
        result?.status === 'success' ? STEPS.length + 1 : stepperPosition(step);

    /*
     * The questions this draft is asked, in order. One waiting for an answer is
     * not drawn until the reader reaches it.
     */
    const questions = isStandaloneOnly(flowDraft)
        ? [SERVICES_STEP, REQUIREMENTS_STEP]
        : [SERVICES_STEP, STONES_STEP, APPLICATION_STEP, REQUIREMENTS_STEP];

    const isAnswered: Record<number, boolean> = {
        [SERVICES_STEP]: hasServices,
        [STONES_STEP]: hasSelection || draftStoneAdvice,
        [APPLICATION_STEP]: draftApplicationIds.length > 0,
        [REQUIREMENTS_STEP]: draftLocation !== '' && draftNeededBy !== '',
    };

    /** The question's props, or null while it waits for the reader to reach it. */
    const question = (questionStep: number) =>
        step === questionStep || isAnswered[questionStep]
            ? {
                  step: questionStep,
                  number: questions.indexOf(questionStep) + 1,
                  isOpen: step === questionStep,
                  onChange: () => openQuestion(questionStep),
              }
            : null;

    const namesOf = (names: string[], limit = 3): string =>
        names.length > limit
            ? `${names.slice(0, limit).join(', ')} and ${names.length - limit} more`
            : names.join(', ');

    const servicesSummary = namesOf(
        chosenServiceIds.map((id) => {
            const service = catalog.services.find((entry) => entry.id === id);
            const kind = service?.options.find(
                (option) => option.id === draftServiceOptions[id],
            )?.name;

            return kind === undefined
                ? serviceNameIn(catalog, id)
                : `${serviceNameIn(catalog, id)} (${kind})`;
        }),
    );

    const stonesSummary = draftStoneAdvice
        ? "We'll recommend a stone for you"
        : namesOf(
              draftStoneIds.map(
                  (id) => stones.find((stone) => stone.slug === id)?.name ?? id,
              ),
          );

    const applicationsSummary = namesOf(
        draftApplicationIds.flatMap((id) => {
            const application = catalog.applications.find(
                (entry) => entry.id === id,
            );

            return application === undefined ? [] : [application.name];
        }),
    );

    const detailsSummary =
        draftNeededBy === ''
            ? draftLocation
            : [
                  draftLocation,
                  neededByFormatter.format(new Date(`${draftNeededBy}T00:00`)),
                  isStandaloneOnly(flowDraft) ? draftNeededByTime : '',
              ]
                  .filter((part) => part !== '')
                  .join(', ');

    const servicesQuestion = question(SERVICES_STEP);
    const stonesQuestion = questions.includes(STONES_STEP)
        ? question(STONES_STEP)
        : null;
    const applicationQuestion = questions.includes(APPLICATION_STEP)
        ? question(APPLICATION_STEP)
        : null;
    const detailsQuestion = question(REQUIREMENTS_STEP);

    return (
        <FormProvider {...methods}>
            <div className="w-full">
                <PageHeading
                    title={
                        edit === null ? 'Inquire' : `Update ${edit.reference}`
                    }
                    description={
                        result?.status === 'success'
                            ? 'Your request has been sent'
                            : edit !== null && step === REVIEW_STEP
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
                                    <ArrowLeft aria-hidden />
                                    {edit.back.label}
                                </Link>
                            </Button>
                        )
                    }
                />

                <InquiryStepper steps={STEPS} current={stepperCurrent} />

                {result !== null ? (
                    <InquiryResult
                        status={result.status}
                        title={result.status === 'success' ? result.title : ''}
                        reference={
                            result.status === 'success'
                                ? result.reference
                                : undefined
                        }
                        message={
                            result.status === 'error'
                                ? result.message
                                : undefined
                        }
                        retryAt={
                            result.status === 'error'
                                ? (result.retryAt ?? null)
                                : null
                        }
                        canChat={canChat}
                        inquiriesHref={inquiriesHref}
                        onTryAgain={sendInquiry}
                        onBackToReview={() => setResult(null)}
                        onInquireAgain={inquireAgain}
                        submitting={isSending}
                    />
                ) : step === REVIEW_STEP ? (
                    <InquiryReview
                        onEditProject={openProject}
                        sectionMessages={sectionMessages}
                        projectMessages={projectMessages}
                        validationErrors={validationAlertErrors}
                        locked={isSending}
                        onRequestQuote={requestQuote}
                        mode={edit === null ? 'create' : 'edit'}
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

                        {servicesQuestion !== null ? (
                            <InquiryQuestion
                                {...servicesQuestion}
                                title="What do you need?"
                                summary={servicesSummary}
                            >
                                {catalog.services.some(
                                    (service) =>
                                        !canPickService(
                                            catalog.services,
                                            chosenServiceIds,
                                            service.id,
                                        ),
                                ) ? (
                                    <p className="mb-3 text-sm text-secondary-foreground">
                                        Some services can't be combined with the
                                        ones you picked. Send them as a new
                                        inquiry.
                                    </p>
                                ) : null}

                                {/*
                                 * A list, not a grid: the stone grid two steps
                                 * on is the same control all over again, and the
                                 * rules between full-width rows are what keep
                                 * this from reading as it.
                                 */}
                                <div className="divide-y divide-sidebar-border border-y border-sidebar-border">
                                    {catalog.services.map((service, index) => (
                                        <ServiceCard
                                            key={service.id}
                                            service={service}
                                            index={index}
                                            selected={chosenServiceIds.includes(
                                                service.id,
                                            )}
                                            disabled={
                                                !canPickService(
                                                    catalog.services,
                                                    chosenServiceIds,
                                                    service.id,
                                                )
                                            }
                                            onSelect={() =>
                                                toggleService(service.id)
                                            }
                                            option={
                                                draftServiceOptions[
                                                    service.id
                                                ] ?? ''
                                            }
                                            onOptionChange={(option) =>
                                                setServiceOption(
                                                    service.id,
                                                    option,
                                                )
                                            }
                                            optionError={fieldMessage(
                                                draftMethods.formState.errors
                                                    .serviceOptions?.[
                                                    service.id
                                                ],
                                            )}
                                        />
                                    ))}
                                </div>

                                {/* Sticky for the same reason as step 2's. */}
                                <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                        <p
                                            aria-live="polite"
                                            className="text-sm font-bold text-foreground"
                                        >
                                            {chosenServiceIds.length}{' '}
                                            {chosenServiceIds.length === 1
                                                ? 'service'
                                                : 'services'}{' '}
                                            selected
                                        </p>
                                        {hasServices ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    const options = {
                                                        shouldDirty: true,
                                                        shouldValidate:
                                                            hasDraftContinued.current,
                                                    };

                                                    setDraftValue(
                                                        'serviceIds',
                                                        [],
                                                        options,
                                                    );
                                                    setDraftValue(
                                                        'standaloneServiceIds',
                                                        [],
                                                        options,
                                                    );
                                                    setDraftValue(
                                                        'serviceOptions',
                                                        {},
                                                        options,
                                                    );
                                                }}
                                                className="h-7 cursor-pointer px-1.5 text-xs"
                                            >
                                                Clear
                                            </Button>
                                        ) : null}
                                        <InputError
                                            message={fieldMessage(
                                                draftMethods.formState.errors
                                                    .serviceIds,
                                            )}
                                            role="alert"
                                        />
                                    </div>

                                    {/*
                                     * Disabled until a service is picked: every
                                     * inquiry starts from one, and the count
                                     * beside it already says how many are.
                                     */}
                                    <Button
                                        type="button"
                                        onClick={continueFromServices}
                                        disabled={!hasServices}
                                        className="w-full sm:w-auto"
                                    >
                                        Next
                                    </Button>
                                </div>
                            </InquiryQuestion>
                        ) : null}

                        {stonesQuestion !== null ? (
                            <InquiryQuestion
                                {...stonesQuestion}
                                title="Which stones do you like?"
                                summary={stonesSummary}
                            >
                                <div className="mb-5 flex flex-col gap-3 rounded-md border border-sidebar-border bg-secondary p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="text-base font-bold">
                                            Not sure which stone yet?
                                        </p>
                                        <p className="mt-0.5 text-sm text-secondary-foreground">
                                            Our team will recommend one for you.
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
                                        className="w-full shrink-0 sm:w-auto"
                                    >
                                        Help me choose
                                    </Button>
                                </div>

                                <div className="mb-6 border-b border-sidebar-border pb-4">
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
                                                    setSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Search stones"
                                                aria-label="Search stones"
                                                className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                                            />
                                            {search ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSearch('')
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
                                            className="hidden items-center gap-5 text-sm sm:ml-auto sm:flex"
                                            role="tablist"
                                            aria-label="Filter stones"
                                        >
                                            {stoneTabs.map((tab) => {
                                                const isActive =
                                                    filter === tab.value;

                                                return (
                                                    <button
                                                        key={tab.value}
                                                        type="button"
                                                        role="tab"
                                                        aria-selected={isActive}
                                                        onClick={() =>
                                                            setFilter(tab.value)
                                                        }
                                                        className={`-my-2.5 rounded-md px-2.5 py-2.5 transition-opacity hover:opacity-70 ${
                                                            isActive
                                                                ? 'font-bold text-foreground underline decoration-foreground underline-offset-4'
                                                                : 'text-secondary-foreground'
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
                                                    filter === tab.value;

                                                return (
                                                    <Button
                                                        key={tab.value}
                                                        type="button"
                                                        role="tab"
                                                        aria-selected={isActive}
                                                        variant={
                                                            isActive
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        size="sm"
                                                        className="px-3 py-1.5"
                                                        onClick={() =>
                                                            setFilter(tab.value)
                                                        }
                                                    >
                                                        {tab.label}
                                                    </Button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>

                                {filter === 'saved' && savedIds.size === 0 ? (
                                    <EmptyState
                                        title="You haven't saved any stones yet"
                                        description="Tap the heart on a stone and it will show up here."
                                        action={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setFilter('all')}
                                            >
                                                Browse all stones
                                            </Button>
                                        }
                                    />
                                ) : visibleStones.length === 0 ? (
                                    <EmptyState
                                        title="No stones found"
                                        description="No stone in this filter matches that search."
                                        action={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setSearch('')}
                                            >
                                                Clear search
                                            </Button>
                                        }
                                    />
                                ) : (
                                    <div className="grid grid-cols-2 gap-3.5 md:grid-cols-4 md:gap-4.5 xl:grid-cols-5">
                                        {visibleStones.map((stone) => (
                                            <ProductCard
                                                key={stone.slug}
                                                product={{
                                                    slug: stone.slug,
                                                    name: stone.name,
                                                    thumbnail: stone.thumbnail,
                                                    category: stone.category,
                                                }}
                                                aspect="aspect-[4/3]"
                                                saved={savedIds.has(stone.slug)}
                                                onToggleSave={
                                                    canSave
                                                        ? () =>
                                                              toggleSave(
                                                                  stone.slug,
                                                              )
                                                        : undefined
                                                }
                                                selectable
                                                selected={draftStoneIds.includes(
                                                    stone.slug,
                                                )}
                                                onSelect={() =>
                                                    toggleStone(stone.slug)
                                                }
                                                // A standalone stone profile
                                                // lives under Saved, so
                                                // without that permission
                                                // the new tab would only show
                                                // the forbidden page.
                                                viewHref={
                                                    canSeeStoneProfile
                                                        ? savedShow.url({
                                                              stone: stone.slug,
                                                          })
                                                        : undefined
                                                }
                                            />
                                        ))}
                                    </div>
                                )}

                                {/*
                                 * Sticky so the way forward stays reachable
                                 * while a long grid scrolls past, and opaque so
                                 * the grid does not read through it.
                                 */}
                                <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                        <p
                                            aria-live="polite"
                                            className="text-sm font-bold text-foreground"
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
                                                    setDraftValue(
                                                        'stoneIds',
                                                        [],
                                                        {
                                                            shouldDirty: true,
                                                            shouldValidate:
                                                                hasDraftContinued.current,
                                                        },
                                                    )
                                                }
                                                className="h-7 cursor-pointer px-1.5 text-xs"
                                            >
                                                Clear
                                            </Button>
                                        ) : null}
                                        {/*
                                         * There is no control here to focus — a
                                         * grid of cards is not a field — so
                                         * Next is left enabled and the
                                         * objection is said here instead of by
                                         * refusing to let the reader onwards.
                                         */}
                                        <InputError
                                            message={fieldMessage(
                                                draftMethods.formState.errors
                                                    .stoneIds,
                                            )}
                                            role="alert"
                                        />
                                    </div>

                                    <div className="flex w-full shrink-0 flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center">
                                        <Button
                                            type="button"
                                            onClick={continueFromStones}
                                            className="w-full sm:w-auto"
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            </InquiryQuestion>
                        ) : null}

                        {applicationQuestion !== null ? (
                            <InquiryQuestion
                                {...applicationQuestion}
                                title="Where will the stone be used?"
                                summary={applicationsSummary}
                            >
                                <div className="mb-6">
                                    <div className="relative w-full sm:w-auto sm:max-w-xs">
                                        <Search
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                                            aria-hidden
                                        />
                                        <Input
                                            type="search"
                                            value={applicationSearch}
                                            onChange={(event) =>
                                                setApplicationSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Search places"
                                            aria-label="Search places"
                                            className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                                        />
                                        {applicationSearch ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setApplicationSearch('')
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
                                </div>

                                {(() => {
                                    const query = applicationSearch
                                        .trim()
                                        .toLowerCase();
                                    const filteredApplications =
                                        query === ''
                                            ? catalog.applications
                                            : catalog.applications.filter(
                                                  (application) =>
                                                      application.name
                                                          .toLowerCase()
                                                          .includes(query),
                                              );

                                    return filteredApplications.length === 0 ? (
                                        <EmptyState
                                            title="Nothing found"
                                            description="No place matches that search."
                                            action={
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setApplicationSearch('')
                                                    }
                                                >
                                                    Clear search
                                                </Button>
                                            }
                                        />
                                    ) : (
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 lg:gap-5">
                                            {filteredApplications.map(
                                                (application) => (
                                                    <ApplicationCard
                                                        key={application.id}
                                                        application={
                                                            application
                                                        }
                                                        selected={draftApplicationIds.includes(
                                                            application.id,
                                                        )}
                                                        onSelect={() =>
                                                            toggleApplication(
                                                                application.id,
                                                            )
                                                        }
                                                    />
                                                ),
                                            )}
                                        </div>
                                    );
                                })()}

                                <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                        <p
                                            aria-live="polite"
                                            className="text-sm font-bold text-foreground"
                                        >
                                            {draftApplicationIds.length}{' '}
                                            selected
                                        </p>
                                        <InputError
                                            message={fieldMessage(
                                                draftMethods.formState.errors
                                                    .applicationIds,
                                            )}
                                            role="alert"
                                        />
                                    </div>

                                    <div className="flex w-full shrink-0 flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center">
                                        <Button
                                            type="button"
                                            onClick={continueFromApplication}
                                            className="w-full sm:w-auto"
                                        >
                                            Next
                                        </Button>
                                    </div>
                                </div>
                            </InquiryQuestion>
                        ) : null}

                        {detailsQuestion !== null ? (
                            <InquiryQuestion
                                {...detailsQuestion}
                                title="A few last details"
                                summary={detailsSummary}
                            >
                                <InquiryProjectForm
                                    projectNumber={
                                        editingProjectNumber > 0
                                            ? editingProjectNumber
                                            : projects.length + 1
                                    }
                                    isStandalone={isStandaloneOnly(flowDraft)}
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
            </div>
        </FormProvider>
    );
};

const Inquire = (props: InquirePageProps) => {
    const {
        services,
        stones,
        applications,
        edgeProfiles,
        finishes,
        locations,
        mode,
        reference,
        projects,
        editor = 'client',
        leads = null,
        lead: leadInUrl = null,
    } = props;

    /*
     * Staff say who the inquiry is for first: the branch (`?kind=`, Platform or
     * Direct), then the client and, on Platform, the lead (`?lead=`). The URL
     * keeps both, so a reload, or the Leads page's "Create inquiry", lands past
     * what was already answered. A direct client is not in the URL: it is taken
     * down again after a reload, and the duplicate check finds it.
     */
    const [kind, setKind] = useState<StaffInquireKind | null>(() =>
        leadInUrl !== null
            ? 'platform'
            : staffInquireKindFrom(
                  typeof window === 'undefined'
                      ? null
                      : new URLSearchParams(window.location.search).get('kind'),
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

    const chooseKind = (next: StaffInquireKind | null) => {
        setKind(next);
        setTarget(null);
        keepInUrl(next, null);
    };

    const chooseTarget = (next: StaffInquireTarget) => {
        setTarget(next);
        keepInUrl(next.kind, next.kind === 'platform' ? next.detail : null);
    };

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
        () =>
            createInquiryCatalog({
                services,
                stones,
                applications,
                edgeProfiles,
                finishes,
                locations,
            }),
        [services, stones, applications, edgeProfiles, finishes, locations],
    );

    if (edit === null && leads !== null && target === null) {
        return (
            <StaffInquireStart
                kind={kind}
                leads={leads}
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
            />
        </InquiryCatalogProvider>
    );
};

export default Inquire;

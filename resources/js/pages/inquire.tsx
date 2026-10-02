import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, Search, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { FormProvider, useForm, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
import InquiryProjectForm from '@/components/inquiry-project-form';
import InquiryResult from '@/components/inquiry-result';
import InquiryReview from '@/components/inquiry-review';
import InquiryStepper from '@/components/inquiry-stepper';
import InputError from '@/components/input-error';
import PageHeading from '@/components/page-heading';
import ServiceCard from '@/components/service-card';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    serviceName,
    serviceOptions,
    STANDALONE_SERVICE_IDS,
    stones,
    stoneProfiles,
    type FeedItem,
} from '@/data/constant';
import { usePermissions } from '@/hooks/use-permissions';
import { useSavedStones } from '@/hooks/use-saved-stones';
import {
    draftDefaults,
    inquiryDefaults,
    inquirySchema,
    projectDraftSchema,
    SERVICES_STEP,
    REQUIREMENTS_STEP,
    STEP_FIELDS,
    STONES_STEP,
    type InquiryDraftInput,
    type InquiryDraftValues,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';
import { show as homeShow } from '@/routes/home';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as quoteIndex } from '@/routes/quote';

const STEPS = ['Services', 'Stones', 'Requirements', 'Review'];

/** Review is the last step, and owns no fields of its own to validate. */
const REVIEW_STEP = REQUIREMENTS_STEP + 1;

const STEP_DESCRIPTIONS: Record<number, string> = {
    [SERVICES_STEP]: 'Choose the services you need',
    [STONES_STEP]: "Choose the stones you're interested in",
    [REQUIREMENTS_STEP]:
        'Name this project, set how each stone is worked, and tell us where and when',
    [REVIEW_STEP]: 'Check your inquiry before sending it',
};

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
    | { status: 'success'; title: string }
    | { status: 'error' }
    | null;

/**
 * The send, and the only thing in this page that stands in for it.
 *
 * A stand-in: the project has no inquiry endpoint, model or migration yet, so
 * the exchange is faked in the browser the same way the uploader's is. It
 * returns a promise that may resolve or reject, which is the shape both
 * outcomes above it need.
 *
 * Replacing this one function with a real `router.post` is the only change an
 * endpoint requires. Two things about the shape it is handed: the stones, their
 * counts and their edge and finish arrive nested per project rather than as one
 * flat list, because that is how they were asked for; and the attachments are
 * still `File` objects rather than paths, so that post has to be a multipart
 * `FormData` with the files appended as they are, not a JSON body.
 */
const submitInquiry = (values: InquiryFormValues): Promise<void> =>
    new Promise((resolve) => {
        /*
         * Scaled by what is going with it, for the same reason the uploader's
         * fake is: a request carrying four plans should not take the same moment
         * as one carrying nothing. Capped, so it stays inside 1.2 to 1.8 seconds.
         */
        const megabytes =
            values.projects
                .flatMap((project) => project.attachments)
                .reduce(
                    (total, attachment) => total + attachment.file.size,
                    0,
                ) /
            (1024 * 1024);

        window.setTimeout(resolve, Math.min(1800, 1200 + megabytes * 60));
    });

const SKELETON_MS = 700;

type StoneFilter = 'latest' | 'recommended' | 'saved';

const EmptyState = ({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action: ReactNode;
}) => (
    <div className="flex flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
        <p className="text-lg font-bold tracking-[-0.03em]">{title}</p>
        <p className="max-w-sm text-sm text-secondary-foreground">
            {description}
        </p>
        <div className="mt-1">{action}</div>
    </div>
);

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
    <div className="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border border-sidebar-border bg-background px-4 py-3">
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
const InquireSkeleton = () => (
    <div role="status" aria-busy="true" className="mx-auto w-full max-w-6xl">
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
                            <Skeleton className="size-9 rounded-none" />
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
            {serviceOptions.map((service) => (
                <div
                    key={service.id}
                    className="flex w-full items-start gap-4 p-4 sm:gap-6 sm:p-5"
                >
                    <Skeleton className="size-20 shrink-0 rounded-none sm:size-28" />

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
            <Skeleton className="h-[46px] w-full rounded-none sm:w-36" />
        </div>
    </div>
);

const Inquire = () => {
    const [isLoading, setIsLoading] = useState(true);
    const [step, setStep] = useState(SERVICES_STEP);
    const [filter, setFilter] = useState<StoneFilter>('latest');
    const [search, setSearch] = useState('');

    /** Which committed project the open draft is editing, or null when new. */
    const [editingProjectId, setEditingProjectId] = useState<string | null>(
        null,
    );

    /** The project waiting to be confirmed out. */
    const [removeTarget, setRemoveTarget] = useState<string | null>(null);

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
    const { savedIds, toggleSave } = useSavedStones();

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), SKELETON_MS);

        return () => window.clearTimeout(timer);
    }, []);

    /*
     * Both grids are long enough to leave the reader scrolled well past the
     * heading, so a step change takes them back to the top. The result panel is
     * deliberately left out: it arrives where the reader pressed the button, and
     * jumping to the top would move the thing they were waiting for off screen.
     */
    useEffect(() => {
        if (result !== null) {
            return;
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [step, result]);

    const visibleStones = useMemo(() => {
        const base = ((): FeedItem[] => {
            if (filter === 'recommended') {
                return stones.filter((stone) => stone.recommended);
            }

            if (filter === 'saved') {
                return stones.filter((stone) => savedIds.has(stone.id));
            }

            return stones;
        })();

        const query = search.trim().toLowerCase();

        if (query === '') {
            return base;
        }

        return base.filter((stone) => {
            const profile = stoneProfiles[stone.id];

            return [
                stone.name,
                profile?.specifications.stoneType,
                profile?.specifications.origin,
            ]
                .filter((value): value is string => Boolean(value))
                .some((value) => value.toLowerCase().includes(query));
        });
    }, [filter, savedIds, search]);

    /*
     * Which stones are already part of a committed project.
     *
     * Shown as a badge rather than as a disabled card: a stone wanted in two
     * projects is a real and common request — a honed floor and a polished top
     * of the same material — and refusing the second pick would say it is not.
     */
    const committedStoneIds = useMemo(
        () =>
            new Set(
                projects.flatMap((project) =>
                    project.stones.map((selection) => selection.stoneId),
                ),
            ),
        [projects],
    );

    const toggleIn = (chosen: string[], id: string): string[] =>
        chosen.includes(id)
            ? chosen.filter((entry) => entry !== id)
            : [...chosen, id];

    const toggleStone = (id: string) => {
        setDraftValue('stoneIds', toggleIn(draftStoneIds, id), {
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
        const field = STANDALONE_SERVICE_IDS.has(id)
            ? 'standaloneServiceIds'
            : 'serviceIds';
        const options = {
            shouldDirty: true,
            shouldValidate: hasDraftContinued.current,
        };

        setDraftValue(field, toggleIn(getDraftValues(field), id), options);
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

    /** A fresh project, starting from the services list. */
    const startProject = () => {
        clearDraft();
        setStep(SERVICES_STEP);
    };

    /**
     * Walk away from the project in progress, from the strip on steps 1 and 2.
     *
     * Review rather than the requirements step, because there is no longer a list of
     * projects in between: Review is the only screen that shows what has already
     * been added, so it is the only one a reader who has decided not to finish
     * this project can usefully be sent to.
     */
    const cancelExcursion = () => {
        clearDraft();
        setStep(REVIEW_STEP);
    };

    /**
     * One committed project, reopened in the requirements step.
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
     * A project that is a service standing on its own goes back into the draft's
     * queue rather than into its services, because that is how the requirements step
     * knows to ask for a site and a date instead of a material: the queue being
     * what is there and this project's own service not being in it is exactly
     * what "this project is the service" means.
     */
    const openProject = (id: string) => {
        const project = getValues('projects').find((entry) => entry.id === id);

        if (project === undefined) {
            return;
        }

        const isStandalone = project.stones.length === 0;

        draftMethods.reset({
            name: project.name,
            stoneIds: project.stones.map((selection) => selection.stoneId),
            serviceIds: isStandalone ? [] : [...project.serviceIds],
            standaloneServiceIds: isStandalone ? [...project.serviceIds] : [],
            stones: project.stones.map((selection) => ({ ...selection })),
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
        setStep(REQUIREMENTS_STEP);
    };

    /**
     * Name the draft after the service at the head of the queue, so the
     * requirements form has something to title itself with.
     *
     * Seeded rather than asked for because there is nothing to ask: the service is
     * what the reader asked for and is therefore what this piece of work is
     * called, and a field for it could only ever hold the value this writes into
     * it. The form hides it rather than showing it read-only, because a name the
     * reader cannot change is a question they were not asked.
     *
     * Takes the queue as an argument rather than reading it back, so it does not
     * depend on a reset having landed in the form's values before it runs.
     *
     * The head is the one being specified and committing takes it off, so the
     * count in the requirements form is always the truth about what is left.
     */
    const seedStandaloneName = (queue: string[]): boolean => {
        const [next] = queue;

        if (next === undefined) {
            return false;
        }

        setDraftValue('name', serviceName(next), {
            shouldDirty: true,
            shouldValidate: false,
        });

        return true;
    };

    /**
     * Give the draft a specification row per chosen stone, keeping the ones that
     * were already filled in.
     *
     * Rows are made here rather than at step 2 because a count is only asked for
     * once the reader knows they are staying on this step: a material added and
     * removed in the grid never has to be given a count at all.
     */
    const seedDraftStones = () => {
        const picked = getDraftValues('stoneIds');
        const current = getDraftValues('stones');
        const alreadyAnswered = new Map(
            current.map((row) => [row.stoneId, row]),
        );

        setDraftValue(
            'stones',
            picked.map(
                (id) =>
                    alreadyAnswered.get(id) ?? {
                        stoneId: id,
                        quantity: 1,
                        edge: '',
                        finish: '',
                    },
            ),
        );
    };

    /**
     * Step 2 onward, once the draft has at least one stone in it.
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
            setStep(REQUIREMENTS_STEP);
        }
    };

    /**
     * Step 1 onward, and the point at which the reader's answer decides the rest
     * of their path.
     *
     * If everything they picked stands on its own there is no material to choose,
     * so step 2 has nothing to ask; they go straight to the requirements step with the
     * first of those services under its own name and work through them one at a
     * time from there.
     *
     * Anything they picked alongside those services carries on to the stones as
     * usual, and the queued services wait behind it for their own forms.
     */
    const continueFromServices = async () => {
        hasDraftContinued.current = true;

        const valid = await triggerDraft(
            ['serviceIds', 'standaloneServiceIds'],
            { shouldFocus: true },
        );

        if (!valid) {
            return;
        }

        if (getDraftValues('serviceIds').length > 0) {
            setStep(STONES_STEP);

            return;
        }

        /*
         * Straight to the requirements step rather than to the stones: there is no
         * material to choose, so the only thing left to ask is where the work is
         * and when it is wanted, and that is a question about this piece of work
         * alone. Each of the queued services gets a form of its own from here,
         * one after the other.
         */
        seedStandaloneName(getDraftValues('standaloneServiceIds'));
        hasDraftContinued.current = false;
        setStep(REQUIREMENTS_STEP);
    };

    /**
     * The draft, checked whole and then committed.
     *
     * Checked against the draft's own schema rather than against any one step's,
     * because this is the one press that takes all three steps' answers together
     * and puts them on the inquiry; a reader who skipped nothing should never be
     * stopped by a rule they have already satisfied.
     *
     * Where it lands is decided by what is left in the queue of services that
     * stand on their own, and by nothing else. There is one destination for a
     * commit — the sheet — and one place the queue can send a reader instead: a
     * press that writes out a project and then asks about the next service is one
     * press, and refusing to say so would leave the reader wondering whether the
     * project they had just filled in was kept.
     *
     * The service at the head of the queue is the one committed here, as a
     * project of its own: a survey visit asked for with a countertop is still a
     * survey of somebody else's stone, and folding it into the countertop's
     * project would say the reader wants that material for it. A draft that has
     * stone of its own is committed beside that queue rather than out of it,
     * because a countertop and the survey of the site it is going into are two
     * pieces of work and only one of them has material.
     */
    const commitDraft = async () => {
        hasDraftContinued.current = true;

        const valid = await triggerDraft(undefined, { shouldFocus: true });

        if (!valid) {
            return;
        }

        const draft = getDraftValues();
        const [head, ...queue] = draft.standaloneServiceIds;
        const isStandalone =
            draft.serviceIds.length === 0 && head !== undefined;

        const project = {
            id: editingProjectId ?? newProjectId(),
            name: isStandalone ? serviceName(head) : draft.name,
            serviceIds: isStandalone ? [head] : [...draft.serviceIds],
            stones: isStandalone ? [] : draft.stones.map((row) => ({ ...row })),
            location: draft.location,
            landmark: draft.landmark,
            areaSqm: draft.areaSqm,
            neededBy: draft.neededBy,
            neededByTime: draft.neededByTime,
            notes: draft.notes,
            attachments: draft.attachments.map((attachment) => ({
                ...attachment,
            })),
        };

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

        /*
         * Straight back to the requirements step for the next of the queued services,
         * with the committed project left behind rather than wiped: the draft has
         * to come down to that project being committed so the site and the date the
         * reader just gave it are not the next project's answers, and the queue has
         * to be shortened so the form is not asked about this service again.
         *
         * The whole queue is carried over rather than only the tail of it, because
         * a project of stone is committed beside the queue rather than out of it:
         * a countertop asked for alongside a survey is two pieces of work, and the
         * survey is still owed a form of its own once the countertop is written
         * down. Dropping the queue here would lose a service the reader had
         * picked and been told all along that they would be asked about.
         */
        const remaining = isStandalone ? queue : draft.standaloneServiceIds;

        if (remaining.length > 0) {
            draftMethods.reset({
                ...draftDefaults,
                standaloneServiceIds: remaining,
            });
            seedStandaloneName(remaining);
            hasDraftContinued.current = false;

            return;
        }

        clearDraft();
        setStep(REVIEW_STEP);
    };

    /**
     * Take a project out, once the reader has said which one.
     *
     * The sheet keeps its place either way, because it has a way to add a project
     * of its own: sending a reader to the services list instead would be a step
     * backwards past a screen they did not come back for, and the empty sheet it
     * would leave them at has only one thing on it.
     */
    const confirmRemoveProject = () => {
        if (removeTarget === null) {
            return;
        }

        setValue(
            'projects',
            getValues('projects').filter(
                (project) => project.id !== removeTarget,
            ),
            { shouldDirty: true },
        );

        setProjectMessages((messages) =>
            Object.fromEntries(
                Object.entries(messages).filter(([id]) => id !== removeTarget),
            ),
        );

        setRemoveTarget(null);
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

        await sendInquiry();
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
     * The project's name is read out first because the panel names the request
     * and the form is about to stop holding it.
     *
     * On failure nothing is touched at all — that promise is the reason the
     * panel offers a retry rather than making the reader rebuild the request.
     */
    const sendInquiry = async () => {
        if (isSendingRef.current) {
            return;
        }

        isSendingRef.current = true;
        setIsSending(true);

        try {
            await submitInquiry(getValues());
        } catch {
            isSendingRef.current = false;
            setIsSending(false);
            setResult({ status: 'error' });

            return;
        }

        /*
         * The request is known by the name of its first project, the same answer
         * `inquiryTitle` gives every other page that lists one. Read out before the
         * form is emptied, because the panel names the request and the form is
         * about to stop holding it.
         */
        const title = getValues('projects')[0]?.name ?? '';

        /*
         * A fresh inquiry starts empty rather than from the request just sent,
         * and the draft goes with it: a half-built project is the one thing that
         * must not survive into the next inquiry.
         */
        reset(inquiryDefaults);
        clearDraft();
        setSearch('');
        setFilter('latest');
        setValidationAlertErrors([]);
        setSectionMessages({});
        setProjectMessages({});
        setStep(SERVICES_STEP);

        isSendingRef.current = false;
        setIsSending(false);
        setResult({ status: 'success', title });
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
    const canSeeStoneProfile = can('view home');
    const canChat = can('view message');
    const canSeeQuotation = can('view quotation');
    const canSeeInquiry = can('view inquiry');

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
        { value: 'latest', label: 'Latest' },
        { value: 'recommended', label: 'Recommended' },
        { value: 'saved', label: 'Saved' },
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

    const draftBannerLabel =
        editingProjectNumber > 0
            ? `Editing Project ${editingProjectNumber}`
            : `Adding Project ${projects.length + 1}`;

    /*
     * The strip only appears once there is more than one project in play. On the
     * very first one there is nothing it could tell the reader.
     */
    const showDraftBanner = projects.length > 0 || editingProjectNumber > 0;

    const removeProjectNumber = removeTarget
        ? projects.findIndex((project) => project.id === removeTarget) + 1
        : 0;

    /*
     * The service the requirements form is about, when the project in progress is
     * one that stands on its own.
     *
     * Read off the two service lists together rather than off a flag: a draft is
     * such a project when nothing in it is wanted for stone and something in it is
     * queued to become one. Handed down rather than worked out in the form too,
     * because the page has to answer the same question to decide what the commit
     * button says and where Back goes, and two readings of one pair of lists would
     * be free to disagree.
     */
    const draftStandaloneServiceId =
        draftServiceIds.length === 0
            ? (draftStandaloneServiceIds[0] ?? '')
            : '';

    const standalone =
        draftStandaloneServiceId === ''
            ? null
            : {
                  serviceId: draftStandaloneServiceId,
                  total: draftStandaloneServiceIds.length,
              };

    /*
     * Whether committing leaves another of the queued services to specify, which
     * is what the commit button has to say before it is pressed: "Next service"
     * promises the reader this press saves what they have filled in and takes
     * them to the next one, and the promise is only honest if the press does both.
     */
    const commitLeavesMore =
        draftStandaloneServiceIds.length > (standalone === null ? 0 : 1);

    const requirementsSubmitLabel = commitLeavesMore
        ? 'Next service'
        : editingProjectId !== null
          ? 'Back to Review'
          : 'Add to inquiry';

    /*
     * A finished request is past every step, so the rail shows all four done. A
     * failure is not a step of its own, so it leaves the reader where they were:
     * they are looking at the request that failed, not at a new question.
     */
    const stepperCurrent =
        result?.status === 'success' ? STEPS.length + 1 : step;

    return (
        <FormProvider {...methods}>
            <div className="mx-auto w-full max-w-6xl">
                <PageHeading
                    title="Inquire"
                    description={
                        result?.status === 'success'
                            ? 'Your request has been sent'
                            : STEP_DESCRIPTIONS[step]
                    }
                />

                <InquiryStepper steps={STEPS} current={stepperCurrent} />

                {result !== null ? (
                    <InquiryResult
                        status={result.status}
                        title={result.status === 'success' ? result.title : ''}
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
                        onRemoveProject={setRemoveTarget}
                        onAddProject={startProject}
                        sectionMessages={sectionMessages}
                        projectMessages={projectMessages}
                        validationErrors={validationAlertErrors}
                        locked={isSending}
                        onRequestQuote={requestQuote}
                    />
                ) : (
                    /*
                     * Steps 1 to 3 with a project open all read the draft form, so
                     * they sit inside its own provider. Shadowing the inquiry's
                     * provider for this subtree is the point: neither can reach an
                     * answer the other owns.
                     */
                    <FormProvider {...draftMethods}>
                        {step === STONES_STEP ? (
                            <>
                                {showDraftBanner ? (
                                    <ProjectBanner
                                        label={draftBannerLabel}
                                        onCancel={cancelExcursion}
                                    />
                                ) : null}

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
                                                    className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                                                        className={`-my-2.5 px-2.5 py-2.5 transition-opacity hover:opacity-70 ${
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
                                                onClick={() =>
                                                    setFilter('latest')
                                                }
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
                                    <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
                                        {visibleStones.map((product) => (
                                            <StoneCard
                                                key={product.id}
                                                item={product}
                                                aspect="aspect-[4/3]"
                                                saved={savedIds.has(product.id)}
                                                onToggleSave={() =>
                                                    toggleSave(product.id)
                                                }
                                                selectable
                                                selected={draftStoneIds.includes(
                                                    product.id,
                                                )}
                                                onSelect={() =>
                                                    toggleStone(product.id)
                                                }
                                                note={
                                                    committedStoneIds.has(
                                                        product.id,
                                                    )
                                                        ? 'Already added'
                                                        : undefined
                                                }
                                                // A stone profile is the Home page,
                                                // so without that permission the
                                                // new tab would only show the
                                                // forbidden page.
                                                viewHref={
                                                    canSeeStoneProfile
                                                        ? homeShow.url({
                                                              stone: product.id,
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
                                        {projects.length > 0 ? (
                                            <p className="text-sm font-bold text-foreground">
                                                {projects.length}{' '}
                                                {projects.length === 1
                                                    ? 'project'
                                                    : 'projects'}{' '}
                                                added
                                            </p>
                                        ) : null}
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
                                         * Continue is left enabled and the
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
                                        {/*
                                         * The way back to the services that got
                                         * them here, which is the one answer on
                                         * the previous step that decides whether
                                         * this step has anything to ask.
                                         */}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setStep(SERVICES_STEP)
                                            }
                                            className="w-full cursor-pointer sm:w-auto"
                                        >
                                            <ArrowLeft aria-hidden />
                                            Back
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={continueFromStones}
                                            className="w-full sm:w-auto"
                                        >
                                            Continue
                                        </Button>
                                    </div>
                                </div>
                            </>
                        ) : step === SERVICES_STEP ? (
                            <>
                                {showDraftBanner ? (
                                    <ProjectBanner
                                        label={draftBannerLabel}
                                        onCancel={cancelExcursion}
                                    />
                                ) : null}

                                <h3 className="text-lg font-bold tracking-[-0.03em]">
                                    Our services
                                </h3>

                                {/*
                                 * A list, not a grid: the stone grid two steps
                                 * on is the same control all over again, and the
                                 * rules between full-width rows are what keep
                                 * this from reading as it.
                                 */}
                                <div className="mt-4 divide-y divide-sidebar-border border-y border-sidebar-border">
                                    {serviceOptions.map((service, index) => (
                                        <ServiceCard
                                            key={service.id}
                                            service={service}
                                            index={index}
                                            selected={chosenServiceIds.includes(
                                                service.id,
                                            )}
                                            onSelect={() =>
                                                toggleService(service.id)
                                            }
                                        />
                                    ))}
                                </div>

                                {/* Sticky for the same reason as step 2's. */}
                                <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                        {projects.length > 0 ? (
                                            <p className="text-sm font-bold text-foreground">
                                                {projects.length}{' '}
                                                {projects.length === 1
                                                    ? 'project'
                                                    : 'projects'}{' '}
                                                added
                                            </p>
                                        ) : null}
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
                                     * No Back: this is the first step, and what
                                     * Continue does next is decided by what has
                                     * been picked here rather than by where the
                                     * reader came from.
                                     */}
                                    <Button
                                        type="button"
                                        onClick={continueFromServices}
                                        className="w-full sm:w-auto"
                                    >
                                        Continue
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <InquiryProjectForm
                                projectNumber={
                                    editingProjectNumber > 0
                                        ? editingProjectNumber
                                        : projects.length + 1
                                }
                                standalone={standalone}
                                submitLabel={requirementsSubmitLabel}
                                onChangeServices={() => setStep(SERVICES_STEP)}
                                onCommit={commitDraft}
                                onBack={() =>
                                    setStep(
                                        standalone === null
                                            ? STONES_STEP
                                            : SERVICES_STEP,
                                    )
                                }
                            />
                        )}
                    </FormProvider>
                )}
            </div>

            {/*
             * One dialog asking before a project goes. Every project can now be
             * removed from the one screen that lists it, so there is only ever one
             * set of words asking.
             */}
            <Dialog
                open={removeTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRemoveTarget(null);
                    }
                }}
            >
                <DialogContent className="rounded-none sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold tracking-[-0.03em]">
                            Remove Project {removeProjectNumber}?
                        </DialogTitle>
                        <DialogDescription>
                            The stones, the counts and the services it holds
                            will be taken off this inquiry. You can add them
                            again straight afterwards.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRemoveTarget(null)}
                        >
                            Keep project
                        </Button>
                        <Button type="button" onClick={confirmRemoveProject}>
                            <Trash2 aria-hidden />
                            Remove project
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </FormProvider>
    );
};

export default Inquire;

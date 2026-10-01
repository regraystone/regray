import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import InquiryRequirementsStep from '@/components/inquiry-requirements';
import InquiryResult from '@/components/inquiry-result';
import InquiryReview from '@/components/inquiry-review';
import InquiryStepper from '@/components/inquiry-stepper';
import InputError from '@/components/input-error';
import PageHeading from '@/components/page-heading';
import ServiceCard from '@/components/service-card';
import StoneCard from '@/components/stone-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    serviceOptions,
    stones,
    stoneProfiles,
    type FeedItem,
} from '@/data/constant';
import { usePermissions } from '@/hooks/use-permissions';
import { useSavedStones } from '@/hooks/use-saved-stones';
import {
    inquiryDefaults,
    inquirySchema,
    REQUIREMENTS_STEP,
    SERVICES_STEP,
    STONES_STEP,
    STEP_FIELDS,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';
import { show as homeShow } from '@/routes/home';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as quoteIndex } from '@/routes/quote';

const STEPS = ['Stones', 'Services', 'Requirements', 'Review'];

/** Review is the last step, and owns no fields of its own to validate. */
const REVIEW_STEP = REQUIREMENTS_STEP + 1;

const STEP_DESCRIPTIONS: Record<number, string> = {
    [STONES_STEP]: "Choose the stones you're interested in",
    [SERVICES_STEP]: 'Choose the services you need',
    [REQUIREMENTS_STEP]: 'Tell us about your project',
    [REVIEW_STEP]: 'Check your inquiry before sending it',
};

/**
 * Which step owns which field, read off `STEP_FIELDS` rather than written out
 * again. The whole-form check has to say "change your stones" beside the one
 * stone complaint, and a second hand-written list of the same pairing could
 * drift from the one Continue already validates against.
 */
const STEP_OF_FIELD = new Map<string, number>(
    Object.entries(STEP_FIELDS).flatMap(([step, fields]) =>
        (fields ?? []).map((field) => [field as string, Number(step)]),
    ),
);

/**
 * Said once, in one place, so the section header and the list above the sheet
 * cannot offer different advice about the same wait.
 */
const WAITING_FOR_UPLOADS = 'Wait for uploads to finish.';

type InquiryResultState =
    | { status: 'success'; projectName: string }
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
 * endpoint requires — and because the attachments are still `File` objects
 * rather than paths, that post has to be a multipart `FormData` with the files
 * appended as they are, not a JSON body.
 */
const submitInquiry = (values: InquiryFormValues): Promise<void> =>
    new Promise((resolve) => {
        /*
         * Scaled by what is going with it, for the same reason the uploader's
         * fake is: a request carrying four plans should not take the same moment
         * as one carrying nothing. Capped, so it stays inside 1.2 to 1.8 seconds.
         */
        const megabytes =
            values.attachments.reduce(
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

const InquireSkeleton = () => (
    <div className="mx-auto w-full max-w-6xl">
        <div className="mb-8 flex flex-col gap-3 border-b border-sidebar-border pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-2">
                <Skeleton className="h-8 w-40" />
                <Skeleton className="h-4 w-56" />
            </div>
        </div>

        <div className="mx-auto mb-8 w-full max-w-2xl">
            <Skeleton className="h-16 w-full rounded-none" />
        </div>

        <div className="mb-6 border-b border-sidebar-border pb-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <Skeleton className="h-9 w-full sm:max-w-xs" />
                <Skeleton className="hidden h-4 w-64 sm:block" />
                <div className="flex items-center gap-2.5 sm:hidden">
                    {Array.from({ length: 3 }, (_, index) => (
                        <Skeleton
                            key={index}
                            className="h-7 w-20 rounded-none"
                        />
                    ))}
                </div>
            </div>
        </div>

        <div className="grid grid-cols-2 gap-4 md:gap-5 lg:grid-cols-3">
            {Array.from({ length: 6 }, (_, index) => (
                <div
                    key={index}
                    className="border border-sidebar-border bg-background"
                >
                    <Skeleton className="aspect-[4/3] w-full rounded-none" />
                </div>
            ))}
        </div>
    </div>
);

const Inquire = () => {
    const [isLoading, setIsLoading] = useState(true);
    const [step, setStep] = useState(STONES_STEP);
    const [filter, setFilter] = useState<StoneFilter>('latest');
    const [search, setSearch] = useState('');
    const [editingFromReview, setEditingFromReview] = useState(false);
    const [result, setResult] = useState<InquiryResultState>(null);
    const [isSending, setIsSending] = useState(false);
    const [validationAlertErrors, setValidationAlertErrors] = useState<
        string[]
    >([]);
    const [sectionMessages, setSectionMessages] = useState<
        Partial<Record<number, string>>
    >({});

    /*
     * Every answer the reader has given lives in this one form, which is why
     * stepping back to swap a stone costs neither the services already picked
     * nor a typed project name, and why a finished upload survives the round
     * trip: the uploads in particular have nowhere else to live, since there is
     * nowhere to send them yet.
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

    const {
        setValue,
        trigger,
        watch,
        getValues,
        reset,
        formState: { errors },
    } = methods;

    const stoneIds = watch('stoneIds');
    const serviceIds = watch('serviceIds');

    /*
     * Whether Continue has been pressed at all, not which step it was pressed
     * on. A card is checked once there is something to correct, so picking the
     * first stone does not raise an objection the reader has not caused yet.
     */
    const hasContinued = useRef(false);
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

    const toggleIn = (chosen: string[], id: string): string[] =>
        chosen.includes(id)
            ? chosen.filter((entry) => entry !== id)
            : [...chosen, id];

    const toggleStone = (id: string) => {
        setValue('stoneIds', toggleIn(stoneIds, id), {
            shouldDirty: true,
            shouldValidate: hasContinued.current,
        });
    };

    const toggleService = (id: string) => {
        setValue('serviceIds', toggleIn(serviceIds, id), {
            shouldDirty: true,
            shouldValidate: hasContinued.current,
        });
    };

    /**
     * Onward, but only once the step in hand is answered.
     *
     * `shouldFocus` is what sends the reader to the first bad control, in the
     * order the fields are declared, and it stops at the first one it can
     * focus. Back deliberately takes none of this: moving backwards is not a
     * claim about the answers, and blocking it would trap a reader who has
     * decided they want to change a stone instead.
     */
    const continueTo = async (next: number) => {
        hasContinued.current = true;

        const valid = await trigger(STEP_FIELDS[step] ?? [], {
            shouldFocus: true,
        });

        if (valid) {
            setStep(next);

            // Back at the sheet, so the next step is an ordinary step again.
            setEditingFromReview(false);
        }
    };

    /**
     * The way forward while the reader is fixing one answer.
     *
     * Reached from Review, the step's Continue button is not "onward" but "back
     * to the sheet", and it returns there rather than pushing the reader through
     * the steps they did not come to change. The check still runs, because a
     * half-corrected step that used to be valid must not go back unnoticed.
     */
    const returnToReview = async () => {
        hasContinued.current = true;

        const valid = await trigger(STEP_FIELDS[step] ?? [], {
            shouldFocus: true,
        });

        if (valid) {
            setEditingFromReview(false);
            setStep(REVIEW_STEP);
        }
    };

    /** A Change button on the sheet: go and fix that one section. */
    const changeStep = (target: number) => {
        setEditingFromReview(true);
        setStep(target);
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
     * sheet, with the reasons above it and beside each section's own Change
     * button.
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
        if (
            values.attachments.some(
                (attachment) => attachment.status === 'uploading',
            )
        ) {
            setSectionMessages({ [REQUIREMENTS_STEP]: WAITING_FOR_UPLOADS });
            setValidationAlertErrors([WAITING_FOR_UPLOADS]);

            return;
        }

        const parsed = inquirySchema.safeParse(values);

        const valid = await trigger();

        if (!parsed.success || !valid) {
            const issues = parsed.success ? [] : parsed.error.issues;
            const perSection: Partial<Record<number, string>> = {};

            /*
             * One short line per section, taken from the first complaint about
             * that section. The whole list is said once at the top; repeating
             * every rule under every header would turn a sheet into a form
             * again.
             */
            for (const issue of issues) {
                const field = String(issue.path[0] ?? '');
                const owner = STEP_OF_FIELD.get(field);

                if (owner !== undefined && perSection[owner] === undefined) {
                    perSection[owner] = issue.message;
                }
            }

            setSectionMessages(perSection);
            setValidationAlertErrors(issues.map((issue) => issue.message));

            return;
        }

        setSectionMessages({});
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
     * The project name is read out first because the panel names the request and
     * the form is about to stop holding it.
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

        const projectName = getValues('projectName').trim();

        /*
         * A fresh inquiry starts empty rather than from the request just sent,
         * and the stepper's own "has Continue been pressed" flag goes with it, so
         * the new stone grid raises no objection the reader has not caused.
         */
        reset(inquiryDefaults);
        hasContinued.current = false;
        setEditingFromReview(false);
        setSearch('');
        setFilter('latest');
        setValidationAlertErrors([]);
        setSectionMessages({});
        setStep(STONES_STEP);

        isSendingRef.current = false;
        setIsSending(false);
        setResult({ status: 'success', projectName });
    };

    /**
     * Start again on a blank form. The form was already emptied when the request
     * was sent, so this is a clean start with nothing carried over.
     */
    const inquireAgain = () => {
        setResult(null);
        setStep(STONES_STEP);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    if (isLoading) {
        return <InquireSkeleton />;
    }

    const hasSelection = stoneIds.length > 0;
    const hasServices = serviceIds.length > 0;
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
                        projectName={
                            result.status === 'success'
                                ? result.projectName
                                : ''
                        }
                        canChat={canChat}
                        inquiriesHref={inquiriesHref}
                        onTryAgain={sendInquiry}
                        onBackToReview={() => setResult(null)}
                        onInquireAgain={inquireAgain}
                        submitting={isSending}
                    />
                ) : step === STONES_STEP ? (
                    <>
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
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Search stones"
                                        aria-label="Search stones"
                                        className="rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                                    />
                                    {search ? (
                                        <button
                                            type="button"
                                            onClick={() => setSearch('')}
                                            aria-label="Clear search"
                                            className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center border border-transparent text-secondary-foreground transition-opacity hover:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                        >
                                            <X className="size-4" aria-hidden />
                                        </button>
                                    ) : null}
                                </div>

                                <div
                                    className="hidden items-center gap-5 text-sm sm:ml-auto sm:flex"
                                    role="tablist"
                                    aria-label="Filter stones"
                                >
                                    {stoneTabs.map((tab) => {
                                        const isActive = filter === tab.value;

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
                                        const isActive = filter === tab.value;

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
                                        onClick={() => setFilter('latest')}
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
                                        selected={stoneIds.includes(product.id)}
                                        onSelect={() => toggleStone(product.id)}
                                        // A stone profile is the Home page, so
                                        // without that permission the new tab
                                        // would only show the forbidden page.
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
                         * Sticky so the way forward stays reachable while a long
                         * grid scrolls past, and opaque so the grid does not read
                         * through it.
                         */}
                        <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                <p
                                    aria-live="polite"
                                    className="text-sm font-bold text-foreground"
                                >
                                    {stoneIds.length}{' '}
                                    {stoneIds.length === 1 ? 'stone' : 'stones'}{' '}
                                    selected
                                </p>
                                {hasSelection ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setValue('stoneIds', [], {
                                                shouldDirty: true,
                                                shouldValidate:
                                                    hasContinued.current,
                                            })
                                        }
                                        className="h-7 cursor-pointer px-1.5 text-xs"
                                    >
                                        Clear
                                    </Button>
                                ) : null}
                                {/*
                                 * There is no control here to focus — a grid of
                                 * cards is not a field — so Continue is left
                                 * enabled and the objection is said here instead of
                                 * by refusing to let the reader onwards.
                                 */}
                                <InputError
                                    message={errors.stoneIds?.message}
                                    role="alert"
                                />
                            </div>

                            <Button
                                type="button"
                                onClick={() =>
                                    editingFromReview
                                        ? returnToReview()
                                        : continueTo(SERVICES_STEP)
                                }
                                className="w-full shrink-0 sm:w-auto"
                            >
                                {editingFromReview
                                    ? 'Back to Review'
                                    : 'Continue'}
                            </Button>
                        </div>
                    </>
                ) : step === SERVICES_STEP ? (
                    <>
                        <h3 className="text-lg font-bold tracking-[-0.03em]">
                            Our services
                        </h3>

                        {/*
                         * A list, not a grid: step 1 is already a grid of stone
                         * cards, so the services get rules between full-width rows
                         * to keep the two steps from reading as the same control
                         * twice.
                         */}
                        <div className="mt-4 divide-y divide-sidebar-border border-y border-sidebar-border">
                            {serviceOptions.map((service, index) => (
                                <ServiceCard
                                    key={service.id}
                                    service={service}
                                    index={index}
                                    selected={serviceIds.includes(service.id)}
                                    onSelect={() => toggleService(service.id)}
                                />
                            ))}
                        </div>

                        {/* Sticky for the same reason as step 1's. */}
                        <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                                <p
                                    aria-live="polite"
                                    className="text-sm font-bold text-foreground"
                                >
                                    {serviceIds.length}{' '}
                                    {serviceIds.length === 1
                                        ? 'service'
                                        : 'services'}{' '}
                                    selected
                                </p>
                                {hasServices ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setValue('serviceIds', [], {
                                                shouldDirty: true,
                                                shouldValidate:
                                                    hasContinued.current,
                                            })
                                        }
                                        className="h-7 cursor-pointer px-1.5 text-xs"
                                    >
                                        Clear
                                    </Button>
                                ) : null}
                                <InputError
                                    message={errors.serviceIds?.message}
                                    role="alert"
                                />
                            </div>

                            <div className="flex w-full shrink-0 flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setStep(STONES_STEP)}
                                    className="w-full cursor-pointer sm:w-auto"
                                >
                                    <ArrowLeft aria-hidden />
                                    Back
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        editingFromReview
                                            ? returnToReview()
                                            : continueTo(REQUIREMENTS_STEP)
                                    }
                                    className="w-full sm:w-auto"
                                >
                                    {editingFromReview
                                        ? 'Back to Review'
                                        : 'Continue'}
                                </Button>
                            </div>
                        </div>
                    </>
                ) : step === REQUIREMENTS_STEP ? (
                    <InquiryRequirementsStep
                        onSubmit={() =>
                            editingFromReview
                                ? returnToReview()
                                : continueTo(REVIEW_STEP)
                        }
                        onBack={() => setStep(SERVICES_STEP)}
                        submitLabel={
                            editingFromReview ? 'Back to Review' : undefined
                        }
                    />
                ) : (
                    <InquiryReview
                        onChangeStones={() => changeStep(STONES_STEP)}
                        onChangeServices={() => changeStep(SERVICES_STEP)}
                        onChangeDetails={() => changeStep(REQUIREMENTS_STEP)}
                        sectionMessages={sectionMessages}
                        validationErrors={validationAlertErrors}
                        locked={isSending}
                        onRequestQuote={requestQuote}
                    />
                )}
            </div>
        </FormProvider>
    );
};

export default Inquire;

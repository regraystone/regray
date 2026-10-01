import { zodResolver } from '@hookform/resolvers/zod';
import { ArrowLeft, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import InquiryRequirementsStep from '@/components/inquiry-requirements';
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

const STEPS = ['Stones', 'Services', 'Requirements', 'Review'];

/** Review is the last step and is not built yet, so it is a placeholder. */
const REVIEW_STEP = REQUIREMENTS_STEP + 1;

const STEP_DESCRIPTIONS: Record<number, string> = {
    [STONES_STEP]: "Choose the stones you're interested in",
    [SERVICES_STEP]: 'Choose the services you need',
    [REQUIREMENTS_STEP]: 'Tell us about your project',
    [REVIEW_STEP]: 'Check your inquiry before sending it',
};

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

    const { can } = usePermissions();
    const { savedIds, toggleSave } = useSavedStones();

    useEffect(() => {
        const timer = window.setTimeout(() => setIsLoading(false), SKELETON_MS);

        return () => window.clearTimeout(timer);
    }, []);

    /*
     * Both grids are long enough to leave the reader scrolled well past the
     * heading, so a step change takes them back to the top.
     */
    useEffect(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [step]);

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
        }
    };

    if (isLoading) {
        return <InquireSkeleton />;
    }

    const hasSelection = stoneIds.length > 0;
    const hasServices = serviceIds.length > 0;
    const canSeeStoneProfile = can('view home');

    const stoneTabs: Array<{ value: StoneFilter; label: string }> = [
        { value: 'latest', label: 'Latest' },
        { value: 'recommended', label: 'Recommended' },
        { value: 'saved', label: 'Saved' },
    ];

    return (
        <FormProvider {...methods}>
            <div className="mx-auto w-full max-w-6xl">
                <PageHeading
                    title="Inquire"
                    description={STEP_DESCRIPTIONS[step]}
                />

                <InquiryStepper steps={STEPS} current={step} />

                {step === STONES_STEP ? (
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
                                onClick={() => continueTo(SERVICES_STEP)}
                                className="w-full shrink-0 sm:w-auto"
                            >
                                Continue
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
                                        continueTo(REQUIREMENTS_STEP)
                                    }
                                    className="w-full sm:w-auto"
                                >
                                    Continue
                                </Button>
                            </div>
                        </div>
                    </>
                ) : step === REQUIREMENTS_STEP ? (
                    <InquiryRequirementsStep
                        onSubmit={() => continueTo(REVIEW_STEP)}
                        onBack={() => setStep(SERVICES_STEP)}
                    />
                ) : (
                    <section className="flex min-h-[40vh] flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
                        <p className="text-lg font-bold tracking-[-0.03em]">
                            {STEPS[step - 1]}: coming next
                        </p>
                        <p className="max-w-sm text-sm text-secondary-foreground">
                            This step is not built yet. Your stones, services,
                            details and attachments are all kept.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(REQUIREMENTS_STEP)}
                        >
                            <ArrowLeft aria-hidden />
                            Back
                        </Button>

                        {/*
                         * Nothing is sent from this page yet. When the send button
                         * arrives it should check the whole form in one pass before
                         * anything leaves the browser —
                         * `inquirySchema.safeParse(getValues())` — rather than
                         * trusting the step-by-step checks, because the reader can
                         * still reach back and change an earlier step.
                         */}
                    </section>
                )}
            </div>
        </FormProvider>
    );
};

export default Inquire;

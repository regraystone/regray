import { ArrowLeft, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import InquiryStepper from '@/components/inquiry-stepper';
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
import { show as homeShow } from '@/routes/home';

const STEPS = ['Stones', 'Services', 'Requirements', 'Review'];

const STONES_STEP = 1;
const SERVICES_STEP = 2;

/** Steps 3 and 4 are not built yet, so everything past Services is a placeholder. */
const NEXT_STEP = SERVICES_STEP + 1;

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
    const [selected, setSelected] = useState<string[]>([]);
    /*
     * The two steps hold their own selections instead of resetting when the
     * step changes, because stepping back to swap a stone should not cost the
     * services already picked, and the same the other way round.
     */
    const [selectedServices, setSelectedServices] = useState<string[]>([]);

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

    const toggleSelected = (id: string) => {
        setSelected((previous) =>
            previous.includes(id)
                ? previous.filter((entry) => entry !== id)
                : [...previous, id],
        );
    };

    const toggleSelectedService = (id: string) => {
        setSelectedServices((previous) =>
            previous.includes(id)
                ? previous.filter((entry) => entry !== id)
                : [...previous, id],
        );
    };

    if (isLoading) {
        return <InquireSkeleton />;
    }

    const hasSelection = selected.length > 0;
    const hasServices = selectedServices.length > 0;
    const canSeeStoneProfile = can('view home');

    const stoneTabs: Array<{ value: StoneFilter; label: string }> = [
        { value: 'latest', label: 'Latest' },
        { value: 'recommended', label: 'Recommended' },
        { value: 'saved', label: 'Saved' },
    ];

    return (
        <div className="mx-auto w-full max-w-6xl">
            <PageHeading
                title="Inquire"
                description={
                    step === STONES_STEP
                        ? "Choose the stones you're interested in"
                        : 'Choose the services you need'
                }
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
                                            onClick={() => setFilter(tab.value)}
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
                                            onClick={() => setFilter(tab.value)}
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
                                    onToggleSave={() => toggleSave(product.id)}
                                    selectable
                                    selected={selected.includes(product.id)}
                                    onSelect={() => toggleSelected(product.id)}
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
                                {selected.length}{' '}
                                {selected.length === 1 ? 'stone' : 'stones'}{' '}
                                selected
                            </p>
                            {hasSelection ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelected([])}
                                    className="h-7 cursor-pointer px-1.5 text-xs"
                                >
                                    Clear
                                </Button>
                            ) : (
                                <p className="text-xs text-secondary-foreground">
                                    Select at least one stone to continue
                                </p>
                            )}
                        </div>

                        <Button
                            type="button"
                            onClick={() => setStep(SERVICES_STEP)}
                            disabled={!hasSelection}
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
                                selected={selectedServices.includes(service.id)}
                                onSelect={() =>
                                    toggleSelectedService(service.id)
                                }
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
                                {selectedServices.length}{' '}
                                {selectedServices.length === 1
                                    ? 'service'
                                    : 'services'}{' '}
                                selected
                            </p>
                            {hasServices ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelectedServices([])}
                                    className="h-7 cursor-pointer px-1.5 text-xs"
                                >
                                    Clear
                                </Button>
                            ) : (
                                <p className="text-xs text-secondary-foreground">
                                    Select at least one service to continue
                                </p>
                            )}
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
                                onClick={() => setStep(NEXT_STEP)}
                                disabled={!hasServices}
                                className="w-full sm:w-auto"
                            >
                                Continue
                            </Button>
                        </div>
                    </div>
                </>
            ) : (
                <section className="flex min-h-[40vh] flex-col items-center justify-center gap-3 border border-sidebar-border bg-background px-6 py-16 text-center">
                    <p className="text-lg font-bold tracking-[-0.03em]">
                        {STEPS[step - 1]}: coming next
                    </p>
                    <p className="max-w-sm text-sm text-secondary-foreground">
                        This step is not built yet. Your stones and services are
                        kept.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setStep(SERVICES_STEP)}
                    >
                        <ArrowLeft aria-hidden />
                        Back
                    </Button>
                </section>
            )}
        </div>
    );
};

export default Inquire;

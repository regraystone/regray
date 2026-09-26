import { Button } from '@/components/ui/button';

type SectionHeadingProps<T extends string> = {
    title: string;
    tabs?: Array<{ value: T; label: string }>;
    active?: T;
    onTabChange?: (value: T) => void;
};

const SectionHeading = <T extends string>({
    title,
    tabs,
    active,
    onTabChange,
}: SectionHeadingProps<T>) => {
    const hasTabs = Boolean(tabs?.length);

    return (
        <div className="mb-6 flex flex-col gap-4 border-b border-sidebar-border pb-4">
            <div className="flex items-end justify-between gap-4">
                <h2 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl">
                    {title}
                </h2>
                {hasTabs ? (
                    <div
                        className="hidden items-center gap-5 text-sm sm:flex"
                        role="tablist"
                        aria-label={`Filter ${title}`}
                    >
                        {tabs?.map((tab) => {
                            const isActive = active === tab.value;

                            return (
                                <button
                                    key={tab.value}
                                    type="button"
                                    role="tab"
                                    aria-selected={isActive}
                                    onClick={() => onTabChange?.(tab.value)}
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
                ) : null}
            </div>
            {hasTabs ? (
                <div
                    className="flex items-center gap-2.5 sm:hidden"
                    role="tablist"
                    aria-label={`Filter ${title}`}
                >
                    {tabs?.map((tab) => {
                        const isActive = active === tab.value;

                        return (
                            <Button
                                key={tab.value}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                variant={isActive ? 'default' : 'secondary'}
                                size="sm"
                                className="px-3 py-1.5"
                                onClick={() => onTabChange?.(tab.value)}
                            >
                                {tab.label}
                            </Button>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
};

export default SectionHeading;

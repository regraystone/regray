import { Check } from 'lucide-react';

type InquiryStepperProps = {
    steps: string[];
    current: number;
};

/**
 * The progress rail for the inquiry flow.
 *
 * It is presentational: it shows where the user is and does not move them, so
 * every step can decide for itself what the next one is called. Below `sm` the
 * rail is replaced by a single line, because a row of labelled boxes does not
 * fit across 360px — and the line is where the count lives, so "Step 3 of 5"
 * reads the same whether or not the rail is on screen.
 *
 * Each step's box is the only thing that takes up room in the row. The label
 * is positioned under the box and does not count toward the row's width, so a
 * long label ("Services") cannot push the connector lines away from its box,
 * and every connector ends up the same length however many steps there are.
 *
 * A finished step is filled black with a white tick, the current step is an
 * empty box inside a black ring, and a step still to come is a grey outline.
 * The ring is what keeps "here now" from looking like "already done", which is
 * what happened when both were filled.
 */
const InquiryStepper = ({ steps, current }: InquiryStepperProps) => {
    const active = steps[current - 1] ?? '';

    return (
        <nav
            aria-label="Inquiry progress"
            className="mx-auto mb-8 w-full max-w-2xl"
        >
            <p
                aria-live="polite"
                className="text-center text-xs font-bold tracking-[0.05em] text-foreground uppercase sm:hidden"
            >
                Step {current} of {steps.length} · {active}
            </p>

            {/*
             * The horizontal padding leaves room for the first and last labels,
             * which are wider than their boxes and hang out past the row's ends.
             * The bottom padding reserves the height the labels take up.
             */}
            <ol className="hidden items-center px-6 pb-7 sm:flex">
                {steps.map((label, index) => {
                    const number = index + 1;
                    const isActive = number === current;
                    const isDone = number < current;

                    return (
                        <li
                            key={label}
                            aria-current={isActive ? 'step' : undefined}
                            className="flex min-w-0 flex-1 items-center last:flex-none"
                        >
                            <div className="relative shrink-0">
                                <span
                                    aria-hidden
                                    /*
                                     * Three states, and the two that used to
                                     * share a fill no longer do. A finished step
                                     * is solid, because it is settled; the
                                     * current one is a ring on an empty box, the
                                     * same treatment the selectable stone and
                                     * service cards use for "chosen", so being
                                     * the current step looks like being picked
                                     * rather than like being finished.
                                     */
                                    className={`grid size-9 place-items-center rounded-md border text-xs font-bold ${
                                        isActive
                                            ? 'border-primary bg-background text-foreground ring-2 ring-primary'
                                            : isDone
                                              ? 'border-primary bg-primary text-primary-foreground'
                                              : 'border-sidebar-border text-secondary-foreground'
                                    }`}
                                >
                                    {isDone ? (
                                        <Check className="size-4" />
                                    ) : (
                                        number
                                    )}
                                </span>
                                <span
                                    className={`absolute top-full left-1/2 mt-2 -translate-x-1/2 text-xs font-bold tracking-[0.05em] whitespace-nowrap uppercase ${
                                        isActive || isDone
                                            ? 'text-foreground'
                                            : 'text-secondary-foreground'
                                    }`}
                                >
                                    {label}
                                </span>
                            </div>

                            {/* The same small gap on both sides of every box. */}
                            {number < steps.length ? (
                                <span
                                    aria-hidden
                                    className={`mx-2 h-px flex-1 ${
                                        isDone
                                            ? 'bg-foreground'
                                            : 'bg-sidebar-border'
                                    }`}
                                />
                            ) : null}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
};

export default InquiryStepper;

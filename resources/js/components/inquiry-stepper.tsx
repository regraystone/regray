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
 * rail is replaced by a single line, and the line is where the count lives,
 * so "Step 1 of 2" reads the same whether or not the rail is on screen.
 *
 * The steps sit side by side, each over a rule that turns black once reached,
 * with a circle and its name. A finished step's circle is filled black with a
 * white tick, the current step's is white with a black ring, and a step still
 * to come is grey.
 */
const InquiryStepper = ({ steps, current }: InquiryStepperProps) => {
    const active = steps[current - 1] ?? '';

    return (
        <nav aria-label="Inquiry progress" className="mb-2 w-full">
            <p
                aria-live="polite"
                className="border-b-2 border-foreground pb-3 text-xs font-bold tracking-[0.06em] text-foreground uppercase sm:hidden"
            >
                Step {current} of {steps.length} · {active}
            </p>

            {/*
             * The steps side by side, each over a rule that turns black once
             * the reader has reached it: a circle with its number (a tick once
             * passed), "Step N" and its name.
             */}
            <ol
                className="hidden gap-6 sm:grid"
                style={{
                    gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))`,
                }}
            >
                {steps.map((label, index) => {
                    const number = index + 1;
                    const isActive = number === current;
                    const isDone = number < current;
                    const isReached = isActive || isDone;

                    return (
                        <li
                            key={label}
                            aria-current={isActive ? 'step' : undefined}
                            className={`flex min-w-0 items-center gap-3 border-b-2 pb-4.5 ${
                                isReached
                                    ? 'border-foreground text-foreground'
                                    : 'border-border text-muted-foreground'
                            }`}
                        >
                            <span
                                aria-hidden
                                className={`grid size-7 shrink-0 place-items-center rounded-full border text-xs font-bold ${
                                    isDone
                                        ? 'border-foreground bg-foreground text-background'
                                        : isActive
                                          ? 'border-foreground bg-background text-foreground'
                                          : 'border-border bg-background text-muted-foreground'
                                }`}
                            >
                                {isDone ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    number
                                )}
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5">
                                <span className="text-[0.6875rem] tracking-[0.12em] text-muted-foreground uppercase">
                                    Step {number}
                                </span>
                                <span className="truncate text-sm font-semibold tracking-[0.06em] uppercase">
                                    {label}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
};

export default InquiryStepper;

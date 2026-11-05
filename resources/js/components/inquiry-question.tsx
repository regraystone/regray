import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

/** The id of one question's section, for moving the reader to it when it opens. */
export const inquiryQuestionId = (step: number): string =>
    `inquiry-question-${step}`;

/**
 * One question on the first step of the inquiry. Open, it shows its answers and
 * its way forward. Answered, it closes to one line saying what was picked, with
 * a Change button to open it again.
 */
const InquiryQuestion = ({
    step,
    number,
    title,
    isOpen,
    summary,
    onChange,
    children,
}: {
    /** The flow's own number for this question, which names its section. */
    step: number;
    /** Its position as the reader counts it, which a visit's skips can change. */
    number: number;
    title: string;
    isOpen: boolean;
    summary: ReactNode;
    onChange: () => void;
    children: ReactNode;
}) => (
    <section
        id={inquiryQuestionId(step)}
        aria-labelledby={`${inquiryQuestionId(step)}-title`}
        className="scroll-mt-24 border-t border-sidebar-border py-5 first:border-t-0"
        data-testid={inquiryQuestionId(step)}
        data-state={isOpen ? 'open' : 'answered'}
    >
        <div className="flex items-start gap-3">
            <span
                aria-hidden
                className={`grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold ${
                    isOpen
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-primary text-foreground'
                }`}
            >
                {isOpen ? number : <Check className="size-4" />}
            </span>

            <div className="min-w-0 flex-1">
                {/* Focus lands here when the question opens. */}
                <h2
                    id={`${inquiryQuestionId(step)}-title`}
                    tabIndex={-1}
                    className="text-lg font-bold tracking-[-0.03em] outline-none"
                >
                    {title}
                </h2>

                {isOpen ? null : (
                    <p
                        className="mt-0.5 text-sm break-words text-secondary-foreground"
                        data-testid="inquiry-question-summary"
                    >
                        {summary}
                    </p>
                )}
            </div>

            {isOpen ? null : (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onChange}
                    aria-label={`Change: ${title}`}
                    className="shrink-0"
                >
                    Change
                </Button>
            )}
        </div>

        {isOpen ? <div className="mt-5">{children}</div> : null}
    </section>
);

export default InquiryQuestion;

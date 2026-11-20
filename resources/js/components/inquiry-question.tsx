import { Check } from 'lucide-react';
import { createContext, useContext, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';

/**
 * Where the page's bar goes: one place at the end of the page, so it stays at the
 * bottom of the screen whichever question is open and however short it is. A bar
 * drawn inside a question could only stick within that question, and would sit
 * above the summaries of the questions after it.
 */
export const InquiryActionBarSlot = createContext<HTMLElement | null>(null);

/** The way forward of whatever is open, drawn into the page's slot. */
export const InquiryActionBar = ({ children }: { children: ReactNode }) => {
    const slot = useContext(InquiryActionBarSlot);

    return slot === null
        ? null
        : createPortal(
              <div className="flex flex-col gap-3 border-t border-border bg-background py-3.5 sm:flex-row sm:items-center sm:justify-between [&_[data-slot=button]]:min-h-12 [&_[data-slot=button]]:rounded-none">
                  {children}
              </div>,
              slot,
          );
};

/** The id of one question's section, for moving the reader to it when it opens. */
export const inquiryQuestionId = (step: number): string =>
    `inquiry-question-${step}`;

/**
 * One question on the first step of the inquiry. Open, it shows its answers and
 * its way forward, under its number in a white circle. Answered, the circle
 * turns black with a tick and the question closes to one line saying what was
 * picked, with a Change button to open it again.
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
        className={`scroll-mt-24 border-t border-border first:border-t-0 ${
            isOpen ? 'py-8 sm:py-10' : 'py-5'
        }`}
        data-testid={inquiryQuestionId(step)}
        data-state={isOpen ? 'open' : 'answered'}
    >
        <div className="flex items-start gap-3">
            <span
                aria-hidden
                className={`grid size-7.5 shrink-0 place-items-center rounded-full border border-foreground text-xs font-bold ${
                    isOpen
                        ? 'bg-background text-foreground'
                        : 'bg-foreground text-background'
                }`}
            >
                {isOpen ? number : <Check className="size-3.5" />}
            </span>

            <div className="min-w-0 flex-1">
                {/* Focus lands here when the question opens. */}
                <h2
                    id={`${inquiryQuestionId(step)}-title`}
                    tabIndex={-1}
                    className="pt-0.5 font-display text-xl font-normal outline-none"
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

        {isOpen ? <div className="mt-6">{children}</div> : null}
    </section>
);

export default InquiryQuestion;

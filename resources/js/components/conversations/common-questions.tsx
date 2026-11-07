import { MessageCircleQuestion, Send } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';

const LIST_ID = 'composer-common-questions';

/**
 * The pill on the left of a client's thread, which opens the list under the box.
 * With `compactWhenNarrow`, it is only its icon when the row it sits in is too
 * narrow for its label beside the quotation pill. It reads the row's own width, so
 * dragging the inbox panel counts as well as the size of the screen.
 */
export const CommonQuestionsToggle = ({
    isOpen,
    compactWhenNarrow,
    onToggle,
}: {
    isOpen: boolean;
    compactWhenNarrow: boolean;
    onToggle: () => void;
}) => (
    <Button
        type="button"
        size="sm"
        onClick={onToggle}
        aria-label="Common Questions"
        aria-expanded={isOpen}
        aria-controls={LIST_ID}
        className={`pointer-events-auto rounded-full shadow-md ${
            compactWhenNarrow ? '@max-[28rem]:px-2.5' : ''
        }`}
        data-testid="common-questions-toggle"
    >
        <MessageCircleQuestion aria-hidden />
        <span
            className={compactWhenNarrow ? '@max-[28rem]:hidden' : undefined}
            data-testid="common-questions-label"
        >
            Common Questions
        </span>
    </Button>
);

/**
 * The questions under the composer's box, each sent by its own button. Stays
 * mounted so it can open and close like the send slot, and is inert while closed.
 */
export const CommonQuestionsList = ({
    questions,
    shown,
    onSend,
}: {
    questions: readonly string[];
    shown: boolean;
    onSend: (question: string) => void;
}) => {
    const labelId = useId();

    return (
        <div
            id={LIST_ID}
            inert={!shown}
            // A press here leaves focus where it was, so typing carries on.
            onMouseDown={(event) => event.preventDefault()}
            className={`grid origin-bottom transition-[grid-template-rows,opacity,transform] duration-200 ease-out motion-reduce:transition-none ${
                shown
                    ? 'scale-100 grid-rows-[1fr] opacity-100'
                    : 'pointer-events-none scale-95 grid-rows-[0fr] opacity-0'
            }`}
            data-testid="composer-common"
        >
            <div className="min-h-0 overflow-hidden">
                <div className="px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <p
                        id={labelId}
                        className="mb-1.5 text-xs font-bold text-secondary-foreground"
                    >
                        Common Questions
                    </p>
                    {/* A short phone with its keyboard open still keeps the messages. */}
                    <ul
                        aria-labelledby={labelId}
                        className="flex max-h-[30dvh] flex-col gap-1.5 overflow-y-auto"
                    >
                        {questions.map((question, index) => (
                            <li
                                key={question}
                                className="flex items-center gap-2 rounded-md border border-input bg-background py-0.5 pr-0.5 pl-3"
                            >
                                <span className="min-w-0 flex-1 text-sm break-words">
                                    {question}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onSend(question)}
                                    aria-label={`Send "${question}"`}
                                    className="size-10 shrink-0 rounded-md p-0"
                                    data-testid={`composer-common-send-${index}`}
                                >
                                    <Send className="size-5" aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
};

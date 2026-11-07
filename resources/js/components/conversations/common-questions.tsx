import { Send } from 'lucide-react';
import {
    useEffect,
    useId,
    useRef,
    type TouchEvent,
    type WheelEvent,
} from 'react';
import { Button } from '@/components/ui/button';
import { HIDE_DISTANCE_PX, wheelPull } from '@/lib/common-questions';

export const COMMON_QUESTIONS_LIST_ID = 'composer-common-questions';

/**
 * The questions above the composer's box, each sent by its own button. Scrolling
 * down over them, swiping down on a touch screen, or pressing anywhere outside them
 * hides them. Stays mounted so it can open and close like the send slot, and is
 * inert while closed.
 */
const CommonQuestions = ({
    questions,
    shown,
    onSend,
    onHide,
}: {
    questions: readonly string[];
    shown: boolean;
    onSend: (question: string) => void;
    onHide: () => void;
}) => {
    const labelId = useId();
    const listRef = useRef<HTMLUListElement>(null);
    const pulledRef = useRef(0);
    const touchStartRef = useRef<number | null>(null);
    const blockRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!shown) {
            return;
        }

        const hideFromOutside = (event: PointerEvent) => {
            const target = event.target as Element;

            // The icon opens and closes them itself.
            if (
                !blockRef.current?.contains(target) &&
                target.closest(
                    `[aria-controls="${COMMON_QUESTIONS_LIST_ID}"]`,
                ) === null
            ) {
                onHide();
            }
        };

        document.addEventListener('pointerdown', hideFromOutside);

        return () =>
            document.removeEventListener('pointerdown', hideFromOutside);
    }, [shown, onHide]);

    const hide = () => {
        pulledRef.current = 0;
        touchStartRef.current = null;
        onHide();
    };

    const handleWheel = (event: WheelEvent) => {
        const list = listRef.current;

        if (list === null) {
            return;
        }

        pulledRef.current = wheelPull(pulledRef.current, event.deltaY, list);

        if (pulledRef.current >= HIDE_DISTANCE_PX) {
            hide();
        }
    };

    const handleTouchMove = (event: TouchEvent) => {
        const y = event.touches[0]?.clientY;

        if (y === undefined || touchStartRef.current === null) {
            return;
        }

        // A list that overflows scrolls back to its top before the swipe counts.
        if ((listRef.current?.scrollTop ?? 0) > 0) {
            touchStartRef.current = y;

            return;
        }

        if (y - touchStartRef.current >= HIDE_DISTANCE_PX) {
            hide();
        }
    };

    return (
        <div
            ref={blockRef}
            id={COMMON_QUESTIONS_LIST_ID}
            inert={!shown}
            // A press here leaves focus where it was, so typing carries on.
            onMouseDown={(event) => event.preventDefault()}
            onWheel={handleWheel}
            onTouchStart={(event) => {
                touchStartRef.current = event.touches[0]?.clientY ?? null;
            }}
            onTouchMove={handleTouchMove}
            className={`grid origin-bottom transition-[grid-template-rows,opacity,transform] duration-200 ease-out motion-reduce:transition-none ${
                shown
                    ? 'scale-100 grid-rows-[1fr] opacity-100'
                    : 'pointer-events-none scale-95 grid-rows-[0fr] opacity-0'
            }`}
            data-testid="composer-common"
        >
            <div className="min-h-0 overflow-hidden">
                <div className="px-3 pt-2">
                    {/* The usual sign that a panel can be swiped away. */}
                    <span
                        aria-hidden
                        className="mx-auto mb-2 block h-1 w-10 rounded-full bg-sidebar-border"
                    />
                    <p
                        id={labelId}
                        className="mb-1.5 text-xs font-bold text-secondary-foreground"
                    >
                        Frequently Asked Questions
                    </p>
                    {/* A short phone with its keyboard open still keeps the messages. */}
                    <ul
                        ref={listRef}
                        aria-labelledby={labelId}
                        className="flex max-h-[30dvh] flex-col gap-1.5 overflow-y-auto overscroll-contain"
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

export default CommonQuestions;

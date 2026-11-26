import {
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    Image as PictureIcon,
    PencilRuler,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    ANSWER_TEXT_MAX,
    pickedChoice,
    type ServiceField,
    type ServiceFieldChoice,
} from '@/lib/inquiry-flow';
import {
    ARROW_CLASSES,
    ARROW_SCROLL_SHARE,
    CARD_WIDTH_CLASSES,
    IN_VIEW_SLACK_PX,
    ROW_GAP_CLASSES,
} from '@/lib/scroll-row';

/**
 * The picture of one card: the choice's own, or, for a choice with none, an
 * icon: what Custom and Not sure yet are, or a plain picture until one is
 * added on the Choices page.
 */
const CardPicture = ({ choice }: { choice: ServiceFieldChoice }) => {
    if (choice.image) {
        return (
            <img
                src={choice.image}
                alt=""
                loading="lazy"
                className="size-full object-cover"
            />
        );
    }

    const Icon = choice.isAdvisory
        ? CircleHelp
        : choice.isCustom
          ? PencilRuler
          : PictureIcon;

    return (
        <span className="flex size-full items-center justify-center">
            <Icon
                aria-hidden
                strokeWidth={1.25}
                className="size-10 text-secondary-foreground"
            />
        </span>
    );
};

/**
 * The box a custom choice ("Custom shape") opens for the reader to describe
 * theirs. Required while the custom choice is picked; held in the form
 * beside the answer (customAnswerKey()).
 */
export const CustomChoiceDescription = ({
    id,
    choice,
    value,
    error,
    onChange,
    onBlur,
}: {
    id: string;
    choice: ServiceFieldChoice;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    onBlur: () => void;
}) => (
    <div className="mt-4">
        <label htmlFor={id} className="text-sm font-bold text-foreground">
            Describe your {choice.label.toLowerCase()}
            <span className="text-destructive" aria-hidden>
                {' *'}
            </span>
            <span className="sr-only"> (required)</span>
        </label>
        <Input
            id={id}
            value={value}
            maxLength={ANSWER_TEXT_MAX}
            onChange={(event) => onChange(event.target.value)}
            onBlur={onBlur}
            placeholder="Its size, look or anything that helps us picture it"
            aria-invalid={error !== undefined}
            aria-describedby={error ? `${id}-error` : undefined}
            className="mt-2 rounded-none"
        />
        <InputError
            id={`${id}-error`}
            message={error}
            role="alert"
            className="mt-2"
        />
    </div>
);

/**
 * A list question asked with pictures (Table shape, Table base design): one
 * card per choice, a picture over its name, in a row that scrolls sideways
 * like the stones (lib/scroll-row): two to a view on a phone, three from a
 * tablet, an arrow at either end while there is more that way. The cards are
 * radio buttons underneath, so arrow keys move between them (the row follows
 * the focus) and a screen reader hears one group.
 *
 * Once one is picked, what it is (`description`) is said under the cards, as
 * a service's description is under the service. A custom choice ("Custom
 * shape") also opens a box for the reader to describe theirs, held in the
 * form beside the answer (`customValue`, customAnswerKey()).
 */
const InquiryPictureChoice = ({
    id,
    field,
    value,
    error,
    onChange,
    onBlur,
    fieldRef,
    customValue,
    customError,
    onCustomChange,
    onCustomBlur,
}: {
    id: string;
    field: ServiceField;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    onBlur: () => void;
    /** The form's ref, so a refused answer can be focused. */
    fieldRef?: (element: HTMLElement | null) => void;
    customValue: string;
    customError?: string;
    onCustomChange: (value: string) => void;
    onCustomBlur: () => void;
}) => {
    const picked = pickedChoice(field, value);
    const focusIndex = Math.max(
        0,
        field.options.findIndex((option) => option.value === value),
    );
    const rowRef = useRef<HTMLDivElement>(null);
    const [canScrollBack, setCanScrollBack] = useState(false);
    const [canScrollOn, setCanScrollOn] = useState(false);

    // An arrow shows only while the card at its end is not wholly in view.
    const readScroll = useCallback(() => {
        const row = rowRef.current;
        const cards = row?.querySelectorAll<HTMLElement>('[data-choice-slot]');
        const first = cards?.[0];
        const last = cards?.[cards.length - 1];

        if (!row || first === undefined || last === undefined) {
            setCanScrollBack(false);
            setCanScrollOn(false);

            return;
        }

        const view = row.getBoundingClientRect();

        setCanScrollBack(
            first.getBoundingClientRect().left < view.left - IN_VIEW_SLACK_PX,
        );
        setCanScrollOn(
            last.getBoundingClientRect().right > view.right + IN_VIEW_SLACK_PX,
        );
    }, []);

    useEffect(() => {
        const row = rowRef.current;

        if (row === null) {
            return;
        }

        readScroll();
        const observer = new ResizeObserver(readScroll);
        observer.observe(row);

        return () => observer.disconnect();
    }, [readScroll, field.options.length]);

    /*
     * A choice already picked (a restored draft, an edit, a furniture piece's
     * own) is brought into the row's view when the question opens, without
     * moving the page.
     */
    useEffect(() => {
        const row = rowRef.current;
        const card = row?.querySelector<HTMLElement>('[data-choice-picked]');

        if (!row || !card) {
            return;
        }

        const offset =
            card.getBoundingClientRect().left -
            row.getBoundingClientRect().left;

        if (offset < 0 || offset + card.offsetWidth > row.clientWidth) {
            row.scrollTo({ left: row.scrollLeft + offset });
        }
        // Once, on opening: a pick made in the row is already in view.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const scrollBy = (direction: 1 | -1) => {
        const row = rowRef.current;

        row?.scrollBy({
            left: direction * row.clientWidth * ARROW_SCROLL_SHARE,
            behavior: 'smooth',
        });
    };

    return (
        <FormField
            id={id}
            label={field.label}
            required={field.required}
            error={error}
        >
            {(accessibility) => (
                <div>
                    <div className="relative min-w-0">
                        <div
                            ref={rowRef}
                            onScroll={readScroll}
                            role="radiogroup"
                            aria-label={field.label}
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            className={`@container -my-1 flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain pt-1 pb-3 ${ROW_GAP_CLASSES}`}
                            data-testid={`picture-choices-${field.slug}`}
                        >
                            {field.options.map((choice, index) => {
                                const isPicked = choice.value === value;

                                return (
                                    <label
                                        key={choice.value}
                                        data-choice-slot
                                        data-choice-picked={
                                            isPicked || undefined
                                        }
                                        className={`${CARD_WIDTH_CLASSES} group relative flex cursor-pointer flex-col border bg-background transition-colors has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 ${
                                            isPicked
                                                ? 'border-foreground outline-2 -outline-offset-2 outline-foreground'
                                                : 'border-border hover:border-foreground/50'
                                        }`}
                                        data-testid={`picture-choice-${choice.value}`}
                                    >
                                        <input
                                            type="radio"
                                            name={id}
                                            value={choice.value}
                                            checked={isPicked}
                                            onChange={() => {
                                                onChange(choice.value);
                                                onBlur();
                                            }}
                                            ref={
                                                index === focusIndex
                                                    ? fieldRef
                                                    : undefined
                                            }
                                            className="sr-only"
                                        />
                                        <span className="aspect-[4/3] w-full overflow-hidden bg-muted">
                                            <CardPicture choice={choice} />
                                        </span>
                                        <span className="px-3 py-2.5 text-sm leading-snug font-medium">
                                            {choice.label}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>

                        {canScrollBack ? (
                            <button
                                type="button"
                                aria-label={`Earlier ${field.label.toLowerCase()} choices`}
                                onClick={() => scrollBy(-1)}
                                className={`${ARROW_CLASSES} left-2`}
                                data-testid={`picture-choices-${field.slug}-back`}
                            >
                                <ChevronLeft aria-hidden className="size-5" />
                            </button>
                        ) : null}
                        {canScrollOn ? (
                            <button
                                type="button"
                                aria-label={`More ${field.label.toLowerCase()} choices`}
                                onClick={() => scrollBy(1)}
                                className={`${ARROW_CLASSES} right-2`}
                                data-testid={`picture-choices-${field.slug}-on`}
                            >
                                <ChevronRight aria-hidden className="size-5" />
                            </button>
                        ) : null}
                    </div>

                    {picked?.description ? (
                        <div
                            className="mt-4 border-l-2 border-foreground bg-muted px-5 py-4"
                            aria-live="polite"
                            data-testid="picture-choice-description"
                        >
                            <p className="text-xs font-semibold tracking-[0.12em] text-foreground uppercase">
                                {picked.label}
                            </p>
                            <p className="mt-1.5 text-[0.9375rem] leading-normal">
                                {picked.description}
                            </p>
                        </div>
                    ) : null}

                    {picked?.isCustom ? (
                        <CustomChoiceDescription
                            id={`${id}-custom`}
                            choice={picked}
                            value={customValue}
                            error={customError}
                            onChange={onCustomChange}
                            onBlur={onCustomBlur}
                        />
                    ) : null}
                </div>
            )}
        </FormField>
    );
};

export default InquiryPictureChoice;

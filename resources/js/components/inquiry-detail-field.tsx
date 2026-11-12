import { useState } from 'react';
import FormField from '@/components/form-field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ANSWER_TEXT_MAX,
    NO,
    SLAB_SIZE_TEXT_MAX,
    YES,
    type ServiceField,
    type ServiceFieldChoice,
} from '@/lib/inquiry-flow';

/** The entry of a suggestion list that opens the free input. */
const OTHER = '__other__';

const SELECT_CLASS = 'w-full rounded-none text-base data-[size=default]:h-11';

/**
 * One question a service asks, drawn from what the server says about it:
 * every list is a dropdown, a yes or no question is a Yes and No dropdown, and
 * a question that takes a free entry lists its suggestions with an Other entry
 * that reveals a text or number input. Nothing it lists is written here.
 *
 * The value is the form's plain string (`''` while unanswered), so the same
 * control serves a stone row's answer and a project answer. Whether the free
 * input is showing is the control's own state: it starts open when the
 * stored answer is not one of the suggestions.
 */
const InquiryDetailField = ({
    id,
    field,
    value,
    error,
    onChange,
    onBlur,
    labelContext,
    fieldRef,
}: {
    id: string;
    field: ServiceField;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    onBlur: () => void;
    /** Which stone row this is, for a screen reader; the row shows it. */
    labelContext?: string;
    /** The form's ref, so a refused answer can be focused. */
    fieldRef?: (element: HTMLElement | null) => void;
}) => {
    const hasSuggestions =
        field.answerType !== 'choice' &&
        field.answerType !== 'boolean' &&
        field.suggestions.length > 0;
    const [isCustom, setIsCustom] = useState(
        () =>
            hasSuggestions &&
            value !== '' &&
            !field.suggestions.some((option) => option.value === value),
    );

    const choose = (next: string) => {
        onChange(next);
        onBlur();
    };

    const list = (
        options: ServiceFieldChoice[],
        accessibility: { describedBy: string | undefined; invalid: boolean },
        selected: string,
        onPick: (next: string) => void,
        extra?: { value: string; label: string },
    ) => (
        <Select value={selected} onValueChange={onPick}>
            <SelectTrigger
                id={id}
                ref={fieldRef}
                aria-describedby={accessibility.describedBy}
                aria-invalid={accessibility.invalid}
                aria-label={
                    labelContext
                        ? `${field.label} for ${labelContext}`
                        : undefined
                }
                className={SELECT_CLASS}
            >
                <SelectValue placeholder="Choose one" />
            </SelectTrigger>
            <SelectContent>
                {[...options, ...(extra === undefined ? [] : [extra])].map(
                    (option) => (
                        <SelectItem
                            key={option.value}
                            value={option.value}
                            className="rounded-none"
                        >
                            {option.label}
                        </SelectItem>
                    ),
                )}
            </SelectContent>
        </Select>
    );

    const freeInput = (accessibility: {
        describedBy: string | undefined;
        invalid: boolean;
    }) => (
        <Input
            id={hasSuggestions ? `${id}-custom` : id}
            ref={hasSuggestions ? undefined : fieldRef}
            type={field.answerType === 'number' ? 'number' : 'text'}
            inputMode={field.answerType === 'number' ? 'decimal' : undefined}
            min={field.answerType === 'number' ? '0' : undefined}
            step={field.answerType === 'number' ? 'any' : undefined}
            maxLength={
                field.answerType === 'number'
                    ? undefined
                    : field.slug === 'slab_size'
                      ? SLAB_SIZE_TEXT_MAX
                      : ANSWER_TEXT_MAX
            }
            value={value}
            onChange={(event) => onChange(event.target.value)}
            onBlur={onBlur}
            aria-label={
                hasSuggestions
                    ? `${field.label}, your own${labelContext ? ` for ${labelContext}` : ''}`
                    : labelContext
                      ? `${field.label} for ${labelContext}`
                      : undefined
            }
            aria-describedby={accessibility.describedBy}
            aria-invalid={accessibility.invalid}
            className="rounded-none"
        />
    );

    return (
        <FormField
            id={id}
            label={field.label}
            required={field.required}
            error={error}
        >
            {(accessibility) => {
                if (field.answerType === 'choice') {
                    return list(field.options, accessibility, value, choose);
                }

                if (field.answerType === 'boolean') {
                    return list(
                        [
                            { value: YES, label: 'Yes' },
                            { value: NO, label: 'No' },
                        ],
                        accessibility,
                        value,
                        choose,
                    );
                }

                if (!hasSuggestions) {
                    return freeInput(accessibility);
                }

                return (
                    /*
                     * With Other picked, the list and the entry share the
                     * field's width side by side, half each.
                     */
                    <div
                        className={
                            isCustom ? 'grid grid-cols-2 gap-2' : undefined
                        }
                    >
                        {list(
                            field.suggestions,
                            accessibility,
                            isCustom ? OTHER : value,
                            (next) => {
                                if (next === OTHER) {
                                    setIsCustom(true);
                                    onChange('');

                                    // The input it reveals is where the answer goes next.
                                    window.setTimeout(
                                        () =>
                                            document
                                                .getElementById(`${id}-custom`)
                                                ?.focus(),
                                        0,
                                    );

                                    return;
                                }

                                setIsCustom(false);
                                choose(next);
                            },
                            field.allowsCustom
                                ? { value: OTHER, label: 'Other' }
                                : undefined,
                        )}
                        {isCustom ? freeInput(accessibility) : null}
                    </div>
                );
            }}
        </FormField>
    );
};

export default InquiryDetailField;

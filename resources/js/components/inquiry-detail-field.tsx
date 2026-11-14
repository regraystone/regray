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
    SIZE_UNITS,
    THICKNESS_UNITS,
    YES,
    measurementKind,
    parseSlabSize,
    parseThickness,
    type MeasurementKind,
    type ServiceField,
    type ServiceFieldChoice,
} from '@/lib/inquiry-flow';

/** The entry of a suggestion list that opens the free input. */
const OTHER = '__other__';

const SELECT_CLASS = 'w-full rounded-none text-base data-[size=default]:h-11';

/** The parts of a measurement typed under Other, before they are one value. */
type MeasurementParts = {
    width: string;
    length: string;
    value: string;
    unit: string;
};

/**
 * A stored measurement split back into its parts, so a custom size or
 * thickness reopens with its numbers and unit filled in. One that does not
 * read starts empty, in the first unit.
 */
const measurementParts = (
    kind: MeasurementKind,
    written: string,
): MeasurementParts => {
    if (kind === 'size') {
        const size = parseSlabSize(written);

        return {
            width: size?.width ?? '',
            length: size?.length ?? '',
            value: '',
            unit: size?.unit ?? SIZE_UNITS[0],
        };
    }

    const thickness = parseThickness(written);

    return {
        width: '',
        length: '',
        value: thickness?.value ?? '',
        unit: thickness?.unit ?? THICKNESS_UNITS[0],
    };
};

/**
 * The parts as the one string the form holds and the server parses: "150 x
 * 300 cm", "0.75 in". Nothing typed yet is no answer (`''`); a half-typed
 * size keeps its gap ("150 x  cm") so it is refused as not yet a size rather
 * than taken for one.
 */
const measurementValue = (
    kind: MeasurementKind,
    parts: MeasurementParts,
): string => {
    if (kind === 'size') {
        const width = parts.width.trim();
        const length = parts.length.trim();

        return width === '' && length === ''
            ? ''
            : `${width} x ${length} ${parts.unit}`;
    }

    const value = parts.value.trim();

    return value === '' ? '' : `${value} ${parts.unit}`;
};

/**
 * A size or thickness of one's own, as numbers and a unit: Width, Length and
 * a unit for a slab size (`{id}-width`, `{id}-length`, `{id}-unit`), a value
 * and a unit for a thickness (`{id}-value`, `{id}-unit`). The reader never
 * types "x" or the unit, so what they enter is always written the one way
 * the server reads.
 *
 * Laid out to shrink rather than scroll: every number takes a share of the
 * line (`minmax(0, 1fr)`) beside a unit list of fixed width, which still fits
 * a 320 px screen.
 */
const MeasurementEntry = ({
    id,
    kind,
    parts,
    onParts,
    onBlur,
    accessibility,
    label,
    fieldRef,
}: {
    id: string;
    kind: MeasurementKind;
    parts: MeasurementParts;
    onParts: (next: MeasurementParts) => void;
    onBlur: () => void;
    accessibility: { describedBy: string | undefined; invalid: boolean };
    /** "Slab size for Cosmic Black": what each part's own name is read after. */
    label: string;
    fieldRef?: (element: HTMLElement | null) => void;
}) => {
    const number = (
        part: 'width' | 'length' | 'value',
        name: string,
        first: boolean,
    ) => (
        <div className="min-w-0">
            <label
                htmlFor={`${id}-${part}`}
                className="mb-1 block text-xs text-secondary-foreground"
            >
                {name}
            </label>
            <Input
                id={`${id}-${part}`}
                ref={first ? fieldRef : undefined}
                type="number"
                inputMode="decimal"
                min="0"
                step="any"
                value={parts[part]}
                onChange={(event) =>
                    onParts({ ...parts, [part]: event.target.value })
                }
                onBlur={onBlur}
                aria-label={`${name}, ${label}`}
                aria-describedby={accessibility.describedBy}
                aria-invalid={accessibility.invalid}
                className="no-stepper rounded-none"
            />
        </div>
    );

    const units = kind === 'size' ? SIZE_UNITS : THICKNESS_UNITS;

    return (
        <div
            role="group"
            aria-label={`${label}, your own`}
            className={`grid items-end gap-2 ${
                kind === 'size'
                    ? 'grid-cols-[minmax(0,1fr)_minmax(0,1fr)_4.75rem]'
                    : 'grid-cols-[minmax(0,1fr)_4.75rem]'
            }`}
        >
            {kind === 'size' ? (
                <>
                    {number('width', 'Width', true)}
                    {number('length', 'Length', false)}
                </>
            ) : (
                number('value', 'Thickness', true)
            )}
            <div className="min-w-0">
                <span
                    id={`${id}-unit-label`}
                    className="mb-1 block text-xs text-secondary-foreground"
                >
                    Unit
                </span>
                <Select
                    value={parts.unit}
                    onValueChange={(unit) => {
                        onParts({ ...parts, unit });
                        onBlur();
                    }}
                >
                    <SelectTrigger
                        id={`${id}-unit`}
                        aria-label={`Unit, ${label}`}
                        className={SELECT_CLASS}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {units.map((unit) => (
                            <SelectItem
                                key={unit}
                                value={unit}
                                className="rounded-none"
                            >
                                {unit}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
};

/**
 * One question a service asks, drawn from what the server says about it:
 * every list is a dropdown, a yes or no question is a Yes and No dropdown, and
 * a question that takes a free entry lists its suggestions with an Other entry
 * that reveals a text or number input. Nothing it lists is written here.
 *
 * A slab size or thickness lists the row's own stone's options (the stone row
 * hands in the question with them as its suggestions), and its Other is a
 * measurement of one's own (MeasurementEntry) rather than free text. Its list
 * is drawn even when the stone has none to offer, as Other alone, so every
 * row answers it the same way.
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
    hint,
}: {
    id: string;
    field: ServiceField;
    value: string;
    error?: string;
    /** A line under the label, such as how big a stone's pieces run. */
    hint?: string;
    onChange: (value: string) => void;
    onBlur: () => void;
    /** Which stone row this is, for a screen reader; the row shows it. */
    labelContext?: string;
    /** The form's ref, so a refused answer can be focused. */
    fieldRef?: (element: HTMLElement | null) => void;
}) => {
    const kind = measurementKind(field);
    const hasSuggestions =
        field.answerType !== 'choice' &&
        field.answerType !== 'boolean' &&
        (field.suggestions.length > 0 || (kind !== null && field.allowsCustom));
    const [isCustom, setIsCustom] = useState(
        () =>
            hasSuggestions &&
            value !== '' &&
            !field.suggestions.some((option) => option.value === value),
    );
    const [parts, setParts] = useState<MeasurementParts | null>(() =>
        kind === null ? null : measurementParts(kind, value),
    );

    const choose = (next: string) => {
        onChange(next);
        onBlur();
    };

    const contextLabel = labelContext
        ? `${field.label} for ${labelContext}`
        : field.label;

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

    const measurementEntry = (accessibility: {
        describedBy: string | undefined;
        invalid: boolean;
    }) =>
        kind === null || parts === null ? null : (
            <MeasurementEntry
                id={id}
                kind={kind}
                parts={parts}
                onParts={(next) => {
                    setParts(next);
                    onChange(measurementValue(kind, next));
                }}
                onBlur={onBlur}
                accessibility={accessibility}
                label={contextLabel}
                // With a list above it, the list is what a refusal focuses.
                fieldRef={hasSuggestions ? undefined : fieldRef}
            />
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
                field.answerType === 'number' ? undefined : ANSWER_TEXT_MAX
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
            hint={hint}
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
                    return kind === null
                        ? freeInput(accessibility)
                        : measurementEntry(accessibility);
                }

                return (
                    /*
                     * With Other picked, the list and the entry share the
                     * field's width side by side, half each; a measurement's
                     * entry has more parts, so it goes under the list instead.
                     */
                    <div
                        className={
                            !isCustom
                                ? undefined
                                : kind === null
                                  ? 'grid grid-cols-2 gap-2'
                                  : 'grid gap-2'
                        }
                    >
                        {list(
                            field.suggestions,
                            accessibility,
                            isCustom ? OTHER : value,
                            (next) => {
                                if (next === OTHER) {
                                    setIsCustom(true);

                                    if (kind === null) {
                                        onChange('');
                                    } else {
                                        // A fresh entry, in the first unit.
                                        const fresh = measurementParts(
                                            kind,
                                            '',
                                        );
                                        setParts(fresh);
                                        onChange('');
                                    }

                                    // The input it reveals is where the answer goes next.
                                    window.setTimeout(
                                        () =>
                                            document
                                                .getElementById(
                                                    kind === null
                                                        ? `${id}-custom`
                                                        : kind === 'size'
                                                          ? `${id}-width`
                                                          : `${id}-value`,
                                                )
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
                        {isCustom
                            ? kind === null
                                ? freeInput(accessibility)
                                : measurementEntry(accessibility)
                            : null}
                    </div>
                );
            }}
        </FormField>
    );
};

export default InquiryDetailField;

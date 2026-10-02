import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

/**
 * One label, one control, one error.
 *
 * The control is passed in as a function so the same wrapper can drive an
 * `Input`, a `Select` or a `Textarea`: all three need the same
 * `aria-describedby` and `aria-invalid` wiring, and passing them through a
 * render keeps that wiring in one place instead of three.
 *
 * Shared rather than written out per step because the inquiry form has more than
 * one of them — a stone's specifications, the project's own name, and its site
 * and date are all the same kind of page: a grid of labelled controls with a
 * complaint under each. A reader who has used one should not find the next laid
 * out differently.
 */
const FormField = ({
    id,
    label,
    required,
    error,
    hint,
    className,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    className?: string;
    children: (accessibility: {
        describedBy: string | undefined;
        invalid: boolean;
    }) => ReactNode;
}) => {
    const describedBy =
        [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className={className}>
            <Label htmlFor={id} className="text-sm font-bold text-foreground">
                {label}
                {required ? (
                    <>
                        {/* Announced as "required" rather than read as a stray asterisk. */}
                        <span className="text-destructive" aria-hidden>
                            {' *'}
                        </span>
                        <span className="sr-only"> (required)</span>
                    </>
                ) : null}
            </Label>

            {hint ? (
                <p
                    id={`${id}-hint`}
                    className="mt-1 text-xs text-secondary-foreground"
                >
                    {hint}
                </p>
            ) : null}

            {/*
             * The gap between the label and the control belongs to the wrapper,
             * not to each control, so a text input, a select and a textarea all
             * sit the same distance under their label.
             */}
            <div className="mt-2">
                {children({ describedBy, invalid: error !== undefined })}
            </div>

            <InputError
                message={error}
                id={`${id}-error`}
                role="alert"
                className="mt-2"
            />
        </div>
    );
};

/**
 * A value picked from a list, which is a finished answer rather than a
 * keystroke inside one.
 *
 * The form re-checks a field as it is changed, but only once that field has been
 * left at least once — and picking from a list is never a blur. So without
 * marking the field settled here, a good answer would sit beside the complaint
 * from the last check until the reader happened to touch something else.
 *
 * Lives beside `FormField` because it exists for the same reason: the wiring
 * between a control and the complaint under it, in one place per control type.
 */
export const chooseFromList =
    (set: (value: string) => void, settle: () => void) => (value: string) => {
        set(value);
        settle();
    };

/**
 * One field's complaint, read out of the nested error tree.
 *
 * The errors on a list of objects are a tree rather than a fixed shape, and
 * TypeScript will not narrow an entry two levels down to `string | undefined`.
 * Reading the message out is what lets each row of the requirements step name
 * its own problem without casting at every use.
 */
export const fieldMessage = (error: unknown): string | undefined =>
    typeof error === 'object' &&
    error !== null &&
    'message' in error &&
    typeof (error as { message?: unknown }).message === 'string'
        ? (error as { message: string }).message
        : undefined;

export default FormField;

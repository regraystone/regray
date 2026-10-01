import { Controller, useFormContext } from 'react-hook-form';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import FileUpload from '@/components/file-upload';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { searchLocations, LOCATIONS } from '@/data/locations';
import {
    EDGE_PROFILES,
    FINISHES,
    LANDMARK_MAX,
    NOTES_MAX,
    PROJECT_NAME_MAX,
} from '@/data/constant';
import {
    todayIsoDate,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';

/*
 * The ids the controls are wired to. They live next to the markup that uses them
 * rather than in the page, so a renamed field cannot leave a label pointing at
 * nothing.
 */
const FIELD_IDS = {
    projectName: 'inquiry-project-name',
    location: 'inquiry-location',
    landmark: 'inquiry-landmark',
    edge: 'inquiry-edge',
    finish: 'inquiry-finish',
    areaSqm: 'inquiry-area',
    neededBy: 'inquiry-needed-by',
    notes: 'inquiry-notes',
};

/**
 * Everything the attachment field has to say, as plain sentences.
 *
 * Zod reports a problem with one file against that file and a problem with the
 * list as a whole against the list, so the shape coming back is either one error
 * object or an array of them and sometimes both at once. Reading both here keeps
 * the step from having to know which rule produced what.
 */
const attachmentMessages = (error: unknown): string[] => {
    const onTheList =
        typeof error === 'object' &&
        error !== null &&
        'message' in error &&
        typeof (error as { message?: unknown }).message === 'string'
            ? [(error as { message: string }).message]
            : [];

    const onAFile = Array.isArray(error)
        ? error.flatMap((entry) => attachmentMessages(entry))
        : [];

    return [...onTheList, ...onAFile];
};

/**
 * A value picked from a list, which is a finished answer rather than a
 * keystroke inside one.
 *
 * The form re-checks a field as it is changed, but only once that field has been
 * left at least once — and picking from a list is never a blur. So without
 * marking the field settled here, a good answer would sit beside the complaint
 * from the last check until the reader happened to touch something else.
 */
const chooseFromList =
    (set: (value: string) => void, settle: () => void) => (value: string) => {
        set(value);
        settle();
    };

type InquiryRequirementsProps = {
    /**
     * Run by the page when Continue is pressed. It decides whether the step is
     * allowed to hand over, so the check itself lives with the rest of the
     * stepper rather than here.
     */
    onSubmit: () => void;
    onBack: () => void;
    /**
     * The bar's forward button, which is renamed when this step was reached
     * from Review: the reader is not going on to the next step but returning to
     * the sheet they came from.
     */
    submitLabel?: string;
};

/**
 * One label, one control, one error.
 *
 * The control is passed in as a function so the same wrapper can drive an
 * `Input`, a `Select` or a `Textarea`: all three need the same
 * `aria-describedby` and `aria-invalid` wiring, and passing them through a
 * render keeps that wiring in one place instead of three.
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
 * Step 3 of the inquiry flow: what is being built, and anything that helps us
 * quote it.
 *
 * The whole step is one form, so pressing Enter anywhere in it is the same
 * answer as pressing Continue — it runs the same validation and lands on the
 * same errors. `noValidate` because the browser's own bubbles are the wrong
 * voice for this app, and they would fire before the page's own messages.
 *
 * The form runs the width of the page rather than being capped at a reading
 * measure, because the fields sit in columns of two and three: capped, the
 * narrowest column was too short to read a label in.
 *
 * Values and errors come from the stepper's one form rather than from props, so
 * a step change cannot cost a typed answer or a finished upload.
 */
const InquiryRequirements = ({
    onSubmit,
    onBack,
    submitLabel = 'Continue',
}: InquiryRequirementsProps) => {
    const {
        register,
        control,
        setValue,
        watch,
        formState: { errors },
    } = useFormContext<InquiryFormInput, unknown, InquiryFormValues>();

    const notes = watch('notes');
    const isUploading = watch('attachments').some(
        (attachment) => attachment.status === 'uploading',
    );

    const today = todayIsoDate();

    const attachmentErrors = attachmentMessages(errors.attachments);

    return (
        <form
            noValidate
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <h3 className="text-lg font-bold tracking-[-0.03em]">
                Project details
            </h3>

            <div className="mt-4 grid gap-5 md:grid-cols-3">
                <FormField
                    id={FIELD_IDS.projectName}
                    label="Project name"
                    required
                    error={errors.projectName?.message}
                >
                    {(accessibility) => (
                        <Input
                            id={FIELD_IDS.projectName}
                            {...register('projectName')}
                            maxLength={PROJECT_NAME_MAX}
                            placeholder="Riverside Residence — Kitchen"
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            className="rounded-none"
                        />
                    )}
                </FormField>

                <FormField
                    id={FIELD_IDS.location}
                    label="Location"
                    required
                    error={errors.location?.message}
                >
                    {(accessibility) => (
                        /*
                         * The combobox owns its own input, so it cannot be
                         * `register`ed: what it hands back is a string it has
                         * already decided is the value. The ref is forwarded to
                         * its text input so a failed check can still send focus
                         * here, exactly as it would for a plain input.
                         */
                        <Controller
                            control={control}
                            name="location"
                            render={({ field }) => (
                                <Combobox
                                    id={FIELD_IDS.location}
                                    ref={field.ref}
                                    value={field.value}
                                    options={LOCATIONS}
                                    filter={searchLocations}
                                    listLabel="Cities and municipalities"
                                    placeholder="Search a city or municipality"
                                    emptyMessage="No city or municipality matches that."
                                    aria-describedby={accessibility.describedBy}
                                    aria-invalid={accessibility.invalid}
                                    className="rounded-none"
                                    onChange={chooseFromList(
                                        field.onChange,
                                        field.onBlur,
                                    )}
                                />
                            )}
                        />
                    )}
                </FormField>

                <FormField
                    id={FIELD_IDS.landmark}
                    label="Landmark"
                    error={errors.landmark?.message}
                >
                    {(accessibility) => (
                        <Input
                            id={FIELD_IDS.landmark}
                            {...register('landmark')}
                            maxLength={LANDMARK_MAX}
                            placeholder="Near the old railway station"
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            className="rounded-none"
                        />
                    )}
                </FormField>
            </div>

            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                <FormField
                    id={FIELD_IDS.edge}
                    label="Edge profile"
                    required
                    error={errors.edge?.message}
                >
                    {(accessibility) => (
                        <Controller
                            control={control}
                            name="edge"
                            render={({ field }) => (
                                <Select
                                    /*
                                     * Radix refuses an empty string as a value, so an
                                     * unanswered select is `undefined` and shows its
                                     * placeholder instead.
                                     */
                                    value={field.value || undefined}
                                    onValueChange={chooseFromList(
                                        field.onChange,
                                        field.onBlur,
                                    )}
                                >
                                    <SelectTrigger
                                        id={FIELD_IDS.edge}
                                        ref={field.ref}
                                        aria-describedby={
                                            accessibility.describedBy
                                        }
                                        aria-invalid={accessibility.invalid}
                                        className="w-full rounded-none"
                                    >
                                        <SelectValue placeholder="Choose an edge profile" />
                                    </SelectTrigger>
                                    <SelectContent className="rounded-none">
                                        {EDGE_PROFILES.map((edge) => (
                                            <SelectItem
                                                key={edge}
                                                value={edge}
                                                className="rounded-none"
                                            >
                                                {edge}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        />
                    )}
                </FormField>

                <FormField
                    id={FIELD_IDS.finish}
                    label="Finish"
                    required
                    error={errors.finish?.message}
                >
                    {(accessibility) => (
                        <Controller
                            control={control}
                            name="finish"
                            render={({ field }) => (
                                <Select
                                    value={field.value || undefined}
                                    onValueChange={chooseFromList(
                                        field.onChange,
                                        field.onBlur,
                                    )}
                                >
                                    <SelectTrigger
                                        id={FIELD_IDS.finish}
                                        ref={field.ref}
                                        aria-describedby={
                                            accessibility.describedBy
                                        }
                                        aria-invalid={accessibility.invalid}
                                        className="w-full rounded-none"
                                    >
                                        <SelectValue placeholder="Choose a finish" />
                                    </SelectTrigger>
                                    <SelectContent className="rounded-none">
                                        {FINISHES.map((finish) => (
                                            <SelectItem
                                                key={finish}
                                                value={finish}
                                                className="rounded-none"
                                            >
                                                {finish}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        />
                    )}
                </FormField>
            </div>

            <div className="mt-5 grid gap-5 sm:grid-cols-2">
                <FormField
                    id={FIELD_IDS.areaSqm}
                    label="Approximate area (sqm)"
                    error={errors.areaSqm?.message}
                >
                    {(accessibility) => (
                        <Input
                            id={FIELD_IDS.areaSqm}
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="any"
                            {...register('areaSqm')}
                            placeholder="24"
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            className="rounded-none"
                        />
                    )}
                </FormField>

                <FormField
                    id={FIELD_IDS.neededBy}
                    label="Needed by"
                    error={errors.neededBy?.message}
                >
                    {(accessibility) => (
                        <Input
                            id={FIELD_IDS.neededBy}
                            type="date"
                            min={today}
                            {...register('neededBy')}
                            /*
                             * Chrome and Edge only open the calendar when their own
                             * small icon is clicked, so a click anywhere else in
                             * the field does nothing. Asking for the picker here
                             * makes the whole field the target.
                             *
                             * `showPicker` needs a live click to count as a user
                             * gesture and throws without one, and it throws in
                             * other cases too (a cross-origin iframe, or a click
                             * the browser has already handled itself). Swallowing
                             * that is safe: the field is still a plain date input,
                             * so the date can always be typed instead.
                             */
                            onClick={(event) => {
                                try {
                                    event.currentTarget.showPicker?.();
                                } catch {
                                    // Nothing to do: the field is still typeable.
                                }
                            }}
                            aria-describedby={accessibility.describedBy}
                            aria-invalid={accessibility.invalid}
                            /*
                             * The browser's own calendar button is hidden. It is a
                             * rounded grey pill sitting inside an otherwise flat
                             * square field, and nothing else in this form carries
                             * one, so it reads as a stray control rather than as
                             * part of the field. The field is still a date input,
                             * so the date is typed or picked exactly as before.
                             */
                            className="rounded-none [&::-webkit-calendar-picker-indicator]:hidden"
                        />
                    )}
                </FormField>
            </div>

            <div className="mt-8">
                <h3 className="text-lg font-bold tracking-[-0.03em]">
                    Attachments and notes
                </h3>

                <p className="mt-2 max-w-2xl text-xs text-secondary-foreground">
                    Plans, sketches or site photos help us quote accurately.
                    Everything here is optional.
                </p>

                <div className="mt-4">
                    <Controller
                        control={control}
                        name="attachments"
                        render={({ field }) => (
                            <FileUpload
                                files={field.value}
                                onFilesChange={(next) =>
                                    /*
                                     * The list is rewritten on every progress
                                     * tick, so validation is asked for only once
                                     * nothing is in flight. Re-running the check
                                     * ten times a second over the whole field is
                                     * what would make the progress bar stutter.
                                     */
                                    setValue('attachments', next, {
                                        shouldValidate: next.every(
                                            (attachment) =>
                                                attachment.status === 'done',
                                        ),
                                    })
                                }
                            />
                        )}
                    />

                    {/*
                     * Whatever the schema objected to, rather than only the
                     * first thing: a list of four files can be wrong in more than
                     * one way at once, and the reader has to be able to fix all
                     * of it in one pass.
                     */}
                    <div aria-live="polite">
                        {attachmentErrors.map((message, index) => (
                            <InputError
                                key={`${index}-${message}`}
                                message={message}
                                role="alert"
                                className="mt-2"
                            />
                        ))}
                    </div>
                </div>

                <FormField
                    id={FIELD_IDS.notes}
                    label="Additional notes"
                    error={errors.notes?.message}
                    className="mt-6"
                >
                    {(accessibility) => (
                        <div>
                            <Textarea
                                id={FIELD_IDS.notes}
                                {...register('notes')}
                                maxLength={NOTES_MAX}
                                placeholder="Anything else we should know about the site, the access, or the look you are after."
                                aria-describedby={accessibility.describedBy}
                                aria-invalid={accessibility.invalid}
                            />
                            {/*
                             * The count is the limit made visible, so there is
                             * no reason to also error on a limit the field
                             * already refuses to pass. Reading the value rather
                             * than a copy of it in local state is what keeps the
                             * count true on autofill too, which writes into the
                             * field without going through this component.
                             */}
                            <p className="mt-2 text-right text-xs text-secondary-foreground">
                                <span className="sr-only">
                                    {notes.length} of {NOTES_MAX} characters
                                    used
                                </span>
                                <span aria-hidden>
                                    {notes.length} / {NOTES_MAX}
                                </span>
                            </p>
                        </div>
                    )}
                </FormField>
            </div>

            {/*
             * Inside the form on purpose. The bar looks the same as the
             * earlier steps', but living here makes Continue this form's
             * submit button, which is what lets Enter in a text field reach
             * the same validation instead of quietly doing nothing. Sticky
             * for the same reason as the earlier steps', and a file still in
             * flight is the one thing that can hold it back.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-1">
                    {isUploading ? (
                        <p
                            aria-live="polite"
                            className="text-sm font-bold text-foreground"
                        >
                            Waiting for uploads to finish
                        </p>
                    ) : (
                        <p className="text-xs text-secondary-foreground">
                            Plans, sketches or site photos are optional
                        </p>
                    )}
                </div>

                <div className="flex w-full shrink-0 flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onBack}
                        className="w-full cursor-pointer sm:w-auto"
                    >
                        <ArrowLeft aria-hidden />
                        Back
                    </Button>
                    <Button
                        type="submit"
                        disabled={isUploading}
                        className="w-full sm:w-auto"
                    >
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
};

export default InquiryRequirements;

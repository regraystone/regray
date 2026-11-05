import { ChevronDown } from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { Controller, useFormContext, useWatch } from 'react-hook-form';
import FileUpload from '@/components/file-upload';
import FormField, {
    chooseFromList,
    fieldMessage,
} from '@/components/form-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { LANDMARK_MAX, NOTES_MAX } from '@/data/inquiries';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import { searchLocations } from '@/lib/inquiry-catalog';
import {
    todayIsoDate,
    type InquiryDraftInput,
    type InquiryDraftValues,
} from '@/lib/inquiry-schema';

/**
 * Everything the attachment field has to say, as plain sentences.
 *
 * Zod reports a problem with one file against that file and a problem with the
 * list as a whole against the list, so the shape coming back is either one error
 * object or an array of them and sometimes both at once. Reading both here keeps
 * the requirements step from having to know which rule produced what.
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
 * Open the browser's own date or time dialog when the field is clicked.
 *
 * Chrome and Edge only open it when their own small icon is clicked, and this
 * form hides that icon — it is a rounded grey pill inside an otherwise flat
 * square field, and nothing else here carries one, so it reads as a stray control
 * rather than as part of the field. With the icon hidden and nothing else wired
 * up, the only way to answer is to type: a reader who came to point at a day has
 * to work out its date format and key it in, and the three services that are
 * booked by the hour have to do the same twice.
 *
 * So the field itself is the target. Asking for the picker from the click is
 * what puts it back within reach of the whole field rather than one corner of
 * it.
 *
 * `showPicker` needs a live click to count as a user gesture and throws without
 * one, and it throws in other cases too (a cross-origin iframe, or a click the
 * browser has already handled itself). Swallowing that is safe: the field is
 * still a plain `date` or `time` input, so the answer can always be typed
 * instead, and browsers without the method never show a picker on click at all.
 */
const openPickerOnClick = (event: MouseEvent<HTMLInputElement>): void => {
    try {
        event.currentTarget.showPicker?.();
    } catch {
        // Nothing to do: the field is still typeable.
    }
};

const lockedDateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'long',
});

/** "October 27, 2026 at 14:15", or the date alone when there is no time. */
const formatLockedDeadline = (date: string, time: string): string => {
    if (date === '') {
        return 'Not set';
    }

    const day = lockedDateFormatter.format(new Date(`${date}T00:00`));

    return time === '' ? day : `${day} at ${time}`;
};

/**
 * Where and when one project is, and whatever else the team needs about it.
 *
 * Part of the requirements step rather than a step of its own, because all of it is about
 * one piece of work and every other answer about that work is asked for here too.
 * It used to be a separate step asking once for the whole request, which asked a
 * question with no useful answer: one email can carry a countertop in Makati and a
 * floor in Cebu, and there is no city that is both.
 *
 * Its values live on the draft rather than on the inquiry, so an unfinished
 * project costs nothing if it is walked away from, and a finished one carries its
 * own site and date onto the sheet without any later step having to match it back
 * up.
 *
 * The controls are laid out in columns of two rather than stacked, and the block
 * runs the width of the page: capped at a reading measure the narrowest column was
 * too short to read a label in.
 */
const InquiryProjectDetails = ({
    projectNumber,
    needsNeededByTime,
    deadlineLockReason = null,
    allowAttachments = true,
}: {
    /**
     * Whether files can be attached. Staff opening an inquiry for a lead's
     * client cannot: a client attachment is the client's own to upload.
     */
    allowAttachments?: boolean;
    /**
     * Which project these answers are for, 1-based. Only in the control ids, so a
     * label cannot end up pointing at another project's field.
     */
    projectNumber: number;

    /**
     * Whether this project's service is one the team attends at an hour, which is
     * what turns "needed by" from a date into a date and a time.
     *
     * Handed down rather than worked out here: the schema decides the same thing
     * from the same catalogue flag, and this block cannot see which services the
     * draft is for, because on a project with stone the services are not what the
     * date is about.
     */
    needsNeededByTime: boolean;

    /**
     * Why the date and time can no longer be changed, when editing a sent
     * inquiry whose visit is no longer pending. They are then shown, not asked.
     */
    deadlineLockReason?: string | null;
}) => {
    const { locations } = useInquiryCatalog();
    const {
        register,
        control,
        setValue,
        getValues,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    /*
     * Everything optional stays folded away until it is wanted. A project that
     * already holds one of these answers opens with them showing, and so does one
     * with a complaint about them, because hidden answers can't be checked or fixed.
     */
    const [wantsMoreDetails, setWantsMoreDetails] = useState(() => {
        const { landmark, areaSqm, notes, attachments } = getValues();

        return (
            landmark !== '' ||
            areaSqm !== '' ||
            notes !== '' ||
            attachments.length > 0
        );
    });
    const showsMoreDetails =
        wantsMoreDetails ||
        errors.landmark !== undefined ||
        errors.areaSqm !== undefined ||
        errors.notes !== undefined ||
        errors.attachments !== undefined;

    /*
     * Scoped to the project rather than the flat `inquiry-*` ids the details step
     * used, because there is now a field of the same name per project on the page
     * behind this one: `id` has to be unique in the document, and `htmlFor` is how
     * a screen reader is told which label is this control's.
     */
    const id = (field: string) => `requirements-${field}-${projectNumber}`;

    /*
     * Subscribed with `useWatch` rather than read with `watch`: `watch` redraws
     * the component that owns the form, not this one, so the counter below sat
     * at zero while the reader typed.
     */
    const notes = useWatch({ control, name: 'notes' });
    const neededBy = useWatch({ control, name: 'neededBy' });
    const neededByTime = useWatch({ control, name: 'neededByTime' });

    const today = todayIsoDate();

    const attachmentErrors = attachmentMessages(errors.attachments);

    return (
        <div>
            <h3 className="text-base font-bold">Where and when</h3>

            <div className="mt-3 grid gap-5 md:grid-cols-2">
                <FormField
                    id={id('location')}
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
                                    id={id('location')}
                                    ref={field.ref}
                                    value={field.value}
                                    options={locations}
                                    filter={(query) =>
                                        searchLocations(locations, query)
                                    }
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

                {/*
                 * A service the team attends at an hour, so when it is wanted is
                 * a moment rather than a day. The date widens to take the time
                 * beside it instead of the time opening a row of its own: they
                 * are one answer to one question, and splitting them across the
                 * two columns would put the second half below the first on a
                 * narrow screen with the area in between.
                 */}
                {deadlineLockReason !== null ? (
                    /*
                     * Shown rather than asked: the visit has been confirmed (or
                     * has happened), so the answer stands as it is. The value
                     * stays in the draft, so saving sends it back unchanged.
                     */
                    <div>
                        <p className="text-sm font-bold">
                            When do you need it?
                        </p>
                        <p
                            id={id('needed-by')}
                            className="mt-2 text-sm"
                            aria-describedby={id('needed-by-locked')}
                        >
                            {formatLockedDeadline(
                                neededBy,
                                needsNeededByTime ? neededByTime : '',
                            )}
                        </p>
                        <p
                            id={id('needed-by-locked')}
                            className="mt-1 text-xs text-secondary-foreground"
                        >
                            {deadlineLockReason}
                        </p>
                    </div>
                ) : needsNeededByTime ? (
                    <FormField
                        id={id('needed-by')}
                        label="When do you need it?"
                        required
                        /*
                         * The pair is one answer, so the complaint about a missing
                         * half is written against the date: it is the control the
                         * reader is sent to, and the one that says what the hour
                         * has to be later than.
                         */
                        error={
                            fieldMessage(errors.neededBy) ??
                            fieldMessage(errors.neededByTime)
                        }
                    >
                        {(accessibility) => (
                            <div
                                id={id('needed-by-group')}
                                className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2"
                            >
                                <Input
                                    id={id('needed-by')}
                                    type="date"
                                    min={today}
                                    {...register('neededBy')}
                                    onClick={openPickerOnClick}
                                    aria-describedby={accessibility.describedBy}
                                    aria-invalid={accessibility.invalid}
                                    className="rounded-none [&::-webkit-calendar-picker-indicator]:hidden"
                                />

                                <span
                                    aria-hidden
                                    className="text-sm text-secondary-foreground"
                                >
                                    at
                                </span>

                                <Input
                                    id={id('needed-by-time')}
                                    type="time"
                                    {...register('neededByTime')}
                                    onClick={openPickerOnClick}
                                    /*
                                     * Its own name, because the group's label is
                                     * pointed at the date: without this the second
                                     * control would be announced as an edit box
                                     * with nothing on it. "Time of day" beside
                                     * "When do you need it?" says the same thing to a screen
                                     * reader as the two fields and the word
                                     * between them say to anyone else.
                                     */
                                    aria-label="Time of day"
                                    aria-describedby={accessibility.describedBy}
                                    aria-invalid={accessibility.invalid}
                                    className="rounded-none [&::-webkit-calendar-picker-indicator]:hidden"
                                />
                            </div>
                        )}
                    </FormField>
                ) : (
                    <FormField
                        id={id('needed-by')}
                        label="When do you need it?"
                        required
                        error={errors.neededBy?.message}
                    >
                        {(accessibility) => (
                            <Input
                                id={id('needed-by')}
                                type="date"
                                min={today}
                                {...register('neededBy')}
                                onClick={openPickerOnClick}
                                aria-describedby={accessibility.describedBy}
                                aria-invalid={accessibility.invalid}
                                className="rounded-none [&::-webkit-calendar-picker-indicator]:hidden"
                            />
                        )}
                    </FormField>
                )}
            </div>

            <div className="mt-6">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-expanded={showsMoreDetails}
                    aria-controls={id('more-details')}
                    onClick={() => setWantsMoreDetails(!showsMoreDetails)}
                >
                    <ChevronDown
                        aria-hidden
                        className={showsMoreDetails ? 'rotate-180' : ''}
                    />
                    Add more details (optional)
                </Button>

                {/*
                 * Hidden rather than taken out, so the fields stay registered and
                 * an upload in flight is not lost when it is folded away.
                 */}
                <div
                    id={id('more-details')}
                    className={showsMoreDetails ? 'mt-5' : 'hidden'}
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField
                            id={id('landmark')}
                            label="A nearby landmark"
                            error={errors.landmark?.message}
                        >
                            {(accessibility) => (
                                <Input
                                    id={id('landmark')}
                                    {...register('landmark')}
                                    maxLength={LANDMARK_MAX}
                                    placeholder="Near the old railway station"
                                    aria-describedby={accessibility.describedBy}
                                    aria-invalid={accessibility.invalid}
                                    className="rounded-none"
                                />
                            )}
                        </FormField>
                        <FormField
                            id={id('area')}
                            label="Size of the area in square metres, if you know"
                            error={errors.areaSqm?.message}
                        >
                            {(accessibility) => (
                                <Input
                                    id={id('area')}
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
                    </div>

                    <p className="mt-5 max-w-2xl text-sm text-secondary-foreground">
                        {allowAttachments
                            ? 'Plans, sketches or site photos help us quote accurately.'
                            : 'Files are added by the client.'}
                    </p>

                    <div className={allowAttachments ? 'mt-3' : 'hidden'}>
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
                                                    attachment.status ===
                                                    'done',
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
                        id={id('notes')}
                        label="Anything else we should know?"
                        error={errors.notes?.message}
                        className="mt-5"
                    >
                        {(accessibility) => (
                            <div>
                                <Textarea
                                    id={id('notes')}
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
            </div>
        </div>
    );
};

export default InquiryProjectDetails;

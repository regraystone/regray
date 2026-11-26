import type { MouseEvent } from 'react';
import { Controller, useFormContext, useWatch } from 'react-hook-form';
import FileUpload from '@/components/file-upload';
import FormField, { fieldMessage } from '@/components/form-field';
import InquiryAddressField from '@/components/inquiry-address-field';
import InquiryDetailField from '@/components/inquiry-detail-field';
import InquiryPictureChoice, {
    CustomChoiceDescription,
} from '@/components/inquiry-picture-choice';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { NOTES_MAX } from '@/data/inquiries';
import {
    DELIVERY_METHODS,
    PICKUP,
    SIZE_FIELD,
    TABLE_SHAPE_FIELD,
    addressLabel,
    asksDeliveryMethod,
    customAnswerKey,
    findService,
    needsAddress,
    pickedChoice,
    projectFields,
    sizeKindFor,
    type ServiceField,
} from '@/lib/inquiry-flow';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import {
    todayIsoDate,
    type InquiryDraftInput,
    type InquiryDraftValues,
} from '@/lib/inquiry-schema';

/** The id the page scrolls to when the delivery method is unanswered. */
export const INQUIRY_DELIVERY_METHOD_ID = 'inquiry-delivery-method';

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
 * Open the browser's own date dialog when the field is clicked.
 *
 * Chrome and Edge only open it when their own small icon is clicked, and this
 * form hides that icon — it is a rounded grey pill inside an otherwise flat
 * square field, and nothing else here carries one, so it reads as a stray control
 * rather than as part of the field. With the icon hidden and nothing else wired
 * up, the only way to answer is to type: a reader who came to point at a day has
 * to work out its date format and key it in.
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

/** "October 27, 2026". */
const formatLockedDeadline = (date: string): string =>
    date === ''
        ? 'Not set'
        : lockedDateFormatter.format(new Date(`${date}T00:00`));

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
 *
 * What is always asked comes first: the questions the project's service has
 * to have answered (in its order), when, then how it is got and where: a
 * service that is delivered or picked up asks which, and a delivery its
 * address; an installed service asks only the installation address. Under
 * them, always on show, is what may be left out: any question the service
 * marks optional, the files and the notes.
 */
const InquiryProjectDetails = ({
    projectNumber,
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
     * Why the date can no longer be changed, when editing a sent inquiry whose
     * schedule is no longer pending. It is then shown, not asked.
     */
    deadlineLockReason?: string | null;
}) => {
    const { serviceTypes } = useInquiryCatalog();
    const {
        register,
        control,
        setValue,
        clearErrors,
        formState: { errors, isSubmitted },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    const serviceId = useWatch({ control, name: 'serviceId' });
    const answers = useWatch({ control, name: 'answers' });
    const service = findService(serviceTypes, serviceId);
    const questions = projectFields(service);
    const requiredQuestions = questions.filter(({ required }) => required);
    const optionalQuestions = questions.filter(({ required }) => !required);
    // A table's size is asked the way its shape asks it: a diameter, or width x length.
    const sizeKind = sizeKindFor(service, answers ?? {});

    /**
     * A new table shape that asks the size another way leaves the size
     * unanswered: "120 x 80 cm" is no round table's diameter.
     */
    const answerShape = (next: string) => {
        if (
            sizeKindFor(service, { ...answers, [TABLE_SHAPE_FIELD]: next }) !==
            sizeKind
        ) {
            setValue(`answers.${SIZE_FIELD}`, '', { shouldDirty: true });
            clearErrors(`answers.${SIZE_FIELD}`);
        }
    };

    /** A custom choice's description, kept beside its answer. */
    const customDescription = (field: ServiceField) => (
        <Controller
            control={control}
            name={`answers.${customAnswerKey(field.slug)}`}
            defaultValue=""
            render={({ field: described }) => (
                <CustomChoiceDescription
                    id={id(`${field.slug.replaceAll('_', '-')}-custom`)}
                    choice={pickedChoice(field, answers?.[field.slug])!}
                    value={described.value ?? ''}
                    onChange={described.onChange}
                    onBlur={described.onBlur}
                    error={fieldMessage(
                        errors.answers?.[customAnswerKey(field.slug)],
                    )}
                />
            )}
        />
    );

    /**
     * One of the service's project questions, answered once for the project:
     * picture cards across the whole width for a question with pictures, its
     * dropdown or entry otherwise. A custom choice opens a box to describe it.
     */
    const question = (field: ServiceField) => (
        <Controller
            key={field.slug}
            control={control}
            name={`answers.${field.slug}`}
            // Unanswered is `''`, as every answer starts, never undefined.
            defaultValue=""
            render={({ field: answer }) => {
                const onChange = (next: string) => {
                    answer.onChange(next);

                    if (field.slug === TABLE_SHAPE_FIELD) {
                        answerShape(next);
                    }
                };

                if (field.answerType === 'choice' && field.pictures === true) {
                    return (
                        <div className="md:col-span-2">
                            <Controller
                                control={control}
                                name={`answers.${customAnswerKey(field.slug)}`}
                                defaultValue=""
                                render={({ field: described }) => (
                                    <InquiryPictureChoice
                                        id={id(field.slug.replaceAll('_', '-'))}
                                        field={field}
                                        value={answer.value ?? ''}
                                        onChange={onChange}
                                        onBlur={answer.onBlur}
                                        fieldRef={answer.ref}
                                        error={fieldMessage(
                                            errors.answers?.[field.slug],
                                        )}
                                        customValue={described.value ?? ''}
                                        onCustomChange={described.onChange}
                                        onCustomBlur={described.onBlur}
                                        customError={fieldMessage(
                                            errors.answers?.[
                                                customAnswerKey(field.slug)
                                            ],
                                        )}
                                    />
                                )}
                            />
                        </div>
                    );
                }

                const asksSize = field.slug === SIZE_FIELD;

                return (
                    <div>
                        <InquiryDetailField
                            // Remounted when the shape asks the size another way.
                            key={asksSize ? (sizeKind ?? 'free') : undefined}
                            id={id(field.slug.replaceAll('_', '-'))}
                            field={field}
                            value={answer.value ?? ''}
                            onChange={onChange}
                            onBlur={answer.onBlur}
                            fieldRef={answer.ref}
                            error={fieldMessage(errors.answers?.[field.slug])}
                            sizeKind={asksSize ? sizeKind : null}
                        />
                        {pickedChoice(field, answer.value)?.isCustom === true
                            ? customDescription(field)
                            : null}
                    </div>
                );
            }}
        />
    );

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
    const deliveryMethod = useWatch({ control, name: 'deliveryMethod' });

    const asksMethod = asksDeliveryMethod(service);
    /* A pickup is at the studio, so it has no address to ask for. */
    const asksAddress =
        service !== undefined && needsAddress(service, deliveryMethod);

    const chooseDeliveryMethod = (next: string) => {
        setValue('deliveryMethod', next, {
            shouldDirty: true,
            shouldValidate: isSubmitted,
        });

        if (next === PICKUP) {
            clearErrors(['addressId', 'address']);
        }
    };

    const today = todayIsoDate();

    const attachmentErrors = attachmentMessages(errors.attachments);

    return (
        <div>
            <div className="grid gap-5 md:grid-cols-2">
                {requiredQuestions.map(question)}

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
                            {formatLockedDeadline(neededBy)}
                        </p>
                        <p
                            id={id('needed-by-locked')}
                            className="mt-1 text-xs text-secondary-foreground"
                        >
                            {deadlineLockReason}
                        </p>
                    </div>
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

                {asksMethod ? (
                    <FormField
                        id={INQUIRY_DELIVERY_METHOD_ID}
                        label="How would you like to get it?"
                        required
                        error={fieldMessage(errors.deliveryMethod)}
                    >
                        {(accessibility) => (
                            <Select
                                value={deliveryMethod}
                                onValueChange={chooseDeliveryMethod}
                            >
                                <SelectTrigger
                                    id={INQUIRY_DELIVERY_METHOD_ID}
                                    aria-describedby={accessibility.describedBy}
                                    aria-invalid={accessibility.invalid}
                                    className="w-full rounded-none text-base data-[size=default]:h-11"
                                >
                                    <SelectValue placeholder="Choose one" />
                                </SelectTrigger>
                                <SelectContent>
                                    {DELIVERY_METHODS.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                            className="rounded-none"
                                        >
                                            {option.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>
                ) : null}

                {asksAddress ? (
                    <InquiryAddressField
                        label={addressLabel(service)}
                        projectNumber={projectNumber}
                    />
                ) : null}
            </div>

            {/*
             * The optional answers sit under the required ones, always on show:
             * there are few of them, so folding them away only hid them.
             */}
            <div className="mt-6">
                <div id={id('more-details')}>
                    {optionalQuestions.length > 0 ? (
                        <div className="mb-5 grid gap-5 md:grid-cols-2">
                            {optionalQuestions.map(question)}
                        </div>
                    ) : null}

                    <p className="max-w-2xl text-sm text-secondary-foreground">
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

import {
    Controller,
    useFieldArray,
    useFormContext,
    useWatch,
} from 'react-hook-form';
import { Minus, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { fieldMessage } from '@/components/form-field';
import InquiryDetailField from '@/components/inquiry-detail-field';
import InquiryProjectDetails from '@/components/inquiry-project-details';
import { InquiryActionBar } from '@/components/inquiry-question';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import {
    asksQuantity,
    findProjectService,
    questionForStone,
    stoneFieldKey,
    thicknessAfterSizeChange,
    stoneFields,
    type ServiceField,
    type StoneFieldKey,
} from '@/lib/inquiry-flow';
import {
    MAX_STONE_QUANTITY,
    MAX_STONE_ROWS,
    type InquiryDraftInput,
    type InquiryDraftValues,
} from '@/lib/inquiry-schema';

/*
 * The path to one field of one stone in the draft, each cast to its own literal
 * path rather than to the whole `FieldPath` union.
 *
 * A union would type-check and then be useless: `useWatch` and `Controller` would
 * both report that a quantity might be "a number, or a string, or an array of
 * stones", and every row would have to narrow it again just to be readable. One
 * helper per field is what keeps each of them knowing its own answer.
 */
const stoneIdPath = (index: number) => `stones.${index}.stoneId` as const;
const quantityPath = (index: number) => `stones.${index}.quantity` as const;
const answerPath = (index: number, key: StoneFieldKey) =>
    `stones.${index}.${key}` as const;

/**
 * The columns a stone row's answers take from tablet width up, by how many
 * there are. Four (a slab size, thickness, edge and finish) go two by two, so
 * a size of one's own has room for its width, length and unit.
 */
const ANSWER_COLUMNS = {
    1: 'sm:grid-cols-1',
    2: 'sm:grid-cols-2',
    3: 'sm:grid-cols-3',
    4: 'sm:grid-cols-2',
} as const;

/** The id of a stone row's answer: `requirements-slab-size-0`, `requirements-thickness-0`. */
const stoneAnswerId = (key: StoneFieldKey, index: number): string =>
    `requirements-${key === 'slabSize' ? 'slab-size' : key}-${index}`;

/**
 * One stone's row: how many, and how it is worked.
 *
 * Its own component so it can read the draft form once rather than threading the
 * field path, the current value and the complaint for three controls through the
 * step's own body.
 *
 * Both of its answers are read with `useWatch` rather than `watch`. `watch` reads
 * the live form but subscribes nothing here: the redraw it asks for belongs to
 * the component that called `useForm`, which is three levels up, so a write this
 * row makes to `stones.0.quantity` never reached its own markup. That is what
 * left the decrement disabled at one forever and made every press of the
 * increment count from a count already spent, while the number in the field moved
 * — the field is a DOM node, so it shows a value the component was never told
 * about. `useWatch` subscribes the field itself, so the row that owns the answer
 * is the row that redraws.
 */
const StoneSpecRow = ({
    index,
    rowCount,
    questions,
    countsPieces,
    onDuplicate,
    onRemove,
    canRemove,
}: {
    index: number;
    rowCount: number;
    /** The stone-scope questions the project's service asks, in order. */
    questions: ServiceField[];
    /** Whether the service asks how many pieces: only the slab services do. */
    countsPieces: boolean;
    /** False on the only row: the request needs a stone, or Help me choose. */
    canRemove: boolean;
    onDuplicate: () => void;
    onRemove: () => void;
}) => {
    const {
        control,
        register,
        setValue,
        getValues,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    const stoneId = useWatch({ control, name: stoneIdPath(index) });
    const quantity = useWatch({ control, name: quantityPath(index) });
    // The thickness list follows the size: each size lists only what it is cut in.
    const slabSize = useWatch({ control, name: answerPath(index, 'slabSize') });
    const { stones } = useInquiryCatalog();
    const stone = stones.find((entry) => entry.slug === stoneId);
    const rowErrors = errors.stones?.[index];

    /*
     * Clamped at both ends rather than left to the input: a typed number can
     * arrive empty, negative or as a paste of several digits, and the stepper
     * would then walk the count further out of range rather than back into it.
     *
     * The count is read from the form rather than from the value this row was
     * drawn with, because the two are allowed to disagree for the length of one
     * redraw, and two presses in that gap have to count from the first.
     */
    const stepBy = (amount: number) => {
        const latest = getValues(quantityPath(index));
        const current = Number.isFinite(latest) ? latest : 1;
        const next = Math.min(
            MAX_STONE_QUANTITY,
            Math.max(1, Math.round(current) + amount),
        );

        setValue(quantityPath(index), next, {
            shouldDirty: true,
            shouldValidate: true,
        });
    };

    /*
     * A stone dropped from the catalogue since it was picked. The draft's own
     * schema refuses it, so the reader is sent back to the stones rather than
     * shown a row with nothing in it.
     */
    if (stone === undefined) {
        return (
            <li className="py-6 text-sm text-secondary-foreground">
                That stone is no longer available. Press Change next to the
                stones above and choose another.
            </li>
        );
    }

    const ids = {
        quantity: `requirements-quantity-${index}`,
    };

    return (
        /*
         * Rules between the rows rather than a box around each one: the stones
         * are rows of one list, not separate objects to deal with one at a time.
         */
        <li
            className={`grid gap-x-6 gap-y-4 py-5 ${
                countsPieces ? 'sm:grid-cols-[minmax(0,1fr)_11rem]' : ''
            }`}
        >
            <div className="flex min-w-0 items-center gap-4">
                <img
                    src={stone.thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-14 shrink-0 rounded-md object-cover"
                />
                <div className="min-w-0">
                    <p className="text-base font-bold break-words">
                        {stone.name}
                    </p>
                    {/* The server's word that the stone does not fit the service. */}
                    {fieldMessage(rowErrors?.stoneId) === undefined ? null : (
                        <p
                            role="alert"
                            className="mt-1 text-sm text-destructive"
                        >
                            {fieldMessage(rowErrors?.stoneId)}
                        </p>
                    )}
                </div>
            </div>

            {/*
             * Not a `FormField`: the two buttons are part of this control and the
             * label has to name the count for both of them, so the wrapper's
             * single-child shape would have to be broken anyway. Only the slab
             * services ask it; without it the stone takes the whole line.
             */}
            {countsPieces ? (
                <div>
                    <Label
                        htmlFor={ids.quantity}
                        className="text-sm font-bold text-foreground"
                    >
                        How many pieces?
                    </Label>

                    <div className="mt-2 flex items-stretch">
                        {/*
                         * Native buttons, so Tab reaches them and Space and Enter
                         * press them without any key handling. The count is still a
                         * text field between them, so typing it is not a second-class
                         * way of answering the question.
                         *
                         * `size-11 p-0` puts them at exactly the input's height and
                         * strips the button's own padding, so the three parts read as
                         * one control and are easy to press.
                         */}
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => stepBy(-1)}
                            disabled={quantity <= 1}
                            aria-label={`Fewer pieces of ${stone.name}`}
                            className="size-11 shrink-0 cursor-pointer rounded-none border-r-0 p-0"
                        >
                            <Minus aria-hidden />
                        </Button>
                        {/*
                         * `no-stepper` takes away the arrows the browser draws inside
                         * a number field, which would be a second pair of stepper
                         * buttons beside these two, and which push the count off
                         * centre however the field is aligned. It is defined in
                         * `app.css` rather than spelled out here so the webkit and
                         * Firefox half of it are written once.
                         */}
                        <Input
                            id={ids.quantity}
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={MAX_STONE_QUANTITY}
                            step={1}
                            {...register(quantityPath(index), {
                                valueAsNumber: true,
                            })}
                            aria-invalid={
                                fieldMessage(rowErrors?.quantity) !== undefined
                            }
                            className="no-stepper h-11 rounded-none text-center"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => stepBy(1)}
                            disabled={quantity >= MAX_STONE_QUANTITY}
                            aria-label={`More pieces of ${stone.name}`}
                            className="size-11 shrink-0 cursor-pointer rounded-none border-l-0 p-0"
                        >
                            <Plus aria-hidden />
                        </Button>
                    </div>

                    <p
                        id={`${ids.quantity}-error`}
                        role="alert"
                        className="mt-2 text-sm text-destructive empty:hidden"
                    >
                        {fieldMessage(rowErrors?.quantity)}
                    </p>
                </div>
            ) : null}

            <div className="col-span-full">
                {questions.length > 0 ? (
                    /*
                     * One column per question from tablet width up, so two
                     * questions share the line rather than leaving a third
                     * of it empty.
                     */
                    <div
                        className={`grid gap-4 ${
                            ANSWER_COLUMNS[
                                Math.min(questions.length, 4) as 1 | 2 | 3 | 4
                            ]
                        }`}
                    >
                        {questions.map((serviceQuestion) => {
                            // A slab size or thickness lists this row's stone's own options.
                            const question = questionForStone(
                                serviceQuestion,
                                stone,
                                { slabSize },
                            );
                            const key = stoneFieldKey(question);

                            return key === null ? null : (
                                <Controller
                                    key={question.slug}
                                    control={control}
                                    name={answerPath(index, key)}
                                    render={({ field }) => (
                                        <InquiryDetailField
                                            /*
                                             * Drawn afresh when the thickness list
                                             * narrows, so it reads the new list.
                                             */
                                            key={
                                                key === 'thickness'
                                                    ? question.suggestions
                                                          .map(
                                                              ({ value }) =>
                                                                  value,
                                                          )
                                                          .join('|')
                                                    : undefined
                                            }
                                            id={stoneAnswerId(key, index)}
                                            field={question}
                                            value={field.value}
                                            hint={
                                                key === 'slabSize'
                                                    ? (stone.sizeNote ??
                                                      undefined)
                                                    : undefined
                                            }
                                            onChange={(next: string) => {
                                                field.onChange(next);

                                                if (key !== 'slabSize') {
                                                    return;
                                                }

                                                /*
                                                 * A listed thickness the new size
                                                 * is not cut in no longer answers.
                                                 */
                                                const thickness = getValues(
                                                    answerPath(
                                                        index,
                                                        'thickness',
                                                    ),
                                                );
                                                const kept =
                                                    thicknessAfterSizeChange(
                                                        stone,
                                                        next,
                                                        thickness,
                                                    );

                                                if (kept !== thickness) {
                                                    setValue(
                                                        answerPath(
                                                            index,
                                                            'thickness',
                                                        ),
                                                        kept,
                                                        { shouldDirty: true },
                                                    );
                                                }
                                            }}
                                            onBlur={field.onBlur}
                                            fieldRef={field.ref}
                                            labelContext={stone.name}
                                            error={fieldMessage(
                                                rowErrors?.[key],
                                            )}
                                        />
                                    )}
                                />
                            );
                        })}
                    </div>
                ) : null}

                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onDuplicate}
                        disabled={rowCount >= MAX_STONE_ROWS}
                        aria-label={`Add it again with another finish: ${stone.name}`}
                        title={
                            rowCount >= MAX_STONE_ROWS
                                ? `Maximum ${MAX_STONE_ROWS} rows per project`
                                : undefined
                        }
                        className="h-9 cursor-pointer px-2 text-xs"
                    >
                        Add it again with another finish
                    </Button>

                    {canRemove ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onRemove}
                            aria-label={`Remove this ${stone.name} row`}
                            className="h-9 cursor-pointer px-2 text-xs"
                        >
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>
        </li>
    );
};

/** The bar is drawn outside this form, so its button names the form it submits. */
const DETAILS_FORM_ID = 'inquiry-details-form';

type InquiryProjectFormProps = {
    /** Which project this is, 1-based, for the field ids and the heading. */
    projectNumber: number;
    /**
     * Run by the page when the draft is ready. It names, validates, commits and
     * decides where to go next, so the draft never leaves the page's hands.
     */
    onCommit: () => void;
    /**
     * Why this project's date and time can no longer be changed, when editing a
     * sent inquiry whose visit is no longer pending. Null otherwise.
     */
    deadlineLockReason?: string | null;
    /** Whether files can be attached: not when staff open an inquiry for a lead's client. */
    allowAttachments?: boolean;
    /** Back to the stones question, from the prompt shown while none is picked. */
    onChooseStones: () => void;
};

/**
 * The last question of the request: where and when the work is, how many of each
 * stone and what each needs (the stone-scope questions the service asks), and
 * anything else. A service that asks for no stones (Installation Only) has no
 * stone rows and no prompt to choose them.
 *
 * With no stone picked, the rows give way to a prompt that sends the reader back
 * to the stones. When Regray is to choose (Help me choose), there are no stones
 * to ask about, so that area is left empty. The rest of the details stay as
 * they are either way.
 *
 * One form, so pressing Enter in any field is the same answer as pressing the
 * button. `noValidate` because the browser's own bubbles are the wrong voice for
 * this app, and they would fire before the page's own messages.
 */
const InquiryProjectForm = ({
    projectNumber,
    onCommit,
    deadlineLockReason = null,
    allowAttachments = true,
    onChooseStones,
}: InquiryProjectFormProps) => {
    const {
        setValue,
        getValues,
        watch,
        control,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    const { fields, remove } = useFieldArray({
        control,
        name: 'stones',
    });

    const { stones, serviceTypes } = useInquiryCatalog();
    const rows = watch('stones');
    const serviceId = useWatch({ control, name: 'serviceId' });
    const applicationId = useWatch({ control, name: 'applicationId' });
    // The stone questions are the service's, with or without an application.
    const service = findProjectService(serviceTypes, serviceId, applicationId);
    const questions = stoneFields(service);
    const countsPieces = asksQuantity(service);
    const stoneAdvice = useWatch({ control, name: 'stoneAdvice' });

    const pieceCount = rows.reduce(
        (total, row) =>
            total + (Number.isFinite(row.quantity) ? row.quantity : 0),
        0,
    );

    const countDistinctStones = () => {
        const seen = new Set<string>();
        rows.forEach((row) => {
            seen.add(row.stoneId);
        });
        return seen.size;
    };

    const getRowCountForStone = (stoneId: string): number =>
        rows.filter((row) => row.stoneId === stoneId).length;

    const duplicateRow = (index: number) => {
        const row = getValues(`stones.${index}`);
        if (row && rows.length < MAX_STONE_ROWS) {
            const stoneId = row.stoneId;
            const lastRowIndexOfStone = rows
                .map((r, i) => (r.stoneId === stoneId ? i : -1))
                .filter((i) => i !== -1)
                .pop();

            const insertAtIndex =
                lastRowIndexOfStone !== undefined
                    ? lastRowIndexOfStone + 1
                    : index + 1;

            const allRows = getValues('stones');
            const newRow = {
                stoneId: row.stoneId,
                quantity: 1,
                // The same slab, worked another way.
                slabSize: row.slabSize,
                thickness: row.thickness,
                edge: '',
                finish: '',
            };

            const reordered = [
                ...allRows.slice(0, insertAtIndex),
                newRow,
                ...allRows.slice(insertAtIndex),
            ];

            setValue('stones', reordered, {
                shouldDirty: true,
                shouldValidate: true,
            });

            // The new row starts with its lists unanswered: the first is where to go.
            setTimeout(() => {
                const first =
                    questions.find(
                        ({ answerType }) => answerType === 'choice',
                    ) ?? questions[0];
                const key = first === undefined ? null : stoneFieldKey(first);

                if (key !== null) {
                    document
                        .getElementById(stoneAnswerId(key, insertAtIndex))
                        ?.focus();
                }
            }, 0);
        }
    };

    const removeRow = (index: number) => {
        const stoneId = rows[index]?.stoneId;
        remove(index);

        // The stone's last row: it is no longer one of the reader's stones.
        if (stoneId !== undefined && getRowCountForStone(stoneId) === 1) {
            const remaining = getValues('stoneIds').filter(
                (id) => id !== stoneId,
            );

            setValue('stoneIds', remaining, { shouldDirty: true });
        }

        setTimeout(() => {
            if (index > 0) {
                const prevDuplicateBtn = document.querySelector(
                    `button[aria-label*="Add it again with another finish: ${stones.find((s) => s.slug === stoneId)?.name}"]`,
                );
                if (prevDuplicateBtn instanceof HTMLElement) {
                    prevDuplicateBtn.focus();
                }
            }
        }, 0);
    };

    return (
        <form
            id={DETAILS_FORM_ID}
            noValidate
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
                event.preventDefault();
                onCommit();
            }}
        >
            <>
                {rows.length > 0 ? (
                    <>
                        <ul className="divide-y divide-sidebar-border">
                            {fields.map((field, index) => {
                                const stoneId = rows[index]?.stoneId;
                                const rowCount = stoneId
                                    ? getRowCountForStone(stoneId)
                                    : 1;
                                return (
                                    <StoneSpecRow
                                        key={field.id}
                                        index={index}
                                        rowCount={rowCount}
                                        canRemove={rows.length > 1}
                                        onDuplicate={() => duplicateRow(index)}
                                        onRemove={() => removeRow(index)}
                                        questions={questions}
                                        countsPieces={countsPieces}
                                    />
                                );
                            })}
                        </ul>
                    </>
                ) : stoneAdvice || service?.requiresStones === false ? null : (
                    <div
                        className="flex flex-col gap-3 rounded-md border border-dashed border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
                        data-testid="stones-empty-state"
                    >
                        <p className="text-sm text-secondary-foreground">
                            Choose the stones you want in step 2 first. What
                            each of them needs will show here.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onChooseStones}
                            aria-label="Choose stones in step 2"
                            className="w-full shrink-0 sm:w-auto"
                        >
                            Choose stones
                        </Button>
                    </div>
                )}

                {/*
                 * `stoneIds` is where the reader is sent back to when the
                 * stones came up empty; `stones` is what the committed project
                 * says about the same thing.
                 */}
                <p
                    role="alert"
                    className="mt-4 text-sm text-destructive empty:hidden"
                >
                    {fieldMessage(errors.stoneIds) ??
                        fieldMessage(errors.stones)}
                </p>
            </>

            {/*
             * After the stones, because it is about the work rather than about the
             * material. It reads the draft form, so an unfinished project's site
             * and date cost nothing if the reader walks away from it, and a
             * finished one carries its own onto the sheet.
             */}
            <div
                className={
                    rows.length === 0 &&
                    (stoneAdvice || service?.requiresStones === false)
                        ? ''
                        : 'mt-3'
                }
            >
                <InquiryProjectDetails
                    projectNumber={projectNumber}
                    allowAttachments={allowAttachments}
                    deadlineLockReason={deadlineLockReason}
                />
            </div>

            {/*
             * The form's submit button, so Enter reaches the same validation.
             */}
            <InquiryActionBar>
                <p
                    aria-live="polite"
                    className="min-w-0 flex-1 text-sm font-bold text-foreground"
                >
                    {rows.length === 0
                        ? ''
                        : `${countDistinctStones()} ${countDistinctStones() === 1 ? 'stone' : 'stones'}${
                              countsPieces
                                  ? `, ${pieceCount} ${pieceCount === 1 ? 'piece' : 'pieces'}`
                                  : ''
                          }`}
                </p>

                <Button
                    type="submit"
                    form={DETAILS_FORM_ID}
                    className="w-full shrink-0 sm:w-auto"
                >
                    Continue to review
                </Button>
            </InquiryActionBar>
        </form>
    );
};

export default InquiryProjectForm;

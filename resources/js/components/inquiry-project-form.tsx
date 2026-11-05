import {
    Controller,
    useFieldArray,
    useFormContext,
    useWatch,
} from 'react-hook-form';
import { Copy, Minus, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import FormField, {
    chooseFromList,
    fieldMessage,
} from '@/components/form-field';
import InquiryProjectDetails from '@/components/inquiry-project-details';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
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
const edgePath = (index: number) => `stones.${index}.edge` as const;
const finishPath = (index: number) => `stones.${index}.finish` as const;

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
    onDuplicate,
    onRemove,
    onApplyToAll,
    showApplyToAll,
    canRemove,
}: {
    index: number;
    rowCount: number;
    /** False on the only row: the request needs a stone, or Help me choose. */
    canRemove: boolean;
    onDuplicate: () => void;
    onRemove: () => void;
    onApplyToAll: () => void;
    showApplyToAll: boolean;
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
    const edge = useWatch({ control, name: edgePath(index) });
    const finish = useWatch({ control, name: finishPath(index) });
    const { stones, edgeProfiles, finishes, advisoryEdge, advisoryFinish } =
        useInquiryCatalog();
    const stone = stones.find((entry) => entry.slug === stoneId);
    const rowErrors = errors.stones?.[index];

    /*
     * Edge and finish are trade words, so they stay folded away behind the "advise
     * me" answer unless the reader asks for them. A blank row (a duplicate) and a
     * refused one open by themselves, because those need an answer.
     */
    const [wantsChoices, setWantsChoices] = useState(
        () => edge === '' || finish === '',
    );
    const showsChoices =
        wantsChoices ||
        fieldMessage(rowErrors?.edge) !== undefined ||
        fieldMessage(rowErrors?.finish) !== undefined;
    const isAdvised = edge === advisoryEdge && finish === advisoryFinish;

    const letRegrayAdvise = () => {
        const options = { shouldDirty: true, shouldValidate: true };

        setValue(edgePath(index), advisoryEdge ?? '', options);
        setValue(finishPath(index), advisoryFinish ?? '', options);
        setWantsChoices(false);
    };

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
        edge: `requirements-edge-${index}`,
        finish: `requirements-finish-${index}`,
    };

    return (
        /*
         * Rules between the rows rather than a box around each one: the stones
         * are rows of one list, not separate objects to deal with one at a time.
         */
        <li className="grid gap-x-6 gap-y-4 py-5 sm:grid-cols-[minmax(0,1fr)_11rem]">
            <div className="flex min-w-0 items-center gap-4">
                <img
                    src={stone.thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-14 shrink-0 rounded-md object-cover"
                />
                <p className="min-w-0 text-base font-bold break-words">
                    {stone.name}
                </p>
            </div>

            {/*
             * Not a `FormField`: the two buttons are part of this control and the
             * label has to name the count for both of them, so the wrapper's
             * single-child shape would have to be broken anyway.
             */}
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

            <div className="col-span-full">
                {showsChoices ? (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id={ids.edge}
                            label="Edge profile"
                            error={fieldMessage(rowErrors?.edge)}
                        >
                            {(accessibility) => (
                                <Controller
                                    control={control}
                                    name={edgePath(index)}
                                    render={({ field }) => (
                                        <Select
                                            /*
                                             * The value goes over as it is, empty string and
                                             * all. An unanswered select is `''`, and Radix shows
                                             * the placeholder for an empty string exactly as it
                                             * does for no value at all — but swapping one for
                                             * the other would move this from uncontrolled to
                                             * controlled the first time the reader answers, and
                                             * React says so loudly on the console.
                                             */
                                            value={field.value}
                                            onValueChange={chooseFromList(
                                                field.onChange,
                                                field.onBlur,
                                            )}
                                        >
                                            <SelectTrigger
                                                id={ids.edge}
                                                ref={field.ref}
                                                aria-describedby={
                                                    accessibility.describedBy
                                                }
                                                aria-invalid={
                                                    accessibility.invalid
                                                }
                                                className="w-full rounded-none"
                                            >
                                                <SelectValue placeholder="Choose an edge profile" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {edgeProfiles.map(
                                                    ({ name }) => (
                                                        <SelectItem
                                                            key={name}
                                                            value={name}
                                                            className="rounded-none"
                                                        >
                                                            {name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            )}
                        </FormField>

                        <FormField
                            id={ids.finish}
                            label="Finish"
                            error={fieldMessage(rowErrors?.finish)}
                        >
                            {(accessibility) => (
                                <Controller
                                    control={control}
                                    name={finishPath(index)}
                                    render={({ field }) => (
                                        <Select
                                            value={field.value}
                                            onValueChange={chooseFromList(
                                                field.onChange,
                                                field.onBlur,
                                            )}
                                        >
                                            <SelectTrigger
                                                id={ids.finish}
                                                ref={field.ref}
                                                aria-describedby={
                                                    accessibility.describedBy
                                                }
                                                aria-invalid={
                                                    accessibility.invalid
                                                }
                                                className="w-full rounded-none"
                                            >
                                                <SelectValue placeholder="Choose a finish" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {finishes.map(({ name }) => (
                                                    <SelectItem
                                                        key={name}
                                                        value={name}
                                                        className="rounded-none"
                                                    >
                                                        {name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            )}
                        </FormField>

                        {showApplyToAll ? (
                            <div className="col-span-full mt-2 flex flex-col items-end text-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onApplyToAll}
                                    aria-describedby={`apply-to-all-hint-${index}`}
                                    className="h-7 cursor-pointer px-1.5 text-xs"
                                >
                                    Apply to all stones
                                </Button>
                                <p
                                    id={`apply-to-all-hint-${index}`}
                                    className="mt-1 text-xs text-secondary-foreground"
                                >
                                    Copies this edge profile and finish to every
                                    other row.
                                </p>
                            </div>
                        ) : null}

                        <div className="col-span-full flex flex-wrap items-center gap-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={onDuplicate}
                                disabled={rowCount >= MAX_STONE_ROWS}
                                aria-label={`Duplicate ${stone.name}`}
                                title={
                                    rowCount >= MAX_STONE_ROWS
                                        ? `Maximum ${MAX_STONE_ROWS} rows per project`
                                        : undefined
                                }
                                className="h-9 cursor-pointer px-2 text-xs"
                            >
                                <Copy aria-hidden className="size-4" />
                                Add it again with another finish
                            </Button>

                            {advisoryEdge !== null &&
                            advisoryFinish !== null ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={letRegrayAdvise}
                                    className="h-9 cursor-pointer px-2 text-xs"
                                >
                                    Let Regray advise me
                                </Button>
                            ) : null}
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-secondary-foreground">
                        {isAdvised
                            ? "Edge and finish: we'll advise you"
                            : `Edge: ${edge}. Finish: ${finish}.`}
                    </p>
                )}

                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                    {showsChoices ? null : (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setWantsChoices(true)}
                            aria-label={`Choose edge and finish for ${stone.name} (optional)`}
                            className="h-9 cursor-pointer px-2 text-xs"
                        >
                            Choose edge and finish (optional)
                        </Button>
                    )}

                    {canRemove ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onRemove}
                            aria-label={`Remove this ${stone.name} row`}
                            className="h-9 cursor-pointer px-2 text-xs"
                        >
                            <Trash2 aria-hidden className="size-4" />
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>
        </li>
    );
};

type InquiryProjectFormProps = {
    /** Which project this is, 1-based, for the field ids and the heading. */
    projectNumber: number;
    /** Whether this project is a service that stands on its own: a visit. */
    isStandalone: boolean;
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
};

/**
 * The last question of the request: how many of each stone, and where and when
 * the work is. A visit has no stones, so it is only asked the second part.
 *
 * One form, so pressing Enter in any field is the same answer as pressing the
 * button. `noValidate` because the browser's own bubbles are the wrong voice for
 * this app, and they would fire before the page's own messages.
 */
const InquiryProjectForm = ({
    projectNumber,
    isStandalone,
    onCommit,
    deadlineLockReason = null,
    allowAttachments = true,
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

    const { stones } = useInquiryCatalog();
    const rows = watch('stones');

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

            setTimeout(() => {
                const newEdgeId = `requirements-edge-${insertAtIndex}`;
                const element = document.getElementById(newEdgeId);
                if (element) {
                    element.focus();
                    element.setAttribute(
                        'aria-label',
                        `Added another row for ${stones.find((s) => s.slug === stoneId)?.name ?? 'this stone'}. Choose its edge profile and finish.`,
                    );
                }
            }, 0);
        }
    };

    const removeRow = (index: number) => {
        const stoneId = rows[index]?.stoneId;
        remove(index);

        // The stone's last row: it is no longer one of the reader's stones.
        if (stoneId !== undefined && getRowCountForStone(stoneId) === 1) {
            setValue(
                'stoneIds',
                getValues('stoneIds').filter((id) => id !== stoneId),
                { shouldDirty: true },
            );
        }

        setTimeout(() => {
            if (index > 0) {
                const prevDuplicateBtn = document.querySelector(
                    `button[aria-label*="Duplicate ${stones.find((s) => s.slug === stoneId)?.name}"]`,
                );
                if (prevDuplicateBtn instanceof HTMLElement) {
                    prevDuplicateBtn.focus();
                }
            }
        }, 0);
    };

    const applyToAll = () => {
        const [first, ...rest] = getValues('stones');

        if (first === undefined || rest.length === 0) {
            return;
        }

        const updated = rows.map((row, idx) => ({
            ...row,
            ...(idx === 0
                ? {}
                : {
                      edge: first.edge,
                      finish: first.finish,
                  }),
        }));

        setValue('stones', updated, {
            shouldDirty: true,
            shouldValidate: true,
        });
    };

    return (
        <form
            noValidate
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
                event.preventDefault();
                onCommit();
            }}
        >
            {!isStandalone && rows.length > 0 ? (
                <>
                    <h3 className="text-base font-bold">Your stones</h3>

                    <ul className="mt-1 divide-y divide-sidebar-border">
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
                                    onApplyToAll={applyToAll}
                                    showApplyToAll={
                                        index === 0 && rows.length > 1
                                    }
                                />
                            );
                        })}
                    </ul>

                    <div className="border-t border-sidebar-border" />
                </>
            ) : null}

            {/*
             * `stoneIds` is where the reader is sent back to when the stones came
             * up empty; `stones` is what the committed project says about the same
             * thing.
             */}
            {isStandalone ? null : (
                <p
                    role="alert"
                    className="mt-4 text-sm text-destructive empty:hidden"
                >
                    {fieldMessage(errors.stoneIds) ??
                        fieldMessage(errors.stones)}
                </p>
            )}

            {/*
             * Last, because it is about the work rather than about the material,
             * and both shapes of project ask it. It reads the draft form, so an
             * unfinished project's site and date cost nothing if the reader walks
             * away from it, and a finished one carries its own onto the sheet.
             */}
            <div className={isStandalone ? '' : 'mt-8'}>
                <InquiryProjectDetails
                    projectNumber={projectNumber}
                    allowAttachments={allowAttachments}
                    /*
                     * The same fact the schema asks the time about, read from the
                     * same flag: a project that is nothing but a service which
                     * stands on its own is the one kind of work the team attends
                     * at an hour, and the form and the rule have to be looking at
                     * the same list to say so.
                     */
                    needsNeededByTime={isStandalone}
                    deadlineLockReason={deadlineLockReason}
                />
            </div>

            {/*
             * Inside the form on purpose, so this is the form's submit button and
             * Enter reaches the same validation. Sticky for the same reason as the
             * other steps': a project of six stones is taller than a phone.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <p
                    aria-live="polite"
                    className="min-w-0 flex-1 text-sm font-bold text-foreground"
                >
                    {isStandalone || rows.length === 0
                        ? ''
                        : `${countDistinctStones()} ${countDistinctStones() === 1 ? 'stone' : 'stones'}, ${pieceCount} ${pieceCount === 1 ? 'piece' : 'pieces'}`}
                </p>

                <Button type="submit" className="w-full shrink-0 sm:w-auto">
                    Continue to review
                </Button>
            </div>
        </form>
    );
};

export default InquiryProjectForm;

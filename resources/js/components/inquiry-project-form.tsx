import { Controller, useFormContext, useWatch } from 'react-hook-form';
import { ArrowLeft, Minus, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField, {
    chooseFromList,
    fieldMessage,
} from '@/components/form-field';
import InquiryProjectDetails from '@/components/inquiry-project-details';
import InquiryServiceBullets from '@/components/inquiry-service-bullets';
import { Badge } from '@/components/ui/badge';
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
import {
    EDGE_PROFILES,
    FINISHES,
    PROJECT_NAME_MAX,
    serviceName,
    serviceOptions,
    stones,
} from '@/data/constant';
import {
    MAX_STONE_QUANTITY,
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
const StoneSpecRow = ({ index }: { index: number }) => {
    const {
        control,
        register,
        setValue,
        getValues,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    const stoneId = useWatch({ control, name: stoneIdPath(index) });
    const quantity = useWatch({ control, name: quantityPath(index) });
    const stone = stones.find((entry) => entry.id === stoneId);
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
     * schema refuses it, so the reader is sent back to step 2 rather than shown a
     * row with nothing in it.
     */
    if (stone === undefined) {
        return (
            <li className="py-6 text-sm text-secondary-foreground">
                That stone is no longer available. Go back a step and choose
                another.
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
         * Four bands across on a wide screen, one per row of the stone grid —
         * stone, count, edge, finish — because the three controls are the same
         * three questions for every stone, and lining them up is what makes a
         * project of six stones readable as one specification rather than as six
         * cards. The labels stay on each row rather than moving into one header
         * above them all: a reader who has scrolled to the fourth stone still
         * knows which control is which without scrolling back up.
         *
         * Rules between the rows rather than a box around each one, for the same
         * reason the summary is a sheet: the stones are rows of one table, not
         * separate objects to be dealt with one at a time.
         */
        <li className="grid gap-x-6 gap-y-5 py-6 sm:grid-cols-[minmax(0,1fr)_8rem_minmax(0,1fr)_minmax(0,1fr)]">
            <div className="flex min-w-0 items-center gap-4">
                <img
                    src={stone.thumbnail}
                    alt=""
                    loading="lazy"
                    className="size-14 shrink-0 object-cover"
                />
                <p className="min-w-0 text-sm font-bold break-words">
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
                    Quantity
                    <span className="text-destructive" aria-hidden>
                        {' *'}
                    </span>
                    <span className="sr-only"> (required)</span>
                </Label>

                <div className="mt-2 flex items-stretch">
                    {/*
                     * Native buttons, so Tab reaches them and Space and Enter
                     * press them without any key handling. The count is still a
                     * text field between them, so typing it is not a second-class
                     * way of answering the question.
                     *
                     * `size-9 p-0` puts them at exactly the input's height and
                     * strips the button's own padding. The default button is padded
                     * rather than sized, so left alone it is two thirds taller than
                     * the field it belongs to and the three parts stop reading as
                     * one control.
                     */}
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => stepBy(-1)}
                        disabled={quantity <= 1}
                        aria-label={`Fewer pieces of ${stone.name}`}
                        className="size-9 shrink-0 cursor-pointer rounded-none border-r-0 p-0"
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
                        className="no-stepper rounded-none text-center"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => stepBy(1)}
                        disabled={quantity >= MAX_STONE_QUANTITY}
                        aria-label={`More pieces of ${stone.name}`}
                        className="size-9 shrink-0 cursor-pointer rounded-none border-l-0 p-0"
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

            <FormField
                id={ids.edge}
                label="Edge profile"
                required
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
                                    aria-describedby={accessibility.describedBy}
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
                id={ids.finish}
                label="Finish"
                required
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
                                    aria-describedby={accessibility.describedBy}
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
        </li>
    );
};

type InquiryProjectFormProps = {
    /** Which project this is, 1-based, for the field ids and the heading. */
    projectNumber: number;
    /**
     * The service this project is for when that service stands on its own, and how
     * many of those the reader picked in all. Null for a project built from stones,
     * which is the one shape with a name to give and a material to specify.
     *
     * A project of that kind is worked through one at a time rather than all at
     * once, so the count is what tells the reader there are more of these ahead of
     * them instead of a copy of this form appearing twice over.
     */
    standalone: { serviceId: string; total: number } | null;
    /**
     * The primary button. "Next service" while another of the services they picked
     * is still to be specified, "Back to Review" when they came here to change a
     * project that is already on the sheet, "Add to inquiry" otherwise.
     */
    submitLabel: string;
    /** Back to step 1, to change which services this project wants. */
    onChangeServices: () => void;
    /**
     * Run by the page when the draft is ready. It validates, commits and decides
     * where to go next, so the draft never leaves the page's hands.
     */
    onCommit: () => void;
    onBack: () => void;
};

/**
 * Step 3 of the inquiry flow, editing one project: what it is called, how each of
 * its stones is worked, and where and when the work is.
 *
 * A form rather than a picker because this is the one step where the answers are a
 * number, a specification and an address rather than a yes. It reads the draft form
 * from context like the other steps do, which is also what keeps an unfinished
 * project out of the form the request is actually built from.
 *
 * Laid out as a specification sheet rather than as a stack of cards: the name
 * answers the only question about the project as a whole, the services are said
 * back above the stones they are wanted for, the stones themselves are rows of one
 * table, and the site's own answers come last because they are about the work
 * rather than about the material. Three boxes said those were three separate
 * objects to be dealt with one at a time; one sheet of rows says this is one
 * project, and it hands the whole width to the content, which is what lets the
 * stones line up four across.
 *
 * Two shapes, because a project is two things. A project built from stones is
 * named by the reader and specifies its material. A project that is a service
 * standing on its own is named by the catalogue and has no material at all, so
 * asking for a name it already has and a stone that does not exist would be two
 * questions with only one answer between them. Both are asked the same questions
 * about where the work is.
 *
 * One form, so pressing Enter in any field is the same answer as pressing Add to
 * inquiry. `noValidate` because the browser's own bubbles are the wrong voice for
 * this app, and they would fire before the page's own messages.
 */
const InquiryProjectForm = ({
    projectNumber,
    standalone,
    submitLabel,
    onChangeServices,
    onCommit,
    onBack,
}: InquiryProjectFormProps) => {
    const {
        register,
        setValue,
        getValues,
        watch,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();

    const serviceIds = watch('serviceIds');
    const standaloneServiceIds = watch('standaloneServiceIds');
    const rows = watch('stones');

    /*
     * Whether this project is a service standing on its own. Handed down rather
     * than worked out here as well, because the page has to answer the same
     * question to decide where a commit lands and what the button says, and two
     * readings of one list pair would be free to disagree.
     */
    const isStandalone = standalone !== null;

    const pieceCount = rows.reduce(
        (total, row) =>
            total + (Number.isFinite(row.quantity) ? row.quantity : 0),
        0,
    );

    /*
     * Only the cutting is copied. The counts are the reader's own per stone: one
     * countertop slab and one floor tile of the same material are not the same
     * number, and copying it would quietly say they were.
     */
    const applyToAll = () => {
        const [first, ...rest] = getValues('stones');

        if (first === undefined || rest.length === 0) {
            return;
        }

        setValue(
            'stones',
            [
                first,
                ...rest.map((row) => ({
                    ...row,
                    edge: first.edge,
                    finish: first.finish,
                })),
            ],
            { shouldDirty: true, shouldValidate: true },
        );
    };

    return (
        <form
            noValidate
            onSubmit={(event: FormEvent<HTMLFormElement>) => {
                event.preventDefault();
                onCommit();
            }}
        >
            {isStandalone ? (
                /*
                 * Titled by the service rather than by a number. The service is the
                 * whole of this project — there is no material to specify and so no
                 * name to give it — and the count of how many more of these are ahead
                 * of the reader is the only thing a number could say here.
                 *
                 * Only when there are more than one, because "Service 1 of 1" says
                 * the reader is partway through a queue when there is no queue: a
                 * single service picked, or one project reopened to correct its date,
                 * is the whole of what they asked for and needs no count above it.
                 */
                <header>
                    {standalone.total > 1 ? (
                        <p className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Service 1 of {standalone.total}
                        </p>
                    ) : null}

                    <h3
                        className={`text-lg font-bold tracking-[-0.03em] break-words ${standalone.total > 1 ? 'mt-2' : ''}`}
                    >
                        {serviceName(standalone.serviceId)}
                    </h3>

                    <p className="mt-2 max-w-2xl text-xs text-secondary-foreground">
                        This project is named after the service, so there is
                        nothing to name here. What is left to tell us is where
                        the work is and when you want it done.
                    </p>
                </header>
            ) : (
                <>
                    <h3 className="text-lg font-bold tracking-[-0.03em]">
                        Project {projectNumber}
                    </h3>

                    {/*
                     * First, because it is what the two bands beneath it are the
                     * specifications of: a reader who has three projects to add has
                     * to be able to tell them apart on the summary, in the review
                     * sheet and in the team's inbox, and this is the only place they
                     * can say which is which. The first one named is also the name
                     * the whole request is known by.
                     */}
                    <FormField
                        id={`requirements-name-${projectNumber}`}
                        label="Project name"
                        required
                        error={fieldMessage(errors.name)}
                        className="mt-4 max-w-2xl"
                        hint="What you would call this part of the work, such as “Kitchen counters”."
                    >
                        {(accessibility) => (
                            <Input
                                id={`requirements-name-${projectNumber}`}
                                {...register('name')}
                                maxLength={PROJECT_NAME_MAX}
                                placeholder="Kitchen counters and island"
                                aria-describedby={accessibility.describedBy}
                                aria-invalid={accessibility.invalid}
                                className="rounded-none"
                            />
                        )}
                    </FormField>

                    {/*
                     * What step 1 collected, said back before the numbers are asked
                     * for. A reader who wants a different service set should not
                     * have to go back, remember to change it and come forward again.
                     */}
                    <div className="mt-8 flex flex-wrap items-center gap-x-3 gap-y-2 border-y border-sidebar-border py-3">
                        <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Services
                        </h4>

                        {serviceIds.map((id) => (
                            <Badge
                                key={id}
                                variant="secondary"
                                className="max-w-full rounded-none whitespace-normal text-secondary-foreground"
                            >
                                {/*
                                 * A service dropped from the catalogue since it was
                                 * picked shows its id rather than nothing: the reader
                                 * is about to commit this project, and an empty tag
                                 * would say they had chosen nothing.
                                 */}
                                {serviceOptions.find(
                                    (service) => service.id === id,
                                )?.name ?? id}
                            </Badge>
                        ))}

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onChangeServices}
                            className="h-7 cursor-pointer px-1.5 text-xs"
                        >
                            Change
                        </Button>
                    </div>

                    {/*
                     * Where the services that stand on their own went.
                     *
                     * They were picked on step 1 alongside the ones above, so this
                     * is the first place the reader is told they are not part of this
                     * project: without it, committing would silently split them out
                     * and the band above would go on listing them. Each one gets a
                     * form of its own straight after this one, which is what the
                     * button label says.
                     */}
                    {standaloneServiceIds.length > 0 ? (
                        <p className="mt-2.5 text-xs text-secondary-foreground">
                            Specified next, as{' '}
                            {standaloneServiceIds.length === 1
                                ? 'a project of its own'
                                : 'projects of their own'}
                            :{' '}
                            {standaloneServiceIds
                                .map((id) => serviceName(id))
                                .join(', ')}
                        </p>
                    ) : null}
                </>
            )}

            {isStandalone ? (
                /*
                 * The service's own bullets rather than the band above, because the
                 * name is already the heading and a badge repeating the line
                 * directly under it says nothing. What the service covers is the
                 * thing a reader checking this project wants under its title, since
                 * there is no material here to describe instead.
                 */
                <div className="mt-8 border-t border-sidebar-border pt-6">
                    <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                        What this service covers
                    </h4>

                    <InquiryServiceBullets
                        serviceIds={[standalone.serviceId]}
                    />
                </div>
            ) : (
                <>
                    <div className="mt-8 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <h4 className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Stones
                        </h4>

                        {rows.length > 1 ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={applyToAll}
                                className="h-7 cursor-pointer px-1.5 text-xs"
                            >
                                Apply to all stones
                            </Button>
                        ) : null}
                    </div>

                    <ul className="mt-3 divide-y divide-sidebar-border border-y border-sidebar-border">
                        {rows.map((row, index) => (
                            <StoneSpecRow key={row.stoneId} index={index} />
                        ))}
                    </ul>

                    {/*
                     * Where the two stones a project can be short of are said.
                     * `stoneIds` first because that is where the reader is sent
                     * back to when the grid came up empty; `stones` is read as well
                     * because the committed project says the same thing about the
                     * specifications themselves, and a project cannot be written
                     * from either one alone.
                     */}
                    <p
                        role="alert"
                        className="mt-4 text-sm text-destructive empty:hidden"
                    >
                        {fieldMessage(errors.stoneIds) ??
                            fieldMessage(errors.stones)}
                    </p>
                </>
            )}

            {/*
             * Last, because it is about the work rather than about the material,
             * and both shapes of project ask it. It reads the draft form, so an
             * unfinished project's site and date cost nothing if the reader walks
             * away from it, and a finished one carries its own onto the sheet.
             */}
            <div className="mt-10">
                <InquiryProjectDetails
                    projectNumber={projectNumber}
                    /*
                     * The same fact the schema asks the time about, read from the
                     * same flag: a project that is nothing but a service which
                     * stands on its own is the one kind of work the team attends
                     * at an hour, and the form and the rule have to be looking at
                     * the same list to say so.
                     */
                    needsNeededByTime={isStandalone}
                />
            </div>

            {/*
             * Inside the form on purpose, so this is the form's submit button and
             * Enter reaches the same validation. Sticky for the same reason as the
             * other steps': a project of six stones is taller than a phone.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                {isStandalone ? (
                    /*
                     * What committing will do next, rather than a count of material
                     * this project has none of. The button beside it says the same
                     * thing, so this is the version that is read out as it changes.
                     */
                    <p
                        aria-live="polite"
                        className="min-w-0 flex-1 text-sm font-bold text-foreground"
                    >
                        {standalone.total === 1
                            ? 'The last of the services you picked'
                            : `${standalone.total - 1} more to specify after this`}
                    </p>
                ) : (
                    <p
                        aria-live="polite"
                        className="min-w-0 flex-1 text-sm font-bold text-foreground"
                    >
                        {rows.length} {rows.length === 1 ? 'stone' : 'stones'} ·{' '}
                        {pieceCount} {pieceCount === 1 ? 'pc' : 'pcs'}
                    </p>
                )}

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
                    <Button type="submit" className="w-full sm:w-auto">
                        {submitLabel}
                    </Button>
                </div>
            </div>
        </form>
    );
};

export default InquiryProjectForm;

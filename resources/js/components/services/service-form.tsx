import { zodResolver } from '@hookform/resolvers/zod';
import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Controller,
    useFieldArray,
    useForm,
    useWatch,
    type FieldPath,
} from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { Section } from '@/components/admin-drawer-parts';
import FormDrawer from '@/components/form-drawer';
import FormField from '@/components/form-field';
import MultiSelect from '@/components/multi-select';
import QuestionAdder, {
    type AddedQuestion,
} from '@/components/services/question-adder';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { leaveToAdd } from '@/lib/admin-handoff';
import { FULFILLMENT_OPTIONS, questionHint } from '@/lib/service-admin';
import { index as serviceListIndex } from '@/routes/dashboard/services/lists';
import {
    store as storeService,
    update as updateService,
} from '@/routes/dashboard/services';
import type {
    QuestionOption,
    ServiceLimits,
    ServiceRow,
    ServiceTypeOption,
} from '@/types/service-admin';

export const SERVICE_PROPS = ['services', 'filters'];

/** Where the form is kept while a new question is written (lib/admin-handoff). */
export const SERVICE_HANDOFF_KEY = 'services';

export type ServiceValues = {
    name: string;
    service_type_id: string;
    description: string;
    features: { text: string }[];
    requires_stones: boolean;
    stone_type_ids: number[];
    /** A Fulfillment, or '' until one is chosen. */
    fulfillment: string;
    /** Chosen from the Questions page's list, in the order asked. */
    questions: AddedQuestion[];
    is_active: boolean;
    sort_order: string;
};

const emptyValues = (typeId: number | null): ServiceValues => ({
    name: '',
    service_type_id: typeId === null ? '' : String(typeId),
    description: '',
    features: [],
    requires_stones: true,
    stone_type_ids: [],
    fulfillment: '',
    questions: [],
    is_active: true,
    sort_order: '0',
});

const fromRow = (service: ServiceRow): ServiceValues => ({
    name: service.name,
    service_type_id: String(service.serviceTypeId),
    description: service.description,
    features: service.features.map((text) => ({ text })),
    requires_stones: service.requiresStones,
    stone_type_ids: service.stoneTypes.map((type) => type.id),
    fulfillment: service.fulfillment,
    questions: service.questions.map(({ id, name, required }) => ({
        id,
        name,
        required,
    })),
    is_active: service.isActive,
    sort_order: String(service.sortOrder),
});

/**
 * The form's rules, the server's own (ServiceValidationRules), so the drawer
 * says what is wrong before it sends anything.
 */
const serviceSchema = (limits: ServiceLimits, questions: QuestionOption[]) =>
    z
        .object({
            name: z
                .string()
                .trim()
                .min(1, "Enter the service's name.")
                .max(150, 'Use 150 characters or fewer.'),
            service_type_id: z.string().min(1, 'Choose its service type.'),
            description: z
                .string()
                .trim()
                .min(
                    1,
                    'Describe the service in a line, as the Inquire page shows it.',
                )
                .max(
                    limits.descriptionMax,
                    `The description can be ${limits.descriptionMax} characters at most.`,
                ),
            features: z
                .array(
                    z.object({
                        text: z
                            .string()
                            .trim()
                            .min(1, 'Write this line, or remove it.')
                            .max(
                                limits.featureMax,
                                `Each line can be ${limits.featureMax} characters at most.`,
                            ),
                    }),
                )
                .max(
                    limits.featuresMax,
                    `A service can list ${limits.featuresMax} things it includes at most.`,
                ),
            requires_stones: z.boolean(),
            stone_type_ids: z.array(z.number()),
            fulfillment: z
                .string()
                .min(1, 'Choose how the work reaches the client.'),
            questions: z.array(
                z.object({
                    id: z.number(),
                    name: z.string(),
                    required: z.boolean(),
                }),
            ),
            is_active: z.boolean(),
            sort_order: z
                .string()
                .trim()
                .regex(/^\d{1,5}$/, 'Enter 0 or a whole number above it.')
                .refine(
                    (value) => Number(value) <= 32767,
                    'Enter 32767 or less.',
                ),
        })
        .superRefine((values, ctx) => {
            if (values.requires_stones && values.stone_type_ids.length === 0) {
                ctx.addIssue({
                    code: 'custom',
                    path: ['stone_type_ids'],
                    message:
                        'Choose the stone types it is made from, or turn off "Needs stones".',
                });
            }

            const seen = new Set<string>();

            values.features.forEach((feature, index) => {
                const key = feature.text.trim().toLowerCase();

                if (key !== '' && seen.has(key)) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['features', index, 'text'],
                        message: 'That line is already listed.',
                    });
                }

                seen.add(key);
            });

            if (!values.requires_stones) {
                const stoneQuestions = values.questions.filter(
                    (chosen) =>
                        questions.find((question) => question.id === chosen.id)
                            ?.scope === 'stone',
                );

                if (stoneQuestions.length > 0) {
                    ctx.addIssue({
                        code: 'custom',
                        path: ['questions'],
                        message: `Remove ${stoneQuestions
                            .map(
                                (chosen) =>
                                    questions.find(
                                        (question) => question.id === chosen.id,
                                    )?.name,
                            )
                            .join(
                                ', ',
                            )}: answered for each stone, so only a service that needs stones asks it.`,
                    });
                }
            }
        });

/**
 * Add and Edit service, one form in the large drawer. Everything here is what
 * the Inquire page shows and asks for the service.
 */
const ServiceForm = ({
    open,
    service,
    restored,
    defaultTypeId,
    serviceTypes,
    stoneTypes,
    questions,
    limits,
    onClose,
}: {
    open: boolean;
    /** The service being edited, or null to add one. */
    service: ServiceRow | null;
    /**
     * The form as it was left to add a question on the Questions page, with
     * that question's id once added (lib/admin-handoff).
     */
    restored?: { values: ServiceValues; picked: number | null } | null;
    /** The type a new service starts under. */
    defaultTypeId: number | null;
    serviceTypes: ServiceTypeOption[];
    stoneTypes: { id: number; name: string }[];
    questions: QuestionOption[];
    limits: ServiceLimits;
    onClose: () => void;
}) => {
    const [processing, setProcessing] = useState(false);
    const schema = useMemo(
        () => serviceSchema(limits, questions),
        [limits, questions],
    );
    const form = useForm<ServiceValues>({
        resolver: zodResolver(schema),
        mode: 'onTouched',
        defaultValues: emptyValues(defaultTypeId),
    });
    const { register, handleSubmit, setError, reset, control, formState } =
        form;
    const features = useFieldArray({ control, name: 'features' });
    // keyName: the rows' own `id` is the question's, so React's key goes elsewhere.
    const chosen = useFieldArray({
        control,
        name: 'questions',
        keyName: 'key',
    });
    const [description, requiresStones] = useWatch({
        control,
        name: ['description', 'requires_stones'],
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        if (!restored) {
            reset(service ? fromRow(service) : emptyValues(defaultTypeId));

            return;
        }

        // Back from the Questions page: as it was, asking the new question too.
        const added = questions.find(
            (question) => question.id === restored.picked,
        );
        const values = restored.values;

        reset(
            added && !values.questions.some((asked) => asked.id === added.id)
                ? {
                      ...values,
                      questions: [
                          ...values.questions,
                          { id: added.id, name: added.name, required: true },
                      ],
                  }
                : values,
        );
        // Only on opening: the restored form is filled in once.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, service, defaultTypeId, reset]);

    /** Keep the form and write a new question on the Questions page. */
    const addNewQuestion = () =>
        leaveToAdd(
            SERVICE_HANDOFF_KEY,
            { item: service, values: form.getValues() },
            serviceListIndex.url('questions'),
        );

    const questionById = (id: number) =>
        questions.find((question) => question.id === id);
    const addable = questions.filter(
        (question) =>
            !chosen.fields.some((field) => field.id === question.id) &&
            (requiresStones || question.scope === 'project'),
    );

    const showErrors = (errors: Record<string, string>) => {
        const fields = new Set<string>(Object.keys(emptyValues(null)));
        let unplaced: string | null = null;

        for (const [key, message] of Object.entries(errors)) {
            const feature = /^features\.(\d+)$/.exec(key);

            if (feature) {
                setError(`features.${Number(feature[1])}.text`, {
                    type: 'server',
                    message,
                });
            } else if (fields.has(key)) {
                setError(key as FieldPath<ServiceValues>, {
                    type: 'server',
                    message,
                });
            } else if (key.startsWith('questions')) {
                setError('questions', { type: 'server', message });
            } else if (key.startsWith('stone_type_ids')) {
                setError('stone_type_ids', { type: 'server', message });
            } else if (key.startsWith('features')) {
                setError('features', { type: 'server', message });
            } else {
                unplaced ??= message;
            }
        }

        if (unplaced) {
            toast.error(unplaced);
        }
    };

    const save = handleSubmit((values) => {
        const payload = {
            name: values.name,
            service_type_id: Number(values.service_type_id),
            description: values.description,
            features: values.features.map((feature) => feature.text),
            requires_stones: values.requires_stones,
            stone_type_ids: values.requires_stones ? values.stone_type_ids : [],
            fulfillment: values.fulfillment,
            questions: values.questions.map((question) => ({
                id: question.id,
                required: question.required,
            })),
            is_active: values.is_active,
            sort_order: Number(values.sort_order),
        };
        const options = {
            only: SERVICE_PROPS,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: showErrors,
        };

        if (service) {
            router.put(updateService.url(service.id), payload, options);
        } else {
            router.post(storeService.url(), payload, options);
        }
    });

    return (
        <FormDrawer
            open={open}
            onOpenChange={(next) => (next ? null : onClose())}
            title={service ? `Edit ${service.name}` : 'Add service'}
            description={
                service
                    ? 'Change what the Inquire page offers and asks for this service.'
                    : 'A new service clients can ask for on the Inquire page.'
            }
            submitLabel={service ? 'Save changes' : 'Add service'}
            onSubmit={save}
            processing={processing}
            size="lg"
        >
            <div className="space-y-6">
                <Section title="Service" as="fieldset">
                    <FormField
                        id="service-name"
                        label="Service name"
                        required
                        error={formState.errors.name?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Input
                                id="service-name"
                                autoComplete="off"
                                maxLength={150}
                                placeholder="Slab Supply Only"
                                className="mt-2 rounded-none"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                {...register('name')}
                            />
                        )}
                    </FormField>
                    {service ? (
                        <p className="text-sm text-secondary-foreground">
                            Its link name stays{' '}
                            <span className="font-mono">{service.slug}</span>,
                            so nothing that points to it breaks.
                        </p>
                    ) : null}

                    <FormField
                        id="service-type"
                        label="Service type"
                        required
                        hint="The first choice of “What do you need?” it comes under."
                        error={formState.errors.service_type_id?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Controller
                                control={control}
                                name="service_type_id"
                                render={({ field }) => (
                                    <Select
                                        value={field.value}
                                        onValueChange={field.onChange}
                                    >
                                        <SelectTrigger
                                            id="service-type"
                                            className="mt-2 w-full rounded-none"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            onBlur={field.onBlur}
                                        >
                                            <SelectValue placeholder="Choose a service type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {serviceTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            />
                        )}
                    </FormField>

                    <FormField
                        id="service-description"
                        label="Description"
                        required
                        hint={`Shown under the service on the Inquire page. ${(description ?? '').length}/${limits.descriptionMax}`}
                        error={formState.errors.description?.message}
                    >
                        {({ describedBy, invalid }) => (
                            <Textarea
                                id="service-description"
                                rows={3}
                                maxLength={limits.descriptionMax}
                                placeholder="Stone slabs delivered or picked up, cut to your order."
                                className="mt-2 rounded-none"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                {...register('description')}
                            />
                        )}
                    </FormField>
                </Section>

                <Section title="What it includes" as="fieldset">
                    <p
                        id="service-features-hint"
                        className="text-sm text-secondary-foreground"
                    >
                        The lines shown under the service, in this order.{' '}
                        {limits.featuresMax} at most.
                    </p>
                    {formState.errors.features?.message ? (
                        <p className="text-sm text-destructive">
                            {formState.errors.features.message}
                        </p>
                    ) : null}

                    {features.fields.length > 0 ? (
                        <ol className="space-y-2">
                            {features.fields.map((feature, index) => {
                                const error =
                                    formState.errors.features?.[index]?.text;
                                const number = index + 1;

                                return (
                                    <li
                                        key={feature.id}
                                        className="flex items-start gap-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <label
                                                htmlFor={`service-feature-${index}`}
                                                className="sr-only"
                                            >
                                                Line {number}
                                            </label>
                                            <Input
                                                id={`service-feature-${index}`}
                                                maxLength={limits.featureMax}
                                                placeholder="Cut to size"
                                                className="rounded-none"
                                                aria-invalid={
                                                    error !== undefined
                                                }
                                                aria-describedby={
                                                    error
                                                        ? `service-feature-${index}-error`
                                                        : undefined
                                                }
                                                {...register(
                                                    `features.${index}.text`,
                                                )}
                                            />
                                            {error ? (
                                                <p
                                                    id={`service-feature-${index}-error`}
                                                    className="mt-1 text-sm text-destructive"
                                                >
                                                    {error.message}
                                                </p>
                                            ) : null}
                                        </div>
                                        <MoveButtons
                                            index={index}
                                            total={features.fields.length}
                                            what={`line ${number}`}
                                            onMove={features.move}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 text-destructive"
                                            onClick={() =>
                                                features.remove(index)
                                            }
                                            aria-label={`Remove line ${number}`}
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    </li>
                                );
                            })}
                        </ol>
                    ) : null}

                    {features.fields.length < limits.featuresMax ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => features.append({ text: '' })}
                            aria-describedby="service-features-hint"
                        >
                            <Plus aria-hidden />
                            Add line
                        </Button>
                    ) : null}
                </Section>

                <Section title="Stones" as="fieldset">
                    <Controller
                        control={control}
                        name="requires_stones"
                        render={({ field }) => (
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <label
                                        htmlFor="service-requires-stones"
                                        className="text-sm font-bold"
                                    >
                                        Needs stones
                                    </label>
                                    <p
                                        id="service-requires-stones-hint"
                                        className="mt-1 text-sm text-secondary-foreground"
                                    >
                                        The client picks stones for it. Turn it
                                        off for work on the client&rsquo;s own
                                        stone, like Installation Only.
                                    </p>
                                </div>
                                <Switch
                                    id="service-requires-stones"
                                    checked={field.value}
                                    onCheckedChange={field.onChange}
                                    aria-describedby="service-requires-stones-hint"
                                />
                            </div>
                        )}
                    />

                    {requiresStones ? (
                        <FormField
                            id="service-stone-types"
                            label="Stone types"
                            required
                            hint="The Inquire page lists the stones of these types for it."
                            error={formState.errors.stone_type_ids?.message}
                        >
                            {({ describedBy, invalid }) => (
                                <Controller
                                    control={control}
                                    name="stone_type_ids"
                                    render={({ field }) => (
                                        <div className="mt-2">
                                            <MultiSelect
                                                id="service-stone-types"
                                                value={field.value}
                                                onChange={field.onChange}
                                                onBlur={field.onBlur}
                                                options={stoneTypes.map(
                                                    (type) => ({
                                                        id: type.id,
                                                        label: type.name,
                                                    }),
                                                )}
                                                placeholder="Search stone types"
                                                listLabel="Stone types"
                                                emptyMessage="No stone type by that name"
                                                describedBy={describedBy}
                                                invalid={invalid}
                                            />
                                        </div>
                                    )}
                                />
                            )}
                        </FormField>
                    ) : null}
                </Section>

                <Section title="How it reaches the client" as="fieldset">
                    <Controller
                        control={control}
                        name="fulfillment"
                        render={({ field }) => (
                            <RadioGroup
                                value={field.value}
                                onValueChange={field.onChange}
                                aria-label="How it reaches the client"
                                aria-invalid={
                                    formState.errors.fulfillment !== undefined
                                }
                                aria-describedby={
                                    formState.errors.fulfillment
                                        ? 'service-fulfillment-error'
                                        : undefined
                                }
                                className="grid grid-cols-1 gap-3 sm:grid-cols-2"
                            >
                                {FULFILLMENT_OPTIONS.map((option) => (
                                    <label
                                        key={option.value}
                                        className="flex cursor-pointer items-start gap-3 border border-sidebar-border p-3 transition-colors hover:bg-muted/50 has-[[data-state=checked]]:border-foreground"
                                    >
                                        <RadioGroupItem
                                            value={option.value}
                                            className="mt-0.5"
                                        />
                                        <span>
                                            <span className="block text-sm font-bold">
                                                {option.label}
                                            </span>
                                            <span className="mt-1 block text-sm text-secondary-foreground">
                                                {option.hint}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </RadioGroup>
                        )}
                    />
                    {formState.errors.fulfillment?.message ? (
                        <p
                            id="service-fulfillment-error"
                            className="text-sm text-destructive"
                        >
                            {formState.errors.fulfillment.message}
                        </p>
                    ) : null}
                </Section>

                <Section title="Questions" as="fieldset">
                    <p
                        id="service-questions-hint"
                        className="text-sm text-secondary-foreground"
                    >
                        What the Inquire page asks for this service, in this
                        order. A required one has to be answered.
                    </p>
                    {formState.errors.questions?.message ? (
                        <p className="text-sm text-destructive">
                            {formState.errors.questions.message}
                        </p>
                    ) : null}

                    {chosen.fields.length > 0 ? (
                        <ol className="space-y-2">
                            {chosen.fields.map((field, index) => {
                                const question = questionById(field.id);
                                const number = index + 1;
                                const name = field.name || 'Question';

                                return (
                                    <li
                                        key={field.key}
                                        className="flex flex-wrap items-center gap-2 border border-sidebar-border p-3 sm:flex-nowrap"
                                    >
                                        <div className="min-w-0 basis-full sm:flex-1 sm:basis-0">
                                            <p className="text-sm font-bold">
                                                {name}
                                            </p>
                                            {question ? (
                                                <p className="mt-0.5 text-xs text-secondary-foreground">
                                                    {questionHint(question)}
                                                </p>
                                            ) : null}
                                        </div>
                                        <Controller
                                            control={control}
                                            name={`questions.${index}.required`}
                                            render={({ field: required }) => (
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Switch
                                                        checked={required.value}
                                                        onCheckedChange={
                                                            required.onChange
                                                        }
                                                        aria-label={`${name} is required`}
                                                    />
                                                    Required
                                                </label>
                                            )}
                                        />
                                        <MoveButtons
                                            index={index}
                                            total={chosen.fields.length}
                                            what={name}
                                            onMove={chosen.move}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-9 text-destructive"
                                            onClick={() => chosen.remove(index)}
                                            aria-label={`Stop asking ${name}`}
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                        <span className="sr-only">
                                            Question {number}
                                        </span>
                                    </li>
                                );
                            })}
                        </ol>
                    ) : (
                        <p className="text-sm text-secondary-foreground italic">
                            It asks nothing besides the stones.
                        </p>
                    )}

                    <QuestionAdder
                        key={open ? 'open' : 'closed'}
                        available={addable}
                        onAdd={(question) => chosen.append(question)}
                        onAddNew={addNewQuestion}
                    />
                </Section>

                <Section title="Display" as="fieldset">
                    <Controller
                        control={control}
                        name="is_active"
                        render={({ field }) => (
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <label
                                        htmlFor="service-active"
                                        className="text-sm font-bold"
                                    >
                                        Visible on Inquire
                                    </label>
                                    <p
                                        id="service-active-hint"
                                        className="mt-1 text-sm text-secondary-foreground"
                                    >
                                        A hidden service is not offered for new
                                        inquiries. Sent ones keep it.
                                    </p>
                                </div>
                                <Switch
                                    id="service-active"
                                    checked={field.value}
                                    onCheckedChange={field.onChange}
                                    aria-describedby="service-active-hint"
                                />
                            </div>
                        )}
                    />

                    <FormField
                        id="service-sort-order"
                        label="Display order"
                        error={formState.errors.sort_order?.message}
                        hint="Lower comes first under its type; services with the same number go by name."
                    >
                        {({ describedBy, invalid }) => (
                            <Input
                                id="service-sort-order"
                                type="number"
                                inputMode="numeric"
                                min={0}
                                max={32767}
                                step={1}
                                className="mt-2 w-32 rounded-none"
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                {...register('sort_order')}
                            />
                        )}
                    </FormField>
                </Section>
            </div>
        </FormDrawer>
    );
};

/** Up and down for one row of an ordered list. */
const MoveButtons = ({
    index,
    total,
    what,
    onMove,
}: {
    index: number;
    total: number;
    what: string;
    onMove: (from: number, to: number) => void;
}) => (
    <div className="flex gap-1">
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-9"
            disabled={index === 0}
            onClick={() => onMove(index, index - 1)}
            aria-label={`Move ${what} up`}
        >
            <ArrowUp aria-hidden />
        </Button>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            className="size-9"
            disabled={index === total - 1}
            onClick={() => onMove(index, index + 1)}
            aria-label={`Move ${what} down`}
        >
            <ArrowDown aria-hidden />
        </Button>
    </div>
);

export default ServiceForm;

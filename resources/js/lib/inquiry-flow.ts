/*
 * The Inquire flow's pure logic: where each step leads, where a restored draft
 * may land, what a committed project is made of, how a request is put on the
 * wire and how the backend's complaints are put back on the page.
 *
 * No imports, on purpose. The tests under tests/js run this file straight under
 * Node, where the `@/` alias does not exist, so everything it needs is declared
 * here or handed in as an argument.
 */

/*
 * The reader sees two steps: Your request and Review and send. The first is
 * questions on one page, one open at a time: the brands they would like, what
 * they need, the stones, then the details. Stored drafts hold the same
 * numbers.
 */
export const BRAND_STEP = 1;
export const SERVICE_STEP = 2;
export const STONES_STEP = 3;
export const REQUIREMENTS_STEP = 4;

/** Review is the last step, and owns no fields of its own to validate. */
export const REVIEW_STEP = REQUIREMENTS_STEP + 1;

export const INQUIRY_STEP_LABELS = ['Your request', 'Review and send'] as const;

/** Which of the two steps the reader sees this question or Review belongs to. */
export const stepperPosition = (step: number): number =>
    step >= REVIEW_STEP ? 2 : 1;

export type InquiryMode = 'create' | 'edit';

/** How the project reaches the client. A pickup has no address to ask for. */
export const DELIVERY = 'delivery';
export const PICKUP = 'pickup';

export const DELIVERY_METHODS = [
    { value: DELIVERY, title: 'Delivery' },
    { value: PICKUP, title: 'Pickup' },
];

/*
 * How a service reaches the client (`services.fulfillment`): one that is
 * delivered or picked up asks which, and an address only for a delivery; one
 * that is installed asks no method and always needs the installation address.
 */
export const DELIVERY_OR_PICKUP = 'delivery_or_pickup';
export const INSTALLED = 'installed';

export type Fulfillment = typeof DELIVERY_OR_PICKUP | typeof INSTALLED;

/** Whether the service asks Delivery or Pickup. Unknown asks it, as most do. */
export const asksDeliveryMethod = (
    service: { fulfillment?: Fulfillment } | undefined,
): boolean => service?.fulfillment !== INSTALLED;

/** Whether the project needs an address: an installation, or a delivery. */
export const needsAddress = (
    service: { fulfillment?: Fulfillment } | undefined,
    deliveryMethod: string,
): boolean =>
    service?.fulfillment === INSTALLED ||
    (asksDeliveryMethod(service) && deliveryMethod === DELIVERY);

/** What the address is called for this service. */
export const addressLabel = (
    service: { fulfillment?: Fulfillment } | undefined,
): string =>
    service?.fulfillment === INSTALLED
        ? 'Installation address'
        : 'Delivery address';

/*
 * Where a project's address comes from. A client picks one of their saved
 * addresses (`book`); staff enter one with the address picker (`picker`) and
 * never see the client's book. StoreInquiryRequest::addressSource() says the
 * same on the server.
 */
export type AddressSource = 'book' | 'picker';

/** A client's edit that keeps the address the inquiry already has. */
export const HELD_ADDRESS = 'held';

/**
 * The picker's value as far as the request goes (lib/ph-address.ts
 * AddressValue, which this file cannot import).
 */
export type PickedAddress = {
    region: { code: string; name: string } | null;
    province: { code: string; name: string } | null;
    noProvince: boolean;
    city: { code: string; name: string } | null;
    barangay: { code: string; name: string } | null;
    postalCode: string;
    lat: number | null;
    lng: number | null;
    needsPostalCode?: boolean;
};

/** The brands a project names, or No preference: one or the other, never both. */
export type BrandChoice = { brands: string[]; brandNoPreference: boolean };

/**
 * Picking a brand card or No preference: No preference clears the brands, and
 * a brand clears No preference. Picking a chosen brand again unpicks it.
 */
export const toggleBrand = (
    choice: BrandChoice,
    /** A brand's slug, or NO_BRAND_PREFERENCE. */
    brand: string,
): BrandChoice => {
    if (brand === NO_BRAND_PREFERENCE) {
        return { brands: [], brandNoPreference: !choice.brandNoPreference };
    }

    return {
        brands: choice.brands.includes(brand)
            ? choice.brands.filter((id) => id !== brand)
            : [...choice.brands, brand],
        brandNoPreference: false,
    };
};

export const NO_BRAND_PREFERENCE = '__no_preference__';

/** Why the brand answer cannot be sent, or null. Worded as StoreInquiryRequest words it. */
export const brandProblem = (choice: BrandChoice): string | null =>
    choice.brands.length === 0 && !choice.brandNoPreference
        ? 'Choose at least one brand, or No preference.'
        : choice.brands.length > 0 && choice.brandNoPreference
          ? 'Choose brands or No preference, not both.'
          : null;

/** The answers about getting the work to the client. */
export type FulfillmentAnswers = {
    deliveryMethod: string;
    /** A saved address's id, HELD_ADDRESS, or '' (the address book). */
    addressId: string;
    /** The address entered with the picker, or null. */
    address: PickedAddress | null;
};

/**
 * What a project's delivery answers still mean once its service is `next`:
 * an installed service asks no method, and one that asks a method starts
 * unanswered if the last asked none. The address is kept: an installation
 * and a delivery both need one.
 */
export const fulfillmentAfterServiceChange = <T extends FulfillmentAnswers>(
    answers: T,
    previous: { fulfillment?: Fulfillment } | undefined,
    next: { fulfillment?: Fulfillment } | undefined,
): T =>
    !asksDeliveryMethod(next)
        ? { ...answers, deliveryMethod: '' }
        : !asksDeliveryMethod(previous)
          ? { ...answers, deliveryMethod: '' }
          : answers;

/**
 * The complaints about a project's delivery answers, each on the field it is
 * about, as StoreInquiryRequest words them. `addressComplete` says whether the
 * picker's value has every level (lib/ph-address.ts isAddressComplete).
 */
export const fulfillmentProblems = (
    answers: FulfillmentAnswers,
    service: { fulfillment?: Fulfillment } | undefined,
    source: AddressSource,
    addressComplete: (address: PickedAddress) => boolean,
): Array<{
    field: 'deliveryMethod' | 'addressId' | 'address';
    message: string;
}> => {
    if (service === undefined) {
        return [];
    }

    if (asksDeliveryMethod(service) && answers.deliveryMethod === '') {
        return [
            {
                field: 'deliveryMethod',
                message: 'Choose how you would like to get it.',
            },
        ];
    }

    if (!needsAddress(service, answers.deliveryMethod)) {
        return [];
    }

    const isDelivery = service.fulfillment !== INSTALLED;

    if (source === 'book') {
        return answers.addressId === ''
            ? [
                  {
                      field: 'addressId',
                      message: isDelivery
                          ? 'Choose the delivery address.'
                          : 'Choose the installation address.',
                  },
              ]
            : [];
    }

    return answers.address === null || !addressComplete(answers.address)
        ? [
              {
                  field: 'address',
                  message: isDelivery
                      ? 'Enter the delivery address.'
                      : 'Enter the installation address.',
              },
          ]
        : [];
};

/**
 * The question after this one: the brands, What do you need?, the stones,
 * the details. A service that asks for no stones (Installation Only) goes
 * from What do you need? straight to the details.
 */
export const nextQuestion = (
    step: number,
    requiresStones: boolean = true,
): number =>
    step === SERVICE_STEP && !requiresStones
        ? REQUIREMENTS_STEP
        : Math.min(step + 1, REVIEW_STEP);

/** What a service that asks an application says with none picked; StoreInquiryRequest says the same. */
export const APPLICATION_MISSING = 'Choose the application.';

/** The answers that decide how far the questions go. */
export type DraftProgress = {
    /** Unknown counts as answered, for a draft saved before the brand question. */
    brands?: string[];
    brandNoPreference?: boolean;
    serviceId: string;
    stoneIds: string[];
    stoneAdvice?: boolean;
    /** False when the chosen service asks for no stones; unknown counts as true. */
    requiresStones?: boolean;
    /** True while the service asks an application and none is picked yet. */
    applicationMissing?: boolean;
};

/** Whether the brand question is answered: a brand, or No preference. */
const brandAnswered = (draft: DraftProgress): boolean =>
    draft.brands === undefined ||
    draft.brands.length > 0 ||
    draft.brandNoPreference === true;

/**
 * The furthest question the draft's answers reach: the brands until one or No
 * preference is chosen, What do you need? until a service is, the stones
 * until one is picked or left to Regray, then the details. A service that
 * asks for no stones reaches the details as soon as it is chosen. A restored
 * draft shows every question up to it.
 */
export const furthestQuestion = (draft: DraftProgress): number =>
    !brandAnswered(draft)
        ? BRAND_STEP
        : draft.serviceId === '' || draft.applicationMissing === true
          ? SERVICE_STEP
          : draft.requiresStones === false ||
              draft.stoneIds.length > 0 ||
              draft.stoneAdvice === true
            ? REQUIREMENTS_STEP
            : STONES_STEP;

/**
 * Why Next cannot show the question after this one yet, or null when it can.
 * Only the question Next sits under is checked.
 */
export const questionProblem = (
    question: number,
    draft: DraftProgress,
    offeredServiceIds: ReadonlySet<string>,
): {
    field: 'brands' | 'serviceId' | 'applicationId' | 'stoneIds';
    message: string;
} | null => {
    if (question === BRAND_STEP && !brandAnswered(draft)) {
        return {
            field: 'brands',
            message: 'Choose at least one brand, or No preference.',
        };
    }

    if (question === SERVICE_STEP && !offeredServiceIds.has(draft.serviceId)) {
        return { field: 'serviceId', message: 'Choose what you need.' };
    }

    if (question === SERVICE_STEP && draft.applicationMissing === true) {
        return { field: 'applicationId', message: APPLICATION_MISSING };
    }

    if (
        question === STONES_STEP &&
        draft.requiresStones !== false &&
        draft.stoneIds.length === 0 &&
        draft.stoneAdvice !== true
    ) {
        return { field: 'stoneIds', message: 'Select at least one stone.' };
    }

    return null;
};

/**
 * A project's name, made from the service the reader picked, capped at the name's
 * own limit, which the caller passes because this file imports nothing. It can be
 * changed on Review.
 */
export const autoProjectName = (
    serviceName: string,
    maxLength: number,
): string => serviceName.slice(0, maxLength).trim();

/**
 * Where a restored draft is allowed to land.
 *
 * The stored step is a number the draft behind it may no longer justify: a
 * Review with no projects left to review, a Stones question with no service
 * chosen, details with no stones. So the stored step is held to the furthest step
 * the draft still justifies, and the brand question is the floor. A service that
 * asks for no stones justifies the details with none, and never lands on the
 * stones question it does not ask.
 */
export const clampRestoredStep = (
    stored: number,
    projectCount: number,
    draft: DraftProgress,
): number => {
    if (stored >= REVIEW_STEP && projectCount > 0) {
        return REVIEW_STEP;
    }

    const furthest = furthestQuestion(draft);
    const requested = Number.isFinite(stored) ? Math.trunc(stored) : BRAND_STEP;
    const landed = Math.min(Math.max(requested, BRAND_STEP), furthest);

    return landed === STONES_STEP && draft.requiresStones === false
        ? REQUIREMENTS_STEP
        : landed;
};

/* ------------------------------------------------------------------ */
/* What the reader needs                                                */
/* ------------------------------------------------------------------ */

export const STONE_ADVICE_LABEL = 'Stone to be recommended by Regray';

/**
 * The one thickness a stone row lists when its stone comes in none listed:
 * Regray confirms it in the quote. The server stores it as no thickness
 * (DetailField::THICKNESS_ADVICE holds the same words).
 */
export const THICKNESS_ADVICE = 'To be advised by Regray';

/** How a table shape asks the size: a round table's diameter, or width x length. */
export type SizeKind = 'diameter' | 'width_length';

/** One listed answer of a question, as InquiryDetailFieldResource sends it. */
export type ServiceFieldChoice = {
    value: string;
    label: string;
    /** The "Not sure yet, advise me" choice, edge profile or finish. */
    isAdvisory?: boolean;
    /** "Same as stone": only a project with a stone row can take it. */
    linksToStone?: boolean;
    /** "Custom shape" and the like: picking it asks the reader to describe theirs. */
    isCustom?: boolean;
    /** What the page says of it once it is picked. */
    description?: string | null;
    /** Its picture, for a question asked with picture cards. */
    image?: string | null;
    /** For a table shape: how the size is then asked; none takes the reader's own words. */
    sizeKind?: SizeKind | null;
};

/**
 * One question a service asks besides the stones, as InquiryDetailFieldResource
 * sends it. A `stone` one is answered on each stone row, a `project` one once
 * under Other details. `options` are the closed list to pick from;
 * `suggestions` are sample values shown beside a free entry, which
 * `allowsCustom` takes. Nothing about a question is written into the page.
 */
export type ServiceField = {
    slug: string;
    label: string;
    scope: 'stone' | 'project';
    answerType: 'text' | 'number' | 'choice' | 'boolean';
    required: boolean;
    allowsCustom: boolean;
    /** What the entry that opens the free entry reads ("Custom size"); none reads Other. */
    customLabel?: string | null;
    /** The line under the box that entry opens ("Enter your desired size."). */
    customHint?: string | null;
    /** Asked with picture cards rather than a dropdown: its choices have pictures. */
    pictures?: boolean;
    options: ServiceFieldChoice[];
    suggestions: ServiceFieldChoice[];
};

/**
 * Where a custom choice's description is held among a project's answers:
 * after its question's slug, `table_shape:custom`. A slug never has a colon,
 * so it never meets a question's own answer. The server reads it from
 * `customAnswers[slug]` and sends it back the same way
 * (InquiryDetailFieldResource::CUSTOM_SUFFIX).
 */
export const customAnswerKey = (slug: string): string => `${slug}:custom`;

/** The choice an answer picked, or undefined. */
export const pickedChoice = (
    field: Pick<ServiceField, 'options'>,
    value: string | undefined,
): ServiceFieldChoice | undefined =>
    value === undefined || value === ''
        ? undefined
        : field.options.find((option) => option.value === value);

/**
 * Why a custom choice's description is refused, or null: a custom choice has
 * to be described, in at most ANSWER_TEXT_MAX characters. Any other choice
 * takes none. Worded as StoreInquiryRequest words it.
 */
export const customAnswerProblem = (
    field: ServiceField,
    value: string | undefined,
    description: string | undefined,
): string | null => {
    const choice = pickedChoice(field, value);

    if (choice?.isCustom !== true) {
        return null;
    }

    const written = (description ?? '').trim();

    if (written === '') {
        return `Describe your ${choice.label.toLowerCase()}.`;
    }

    return written.length > ANSWER_TEXT_MAX
        ? `Keep the description to ${ANSWER_TEXT_MAX} characters or fewer.`
        : null;
};

/** The furniture size, asked by the table shape where a service asks both. */
export const SIZE_FIELD = 'size';

/** The table shape, whose choice says how the size is asked. */
export const TABLE_SHAPE_FIELD = 'table_shape';

/**
 * How a project's size is asked, by the table shape it picked: a round
 * table's diameter, width x length, or null for the reader's own words (an
 * organic or custom shape, not sure, no shape yet, or a service that asks
 * one without the other).
 */
export const sizeKindFor = (
    service: Pick<ServiceOption, 'fields'> | undefined,
    answers: Record<string, string>,
): SizeKind | null => {
    const fields = service?.fields ?? [];
    const shape = fields.find(({ slug }) => slug === TABLE_SHAPE_FIELD);

    if (
        shape === undefined ||
        !fields.some(({ slug }) => slug === SIZE_FIELD)
    ) {
        return null;
    }

    return pickedChoice(shape, answers[TABLE_SHAPE_FIELD])?.sizeKind ?? null;
};

/**
 * One entry of the second dropdown, as InquiryServiceResource sends it.
 * `stoneTypes` are the slugs of the stone types it is made from;
 * `requiresStones` is false for a service that asks for no stones;
 * `fulfillment` is how it reaches the client; `fields` are the questions it
 * asks besides them, in order.
 */
export type ServiceOption = {
    id: string;
    name: string;
    description: string;
    features: string[];
    stoneTypes: string[];
    requiresStones: boolean;
    fulfillment: Fulfillment;
    /** The line under "How would you like to get it?". */
    deliveryNote?: string | null;
    /** The line under "When do you need it?". */
    scheduleNote?: string | null;
    /** The fewest days from today its date may be; 0 is today. */
    leadDays?: number;
    fields: ServiceField[];
    /**
     * Whether it asks which application (a sub-item: Countertop and
     * Backsplash, Flooring…) the work is for, and the ones it offers. Only
     * the services with an installation do.
     */
    hasApplications?: boolean;
    applications?: ServiceOption[];
};

/** How far ahead a project's date may be, in years; StoreInquiryRequest holds the same. */
export const DEADLINE_YEARS_MAX = 2;

/**
 * A `YYYY-MM-DD` day this many days later, by the calendar (no time of day,
 * so no time zone can move it).
 */
export const addDaysIso = (iso: string, days: number): string => {
    const [year, month, day] = iso.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + days));

    return date.toISOString().slice(0, 10);
};

/** The furthest day a project's date may be, from today. */
export const latestNeededBy = (today: string): string =>
    today.replace(/^\d{4}/, (year) =>
        String(Number(year) + DEADLINE_YEARS_MAX),
    );

/** What a date sooner than a service's lead time is told, as StoreInquiryRequest says it. */
export const leadTimeMessage = (days: number): string =>
    `Choose a date at least ${days} ${days === 1 ? 'day' : 'days'} from today.`;

/**
 * Why a project's date is refused for its service, or null: sooner than
 * its lead time (`leadDays` from today), or further than DEADLINE_YEARS_MAX
 * ahead. The date an edit already has (`held`) may stay however soon.
 */
export const neededByProblem = (
    date: string,
    service: Pick<ServiceOption, 'leadDays'> | undefined,
    today: string,
    held: string | null = null,
): string | null => {
    if (date === '' || date === held) {
        return null;
    }

    const lead = service?.leadDays ?? 0;

    if (lead > 0 && date < addDaysIso(today, lead)) {
        return leadTimeMessage(lead);
    }

    return date > latestNeededBy(today)
        ? `Choose a date within the next ${DEADLINE_YEARS_MAX} years.`
        : null;
};

/**
 * The key a stone row holds each stone-scope answer under, by question slug.
 * These are the columns of a stone row, which is why they are named here;
 * what each asks, its label and its options all come from the service (and,
 * for a slab size or thickness, from the row's stone).
 */
export const STONE_FIELD_KEYS = {
    slab_size: 'slabSize',
    thickness: 'thickness',
    edge_profile: 'edge',
    finish: 'finish',
} as const satisfies Record<
    string,
    'slabSize' | 'thickness' | 'edge' | 'finish'
>;

export type StoneFieldKey =
    (typeof STONE_FIELD_KEYS)[keyof typeof STONE_FIELD_KEYS];

/** The key a stone-scope question is answered under on a row, or null. */
export const stoneFieldKey = (
    field: Pick<ServiceField, 'slug'>,
): StoneFieldKey | null =>
    (STONE_FIELD_KEYS as Record<string, StoneFieldKey>)[field.slug] ?? null;

/** The question that asks how many pieces of a stone: only the slab services ask it. */
export const QUANTITY_FIELD = 'quantity';

/** Whether a service asks how many pieces of each stone. */
export const asksQuantity = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): boolean =>
    (service?.fields ?? []).some(({ slug }) => slug === QUANTITY_FIELD);

/** The most pieces of one stone a row may ask for; the server holds the same. */
export const QUANTITY_MAX = 9999;

/**
 * Why a piece count is refused, or null, in the words StoreInquiryRequest
 * uses. An emptied count arrives as `NaN`.
 */
export const quantityProblem = (quantity: number): string | null => {
    if (!Number.isFinite(quantity)) {
        return 'Enter how many pieces you need.';
    }

    if (!Number.isInteger(quantity)) {
        return 'Quantity has to be a whole number of pieces.';
    }

    if (quantity < 1) {
        return 'Enter at least 1 piece.';
    }

    return quantity > QUANTITY_MAX
        ? `Enter ${QUANTITY_MAX} pieces or fewer.`
        : null;
};

/**
 * A service's stone-scope questions answered with a choice or an entry, in
 * the order asked. How many pieces is drawn and checked on its own.
 */
export const stoneFields = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): ServiceField[] =>
    (service?.fields ?? []).filter(
        (field) => field.scope === 'stone' && stoneFieldKey(field) !== null,
    );

/** A service's project-scope questions, in the order asked. */
export const projectFields = (
    service: Pick<ServiceOption, 'fields'> | undefined,
): ServiceField[] =>
    (service?.fields ?? []).filter((field) => field.scope === 'project');

/**
 * The service of this id, under whichever type offers it, or an application
 * of one (a sub-item is a service too, offered under the services that ask
 * it).
 */
export const findService = <
    Service extends { id: string; applications?: Service[] },
>(
    serviceTypes: ReadonlyArray<{ services: Service[] }>,
    serviceId: string,
): Service | undefined =>
    serviceId === ''
        ? undefined
        : serviceTypes
              .flatMap(({ services }) => services)
              .flatMap((service) => [service, ...(service.applications ?? [])])
              .find(({ id }) => id === serviceId);

/** Whether a service asks which application the work is for. */
export const asksApplication = (
    service:
        | Pick<ServiceOption, 'hasApplications' | 'applications'>
        | undefined,
): boolean =>
    service?.hasApplications === true &&
    (service.applications ?? []).length > 0;

/**
 * The service as a project with this application asks it: each stone is
 * asked what the service asks of a stone (pieces, slab size, thickness, edge,
 * finish), the project what the application asks (the area, a fascia, a
 * waterfall), and the stones offered are the application's own types. With
 * no application picked, or a service that asks none, the service as it is.
 * StoreInquiryRequest asks the same.
 */
export const withApplication = (
    service: ServiceOption | undefined,
    applicationId: string,
): ServiceOption | undefined => {
    const application = asksApplication(service)
        ? service?.applications?.find(({ id }) => id === applicationId)
        : undefined;

    if (service === undefined || application === undefined) {
        return service;
    }

    return {
        ...service,
        stoneTypes:
            application.stoneTypes.length > 0
                ? application.stoneTypes
                : service.stoneTypes,
        fields: [
            ...service.fields.filter(({ scope }) => scope === 'stone'),
            ...application.fields.filter(({ scope }) => scope === 'project'),
        ],
    };
};

/** The service a project is asked by: its service, with its application if it has one. */
export const findProjectService = (
    serviceTypes: ReadonlyArray<{ services: ServiceOption[] }>,
    serviceId: string,
    applicationId: string,
): ServiceOption | undefined =>
    withApplication(findService(serviceTypes, serviceId), applicationId);

/**
 * The service whose stones a project picks from: its application when it
 * has one, since each application is made from its own stone types.
 */
export const stoneServiceId = (
    serviceId: string,
    applicationId: string,
): string => (applicationId === '' ? serviceId : applicationId);

/** "Slab Supply and Installation · Flooring", or the service alone. */
export const serviceLabel = (
    service: Pick<ServiceOption, 'name' | 'applications'> | undefined,
    applicationId: string,
): string => {
    const application = service?.applications?.find(
        ({ id }) => id === applicationId,
    );

    return [service?.name, application?.name].filter(Boolean).join(' · ');
};

/** What a yes or no question is answered with on the page. */
export const YES = 'yes';
export const NO = 'no';

/** The largest number an answer holds, as the server stores it. */
export const ANSWER_NUMBER_MAX = 9999999999.99;

/** The longest free text an answer holds. */
export const ANSWER_TEXT_MAX = 255;

/* ------------------------------------------------------------------ */
/* Slab sizes and thicknesses                                           */
/* ------------------------------------------------------------------ */

/** The units a slab size is given in (SizeUnit on the server), metric first. */
export const SIZE_UNITS = ['cm', 'm', 'in', 'ft'] as const;

/** The units a thickness is given in (ThicknessUnit on the server). */
export const THICKNESS_UNITS = ['mm', 'cm', 'in'] as const;

export type SizeUnit = (typeof SIZE_UNITS)[number];
export type ThicknessUnit = (typeof THICKNESS_UNITS)[number];

/** The question slugs answered with a measurement: a size, or a thickness. */
export const MEASUREMENT_FIELDS = {
    slab_size: 'size',
    thickness: 'thickness',
} as const;

/**
 * A measurement's kind: a width x length (a slab, or a rectangular table), a
 * thickness, or a round table's diameter (asked by its table shape).
 */
export type MeasurementKind =
    | (typeof MEASUREMENT_FIELDS)[keyof typeof MEASUREMENT_FIELDS]
    | 'diameter';

/** Whether a question is answered with a measurement, and which kind, or null. */
export const measurementKind = (
    field: Pick<ServiceField, 'slug'>,
): MeasurementKind | null =>
    (MEASUREMENT_FIELDS as Record<string, MeasurementKind>)[field.slug] ?? null;

/** The largest side a slab size column holds, and the largest thickness (decimal 8,2 and 6,2). */
export const SIZE_MAX = 999999.99;
export const THICKNESS_MAX = 9999.99;

const NUMBER = String.raw`\d+(?:\.\d{1,2})?`;
const SIZE_PATTERN = new RegExp(
    String.raw`^(${NUMBER})\s*x\s*(${NUMBER})\s*(${SIZE_UNITS.join('|')})$`,
    'i',
);
const THICKNESS_PATTERN = new RegExp(
    String.raw`^(${NUMBER})\s*(${THICKNESS_UNITS.join('|')})$`,
    'i',
);
const DIAMETER_PATTERN = new RegExp(
    String.raw`^Ø\s*(${NUMBER})\s*(${SIZE_UNITS.join('|')})$`,
    'i',
);

export type Diameter = { value: string; unit: SizeUnit };

/** "Ø 120 cm", as a round table's size is written. */
export const formatDiameter = ({ value, unit }: Diameter): string =>
    `Ø ${formatMeasure(value)} ${unit}`;

/** A written diameter, read as the server's FurnitureSize reads it. */
export const parseDiameter = (written: string): Diameter | null => {
    const match = DIAMETER_PATTERN.exec(written.trim());

    return match === null
        ? null
        : { value: match[1], unit: match[2].toLowerCase() as SizeUnit };
};

/**
 * Why a table's size does not fit its shape, or null when it does, in the
 * words FurnitureSize uses: a round table's diameter ("Ø 120 cm"), or width
 * x length ("120 x 80 cm").
 */
export const furnitureSizeProblem = (
    kind: SizeKind,
    written: string,
): string | null => {
    if (kind === 'width_length') {
        const size = parseSlabSize(written);

        if (size === null) {
            return 'Enter the size as width x length and a unit, like 120 x 80 cm.';
        }

        const sides = [Number(size.width), Number(size.length)];

        if (sides.some((side) => side <= 0)) {
            return 'Enter a size greater than 0.';
        }

        return sides.some((side) => side > SIZE_MAX)
            ? 'Enter a smaller size.'
            : null;
    }

    const diameter = parseDiameter(written);

    if (diameter === null) {
        return 'Enter the diameter and a unit, like Ø 120 cm.';
    }

    if (Number(diameter.value) <= 0) {
        return 'Enter a diameter greater than 0.';
    }

    return Number(diameter.value) > SIZE_MAX
        ? 'Enter a smaller diameter.'
        : null;
};

/**
 * A measured number as every page writes it, as the server's Measurement
 * does: no trailing zeros ("160", "0.75", "1.6").
 */
export const formatMeasure = (value: string | number): string =>
    Number(value)
        .toFixed(2)
        .replace(/\.?0+$/, '');

export type SlabSize = { width: string; length: string; unit: SizeUnit };
export type Thickness = { value: string; unit: ThicknessUnit };

/** "160 x 320 cm", as the server's SizeMeasurement labels it. */
export const formatSlabSize = ({ width, length, unit }: SlabSize): string =>
    `${formatMeasure(width)} x ${formatMeasure(length)} ${unit}`;

/** "20 mm", "0.75 in". */
export const formatThickness = ({ value, unit }: Thickness): string =>
    `${formatMeasure(value)} ${unit}`;

/**
 * A written slab size, read as the server reads it: width x length and a
 * unit, case and spacing free. Null when it is not one, whatever the numbers.
 */
export const parseSlabSize = (written: string): SlabSize | null => {
    const match = SIZE_PATTERN.exec(written.trim());

    return match === null
        ? null
        : {
              width: match[1],
              length: match[2],
              unit: match[3].toLowerCase() as SizeUnit,
          };
};

/** A written thickness, read as the server reads it: a number and a unit. */
export const parseThickness = (written: string): Thickness | null => {
    const match = THICKNESS_PATTERN.exec(written.trim());

    return match === null
        ? null
        : { value: match[1], unit: match[2].toLowerCase() as ThicknessUnit };
};

/**
 * Why a written slab size or thickness is refused, or null when it stands, in
 * the words MeasurementRule uses. Any well-formed one stands: Other takes a
 * size the stone is not listed in.
 */
export const measurementProblem = (
    kind: MeasurementKind,
    written: string,
): string | null => {
    if (kind === 'diameter') {
        return furnitureSizeProblem('diameter', written);
    }

    if (kind === 'size') {
        const size = parseSlabSize(written);

        if (size === null) {
            return 'Enter the slab size as width x length and a unit, like 160 x 320 cm.';
        }

        const sides = [Number(size.width), Number(size.length)];

        if (sides.some((side) => side <= 0)) {
            return 'Enter a slab size greater than 0.';
        }

        return sides.some((side) => side > SIZE_MAX)
            ? 'Enter a smaller slab size.'
            : null;
    }

    const thickness = parseThickness(written);

    if (thickness === null) {
        return 'Enter the thickness as a number and a unit, like 20 mm.';
    }

    if (Number(thickness.value) <= 0) {
        return 'Enter a thickness greater than 0.';
    }

    return Number(thickness.value) > THICKNESS_MAX
        ? 'Enter a smaller thickness.'
        : null;
};

/**
 * A stone row's answer as one value, so two rows are compared by what they
 * ask rather than by how it was typed: "160x320 CM" is "160 x 320 cm".
 */
export const normalizedStoneAnswer = (
    field: Pick<ServiceField, 'slug'>,
    written: string,
): string => {
    const kind = measurementKind(field);

    if (kind === 'size') {
        const size = parseSlabSize(written);

        return size === null ? written.trim() : formatSlabSize(size);
    }

    if (kind === 'thickness') {
        const thickness = parseThickness(written);

        return thickness === null ? written.trim() : formatThickness(thickness);
    }

    return written.trim();
};

/** One slab a stone comes in, as labels: a size (null without set sizes) at a thickness. */
export type StoneVariantOption = { size: string | null; thickness: string };

/**
 * What a stone card carries of the slabs it comes in: every combination,
 * and its sizes and thicknesses each once, in order.
 */
export type StoneMeasurementOptions = {
    variants?: StoneVariantOption[];
    sizes?: ServiceFieldChoice[];
    thicknesses?: ServiceFieldChoice[];
    /** How big the pieces run without set sizes: "Diameter 15–40 cm". */
    sizeNote?: string | null;
};

/**
 * The thicknesses a row of this stone lists for the size it has: only those
 * that size is cut in, when it is one of the stone's own sizes; every
 * thickness of the stone while no size is chosen, for a size of one's own
 * (Other), and for a stone with no set sizes. In the stone's own order.
 */
export const thicknessOptionsFor = (
    stone: StoneMeasurementOptions | undefined,
    sizeLabel: string,
): ServiceFieldChoice[] => {
    const all = stone?.thicknesses ?? [];
    const size = parseSlabSize(sizeLabel);
    const label = size === null ? null : formatSlabSize(size);
    const listed =
        label !== null &&
        (stone?.sizes ?? []).some((option) => option.value === label);

    if (!listed) {
        return all;
    }

    const cutIn = new Set(
        (stone?.variants ?? [])
            .filter((variant) => variant.size === label)
            .map((variant) => variant.thickness),
    );

    return all.filter((option) => cutIn.has(option.value));
};

/**
 * The thickness a row keeps when its size changes: a listed thickness the
 * new size is not cut in is cleared, so it reads as unanswered again; one of
 * the reader's own (Other) is theirs to keep, as is one that still fits.
 */
export const thicknessAfterSizeChange = (
    stone: StoneMeasurementOptions | undefined,
    sizeLabel: string,
    thickness: string,
): string => {
    const value = normalizedStoneAnswer({ slug: 'thickness' }, thickness);
    const isListed = (stone?.thicknesses ?? []).some(
        (option) => option.value === value,
    );

    if (!isListed) {
        return thickness;
    }

    return thicknessOptionsFor(stone, sizeLabel).some(
        (option) => option.value === value,
    )
        ? thickness
        : '';
};

/**
 * The question as one stone row asks it: a slab size lists that row's
 * stone's own sizes, a thickness the ones its chosen size is cut in
 * (thicknessOptionsFor); every other question is as the service sent it.
 *
 * A slab size with none listed leaves only Other. A thickness that takes no
 * entry of one's own (`allowsCustom` false) and has none listed offers
 * THICKNESS_ADVICE alone, so the row is still answered from a list.
 */
export const questionForStone = (
    field: ServiceField,
    stone: StoneMeasurementOptions | undefined,
    row: { slabSize: string } = { slabSize: '' },
): ServiceField => {
    const kind = measurementKind(field);

    if (kind === null) {
        return field;
    }

    if (kind === 'size') {
        return { ...field, suggestions: stone?.sizes ?? [] };
    }

    const listed = thicknessOptionsFor(stone, row.slabSize);

    return {
        ...field,
        suggestions:
            listed.length === 0 && !field.allowsCustom
                ? [
                      {
                          value: THICKNESS_ADVICE,
                          label: THICKNESS_ADVICE,
                          isAdvisory: true,
                      },
                  ]
                : listed,
    };
};

/**
 * Why an answer to a question is refused, or null when it stands, in the
 * words StoreInquiryRequest uses: an empty required answer, a closed list's
 * answer that is not on it, and a free entry by its type. A suggestion is
 * never checked against: a free entry stands wherever the question takes one.
 */
export const answerProblem = (
    field: ServiceField,
    raw: string | undefined,
): string | null => {
    const value = (raw ?? '').trim();
    const name = field.label.toLowerCase();
    const fromList = `Choose the ${name} from the list.`;

    if (value === '') {
        if (!field.required) {
            return null;
        }

        return field.answerType === 'text' || field.answerType === 'number'
            ? `Enter the ${name}.`
            : `Choose the ${name}.`;
    }

    const kind = measurementKind(field);

    // Regray's advice stands for a thickness; the server checks it is the stone's only answer.
    if (kind === 'thickness' && value === THICKNESS_ADVICE) {
        return null;
    }

    if (kind !== null) {
        return measurementProblem(kind, value);
    }

    if (field.answerType === 'choice') {
        return field.options.some((option) => option.value === value)
            ? null
            : fromList;
    }

    if (field.answerType === 'boolean') {
        return value === YES || value === NO ? null : 'Choose yes or no.';
    }

    if (
        !field.allowsCustom &&
        !field.suggestions.some((option) => option.value === value)
    ) {
        return fromList;
    }

    if (field.answerType === 'number') {
        const number = Number(value);

        if (!Number.isFinite(number) || number <= 0) {
            return 'Enter a number greater than 0.';
        }

        return number > ANSWER_NUMBER_MAX ? 'Enter a smaller number.' : null;
    }

    return value.length > ANSWER_TEXT_MAX
        ? `Keep the ${name} to ${ANSWER_TEXT_MAX} characters or fewer.`
        : null;
};

/**
 * Why "Same as stone" cannot be the answer, or null: it means a stone row's
 * stone, so a project with none (Help me choose) cannot take it.
 */
export const linkedToStoneProblem = (
    field: ServiceField,
    value: string | undefined,
    hasStones: boolean,
): string | null => {
    const option = field.options.find((entry) => entry.value === value);

    return option?.linksToStone === true && !hasStones
        ? `${option.label} needs a stone. Choose another ${field.label.toLowerCase()}.`
        : null;
};

/**
 * Whether a stone is offered for the service: its primary type is one the
 * service is made from. The same rule as Stone::offeredFor() on the server.
 */
export const stoneFitsService = (
    stone: { type: { slug: string } },
    service: Pick<ServiceOption, 'stoneTypes'> | undefined,
): boolean =>
    service !== undefined && service.stoneTypes.includes(stone.type.slug);

/**
 * The picked stones a service does not offer, in the order they were picked.
 * A stone the page knows nothing about is left alone: whether it is still
 * offered at all is the server's to say.
 */
export const stonesNotOffered = <
    Stone extends { slug: string; type: { slug: string } },
>(
    stoneIds: readonly string[],
    known: readonly Stone[],
    service: Pick<ServiceOption, 'stoneTypes'> | undefined,
): Stone[] =>
    stoneIds.flatMap((id) => {
        const stone = known.find(({ slug }) => slug === id);

        return stone === undefined || stoneFitsService(stone, service)
            ? []
            : [stone];
    });

/**
 * What step 2 says after removing stones a service does not offer. `count` is
 * how many stones the names stand for, when one name is a count ("2 stones")
 * because a restored draft only knows how many the server left out.
 */
export const removedStonesNotice = (
    names: readonly string[],
    serviceName: string,
    count: number = names.length,
): string => {
    if (names.length === 0) {
        return '';
    }

    const listed =
        names.length === 1
            ? names[0]
            : `${names.slice(0, -1).join(', ')} and ${names.at(-1)}`;

    return `Removed ${listed} for this service; ${count === 1 ? 'it is' : 'they are'} not offered for ${serviceName}.`;
};

/** One entry of the first dropdown, as InquiryServiceTypeResource sends it. */
export type ServiceTypeOption = {
    id: string;
    name: string;
    services: ServiceOption[];
};

export const CUSTOM_STONE_FURNITURE = 'custom-stone-furniture';

/** What the second dropdown asks, which depends on the type chosen in the first. */
export const serviceChoiceLabel = (serviceTypeId: string): string =>
    serviceTypeId === CUSTOM_STONE_FURNITURE
        ? 'Which furniture?'
        : 'Which service?';

/** What the third dropdown asks, under a service with an installation. */
export const APPLICATION_CHOICE_LABEL = 'Which application?';

/* ------------------------------------------------------------------ */
/* Finding a stone                                                      */
/* ------------------------------------------------------------------ */

export type StoneFilter = 'all' | 'bestSellers' | 'saved';

/** The value of "All Type", which no stone type's slug can be mistaken for. */
export const ALL_STONE_TYPES = '__all__';

/** "All Type", then the types the server says the stones on offer have. */
export const stoneTypeOptions = (
    types: ReadonlyArray<{ value: string; label: string }>,
): Array<{ value: string; label: string }> => [
    { value: ALL_STONE_TYPES, label: 'All Type' },
    ...types,
];

export type StoneQuery = {
    /** The chosen service's slug; with none, the server sends no stones. */
    service: string;
    tab: StoneFilter;
    stoneType: string;
    search: string;
    /** The brands' slugs to keep; none is "All brands". */
    brands?: string[];
};

/**
 * The filters as the query the server reads, leaving out what is no filter.
 * The brands go as `stone_brands[]`, in the order the brands are listed.
 */
export const stoneFilterQuery = ({
    service,
    tab,
    stoneType,
    search,
    brands = [],
}: StoneQuery): Record<string, string | string[]> => ({
    ...(!service ? {} : { stone_service: service }),
    ...(tab === 'all' ? {} : { stone_tab: tab }),
    ...(stoneType === ALL_STONE_TYPES ? {} : { stone_type: stoneType }),
    ...(search.trim() === '' ? {} : { stone_search: search.trim() }),
    ...(brands.length === 0 ? {} : { stone_brands: [...brands] }),
});

/**
 * The brands the stones may come from, by the project's Brand answer: the
 * brands it chose (in the catalog's order), or every brand with No preference
 * or before it is answered. `restricted` says the answer keeps the rest out.
 */
export type AllowedStoneBrands = { ids: string[]; restricted: boolean };

export const allowedStoneBrands = (
    catalogBrands: readonly { id: string }[],
    answer: { brands: readonly string[]; brandNoPreference: boolean },
): AllowedStoneBrands => {
    const all = catalogBrands.map(({ id }) => id);
    const chosen = answer.brandNoPreference
        ? []
        : all.filter((id) => answer.brands.includes(id));

    return chosen.length === 0
        ? { ids: all, restricted: false }
        : { ids: chosen, restricted: true };
};

/**
 * The brands the stones are asked for: the reader's own pick on the Brand
 * filter, within the allowed ones; with none picked, every allowed brand when
 * the answer restricts them, else none (every brand).
 */
export const stoneBrandsQuery = (
    filter: readonly string[],
    allowed: AllowedStoneBrands,
): string[] => {
    const picked = allowed.ids.filter((id) => filter.includes(id));

    return picked.length > 0 ? picked : allowed.restricted ? allowed.ids : [];
};

/** The picked stones the page knows to be from a brand the answer keeps out. */
export const stonesOutsideBrands = <
    T extends { slug: string; brand: { slug: string } },
>(
    stoneIds: readonly string[],
    stones: readonly T[],
    allowed: AllowedStoneBrands,
): T[] =>
    allowed.restricted
        ? stones.filter(
              (stone) =>
                  stoneIds.includes(stone.slug) &&
                  !allowed.ids.includes(stone.brand.slug),
          )
        : [];

/** What step 2 says of the stones a new Brand answer took off. */
export const removedBrandStonesNotice = (names: readonly string[]): string => {
    if (names.length === 0) {
        return '';
    }

    const listed =
        names.length === 1
            ? names[0]
            : `${names.slice(0, -1).join(', ')} and ${names.at(-1)}`;

    return `Removed ${listed}; ${names.length === 1 ? 'it is' : 'they are'} not from the brands you chose.`;
};

/** Picking an entry of the stones' Brand filter; "All brands" is none. */
export const toggleBrandFilter = (
    current: string[],
    brand: string | null,
    order: readonly string[],
): string[] => {
    if (brand === null) {
        return [];
    }

    const next = current.includes(brand)
        ? current.filter((id) => id !== brand)
        : [...current, brand];

    return order.filter((id) => next.includes(id));
};

/**
 * The stone rows for the picks.
 *
 * Keeps every row of a stone that is still picked, drops the rows of one that
 * is not, and gives each newly picked stone one fresh row. Blank edges and
 * finishes take the "advise me" answer, so the reader only has to say otherwise
 * if they know what they want.
 */
export type StoneRow = {
    stoneId: string;
    quantity: number;
    slabSize: string;
    thickness: string;
    edge: string;
    finish: string;
};

export const seedStoneRows = (
    picked: string[],
    current: StoneRow[],
    advisory: { edge: string; finish: string } | null,
): StoneRow[] => {
    const pickedSet = new Set(picked);
    const kept = current
        .filter((row) => pickedSet.has(row.stoneId))
        .map((row) =>
            advisory === null
                ? row
                : {
                      ...row,
                      edge: row.edge === '' ? advisory.edge : row.edge,
                      finish: row.finish === '' ? advisory.finish : row.finish,
                  },
        );

    return [...new Set(picked)].flatMap((id) => {
        const existing = kept.filter((row) => row.stoneId === id);

        return existing.length > 0
            ? existing
            : [
                  {
                      stoneId: id,
                      quantity: 1,
                      slabSize: '',
                      thickness: '',
                      edge: advisory?.edge ?? '',
                      finish: advisory?.finish ?? '',
                  },
              ];
    });
};

/** The draft, as far as committing it goes. */
export type CommitDraft = {
    name: string;
    serviceTypeId: string;
    serviceId: string;
    /** The application (sub-item) a service with an installation asks; '' for none. */
    applicationId: string;
    stoneIds: string[];
    /** The reader asked Regray to recommend a stone instead of choosing one. */
    stoneAdvice: boolean;
    stones: StoneRow[];
    /** The project-scope answers, by question slug. */
    answers: Record<string, string>;
    brands: string[];
    brandNoPreference: boolean;
    deliveryMethod: string;
    addressId: string;
    address: PickedAddress | null;
    neededBy: string;
    notes: string;
    attachments: unknown[];
};

/**
 * One committed project. A pickup keeps no address: what was picked before
 * changing to it stays on the draft, in case they change back.
 */
export const buildCommittedProject = <T extends CommitDraft>(
    draft: T,
    context: { id: string },
) => {
    const isPickup = draft.deliveryMethod === PICKUP;

    return {
        brands: [...draft.brands],
        brandNoPreference: draft.brandNoPreference,
        id: context.id,
        name: draft.name,
        serviceTypeId: draft.serviceTypeId,
        serviceId: draft.serviceId,
        applicationId: draft.applicationId,
        stoneAdvice: draft.stoneAdvice,
        stones: draft.stones.map((row) => ({ ...row })),
        answers: { ...draft.answers },
        deliveryMethod: draft.deliveryMethod,
        addressId: isPickup ? '' : draft.addressId,
        address:
            isPickup || draft.address === null ? null : { ...draft.address },
        neededBy: draft.neededBy,
        notes: draft.notes,
        attachments: draft.attachments.map((attachment) =>
            typeof attachment === 'object' && attachment !== null
                ? { ...attachment }
                : attachment,
        ) as T['attachments'],
    };
};

/* ------------------------------------------------------------------ */
/* The request                                                          */
/* ------------------------------------------------------------------ */

/**
 * A file on its way out: a new upload carries its bytes, a file the inquiry
 * already holds (when editing) is only named by its id.
 */
export type RequestAttachment =
    | { status: 'uploading' | 'done'; file: File }
    | { status: 'saved'; fileId: number };

export type RequestProject = {
    /** The project's id; sent only when editing, where it is the database id. */
    id?: string;
    name: string;
    serviceId: string;
    /** The application a service with an installation asks; '' or absent for none. */
    applicationId?: string;
    stoneAdvice: boolean;
    stones: StoneRow[];
    answers: Record<string, string>;
    brands: string[];
    brandNoPreference: boolean;
    deliveryMethod: string;
    addressId: string;
    address: PickedAddress | null;
    neededBy: string;
    notes: string;
    attachments: RequestAttachment[];
};

/**
 * The inquiry as multipart form data, in the field names the backend's form
 * request validates: `projects[i][name]`, `projects[i][serviceId]`,
 * `projects[i][stones][k][quantity]`, `projects[i][answers][slug]` and, for
 * each file in the order it was attached, `projects[i][attachments][j]`.
 *
 * Only what the project's service asks is sent (`fieldsFor`): a stone row's
 * answers to its stone-scope questions, and the project answers to the rest,
 * a yes or no as 1 or 0. A project with an application sends it as
 * `projects[i][applicationId]`, and its answers are the application's
 * (serviceFor gives the service as withApplication asks it). A service that
 * asks for no stones sends no stones and
 * no Help me choose. What the reader typed for a question the service no
 * longer asks stays on the page but is not sent, since the server refuses it.
 *
 * The brands go as `projects[i][brands][k]` (slugs), or
 * `projects[i][brandNoPreference]` 1. The delivery method only where the
 * service asks one; the address only where it needs one: a saved address's
 * `addressId` (or `keepAddress` 1 to keep the one an edit already has) from
 * the address book, or the picker's `address[regionCode]` … `address[lng]`.
 *
 * Multipart rather than JSON because the attachments are `File` objects.
 *
 * When editing (`withProjectIds`), each project also carries its database id
 * as `projects[i][id]`, and the stored files it keeps as
 * `projects[i][keepFileIds][k]`; `attachments` are then the new uploads only.
 */
export const buildInquiryFormData = (
    values: { projects: RequestProject[] },
    {
        withProjectIds = false,
        serviceFor,
        addressSource = 'book',
    }: {
        withProjectIds?: boolean;
        /** The service as the project is asked it, with its application (withApplication). */
        serviceFor: (
            serviceId: string,
            applicationId: string,
        ) =>
            | (Pick<ServiceOption, 'fields' | 'requiresStones'> & {
                  fulfillment?: Fulfillment;
              })
            | undefined;
        addressSource?: AddressSource;
    },
): FormData => {
    const body = new FormData();

    values.projects.forEach((project, i) => {
        const field = (name: string): string => `projects[${i}][${name}]`;
        const applicationId = project.applicationId ?? '';
        const service = serviceFor(project.serviceId, applicationId);
        const takesStones = service?.requiresStones !== false;

        if (withProjectIds && project.id !== undefined) {
            body.append(field('id'), project.id);
        }

        body.append(field('name'), project.name);
        body.append(field('serviceId'), project.serviceId);

        if (applicationId !== '') {
            body.append(field('applicationId'), applicationId);
        }

        body.append(
            field('stoneAdvice'),
            takesStones && project.stoneAdvice ? '1' : '0',
        );

        (takesStones ? project.stones : []).forEach((row, k) => {
            const stone = (name: string): string =>
                `${field('stones')}[${k}][${name}]`;

            body.append(stone('stoneId'), row.stoneId);

            if (asksQuantity(service)) {
                body.append(stone('quantity'), String(row.quantity));
            }

            for (const question of stoneFields(service)) {
                const key = stoneFieldKey(question);

                if (key !== null) {
                    body.append(stone(key), row[key].trim());
                }
            }
        });

        for (const question of projectFields(service)) {
            const value = (project.answers[question.slug] ?? '').trim();

            if (value === '') {
                continue;
            }

            body.append(
                `${field('answers')}[${question.slug}]`,
                question.answerType === 'boolean'
                    ? value === YES
                        ? '1'
                        : '0'
                    : value,
            );

            // A custom choice goes with the reader's description of it.
            if (pickedChoice(question, value)?.isCustom === true) {
                body.append(
                    `${field('customAnswers')}[${question.slug}]`,
                    (
                        project.answers[customAnswerKey(question.slug)] ?? ''
                    ).trim(),
                );
            }
        }

        if (project.brandNoPreference) {
            body.append(field('brandNoPreference'), '1');
        } else {
            project.brands.forEach((brand, k) =>
                body.append(`${field('brands')}[${k}]`, brand),
            );
        }

        const method = asksDeliveryMethod(service)
            ? project.deliveryMethod
            : '';

        if (method !== '') {
            body.append(field('deliveryMethod'), method);
        }

        if (needsAddress(service, method)) {
            if (addressSource === 'book') {
                if (project.addressId === HELD_ADDRESS) {
                    body.append(field('keepAddress'), '1');
                } else if (project.addressId !== '') {
                    body.append(field('addressId'), project.addressId);
                }
            } else if (project.address !== null) {
                const picked = project.address;
                const parts: Record<string, string> = {
                    regionCode: picked.region?.code ?? '',
                    provinceCode: picked.noProvince
                        ? ''
                        : (picked.province?.code ?? ''),
                    cityCode: picked.city?.code ?? '',
                    barangayCode: picked.barangay?.code ?? '',
                    postalCode: picked.postalCode,
                    lat: picked.lat === null ? '' : picked.lat.toFixed(6),
                    lng: picked.lng === null ? '' : picked.lng.toFixed(6),
                };

                for (const [name, value] of Object.entries(parts)) {
                    body.append(`${field('address')}[${name}]`, value);
                }
            }
        }

        body.append(field('neededBy'), project.neededBy);
        body.append(field('notes'), project.notes);

        let kept = 0;
        let uploaded = 0;

        for (const attachment of project.attachments) {
            if (attachment.status === 'saved') {
                body.append(
                    `${field('keepFileIds')}[${kept}]`,
                    String(attachment.fileId),
                );
                kept += 1;
            } else {
                body.append(
                    `${field('attachments')}[${uploaded}]`,
                    attachment.file,
                );
                uploaded += 1;
            }
        }
    });

    return body;
};

/* ------------------------------------------------------------------ */
/* The answer                                                           */
/* ------------------------------------------------------------------ */

export type MappedInquiryErrors = {
    /** One complaint per project, keyed by project id. */
    projectMessages: Record<string, string>;
    /** One complaint about the request as a whole, keyed by the owning step. */
    sectionMessages: Partial<Record<number, string>>;
    /** Every complaint, once each, for the alert above the page. */
    alertErrors: string[];
};

/**
 * Put the backend's errors back where the page shows them.
 *
 * The keys are the form's own paths — `projects.0.stones.1.quantity` — so the
 * project is found by the index in the key, read against the same list that was
 * sent. A key about the list itself (`projects`) is the request's, not any one
 * project's. A key naming a project that is no longer there is dropped from the
 * per-project messages but still said in the alert.
 */
export const mapInquiryErrors = (
    errors: Record<string, string>,
    projects: Array<{ id: string }>,
): MappedInquiryErrors => {
    const projectMessages: Record<string, string> = {};
    const sectionMessages: Partial<Record<number, string>> = {};
    const alertErrors: string[] = [];

    for (const [key, message] of Object.entries(errors)) {
        if (!alertErrors.includes(message)) {
            alertErrors.push(message);
        }

        const match = /^projects\.(\d+)(?:\.|$)/.exec(key);

        if (match === null) {
            if (
                key === 'projects' &&
                sectionMessages[REQUIREMENTS_STEP] === undefined
            ) {
                sectionMessages[REQUIREMENTS_STEP] = message;
            }

            continue;
        }

        const project = projects[Number(match[1])];

        if (
            project !== undefined &&
            projectMessages[project.id] === undefined
        ) {
            projectMessages[project.id] = message;
        }
    }

    return {
        projectMessages,
        sectionMessages,
        alertErrors,
    };
};

/** What the backend flashes after a send. */
export type InquiryFlash =
    | { status: 'success'; title: string; reference: string }
    | { status: 'error'; message?: string };

/** Read the flash off an Inertia page, or null when it carries none. */
export const readInquiryFlash = (flash: unknown): InquiryFlash | null => {
    const value = (flash as { inquiry?: unknown } | null | undefined)?.inquiry;

    if (typeof value !== 'object' || value === null) {
        return null;
    }

    const { status, title, reference } = value as Record<string, unknown>;

    if (status === 'success') {
        return {
            status,
            title: typeof title === 'string' ? title : '',
            reference: typeof reference === 'string' ? reference : '',
        };
    }

    if (status === 'error') {
        const { message } = value as Record<string, unknown>;

        return typeof message === 'string' ? { status, message } : { status };
    }

    return null;
};

/* ------------------------------------------------------------------ */
/* Editing a sent inquiry                                               */
/* ------------------------------------------------------------------ */

/**
 * The service types with each edited inquiry's own listed answers added to
 * its service's questions, where they are no longer offered: a closed list's
 * among its options, a listed value among its suggestions. The edit then
 * shows them and its checks take them, as the server keeps them
 * (UpdateInquiryRequest). A new inquiry has none.
 */
export const withHeldChoices = (
    serviceTypes: ServiceTypeOption[],
    projects: ReadonlyArray<{
        serviceId: string | null;
        heldChoices?: Record<string, ServiceFieldChoice[]>;
    }>,
): ServiceTypeOption[] =>
    projects.reduce((types, { serviceId, heldChoices }) => {
        if (serviceId === null || heldChoices === undefined) {
            return types;
        }

        return types.map((type) => ({
            ...type,
            services: type.services.map((service) =>
                service.id !== serviceId
                    ? service
                    : {
                          ...service,
                          fields: service.fields.map((field) => {
                              const held = heldChoices[field.slug] ?? [];
                              const key =
                                  field.answerType === 'choice' ||
                                  field.options.length > 0
                                      ? 'options'
                                      : 'suggestions';
                              const missing = held.filter(
                                  (choice) =>
                                      !field[key].some(
                                          ({ value }) => value === choice.value,
                                      ),
                              );

                              return missing.length === 0
                                  ? field
                                  : {
                                        ...field,
                                        [key]: [...field[key], ...missing],
                                    };
                          }),
                      },
            ),
        }));
    }, serviceTypes);

/** One project as InquiryEditResource sends it. */
export type InquiryEditProject = {
    id: number;
    name: string;
    serviceTypeId: string | null;
    serviceId: string | null;
    /** The application (sub-item) it is for, null for none. */
    applicationId?: string | null;
    /** The service it already has, which may no longer be offered to new inquiries. */
    heldServiceType: ServiceTypeOption | null;
    /**
     * The listed answers it already has, by question slug (its edge profiles
     * and finishes, closed options and listed values), which may since have
     * been taken off their lists. The page lists them beside what is offered,
     * so the edit shows and keeps them (withHeldChoices).
     */
    heldChoices?: Record<string, ServiceFieldChoice[]>;
    /** The brands it names, as the Brand question lists them: one may be archived since. */
    heldBrands?: Array<{ id: string; name: string; logo: string }>;
    /** Its stones as cards, which may be on no page of stones reached yet. */
    heldStones: Array<
        {
            slug: string;
            name: string;
            thumbnail: string;
        } & StoneMeasurementOptions
    >;
    stoneAdviceRequested: boolean;
    /** A stone row's answers are null where its service does not ask them. */
    stones: Array<{
        stoneId: string;
        /** Null where the service does not ask how many pieces. */
        quantity: number | null;
        /** "160 x 320 cm", null where the service does not ask it. */
        slabSize: string | null;
        /** "20 mm", null where the service does not ask it. */
        thickness: string | null;
        edge: string | null;
        finish: string | null;
    }>;
    /** The project answers by question slug: text, a number, an option's slug, yes or no. */
    answers: Record<string, string | number | boolean | null>;
    brands: string[];
    brandNoPreference: boolean;
    /** Null for an installed service. */
    deliveryMethod: string | null;
    /** The address it was sent with (PhAddressPayload), null for a pickup. */
    address: ({ line: string } & Record<string, unknown>) | null;
    notes: string | null;
    neededBy: string | null;
    deadlineLocked: boolean;
    deadlineLockedReason: string | null;
    attachments: Array<{
        id: number;
        name: string;
        sizeBytes: number;
        mimeType: string;
        status: 'saved';
    }>;
};

/** A committed project as the form holds it, filled from an edit payload. */
export type EditFormProject = {
    id: string;
    name: string;
    serviceTypeId: string;
    serviceId: string;
    applicationId: string;
    stoneAdvice: boolean;
    stones: StoneRow[];
    answers: Record<string, string>;
    brands: string[];
    brandNoPreference: boolean;
    deliveryMethod: string;
    addressId: string;
    address: PickedAddress | null;
    neededBy: string;
    notes: string;
    attachments: Array<{
        id: string;
        status: 'saved';
        fileId: number;
        name: string;
        sizeBytes: number;
        mimeType: string;
    }>;
};

/** An answer as the form holds it: text, a yes or no as `yes` or `no`. */
const formAnswer = (value: string | number | boolean | null): string =>
    value === null
        ? ''
        : typeof value === 'boolean'
          ? value
              ? YES
              : NO
          : String(value);

/**
 * The edit payload in the shape the form commits a project in, so Review and
 * Edit read a stored project exactly as they read one built on the page.
 *
 * The project's id becomes the form id, as a string, which is also what is
 * sent back as `projects[i][id]`. Missing answers become the form's blank `''`.
 *
 * The address it was sent with is kept as it is: a client's edit holds it as
 * HELD_ADDRESS (keepAddress), and staff start the picker from it
 * (`toPicked`, lib/ph-address.ts addressFromPayload).
 */
export const toEditFormProjects = (
    projects: InquiryEditProject[],
    toPicked: (
        address: NonNullable<InquiryEditProject['address']>,
    ) => PickedAddress = () => {
        throw new Error('No address reader given.');
    },
): EditFormProject[] =>
    projects.map((project) => ({
        id: String(project.id),
        name: project.name,
        serviceTypeId: project.serviceTypeId ?? '',
        serviceId: project.serviceId ?? '',
        applicationId: project.applicationId ?? '',
        stoneAdvice: project.stoneAdviceRequested,
        stones: project.stones.map((row) => ({
            stoneId: row.stoneId,
            quantity: row.quantity ?? 1,
            slabSize: row.slabSize ?? '',
            thickness: row.thickness ?? '',
            edge: row.edge ?? '',
            finish: row.finish ?? '',
        })),
        answers: Object.fromEntries(
            Object.entries(project.answers).map(([slug, value]) => [
                slug,
                formAnswer(value),
            ]),
        ),
        brands: [...project.brands],
        brandNoPreference: project.brandNoPreference,
        deliveryMethod: project.deliveryMethod ?? '',
        addressId: project.address === null ? '' : HELD_ADDRESS,
        address: project.address === null ? null : toPicked(project.address),
        neededBy: project.neededBy ?? '',
        notes: project.notes ?? '',
        attachments: project.attachments.map((file) => ({
            id: `saved-${file.id}`,
            status: 'saved' as const,
            fileId: file.id,
            name: file.name,
            sizeBytes: file.sizeBytes,
            mimeType: file.mimeType,
        })),
    }));

/**
 * Why each project's date and time can no longer be changed, keyed by the form
 * id. A project whose visit is still pending is not in it.
 */
export const deadlineLocks = (
    projects: InquiryEditProject[],
): Record<string, string> =>
    Object.fromEntries(
        projects
            .filter((project) => project.deadlineLocked)
            .map((project) => [
                String(project.id),
                project.deadlineLockedReason ??
                    'This date and time can no longer be changed.',
            ]),
    );

/**
 * What the Review sheet says in each mode. The send is a save when editing.
 */
export const reviewActions = (
    mode: InquiryMode,
): {
    submitLabel: string;
    footerNote: string;
} =>
    mode === 'edit'
        ? {
              submitLabel: 'Save changes',
              footerNote: 'Nothing is saved until you save changes',
          }
        : {
              submitLabel: 'Send my request',
              footerNote: 'Nothing is sent until you press Send my request',
          };

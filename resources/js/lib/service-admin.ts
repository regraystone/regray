import type {
    AnswerType,
    Fulfillment,
    QuestionOption,
    ServiceLimits,
    ServiceRow,
} from '@/types/service-admin';

/** The kinds of answer a new question can take, as the form names them. */
export const ANSWER_TYPES: { value: AnswerType; label: string }[] = [
    { value: 'text', label: 'Written answer' },
    { value: 'number', label: 'Number' },
    { value: 'boolean', label: 'Yes or no' },
    { value: 'choice', label: 'Choose from a list' },
];

/**
 * What is wrong with a question written on the form before it is added, as
 * the server would say it, or null when it can be added. `takenNames` are
 * the list's questions and the ones already on the form.
 */
export const newQuestionProblem = (
    question: { name: string; answerType: AnswerType | ''; choices: string[] },
    takenNames: string[],
    limits: Pick<ServiceLimits, 'questionNameMax' | 'choicesMax'>,
): string | null => {
    const name = question.name.trim();

    if (name === '') {
        return 'Write the question.';
    }

    if (name.length > limits.questionNameMax) {
        return `A question can be ${limits.questionNameMax} characters at most.`;
    }

    if (
        takenNames.some((taken) => taken.toLowerCase() === name.toLowerCase())
    ) {
        return 'That question already exists. Choose it from the list.';
    }

    if (question.answerType === '') {
        return 'Choose what kind of answer it takes.';
    }

    if (question.answerType === 'choice' && question.choices.length < 2) {
        return 'List at least two choices.';
    }

    if (question.choices.length > limits.choicesMax) {
        return `A question can list ${limits.choicesMax} choices at most.`;
    }

    return null;
};

/** How the work reaches the client, as the form and View name it. */
export const FULFILLMENT_OPTIONS: {
    value: Fulfillment;
    label: string;
    hint: string;
}[] = [
    {
        value: 'delivery_or_pickup',
        label: 'Delivered or picked up',
        hint: 'The client chooses delivery or pickup; a delivery asks for the address.',
    },
    {
        value: 'installed',
        label: 'Installed on site',
        hint: 'Asks only for the installation address.',
    },
];

export const fulfillmentLabel = (value: Fulfillment): string =>
    FULFILLMENT_OPTIONS.find((option) => option.value === value)?.label ??
    value;

const count = (amount: number, one: string, many: string) =>
    `${amount} ${amount === 1 ? one : many}`;

/** A card's short line: "3 questions · 2 included · 5 inquiries". */
export const serviceMeta = (
    service: Pick<ServiceRow, 'questions' | 'features' | 'inquiriesCount'>,
): string =>
    [
        count(service.questions.length, 'question', 'questions'),
        count(service.features.length, 'included', 'included'),
        count(service.inquiriesCount, 'inquiry', 'inquiries'),
    ].join(' · ');

/**
 * Why a service set to visible is still not on the Inquire page, or null when
 * it is offered or was hidden on purpose.
 */
export const notOfferedReason = (
    service: Pick<
        ServiceRow,
        'isActive' | 'isOffered' | 'requiresStones' | 'stoneTypes'
    >,
    typeIsActive: boolean,
): string | null => {
    if (!service.isActive || service.isOffered) {
        return null;
    }

    if (!typeIsActive) {
        return 'Its service type is hidden, so the Inquire page does not show it.';
    }

    if (service.requiresStones && service.stoneTypes.length === 0) {
        return 'It needs stones but has no stone type, so the Inquire page does not show it.';
    }

    return 'The Inquire page does not show it.';
};

/** What a question takes, in a few words, for the form and View. */
export const questionHint = (
    question: Pick<QuestionOption, 'scope' | 'answerType' | 'options'>,
): string => {
    const answer =
        question.answerType === 'boolean'
            ? 'Yes or no'
            : question.answerType === 'number'
              ? 'A number'
              : question.answerType === 'choice'
                ? question.options.length > 0
                    ? `One of: ${question.options.join(', ')}`
                    : 'One from a list'
                : 'A written answer';
    const suggestions =
        question.answerType !== 'choice' && question.options.length > 0
            ? ` (suggests ${question.options.join(', ')})`
            : '';

    return `${question.scope === 'stone' ? 'For each stone' : 'For the project'} · ${answer}${suggestions}`;
};

/** The confirm text of Archive service, saying what it keeps. */
export const archiveServiceDescription = (inquiriesCount: number): string =>
    inquiriesCount > 0
        ? `It leaves this page and the Inquire page. Its ${count(inquiriesCount, 'inquiry', 'inquiries')} keep it.`
        : 'It leaves this page and the Inquire page.';

/** Why a service type can't be archived yet, or null when it can. */
export const typeArchiveBlocker = (servicesCount: number): string | null =>
    servicesCount === 0
        ? null
        : servicesCount === 1
          ? 'Move or archive its service first.'
          : `Move or archive its ${servicesCount} services first.`;

/** Whether a service matches what was searched: its name or description. */
export const matchesSearch = (
    service: Pick<ServiceRow, 'name' | 'description'>,
    search: string,
): boolean => {
    const needle = search.trim().toLowerCase();

    return (
        needle === '' ||
        service.name.toLowerCase().includes(needle) ||
        service.description.toLowerCase().includes(needle)
    );
};

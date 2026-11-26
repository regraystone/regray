import type {
    Fulfillment,
    QuestionOption,
    ServiceRow,
} from '@/types/service-admin';

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

/** A row's short line: "3 questions · 2 included · 5 inquiries". */
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

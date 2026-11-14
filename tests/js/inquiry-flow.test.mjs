/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    ALL_STONE_TYPES,
    DELIVERY,
    INQUIRY_STEP_LABELS,
    PICKUP,
    REQUIREMENTS_STEP,
    REVIEW_STEP,
    SERVICE_STEP,
    STONES_STEP,
    autoProjectName,
    answerProblem,
    asksQuantity,
    buildCommittedProject,
    clampRestoredStep,
    furthestQuestion,
    linkedToStoneProblem,
    nextQuestion,
    quantityProblem,
    questionProblem,
    seedStoneRows,
    serviceChoiceLabel,
    stepperPosition,
    removedStonesNotice,
    stoneFilterQuery,
    stoneFitsService,
    stonesNotOffered,
    stoneTypeOptions,
} from '../../resources/js/lib/inquiry-flow.ts';

const draft = (overrides = {}) => ({
    serviceId: '',
    stoneIds: [],
    ...overrides,
});

const regular = draft({
    serviceId: 'countertop-backsplash',
    stoneIds: ['marble'],
});

/** Every question a reader is shown, following Next from the first. */
const walk = () => {
    const seen = [SERVICE_STEP];

    while (seen.at(-1) !== REVIEW_STEP) {
        seen.push(nextQuestion(seen.at(-1)));
    }

    return seen;
};

describe('the steps', () => {
    it('are Your request and Review and send', () => {
        assert.deepEqual(INQUIRY_STEP_LABELS, [
            'Your request',
            'Review and send',
        ]);
    });

    it('show every question as the first step and Review as the second', () => {
        for (const question of [SERVICE_STEP, STONES_STEP, REQUIREMENTS_STEP]) {
            assert.equal(stepperPosition(question), 1);
        }

        assert.equal(stepperPosition(REVIEW_STEP), 2);
    });
});

describe('moving between the questions', () => {
    it('asks what is needed, then the stones, then the details, then Review', () => {
        assert.deepEqual(walk(), [
            SERVICE_STEP,
            STONES_STEP,
            REQUIREMENTS_STEP,
            REVIEW_STEP,
        ]);
    });

    it('stops at Review', () => {
        assert.equal(nextQuestion(REVIEW_STEP), REVIEW_STEP);
    });
});

describe('the second dropdown', () => {
    it('asks which application, or which furniture for custom furniture', () => {
        assert.equal(
            serviceChoiceLabel('natural-stone-applications'),
            'Which application?',
        );
        assert.equal(
            serviceChoiceLabel('custom-stone-furniture'),
            'Which furniture?',
        );
    });
});

describe('finding a stone', () => {
    it('offers All Type first, then the types the server sent', () => {
        assert.deepEqual(
            stoneTypeOptions([{ value: 'marble', label: 'Marble' }]),
            [
                { value: ALL_STONE_TYPES, label: 'All Type' },
                { value: 'marble', label: 'Marble' },
            ],
        );
    });

    it('asks the server with no filter by default', () => {
        assert.deepEqual(
            stoneFilterQuery({
                tab: 'all',
                stoneType: ALL_STONE_TYPES,
                search: '  ',
            }),
            {},
        );
    });

    it('sends the tab, the stone type and the trimmed search together', () => {
        assert.deepEqual(
            stoneFilterQuery({
                tab: 'saved',
                stoneType: 'marble',
                search: ' bianco ',
            }),
            {
                stone_tab: 'saved',
                stone_type: 'marble',
                stone_search: 'bianco',
            },
        );
    });
});

describe('autoProjectName', () => {
    it('is named after the service', () => {
        assert.equal(autoProjectName('Countertop', 100), 'Countertop');
    });

    it('is blank with no service picked', () => {
        assert.equal(autoProjectName('', 100), '');
    });

    it('stays inside the limit, without a trailing space', () => {
        assert.equal(autoProjectName('Stone supply', 6), 'Stone');
    });
});

describe('clampRestoredStep', () => {
    it('restores Review while there is a project to review', () => {
        assert.equal(clampRestoredStep(REVIEW_STEP, 1, draft()), REVIEW_STEP);
    });

    it('does not restore Review with no project', () => {
        assert.equal(clampRestoredStep(REVIEW_STEP, 0, draft()), SERVICE_STEP);
        assert.equal(
            clampRestoredStep(REVIEW_STEP, 0, regular),
            REQUIREMENTS_STEP,
        );
    });

    it('lands on the first question with no service chosen', () => {
        assert.equal(
            clampRestoredStep(
                REQUIREMENTS_STEP,
                0,
                draft({ stoneIds: ['marble'] }),
            ),
            SERVICE_STEP,
        );
    });

    it('lands on the stones while none is picked', () => {
        assert.equal(
            clampRestoredStep(
                REQUIREMENTS_STEP,
                0,
                draft({ serviceId: 'countertop-backsplash' }),
            ),
            STONES_STEP,
        );
    });

    it('lets a request for a recommendation past the stones with none picked', () => {
        assert.equal(
            clampRestoredStep(
                REQUIREMENTS_STEP,
                0,
                draft({
                    serviceId: 'countertop-backsplash',
                    stoneAdvice: true,
                }),
            ),
            REQUIREMENTS_STEP,
        );
    });

    it('keeps a step the draft still justifies', () => {
        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, regular),
            REQUIREMENTS_STEP,
        );
        assert.equal(clampRestoredStep(STONES_STEP, 0, regular), STONES_STEP);
        assert.equal(clampRestoredStep(SERVICE_STEP, 0, regular), SERVICE_STEP);
    });

    it('treats a step number that is not a number as the first', () => {
        assert.equal(clampRestoredStep(Number.NaN, 0, regular), SERVICE_STEP);
        assert.equal(clampRestoredStep(-3, 0, regular), SERVICE_STEP);
    });
});

describe('stones and the service', () => {
    const marble = {
        slug: 'carrara',
        name: 'Carrara',
        type: { slug: 'marble' },
    };
    const granite = {
        slug: 'absolute',
        name: 'Absolute',
        type: { slug: 'granite' },
    };
    const countertop = { stoneTypes: ['granite'] };

    it('fits a stone by its primary type alone', () => {
        assert.equal(stoneFitsService(granite, countertop), true);
        assert.equal(stoneFitsService(marble, countertop), false);
        assert.equal(stoneFitsService(granite, undefined), false);
    });

    it('lists the picked stones a service does not offer, leaving unknown ones to the server', () => {
        assert.deepEqual(
            stonesNotOffered(
                ['carrara', 'absolute', 'unknown'],
                [marble, granite],
                countertop,
            ),
            [marble],
        );
    });

    it('names the stones it removed', () => {
        assert.equal(
            removedStonesNotice(['Carrara'], 'Countertop'),
            'Removed Carrara for this service; it is not offered for Countertop.',
        );
        assert.equal(
            removedStonesNotice(['Carrara', 'Onyx', 'Nero'], 'Countertop'),
            'Removed Carrara, Onyx and Nero for this service; they are not offered for Countertop.',
        );
        assert.equal(
            removedStonesNotice(['2 stones'], 'Countertop', 2),
            'Removed 2 stones for this service; they are not offered for Countertop.',
        );
        assert.equal(removedStonesNotice([], 'Countertop'), '');
    });

    it('asks the server for the chosen service with the filters', () => {
        assert.deepEqual(
            stoneFilterQuery({
                service: 'countertop-backsplash',
                tab: 'all',
                stoneType: ALL_STONE_TYPES,
                search: '',
            }),
            { stone_service: 'countertop-backsplash' },
        );
    });
});

describe('showing the questions one by one', () => {
    const offered = new Set(['countertop-backsplash']);

    it('reaches as far as the answers do', () => {
        assert.equal(furthestQuestion(draft()), SERVICE_STEP);
        assert.equal(
            furthestQuestion(draft({ serviceId: 'countertop-backsplash' })),
            STONES_STEP,
        );
        assert.equal(furthestQuestion(regular), REQUIREMENTS_STEP);
        assert.equal(
            furthestQuestion(
                draft({
                    serviceId: 'countertop-backsplash',
                    stoneAdvice: true,
                }),
            ),
            REQUIREMENTS_STEP,
        );
    });

    it('lets Next under the first question go on only with a service on offer', () => {
        assert.deepEqual(questionProblem(SERVICE_STEP, draft(), offered), {
            field: 'serviceId',
            message: 'Choose what you need.',
        });
        assert.equal(
            questionProblem(SERVICE_STEP, draft({ serviceId: 'gone' }), offered)
                ?.field,
            'serviceId',
        );
        assert.equal(
            questionProblem(
                SERVICE_STEP,
                draft({ serviceId: 'countertop-backsplash' }),
                offered,
            ),
            null,
        );
    });

    it('lets Next under the stones go on with a stone or Help me choose', () => {
        assert.deepEqual(
            questionProblem(
                STONES_STEP,
                draft({ serviceId: 'countertop-backsplash' }),
                offered,
            ),
            { field: 'stoneIds', message: 'Select at least one stone.' },
        );
        assert.equal(questionProblem(STONES_STEP, regular, offered), null);
        assert.equal(
            questionProblem(
                STONES_STEP,
                draft({
                    serviceId: 'countertop-backsplash',
                    stoneAdvice: true,
                }),
                offered,
            ),
            null,
        );
    });
});
describe('committing a draft', () => {
    const filled = (overrides = {}) => ({
        name: 'Kitchen counters',
        serviceTypeId: 'natural-stone-applications',
        serviceId: 'countertop-backsplash',
        stoneAdvice: false,
        stoneIds: ['marble'],
        stones: [
            {
                stoneId: 'marble',
                quantity: 4,
                edge: 'Straight Edge',
                finish: 'Honed',
            },
        ],
        answers: { area_sqm: '12.5' },
        deliveryMethod: DELIVERY,
        location: 'Makati',
        neededBy: '2030-01-02',
        notes: '',
        attachments: [],
        ...overrides,
    });

    it('carries the service type and the service onto the project', () => {
        const project = buildCommittedProject(filled(), { id: 'p-5' });

        assert.equal(project.serviceTypeId, 'natural-stone-applications');
        assert.equal(project.serviceId, 'countertop-backsplash');
    });

    it('keeps the service of a project left to Regray to recommend a stone', () => {
        const project = buildCommittedProject(
            filled({ stoneAdvice: true, stoneIds: [], stones: [] }),
            { id: 'p-2' },
        );

        assert.equal(project.serviceId, 'countertop-backsplash');
        assert.equal(project.stoneAdvice, true);
    });

    it('keeps the location of a delivery', () => {
        const project = buildCommittedProject(filled(), { id: 'p-6' });

        assert.equal(project.deliveryMethod, DELIVERY);
        assert.equal(project.location, 'Makati');
        assert.deepEqual(project.answers, { area_sqm: '12.5' });
    });

    it('commits no location for a pickup, and leaves the draft holding it', () => {
        const source = filled({ deliveryMethod: PICKUP });
        const project = buildCommittedProject(source, { id: 'p-7' });

        assert.equal(project.deliveryMethod, PICKUP);
        assert.equal(project.location, '');
        assert.equal(source.location, 'Makati');
    });

    it('does not share rows with the draft it was built from', () => {
        const source = filled();
        const project = buildCommittedProject(source, { id: 'p-4' });

        project.stones[0].quantity = 99;
        project.answers.area_sqm = '1';

        assert.equal(source.stones[0].quantity, 4);
        assert.equal(source.answers.area_sqm, '12.5');
    });
});

describe('how many pieces', () => {
    it('is asked only by a service that lists it', () => {
        assert.equal(
            asksQuantity({
                fields: [{ slug: 'quantity' }, { slug: 'finish' }],
            }),
            true,
        );
        assert.equal(asksQuantity({ fields: [{ slug: 'finish' }] }), false);
        assert.equal(asksQuantity(undefined), false);
    });

    it('takes a whole count from 1 to 9999, in the words the server uses', () => {
        assert.equal(quantityProblem(1), null);
        assert.equal(quantityProblem(9999), null);
        assert.equal(quantityProblem(0), 'Enter at least 1 piece.');
        assert.equal(quantityProblem(10000), 'Enter 9999 pieces or fewer.');
        assert.equal(
            quantityProblem(1.5),
            'Quantity has to be a whole number of pieces.',
        );
        assert.equal(
            quantityProblem(Number.NaN),
            'Enter how many pieces you need.',
        );
    });
});

describe('a service that asks for no stones', () => {
    const installation = (overrides = {}) =>
        draft({
            serviceId: 'installation-only',
            requiresStones: false,
            ...overrides,
        });

    it('goes from What do you need? straight to the details', () => {
        assert.equal(nextQuestion(SERVICE_STEP, false), REQUIREMENTS_STEP);
        assert.equal(nextQuestion(SERVICE_STEP, true), STONES_STEP);
        assert.equal(furthestQuestion(installation()), REQUIREMENTS_STEP);
    });

    it('neither shows nor checks the stones question', () => {
        assert.equal(
            questionProblem(
                STONES_STEP,
                installation(),
                new Set(['installation-only']),
            ),
            null,
        );
        assert.equal(
            clampRestoredStep(STONES_STEP, 0, installation()),
            REQUIREMENTS_STEP,
        );
        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, installation()),
            REQUIREMENTS_STEP,
        );
    });
});

describe('answering what a service asks', () => {
    const question = (overrides = {}) => ({
        slug: 'area_sqm',
        label: 'Project area (sqm)',
        scope: 'project',
        answerType: 'number',
        required: true,
        allowsCustom: true,
        options: [],
        suggestions: [
            { value: '1', label: '1' },
            { value: '5', label: '5' },
        ],
        ...overrides,
    });
    const tableBase = question({
        slug: 'table_base',
        label: 'Table base',
        answerType: 'choice',
        allowsCustom: false,
        suggestions: [],
        options: [
            { value: 'wood', label: 'Wood', linksToStone: false },
            {
                value: 'same-as-stone',
                label: 'Same as stone',
                linksToStone: true,
            },
        ],
    });

    it('asks for a required answer in the words the server uses', () => {
        assert.equal(
            answerProblem(question(), ''),
            'Enter the project area (sqm).',
        );
        assert.equal(
            answerProblem(tableBase, undefined),
            'Choose the table base.',
        );
        assert.equal(answerProblem(question({ required: false }), ''), null);
    });

    it('takes a free entry by its type, whatever the suggestions are', () => {
        assert.equal(answerProblem(question(), '12.5'), null);
        assert.equal(
            answerProblem(question(), '0'),
            'Enter a number greater than 0.',
        );
        assert.equal(
            answerProblem(question(), 'lots'),
            'Enter a number greater than 0.',
        );
        assert.equal(
            answerProblem(
                question({
                    slug: 'size',
                    label: 'Furniture size',
                    answerType: 'text',
                }),
                '75 x 75 cm',
            ),
            null,
        );
    });

    it('keeps a closed list to its options, and yes or no to yes or no', () => {
        assert.equal(answerProblem(tableBase, 'wood'), null);
        assert.equal(
            answerProblem(tableBase, 'marble'),
            'Choose the table base from the list.',
        );
        assert.equal(
            answerProblem(
                question({ label: 'Waterfall effect', answerType: 'boolean' }),
                'maybe',
            ),
            'Choose yes or no.',
        );
        assert.equal(
            answerProblem(question({ allowsCustom: false }), '7'),
            'Choose the project area (sqm) from the list.',
        );
    });

    it('takes "Same as stone" only with a stone to be the same as', () => {
        assert.equal(
            linkedToStoneProblem(tableBase, 'same-as-stone', false),
            'Same as stone needs a stone. Choose another table base.',
        );
        assert.equal(
            linkedToStoneProblem(tableBase, 'same-as-stone', true),
            null,
        );
        assert.equal(linkedToStoneProblem(tableBase, 'wood', false), null);
    });
});

describe('seedStoneRows', () => {
    it('makes one blank row per newly picked stone', () => {
        assert.deepEqual(seedStoneRows(['marble', 'onyx'], [], null), [
            {
                stoneId: 'marble',
                quantity: 1,
                slabSize: '',
                thickness: '',
                edge: '',
                finish: '',
            },
            {
                stoneId: 'onyx',
                quantity: 1,
                slabSize: '',
                thickness: '',
                edge: '',
                finish: '',
            },
        ]);
    });

    it('keeps every row of a stone that is still picked and drops the rest', () => {
        const current = [
            { stoneId: 'marble', quantity: 2, edge: 'A', finish: 'X' },
            { stoneId: 'marble', quantity: 5, edge: 'B', finish: 'Y' },
            { stoneId: 'onyx', quantity: 1, edge: 'A', finish: 'X' },
        ];

        assert.deepEqual(seedStoneRows(['marble'], current, null), [
            current[0],
            current[1],
        ]);
    });

    it('does not repeat the rows of a stone picked twice', () => {
        const current = [
            { stoneId: 'marble', quantity: 2, edge: 'A', finish: 'X' },
            { stoneId: 'marble', quantity: 5, edge: 'B', finish: 'Y' },
        ];

        assert.deepEqual(
            seedStoneRows(['marble', 'marble'], current, null),
            current,
        );
    });

    it('fills the advise-me answers for a draft with nothing to specify', () => {
        const advisory = { edge: 'Advise me', finish: 'Advise me' };

        assert.deepEqual(seedStoneRows(['marble'], [], advisory), [
            {
                stoneId: 'marble',
                quantity: 1,
                slabSize: '',
                thickness: '',
                edge: 'Advise me',
                finish: 'Advise me',
            },
        ]);
    });

    it('fills only the blanks of rows already there', () => {
        const advisory = { edge: 'Advise me', finish: 'Advise me' };
        const current = [
            { stoneId: 'marble', quantity: 2, edge: 'Bevel', finish: '' },
        ];

        assert.deepEqual(seedStoneRows(['marble'], current, advisory), [
            {
                stoneId: 'marble',
                quantity: 2,
                edge: 'Bevel',
                finish: 'Advise me',
            },
        ]);
    });
});

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    DELIVERY,
    INQUIRY_STEP_LABELS,
    PICKUP,
    REQUIREMENTS_STEP,
    REVIEW_STEP,
    STONE_ADVICE_KEY,
    STONE_ADVICE_LABEL,
    STONES_STEP,
    WORK_STEP,
    autoProjectName,
    buildCommittedProject,
    chooseWorkService,
    clampRestoredStep,
    keepWorkFor,
    nextQuestion,
    seedStoneRows,
    stepperPosition,
    workProblems,
    workRowKeys,
    workRowLabel,
} from '../../resources/js/lib/inquiry-flow.ts';

const draft = (overrides = {}) => ({
    stoneIds: [],
    ...overrides,
});

const regular = draft({
    stoneIds: ['marble'],
    workIsComplete: true,
});

/** Every question a reader is shown, following Next from the first. */
const walk = () => {
    const seen = [STONES_STEP];

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
        for (const question of [STONES_STEP, WORK_STEP, REQUIREMENTS_STEP]) {
            assert.equal(stepperPosition(question), 1);
        }

        assert.equal(stepperPosition(REVIEW_STEP), 2);
    });
});

describe('moving between the questions', () => {
    it('asks Stones, then the work, then the details, then Review', () => {
        assert.deepEqual(walk(), [
            STONES_STEP,
            WORK_STEP,
            REQUIREMENTS_STEP,
            REVIEW_STEP,
        ]);
    });

    it('stops at Review', () => {
        assert.equal(nextQuestion(REVIEW_STEP), REVIEW_STEP);
    });
});

describe('the work per stone', () => {
    const options = [
        {
            id: 'supply-only',
            name: 'Stone supply',
            standalone: false,
            needsFurniture: false,
        },
        {
            id: 'furniture-only',
            name: 'Customized furniture',
            standalone: false,
            needsFurniture: true,
        },
        {
            id: 'furniture-other',
            name: 'Other furniture',
            standalone: false,
            needsFurniture: true,
        },
    ];
    const types = ['Seating', 'Tables'];
    const entry = (serviceId = '', furnitureType = '') => ({
        serviceId,
        furnitureType,
    });

    it('has one row per stone', () => {
        assert.deepEqual(
            workRowKeys({ stoneIds: ['marble', 'onyx'], stoneAdvice: false }),
            ['marble', 'onyx'],
        );
    });

    it('has one row with no stone for Help me choose', () => {
        assert.deepEqual(workRowKeys({ stoneIds: [], stoneAdvice: true }), [
            STONE_ADVICE_KEY,
        ]);
        assert.equal(
            workRowLabel(STONE_ADVICE_KEY, []),
            'Stone to be recommended by Regray',
        );
        assert.equal(workRowLabel(STONE_ADVICE_KEY, []), STONE_ADVICE_LABEL);
    });

    it('labels a stone by its name', () => {
        assert.equal(
            workRowLabel('marble', [{ slug: 'marble', name: 'Marble' }]),
            'Marble',
        );
    });

    it('blocks a stone with no option', () => {
        assert.deepEqual(workProblems(['marble'], {}, options, types), [
            { key: 'marble', field: 'serviceId' },
        ]);
    });

    it('blocks furniture work until a furniture type is picked', () => {
        assert.deepEqual(
            workProblems(
                ['marble'],
                { marble: entry('furniture-only') },
                options,
                types,
            ),
            [{ key: 'marble', field: 'furnitureType' }],
        );
        assert.deepEqual(
            workProblems(
                ['marble'],
                { marble: entry('furniture-only', 'Tables') },
                options,
                types,
            ),
            [],
        );
    });

    it('does not ask other work for a furniture type', () => {
        assert.deepEqual(
            workProblems(
                ['marble'],
                { marble: entry('supply-only') },
                options,
                types,
            ),
            [],
        );
    });

    it('refuses an option and a furniture type that are not on the list', () => {
        assert.deepEqual(
            workProblems(
                ['marble', 'onyx'],
                {
                    marble: entry('gone'),
                    onyx: entry('furniture-only', 'Gone'),
                },
                options,
                types,
            ),
            [
                { key: 'marble', field: 'serviceId' },
                { key: 'onyx', field: 'furnitureType' },
            ],
        );
    });

    it('checks only the rows that are still there', () => {
        assert.deepEqual(workProblems([], {}, options, types), []);
    });

    it('removes the work of a stone that was unselected', () => {
        const work = {
            marble: entry('supply-only'),
            onyx: entry('supply-only'),
        };

        assert.deepEqual(keepWorkFor(['onyx'], work), { onyx: work.onyx });
    });

    it('clears the furniture type when switching to work without one', () => {
        const work = { marble: entry('furniture-only', 'Tables') };

        assert.equal(
            chooseWorkService(work, 'marble', 'supply-only', options).marble
                .furnitureType,
            '',
        );
    });

    it('keeps the furniture type when the new work needs one too', () => {
        const work = { marble: entry('furniture-only', 'Tables') };

        assert.equal(
            chooseWorkService(work, 'marble', 'furniture-other', options).marble
                .furnitureType,
            'Tables',
        );
    });

    it('leaves the other rows alone', () => {
        const work = { onyx: entry('supply-only') };

        assert.equal(
            chooseWorkService(work, 'marble', 'supply-only', options).onyx,
            work.onyx,
        );
    });
});

describe('autoProjectName', () => {
    it('is named after the first service picked', () => {
        assert.equal(
            autoProjectName(['Stone supply only', 'Full kitchen fit-out'], 100),
            'Stone supply only',
        );
    });

    it('is blank with no service picked', () => {
        assert.equal(autoProjectName([], 100), '');
    });

    it('stays inside the limit, without a trailing space', () => {
        assert.equal(autoProjectName(['Stone supply'], 6), 'Stone');
    });
});

describe('clampRestoredStep', () => {
    it('restores Review while there is a project to review', () => {
        assert.equal(clampRestoredStep(REVIEW_STEP, 1, draft()), REVIEW_STEP);
    });

    it('does not restore Review with no project', () => {
        assert.equal(clampRestoredStep(REVIEW_STEP, 0, draft()), STONES_STEP);
        assert.equal(
            clampRestoredStep(REVIEW_STEP, 0, regular),
            REQUIREMENTS_STEP,
        );
    });

    it('lands on Stones when nothing is picked', () => {
        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, draft()),
            STONES_STEP,
        );
    });

    it('lands on the work while a stone still needs it', () => {
        const unfinished = draft({ stoneIds: ['marble'] });

        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, unfinished),
            WORK_STEP,
        );
    });

    it('lets a request for a recommendation past Stones with none picked', () => {
        const advice = draft({ stoneAdvice: true });

        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, advice),
            WORK_STEP,
        );
        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, {
                ...advice,
                workIsComplete: true,
            }),
            REQUIREMENTS_STEP,
        );
    });

    it('keeps a step the draft still justifies', () => {
        assert.equal(
            clampRestoredStep(REQUIREMENTS_STEP, 0, regular),
            REQUIREMENTS_STEP,
        );
        assert.equal(clampRestoredStep(WORK_STEP, 0, regular), WORK_STEP);
        assert.equal(clampRestoredStep(STONES_STEP, 0, regular), STONES_STEP);
    });

    it('treats a step number that is not a number as the first', () => {
        assert.equal(clampRestoredStep(Number.NaN, 0, regular), STONES_STEP);
        assert.equal(clampRestoredStep(-3, 0, regular), STONES_STEP);
    });
});

describe('committing a draft', () => {
    const filled = (overrides = {}) => ({
        name: 'Kitchen counters',
        stoneAdvice: false,
        stoneIds: ['marble'],
        stoneWork: {
            marble: { serviceId: 'furniture-only', furnitureType: 'tables' },
        },
        stones: [
            {
                stoneId: 'marble',
                quantity: 4,
                edge: 'Straight Edge',
                finish: 'Honed',
            },
        ],
        deliveryMethod: DELIVERY,
        location: 'Makati',
        landmark: 'Near the church',
        areaSqm: '',
        neededBy: '2030-01-02',
        notes: '',
        attachments: [],
        ...overrides,
    });

    it('carries the work per stone onto the project, sharing nothing', () => {
        const source = filled();
        const project = buildCommittedProject(source, { id: 'p-5' });

        assert.deepEqual(project.stoneWork, source.stoneWork);
        assert.notEqual(project.stoneWork.marble, source.stoneWork.marble);
    });

    it('leaves behind the work of a stone taken off', () => {
        const project = buildCommittedProject(
            filled({
                stoneWork: {
                    marble: { serviceId: 'supply-only', furnitureType: '' },
                    onyx: { serviceId: 'supply-only', furnitureType: '' },
                },
            }),
            { id: 'p-1' },
        );

        assert.deepEqual(Object.keys(project.stoneWork), ['marble']);
    });

    it('keeps the work of the row with no stone for Help me choose', () => {
        const project = buildCommittedProject(
            filled({
                stoneAdvice: true,
                stoneIds: [],
                stones: [],
                stoneWork: {
                    [STONE_ADVICE_KEY]: {
                        serviceId: 'supply-only',
                        furnitureType: '',
                    },
                },
            }),
            { id: 'p-2' },
        );

        assert.deepEqual(Object.keys(project.stoneWork), [STONE_ADVICE_KEY]);
    });

    it('keeps the location of a delivery', () => {
        const project = buildCommittedProject(filled(), { id: 'p-6' });

        assert.equal(project.deliveryMethod, DELIVERY);
        assert.equal(project.location, 'Makati');
        assert.equal(project.landmark, 'Near the church');
    });

    it('commits no location for a pickup, and leaves the draft holding it', () => {
        const source = filled({ deliveryMethod: PICKUP });
        const project = buildCommittedProject(source, { id: 'p-7' });

        assert.equal(project.deliveryMethod, PICKUP);
        assert.equal(project.location, '');
        assert.equal(project.landmark, 'Near the church');
        assert.equal(source.location, 'Makati');
    });

    it('does not share rows with the draft it was built from', () => {
        const source = filled();
        const project = buildCommittedProject(source, { id: 'p-4' });

        project.stones[0].quantity = 99;

        assert.equal(source.stones[0].quantity, 4);
    });
});

describe('seedStoneRows', () => {
    it('makes one blank row per newly picked stone', () => {
        assert.deepEqual(seedStoneRows(['marble', 'onyx'], [], null), [
            { stoneId: 'marble', quantity: 1, edge: '', finish: '' },
            { stoneId: 'onyx', quantity: 1, edge: '', finish: '' },
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

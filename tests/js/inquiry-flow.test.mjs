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
    buildCommittedProject,
    clampRestoredStep,
    nextQuestion,
    seedStoneRows,
    serviceChoiceLabel,
    stepperPosition,
    stoneFilterQuery,
    stoneTypeOptions,
} from '../../resources/js/lib/inquiry-flow.ts';

const draft = (overrides = {}) => ({
    serviceId: '',
    stoneIds: [],
    ...overrides,
});

const regular = draft({
    serviceId: 'countertop',
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
                draft({ serviceId: 'countertop' }),
            ),
            STONES_STEP,
        );
    });

    it('lets a request for a recommendation past the stones with none picked', () => {
        assert.equal(
            clampRestoredStep(
                REQUIREMENTS_STEP,
                0,
                draft({ serviceId: 'countertop', stoneAdvice: true }),
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

describe('committing a draft', () => {
    const filled = (overrides = {}) => ({
        name: 'Kitchen counters',
        serviceTypeId: 'natural-stone-applications',
        serviceId: 'countertop',
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
        deliveryMethod: DELIVERY,
        location: 'Makati',
        landmark: 'Near the church',
        areaSqm: '',
        neededBy: '2030-01-02',
        notes: '',
        attachments: [],
        ...overrides,
    });

    it('carries the service type and the service onto the project', () => {
        const project = buildCommittedProject(filled(), { id: 'p-5' });

        assert.equal(project.serviceTypeId, 'natural-stone-applications');
        assert.equal(project.serviceId, 'countertop');
    });

    it('keeps the service of a project left to Regray to recommend a stone', () => {
        const project = buildCommittedProject(
            filled({ stoneAdvice: true, stoneIds: [], stones: [] }),
            { id: 'p-2' },
        );

        assert.equal(project.serviceId, 'countertop');
        assert.equal(project.stoneAdvice, true);
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

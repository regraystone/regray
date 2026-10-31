/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    APPLICATION_STEP,
    INQUIRY_STEP_LABELS,
    OPTIONAL_STEP_NOTE,
    REQUIREMENTS_STEP,
    REVIEW_STEP,
    SERVICES_STEP,
    STONES_STEP,
    buildCommittedProject,
    clampRestoredStep,
    commitPlan,
    draftForNextInQueue,
    isStandaloneOnly,
    isStandaloneProject,
    isStepOptional,
    nextStep,
    previousStep,
    seedStoneRows,
} from '../../resources/js/lib/inquiry-flow.ts';

const draft = (overrides = {}) => ({
    serviceIds: [],
    standaloneServiceIds: [],
    stoneIds: [],
    applicationIds: [],
    ...overrides,
});

const regular = draft({
    serviceIds: ['natural-stone-supply'],
    stoneIds: ['marble'],
    applicationIds: ['3'],
});

const visitOnly = draft({ standaloneServiceIds: ['site-ocular'] });

const mixed = draft({
    serviceIds: ['natural-stone-supply'],
    standaloneServiceIds: ['site-ocular'],
    stoneIds: ['marble'],
    applicationIds: ['3'],
});

/** Every step a reader is shown, following Continue from the first. */
const walk = () => {
    const seen = [SERVICES_STEP];

    while (seen.at(-1) !== REVIEW_STEP) {
        seen.push(nextStep(seen.at(-1)));
    }

    return seen;
};

describe('the steps', () => {
    it('are Services, Stones, Application, Requirements and Review', () => {
        assert.deepEqual(INQUIRY_STEP_LABELS, [
            'Services',
            'Stones',
            'Application',
            'Requirements',
            'Review',
        ]);
        assert.equal(INQUIRY_STEP_LABELS.includes('Category'), false);
    });

    it('are numbered 1 to 5', () => {
        assert.deepEqual(
            [
                SERVICES_STEP,
                STONES_STEP,
                APPLICATION_STEP,
                REQUIREMENTS_STEP,
                REVIEW_STEP,
            ],
            [1, 2, 3, 4, 5],
        );
    });
});

describe('step navigation never skips', () => {
    it('goes Services, Stones, Application, Requirements, Review', () => {
        assert.deepEqual(walk(), [1, 2, 3, 4, 5]);
    });

    it('goes to Stones from Services whatever was picked', () => {
        for (const picked of [regular, visitOnly, mixed]) {
            assert.equal(nextStep(SERVICES_STEP, picked), STONES_STEP);
        }
    });

    it('goes to Application from Stones, even with nothing picked', () => {
        assert.equal(nextStep(STONES_STEP, visitOnly), APPLICATION_STEP);
        assert.equal(nextStep(STONES_STEP, regular), APPLICATION_STEP);
    });

    it('goes to Requirements from Application', () => {
        assert.equal(nextStep(APPLICATION_STEP, visitOnly), REQUIREMENTS_STEP);
        assert.equal(nextStep(APPLICATION_STEP, regular), REQUIREMENTS_STEP);
    });

    it('goes to Review from Requirements and stops there', () => {
        assert.equal(nextStep(REQUIREMENTS_STEP), REVIEW_STEP);
        assert.equal(nextStep(REVIEW_STEP), REVIEW_STEP);
    });

    it('goes back one step at a time, down to Services', () => {
        assert.equal(previousStep(REQUIREMENTS_STEP), APPLICATION_STEP);
        assert.equal(previousStep(APPLICATION_STEP), STONES_STEP);
        assert.equal(previousStep(STONES_STEP), SERVICES_STEP);
        assert.equal(previousStep(SERVICES_STEP), SERVICES_STEP);
    });
});

describe('the standalone-only path', () => {
    it('is recognised', () => {
        assert.equal(isStandaloneOnly(visitOnly), true);
        assert.equal(isStandaloneOnly(regular), false);
        assert.equal(isStandaloneOnly(mixed), false);
        assert.equal(isStandaloneOnly(draft()), false);
    });

    it('makes Stones and Application optional', () => {
        assert.equal(isStepOptional(STONES_STEP, visitOnly), true);
        assert.equal(isStepOptional(APPLICATION_STEP, visitOnly), true);
    });

    it('leaves Services and Requirements as they are', () => {
        assert.equal(isStepOptional(SERVICES_STEP, visitOnly), false);
        assert.equal(isStepOptional(REQUIREMENTS_STEP, visitOnly), false);
    });

    it('keeps Stones and Application required once a service supplies stone', () => {
        assert.equal(isStepOptional(STONES_STEP, regular), false);
        assert.equal(isStepOptional(APPLICATION_STEP, regular), false);
        assert.equal(isStepOptional(STONES_STEP, mixed), false);
        assert.equal(isStepOptional(APPLICATION_STEP, mixed), false);
    });

    it('says the step is optional', () => {
        assert.equal(
            OPTIONAL_STEP_NOTE,
            'Optional — extra information for this visit',
        );
    });
});

describe('clampRestoredStep', () => {
    it('restores Review while there is a project to review', () => {
        assert.equal(clampRestoredStep(5, 1, draft()), REVIEW_STEP);
    });

    it('does not restore Review with no project', () => {
        assert.equal(clampRestoredStep(5, 0, draft()), SERVICES_STEP);
        assert.equal(clampRestoredStep(5, 0, regular), REQUIREMENTS_STEP);
    });

    it('lands on Services when no service is picked', () => {
        assert.equal(clampRestoredStep(4, 0, draft()), SERVICES_STEP);
    });

    it('lands on Stones when a service needs stone and none is picked', () => {
        const noStones = draft({ serviceIds: ['natural-stone-supply'] });

        assert.equal(clampRestoredStep(4, 0, noStones), STONES_STEP);
        assert.equal(clampRestoredStep(3, 0, noStones), STONES_STEP);
    });

    it('lands on Application when stones are picked and no application is', () => {
        const noApplication = draft({
            serviceIds: ['natural-stone-supply'],
            stoneIds: ['marble'],
        });

        assert.equal(clampRestoredStep(4, 0, noApplication), APPLICATION_STEP);
    });

    it('keeps a step the draft still justifies', () => {
        assert.equal(clampRestoredStep(4, 0, regular), REQUIREMENTS_STEP);
        assert.equal(clampRestoredStep(3, 0, regular), APPLICATION_STEP);
        assert.equal(clampRestoredStep(2, 0, regular), STONES_STEP);
        assert.equal(clampRestoredStep(1, 0, regular), SERVICES_STEP);
    });

    it('lets a standalone-only draft reach Requirements with nothing picked', () => {
        assert.equal(clampRestoredStep(4, 0, visitOnly), REQUIREMENTS_STEP);
        assert.equal(clampRestoredStep(3, 0, visitOnly), APPLICATION_STEP);
        assert.equal(clampRestoredStep(2, 0, visitOnly), STONES_STEP);
    });

    it('treats a step number that is not a number as the first', () => {
        assert.equal(clampRestoredStep(Number.NaN, 0, regular), SERVICES_STEP);
        assert.equal(clampRestoredStep(-3, 0, regular), SERVICES_STEP);
    });
});

describe('committing a draft', () => {
    const filled = (overrides = {}) => ({
        ...draft(),
        serviceOptions: {
            'natural-stone-supply': 'supply-only',
            'site-ocular': 'survey-only',
            visit: 'visit-only',
            care: 'care-only',
        },
        name: 'Kitchen counters',
        stones: [
            {
                stoneId: 'marble',
                quantity: 4,
                edge: 'Straight Edge',
                finish: 'Honed',
            },
        ],
        location: 'Makati',
        landmark: '',
        areaSqm: '',
        neededBy: '2030-01-02',
        neededByTime: '09:30',
        notes: '',
        attachments: [],
        ...overrides,
    });

    it('names the committed field `applications`, not `categories`', () => {
        const project = buildCommittedProject(
            filled({
                serviceIds: ['natural-stone-supply'],
                stoneIds: ['marble'],
                applicationIds: ['3', '7'],
            }),
            { id: 'p-1' },
        );

        assert.deepEqual(project.applications, ['3', '7']);
        assert.equal('categories' in project, false);
        assert.equal('applicationIds' in project, false);
        assert.deepEqual(project.serviceIds, ['natural-stone-supply']);
        assert.equal(project.name, 'Kitchen counters');
    });

    it("keeps only the kinds of work of the project's own services", () => {
        const project = buildCommittedProject(
            filled({
                serviceIds: ['natural-stone-supply'],
                standaloneServiceIds: ['site-ocular'],
            }),
            { id: 'p-1' },
        );

        assert.deepEqual(project.serviceOptions, {
            'natural-stone-supply': 'supply-only',
        });
    });

    it('copies stones and applications onto a standalone project as extras', () => {
        const project = buildCommittedProject(
            filled({
                standaloneServiceIds: ['site-ocular'],
                stoneIds: ['marble'],
                applicationIds: ['3'],
            }),
            {
                id: 'p-2',
                standaloneService: { id: 'site-ocular', name: 'Site Ocular' },
            },
        );

        assert.equal(project.name, 'Site Ocular');
        assert.deepEqual(project.serviceIds, ['site-ocular']);
        assert.deepEqual(project.serviceOptions, {
            'site-ocular': 'survey-only',
        });
        assert.deepEqual(project.applications, ['3']);
        assert.equal(project.stones.length, 1);
        assert.equal(project.stones[0].stoneId, 'marble');
    });

    it('commits a standalone project with no extras when none were picked', () => {
        const project = buildCommittedProject(
            filled({ standaloneServiceIds: ['site-ocular'], stones: [] }),
            {
                id: 'p-3',
                standaloneService: { id: 'site-ocular', name: 'Site Ocular' },
            },
        );

        assert.deepEqual(project.stones, []);
        assert.deepEqual(project.applications, []);
    });

    it('does not share rows with the draft it was built from', () => {
        const source = filled({ serviceIds: ['natural-stone-supply'] });
        const project = buildCommittedProject(source, { id: 'p-4' });

        project.stones[0].quantity = 99;

        assert.equal(source.stones[0].quantity, 4);
    });

    it('commits the head of a standalone-only queue and keeps the rest', () => {
        const plan = commitPlan(
            draft({ standaloneServiceIds: ['visit', 'dismantle', 'care'] }),
        );

        assert.deepEqual(plan, {
            isStandalone: true,
            head: 'visit',
            remaining: ['dismantle', 'care'],
        });
    });

    it('commits a project of stone beside a queue that waits whole', () => {
        const plan = commitPlan(mixed);

        assert.equal(plan.isStandalone, false);
        assert.deepEqual(plan.remaining, ['site-ocular']);
    });

    it('carries stones and applications to the next service in the queue', () => {
        const source = filled({
            standaloneServiceIds: ['visit', 'care'],
            stoneIds: ['marble'],
            applicationIds: ['3'],
            location: 'Makati',
        });
        const blank = filled({
            name: '',
            stones: [],
            location: '',
            neededBy: '',
        });
        const next = draftForNextInQueue(source, blank, ['care']);

        assert.deepEqual(next.standaloneServiceIds, ['care']);
        assert.deepEqual(next.serviceOptions, { care: 'care-only' });
        assert.deepEqual(next.stoneIds, ['marble']);
        assert.deepEqual(next.applicationIds, ['3']);
        assert.equal(next.stones.length, 1);
        assert.equal(next.location, '');
        assert.equal('categories' in next, false);
    });

    it('tells a standalone project from its services, not from its stones', () => {
        const standalone = new Set(['site-ocular']);

        assert.equal(isStandaloneProject(['site-ocular'], standalone), true);
        assert.equal(
            isStandaloneProject(
                ['site-ocular', 'natural-stone-supply'],
                standalone,
            ),
            false,
        );
        assert.equal(isStandaloneProject([], standalone), false);
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

/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    APPLICATION_MISSING,
    SERVICE_STEP,
    STONES_STEP,
    THICKNESS_ADVICE,
    asksApplication,
    findProjectService,
    furthestQuestion,
    questionForStone,
    questionProblem,
    serviceLabel,
    stoneServiceId,
    withApplication,
} from '../../resources/js/lib/inquiry-flow.ts';

const field = (slug, scope, extra = {}) => ({
    slug,
    label: slug,
    scope,
    answerType: 'text',
    required: true,
    options: [],
    suggestions: [],
    allowsCustom: false,
    ...extra,
});

const service = (id, extra = {}) => ({
    id,
    name: id,
    description: '',
    features: [],
    stoneTypes: [],
    requiresStones: true,
    fulfillment: 'installed',
    deliveryNote: null,
    scheduleNote: null,
    leadDays: 0,
    fields: [],
    hasApplications: false,
    applications: [],
    ...extra,
});

const flooring = service('flooring', {
    name: 'Flooring',
    stoneTypes: ['marble'],
    fields: [field('area_sqm', 'project'), field('finish', 'stone')],
});
const supplyAndInstall = service('slab-supply-and-installation', {
    name: 'Slab Supply and Installation',
    stoneTypes: ['marble', 'granite'],
    fields: [field('thickness', 'stone'), field('notes', 'project')],
    hasApplications: true,
    applications: [flooring],
});
const supplyOnly = service('slab-supply-only', { name: 'Slab Supply Only' });
const serviceTypes = [
    {
        id: 'natural-stone-applications',
        name: 'Natural Stone',
        services: [supplyOnly, supplyAndInstall],
    },
];

describe('a service with applications', () => {
    it('asks one only where the service has applications', () => {
        assert.equal(asksApplication(supplyAndInstall), true);
        assert.equal(asksApplication(supplyOnly), false);
        assert.equal(asksApplication(undefined), false);
    });

    it("asks each stone the service's questions and the project the application's", () => {
        const merged = withApplication(supplyAndInstall, 'flooring');

        assert.deepEqual(
            merged.fields.map(({ slug }) => slug),
            ['thickness', 'area_sqm'],
        );
        assert.deepEqual(merged.stoneTypes, ['marble']);
        assert.equal(merged.id, 'slab-supply-and-installation');
    });

    it('is the service alone with no application, or one it does not offer', () => {
        assert.equal(withApplication(supplyAndInstall, ''), supplyAndInstall);
        assert.equal(
            withApplication(supplyAndInstall, 'staircase'),
            supplyAndInstall,
        );
        assert.equal(withApplication(supplyOnly, 'flooring'), supplyOnly);
    });

    it('finds the project service through the catalogue', () => {
        assert.deepEqual(
            findProjectService(
                serviceTypes,
                'slab-supply-and-installation',
                'flooring',
            ).fields.map(({ slug }) => slug),
            ['thickness', 'area_sqm'],
        );
        assert.equal(
            findProjectService(serviceTypes, 'slab-supply-only', ''),
            supplyOnly,
        );
    });

    it("lists the application's stones, and is named with it", () => {
        assert.equal(
            stoneServiceId('slab-supply-and-installation', 'flooring'),
            'flooring',
        );
        assert.equal(
            stoneServiceId('slab-supply-only', ''),
            'slab-supply-only',
        );
        assert.equal(
            serviceLabel(supplyAndInstall, 'flooring'),
            'Slab Supply and Installation · Flooring',
        );
        assert.equal(
            serviceLabel(supplyAndInstall, ''),
            'Slab Supply and Installation',
        );
        assert.equal(serviceLabel(undefined, ''), '');
    });

    it('keeps the reader on the service question until the application is picked', () => {
        const draft = {
            brands: ['laminam'],
            brandNoPreference: false,
            serviceTypeId: 'natural-stone-applications',
            serviceId: 'slab-supply-and-installation',
            stoneIds: [],
            stoneAdvice: false,
            requiresStones: true,
        };

        assert.deepEqual(
            questionProblem(
                SERVICE_STEP,
                { ...draft, applicationMissing: true },
                new Set(['slab-supply-and-installation']),
            ),
            { field: 'applicationId', message: APPLICATION_MISSING },
        );
        assert.equal(
            furthestQuestion({ ...draft, applicationMissing: true }),
            SERVICE_STEP,
        );
        assert.equal(
            furthestQuestion({ ...draft, applicationMissing: false }) >=
                STONES_STEP,
            true,
        );
    });
});

describe('a select-only thickness', () => {
    const thickness = field('thickness', 'stone', {
        answerType: 'measurement',
        measurement: 'thickness',
    });

    it("offers Regray's advice for a stone with no thickness listed", () => {
        const asked = questionForStone(thickness, {
            sizes: [],
            thicknesses: [],
        });

        assert.deepEqual(
            asked.suggestions.map(({ value }) => value),
            [THICKNESS_ADVICE],
        );
    });
});

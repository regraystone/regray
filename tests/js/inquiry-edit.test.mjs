/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildInquiryFormData,
    deadlineLocks,
    reviewActions,
    toEditFormProjects,
    withHeldChoices,
} from '../../resources/js/lib/inquiry-flow.ts';

/** The address an inquiry keeps, as the server sends it. */
const MAKATI = {
    regionCode: '1300000000',
    regionName: 'National Capital Region',
    provinceCode: null,
    provinceName: null,
    cityCode: '1380300000',
    cityName: 'City of Makati',
    barangayCode: '1380300001',
    barangayName: 'Bel-Air',
    postalCode: '1209',
    lat: 14.56,
    lng: 121.03,
    line: 'Bel-Air, City of Makati, National Capital Region 1209',
};

/** The page's reader (addressFromPayload), reduced to what these tests look at. */
const toPicked = (address) => ({
    region: { code: address.regionCode, name: address.regionName },
    province: null,
    noProvince: true,
    city: { code: address.cityCode, name: address.cityName },
    barangay: { code: address.barangayCode, name: address.barangayName },
    postalCode: address.postalCode ?? '',
    lat: address.lat,
    lng: address.lng,
});

/** One project as InquiryEditResource sends it. */
const payload = (overrides = {}) => ({
    id: 42,
    name: 'Kitchen counters',
    serviceTypeId: 'natural-stone-applications',
    serviceId: 'slab-supply-and-installation',
    applicationId: 'kitchen-islands',
    heldServiceType: null,
    heldStones: [],
    stoneAdviceRequested: false,
    stones: [
        {
            stoneId: 'marble',
            quantity: 4,
            slabSize: null,
            thickness: null,
            edge: 'Half Bevel',
            finish: 'Honed',
        },
    ],
    answers: { area_sqm: 12.5, waterfall_effect: true },
    brands: ['laminam'],
    brandNoPreference: false,
    deliveryMethod: 'delivery',
    address: MAKATI,
    notes: null,
    neededBy: '2030-01-02',
    deadlineLocked: false,
    deadlineLockedReason: null,
    attachments: [
        {
            id: 7,
            name: 'plan.pdf',
            sizeBytes: 2048,
            mimeType: 'application/pdf',
            status: 'saved',
        },
    ],
    ...overrides,
});

describe('toEditFormProjects', () => {
    it('fills the form the way a committed project is held, with the real id', () => {
        assert.deepEqual(toEditFormProjects([payload()], toPicked), [
            {
                id: '42',
                name: 'Kitchen counters',
                serviceTypeId: 'natural-stone-applications',
                serviceId: 'slab-supply-and-installation',
                applicationId: 'kitchen-islands',
                stoneAdvice: false,
                stones: [
                    {
                        stoneId: 'marble',
                        quantity: 4,
                        slabSize: '',
                        thickness: '',
                        edge: 'Half Bevel',
                        finish: 'Honed',
                    },
                ],
                answers: { area_sqm: '12.5', waterfall_effect: 'yes' },
                brands: ['laminam'],
                brandNoPreference: false,
                deliveryMethod: 'delivery',
                // Held: a client keeps it, whatever their book holds now.
                addressId: 'held',
                address: toPicked(MAKATI),
                neededBy: '2030-01-02',
                notes: '',
                attachments: [
                    {
                        id: 'saved-7',
                        status: 'saved',
                        fileId: 7,
                        name: 'plan.pdf',
                        sizeBytes: 2048,
                        mimeType: 'application/pdf',
                    },
                ],
            },
        ]);
    });

    it('keeps the service of a project with no stone chosen', () => {
        const [project] = toEditFormProjects(
            [payload({ stoneAdviceRequested: true, stones: [] })],
            toPicked,
        );

        assert.equal(project.serviceId, 'slab-supply-and-installation');
        assert.equal(project.applicationId, 'kitchen-islands');
        assert.equal(project.stoneAdvice, true);
    });

    it('reads a project with no service as blanks', () => {
        const [project] = toEditFormProjects(
            [payload({ serviceTypeId: null, serviceId: null })],
            toPicked,
        );

        assert.equal(project.serviceTypeId, '');
        assert.equal(project.serviceId, '');
    });

    it('reads a pickup, which holds no address, as blanks', () => {
        const [project] = toEditFormProjects([
            payload({ deliveryMethod: 'pickup', address: null }),
        ]);

        assert.equal(project.deliveryMethod, 'pickup');
        assert.equal(project.addressId, '');
        assert.equal(project.address, null);
    });

    it('reads an installed service, which asks no method, as blank', () => {
        const [project] = toEditFormProjects(
            [payload({ deliveryMethod: null })],
            toPicked,
        );

        assert.equal(project.deliveryMethod, '');
        assert.equal(project.addressId, 'held');
    });

    it('reads the answers as the form holds them: a missing one blank, no as no', () => {
        const [project] = toEditFormProjects(
            [
                payload({
                    answers: { area_sqm: null, waterfall_effect: false },
                    stones: [
                        {
                            stoneId: 'marble',
                            quantity: 1,
                            slabSize: null,
                            thickness: null,
                            edge: null,
                            finish: null,
                        },
                    ],
                }),
            ],
            toPicked,
        );

        assert.deepEqual(project.answers, {
            area_sqm: '',
            waterfall_effect: 'no',
        });
        assert.deepEqual(project.stones[0], {
            stoneId: 'marble',
            quantity: 1,
            slabSize: '',
            thickness: '',
            edge: '',
            finish: '',
        });
    });

    it('reads a slab row back with its size and thickness as the form holds them', () => {
        const [project] = toEditFormProjects(
            [
                payload({
                    serviceId: 'slab-supply-only',
                    stones: [
                        {
                            stoneId: 'marble',
                            quantity: 3,
                            slabSize: '160 x 320 cm',
                            thickness: '0.75 in',
                            edge: 'Half Bevel',
                            finish: 'Honed',
                        },
                    ],
                }),
            ],
            toPicked,
        );

        assert.equal(project.stones[0].slabSize, '160 x 320 cm');
        assert.equal(project.stones[0].thickness, '0.75 in');
    });
});

describe('deadlineLocks', () => {
    it('names only the projects whose visit can no longer move, by form id', () => {
        assert.deepEqual(
            deadlineLocks([
                payload(),
                payload({
                    id: 43,
                    deadlineLocked: true,
                    deadlineLockedReason: 'Already confirmed.',
                }),
            ]),
            { 43: 'Already confirmed.' },
        );
    });
});

describe('reviewActions', () => {
    it('offers no Add or Remove when editing, and saves changes', () => {
        assert.deepEqual(reviewActions('edit'), {
            submitLabel: 'Save changes',
            footerNote: 'Nothing is saved until you save changes',
        });
    });

    it('is unchanged for a new inquiry', () => {
        assert.deepEqual(reviewActions('create'), {
            submitLabel: 'Send my request',
            footerNote: 'Nothing is sent until you press Send my request',
        });
    });
});

const serviceFor = () => ({ requiresStones: true, fields: [] });

describe('buildInquiryFormData when editing', () => {
    it('sends the project id, the stored files it keeps, and only new uploads as attachments', () => {
        const [project] = toEditFormProjects([payload()], toPicked);
        const upload = new File(['x'], 'site.pdf', { type: 'application/pdf' });

        const body = buildInquiryFormData(
            {
                projects: [
                    {
                        ...project,
                        attachments: [
                            ...project.attachments,
                            { status: 'done', file: upload },
                        ],
                    },
                ],
            },
            { withProjectIds: true, serviceFor },
        );

        assert.equal(body.get('projects[0][id]'), '42');
        assert.equal(body.get('projects[0][keepAddress]'), '1');
        assert.equal(body.get('projects[0][addressId]'), null);
        assert.equal(body.get('projects[0][keepFileIds][0]'), '7');
        assert.equal(body.get('projects[0][attachments][0]').name, 'site.pdf');
        assert.equal(body.get('projects[0][attachments][1]'), null);
    });

    it('never sends a project id for a new inquiry', () => {
        const [project] = toEditFormProjects([payload()], toPicked);

        const body = buildInquiryFormData(
            { projects: [project] },
            { serviceFor },
        );

        assert.equal(body.get('projects[0][id]'), null);
    });
});

describe('withHeldChoices', () => {
    const field = (slug, answerType, options = [], suggestions = []) => ({
        slug,
        label: slug,
        scope: 'project',
        answerType,
        required: false,
        allowsCustom: false,
        options,
        suggestions,
    });
    const types = [
        {
            id: 'slabs',
            name: 'Slabs',
            services: [
                {
                    id: 'slab-supply-only',
                    fields: [
                        field('finish', 'choice', [
                            { value: 'Polished', label: 'Polished' },
                        ]),
                        field(
                            'area',
                            'number',
                            [],
                            [{ value: '10', label: '10' }],
                        ),
                        field('waterfall', 'boolean'),
                    ],
                },
                { id: 'flooring', fields: [field('finish', 'choice', [])] },
            ],
        },
    ];

    it("lists an edited inquiry's own answers on its service's questions, beside what is offered", () => {
        const [type] = withHeldChoices(types, [
            {
                serviceId: 'slab-supply-only',
                heldChoices: {
                    finish: [
                        { value: 'Polished', label: 'Polished' },
                        { value: 'Honed', label: 'Honed', isAdvisory: false },
                    ],
                    area: [{ value: '12.5', label: '12.5' }],
                },
            },
        ]);
        const [slab, flooring] = type.services;

        assert.deepEqual(
            slab.fields[0].options.map(({ value }) => value),
            ['Polished', 'Honed'],
        );
        assert.deepEqual(
            slab.fields[1].suggestions.map(({ value }) => value),
            ['10', '12.5'],
        );
        assert.equal(slab.fields[2], types[0].services[0].fields[2]);
        assert.equal(flooring, types[0].services[1]);
    });

    it('changes nothing for a new inquiry', () => {
        assert.equal(withHeldChoices(types, [])[0], types[0]);
    });
});

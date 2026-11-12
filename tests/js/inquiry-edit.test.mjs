/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    buildInquiryFormData,
    deadlineLocks,
    reviewActions,
    toEditFormProjects,
} from '../../resources/js/lib/inquiry-flow.ts';

/** One project as InquiryEditResource sends it. */
const payload = (overrides = {}) => ({
    id: 42,
    name: 'Kitchen counters',
    serviceTypeId: 'natural-stone-applications',
    serviceId: 'kitchen-islands',
    heldServiceType: null,
    heldStones: [],
    stoneAdviceRequested: false,
    stones: [
        {
            stoneId: 'marble',
            quantity: 4,
            slabSize: null,
            edge: 'Half Bevel',
            finish: 'Honed',
        },
    ],
    answers: { area_sqm: 12.5, waterfall_effect: true },
    deliveryMethod: 'delivery',
    location: 'Makati',
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
        assert.deepEqual(toEditFormProjects([payload()]), [
            {
                id: '42',
                name: 'Kitchen counters',
                serviceTypeId: 'natural-stone-applications',
                serviceId: 'kitchen-islands',
                stoneAdvice: false,
                stones: [
                    {
                        stoneId: 'marble',
                        quantity: 4,
                        slabSize: '',
                        edge: 'Half Bevel',
                        finish: 'Honed',
                    },
                ],
                answers: { area_sqm: '12.5', waterfall_effect: 'yes' },
                deliveryMethod: 'delivery',
                location: 'Makati',
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
        const [project] = toEditFormProjects([
            payload({ stoneAdviceRequested: true, stones: [] }),
        ]);

        assert.equal(project.serviceId, 'kitchen-islands');
        assert.equal(project.stoneAdvice, true);
    });

    it('reads a project with no service as blanks', () => {
        const [project] = toEditFormProjects([
            payload({ serviceTypeId: null, serviceId: null }),
        ]);

        assert.equal(project.serviceTypeId, '');
        assert.equal(project.serviceId, '');
    });

    it('reads a pickup, which holds no location, as blanks', () => {
        const [project] = toEditFormProjects([
            payload({ deliveryMethod: 'pickup', location: null }),
        ]);

        assert.equal(project.deliveryMethod, 'pickup');
        assert.equal(project.location, '');
    });

    it('reads the answers as the form holds them: a missing one blank, no as no', () => {
        const [project] = toEditFormProjects([
            payload({
                answers: { area_sqm: null, waterfall_effect: false },
                stones: [
                    {
                        stoneId: 'marble',
                        quantity: 1,
                        slabSize: null,
                        edge: null,
                        finish: null,
                    },
                ],
            }),
        ]);

        assert.deepEqual(project.answers, {
            area_sqm: '',
            waterfall_effect: 'no',
        });
        assert.deepEqual(project.stones[0], {
            stoneId: 'marble',
            quantity: 1,
            slabSize: '',
            edge: '',
            finish: '',
        });
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
        const [project] = toEditFormProjects([payload()]);
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
        assert.equal(body.get('projects[0][keepFileIds][0]'), '7');
        assert.equal(body.get('projects[0][attachments][0]').name, 'site.pdf');
        assert.equal(body.get('projects[0][attachments][1]'), null);
    });

    it('never sends a project id for a new inquiry', () => {
        const [project] = toEditFormProjects([payload()]);

        const body = buildInquiryFormData(
            { projects: [project] },
            { serviceFor },
        );

        assert.equal(body.get('projects[0][id]'), null);
    });
});

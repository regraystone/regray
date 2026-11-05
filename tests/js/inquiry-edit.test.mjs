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
    serviceSlugs: ['natural-stone-supply'],
    serviceOptionSlugs: { 'natural-stone-supply': 'supply-only' },
    applicationSlugs: ['countertops-backsplash'],
    stoneAdviceRequested: false,
    stones: [
        { stoneId: 'marble', quantity: 4, edge: 'Half Bevel', finish: 'Honed' },
    ],
    location: 'Makati',
    landmark: null,
    areaSqm: 12.5,
    notes: null,
    neededBy: '2030-01-02',
    neededByTime: null,
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
                serviceIds: ['natural-stone-supply'],
                serviceOptions: { 'natural-stone-supply': 'supply-only' },
                applications: ['countertops-backsplash'],
                stoneAdvice: false,
                stones: [
                    {
                        stoneId: 'marble',
                        quantity: 4,
                        edge: 'Half Bevel',
                        finish: 'Honed',
                    },
                ],
                location: 'Makati',
                landmark: '',
                areaSqm: '12.5',
                neededBy: '2030-01-02',
                neededByTime: '',
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

    it('reads an inquiry sent before the kinds of work as none picked', () => {
        const [project] = toEditFormProjects([
            payload({ serviceOptionSlugs: [] }),
        ]);

        assert.deepEqual(project.serviceOptions, {});
    });

    it('keeps a visit time and turns a missing area into a blank', () => {
        const [project] = toEditFormProjects([
            payload({ areaSqm: null, neededByTime: '14:15' }),
        ]);

        assert.equal(project.areaSqm, '');
        assert.equal(project.neededByTime, '14:15');
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
            { withProjectIds: true },
        );

        assert.equal(body.get('projects[0][id]'), '42');
        assert.equal(body.get('projects[0][keepFileIds][0]'), '7');
        assert.equal(body.get('projects[0][attachments][0]').name, 'site.pdf');
        assert.equal(body.get('projects[0][attachments][1]'), null);
    });

    it('never sends a project id for a new inquiry', () => {
        const [project] = toEditFormProjects([payload()]);

        const body = buildInquiryFormData({ projects: [project] });

        assert.equal(body.get('projects[0][id]'), null);
    });
});

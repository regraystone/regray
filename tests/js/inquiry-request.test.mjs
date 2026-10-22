/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    REQUIREMENTS_STEP,
    buildInquiryFormData,
    mapInquiryErrors,
    readInquiryFlash,
} from '../../resources/js/lib/inquiry-flow.ts';

const file = (name, contents = 'x') =>
    new File([contents], name, { type: 'application/pdf' });

const project = (overrides = {}) => ({
    name: 'Kitchen counters',
    serviceIds: ['natural-stone-supply', 'custom-stone-fabrication'],
    applications: ['3', '7'],
    stones: [
        {
            stoneId: 'marble',
            quantity: 4,
            edge: 'Straight Edge',
            finish: 'Honed',
        },
        {
            stoneId: 'onyx',
            quantity: 2,
            edge: 'Half Bevel',
            finish: 'Polished',
        },
    ],
    location: 'Makati',
    landmark: 'Near the church',
    areaSqm: '12.5',
    neededBy: '2030-01-02',
    neededByTime: '09:30',
    notes: 'Ground floor.',
    attachments: [],
    ...overrides,
});

const keys = (body) => [...body.keys()];

describe('buildInquiryFormData', () => {
    it('names every field the way the form request validates it', () => {
        const body = buildInquiryFormData({ projects: [project()] });

        assert.deepEqual(Object.fromEntries(body.entries()), {
            'projects[0][name]': 'Kitchen counters',
            'projects[0][serviceIds][0]': 'natural-stone-supply',
            'projects[0][serviceIds][1]': 'custom-stone-fabrication',
            'projects[0][applicationIds][0]': '3',
            'projects[0][applicationIds][1]': '7',
            'projects[0][stones][0][stoneId]': 'marble',
            'projects[0][stones][0][quantity]': '4',
            'projects[0][stones][0][edge]': 'Straight Edge',
            'projects[0][stones][0][finish]': 'Honed',
            'projects[0][stones][1][stoneId]': 'onyx',
            'projects[0][stones][1][quantity]': '2',
            'projects[0][stones][1][edge]': 'Half Bevel',
            'projects[0][stones][1][finish]': 'Polished',
            'projects[0][location]': 'Makati',
            'projects[0][landmark]': 'Near the church',
            'projects[0][areaSqm]': '12.5',
            'projects[0][neededBy]': '2030-01-02',
            'projects[0][neededByTime]': '09:30',
            'projects[0][notes]': 'Ground floor.',
        });
    });

    it('sends applications as `applicationIds`, never as categories', () => {
        const names = keys(buildInquiryFormData({ projects: [project()] }));

        assert.ok(names.some((name) => name.includes('[applicationIds]')));
        assert.equal(
            names.some((name) => /categor/i.test(name)),
            false,
        );
    });

    it('appends each file as projects[i][attachments][j], in the order attached', () => {
        const first = file('plan.pdf', 'a');
        const second = file('elevation.pdf', 'bb');
        const third = file('site.pdf', 'ccc');

        const body = buildInquiryFormData({
            projects: [
                project({ attachments: [{ file: first }, { file: second }] }),
                project({ attachments: [{ file: third }] }),
            ],
        });

        const files = [...body.entries()].filter(
            ([, value]) => value instanceof File,
        );

        assert.deepEqual(
            files.map(([name]) => name),
            [
                'projects[0][attachments][0]',
                'projects[0][attachments][1]',
                'projects[1][attachments][0]',
            ],
        );
        assert.deepEqual(
            files.map(([, value]) => value.name),
            ['plan.pdf', 'elevation.pdf', 'site.pdf'],
        );
    });

    it('numbers each project by its position', () => {
        const body = buildInquiryFormData({
            projects: [project({ name: 'One' }), project({ name: 'Two' })],
        });

        assert.equal(body.get('projects[0][name]'), 'One');
        assert.equal(body.get('projects[1][name]'), 'Two');
    });

    it('sends empty answers as empty strings, for the backend to read as none', () => {
        const body = buildInquiryFormData({
            projects: [
                project({
                    stones: [],
                    applications: [],
                    landmark: '',
                    areaSqm: '',
                    neededByTime: '',
                    notes: '',
                }),
            ],
        });

        assert.equal(body.get('projects[0][landmark]'), '');
        assert.equal(body.get('projects[0][areaSqm]'), '');
        assert.equal(body.get('projects[0][neededByTime]'), '');
        assert.equal(
            keys(body).some((name) => name.includes('[stones]')),
            false,
        );
        assert.equal(
            keys(body).some((name) => name.includes('[applicationIds]')),
            false,
        );
    });

    it('carries the extras of a standalone project', () => {
        const body = buildInquiryFormData({
            projects: [
                project({
                    serviceIds: ['stone-relocation-dismantling-reinstallation'],
                    stones: [
                        {
                            stoneId: 'marble',
                            quantity: 1,
                            edge: 'Not sure yet, advise me',
                            finish: 'Not sure yet, advise me',
                        },
                    ],
                    applications: ['3'],
                }),
            ],
        });

        assert.equal(body.get('projects[0][stones][0][stoneId]'), 'marble');
        assert.equal(body.get('projects[0][applicationIds][0]'), '3');
    });
});

describe('mapInquiryErrors', () => {
    const projects = [{ id: 'a' }, { id: 'b' }, { id: 'c' }];

    it('puts a complaint on the project whose index is in the key', () => {
        const { projectMessages } = mapInquiryErrors(
            { 'projects.1.stones.0.quantity': 'Enter at least 1 piece.' },
            projects,
        );

        assert.deepEqual(projectMessages, { b: 'Enter at least 1 piece.' });
    });

    it('reads a nested stone path onto its project', () => {
        const { projectMessages } = mapInquiryErrors(
            { 'projects.0.stones.1.quantity': 'Enter 9999 pieces or fewer.' },
            projects,
        );

        assert.deepEqual(projectMessages, {
            a: 'Enter 9999 pieces or fewer.',
        });
    });

    it('keeps the first complaint about a project', () => {
        const { projectMessages } = mapInquiryErrors(
            {
                'projects.0.name': 'Give this project a name.',
                'projects.0.location': 'Choose where the site is.',
            },
            projects,
        );

        assert.deepEqual(projectMessages, { a: 'Give this project a name.' });
    });

    it('keeps one message per project across several projects', () => {
        const { projectMessages } = mapInquiryErrors(
            {
                'projects.0.name': 'Give this project a name.',
                'projects.2.notes':
                    'Keep the notes to 1000 characters or fewer.',
            },
            projects,
        );

        assert.deepEqual(projectMessages, {
            a: 'Give this project a name.',
            c: 'Keep the notes to 1000 characters or fewer.',
        });
    });

    it('reads an attachment path onto its project', () => {
        const { projectMessages } = mapInquiryErrors(
            {
                'projects.2.attachments.0':
                    'Attachments must be 10 MB or smaller.',
            },
            projects,
        );

        assert.deepEqual(projectMessages, {
            c: 'Attachments must be 10 MB or smaller.',
        });
    });

    it('puts a complaint about the list on the request, not on a project', () => {
        const { projectMessages, sectionMessages } = mapInquiryErrors(
            { projects: 'An inquiry can hold up to 10 projects.' },
            projects,
        );

        assert.deepEqual(projectMessages, {});
        assert.deepEqual(sectionMessages, {
            [REQUIREMENTS_STEP]: 'An inquiry can hold up to 10 projects.',
        });
    });

    it('drops a complaint about a project that is no longer there', () => {
        const { projectMessages, alertErrors } = mapInquiryErrors(
            { 'projects.7.name': 'Give this project a name.' },
            projects,
        );

        assert.deepEqual(projectMessages, {});
        assert.deepEqual(alertErrors, ['Give this project a name.']);
    });

    it('says every distinct complaint once in the alert', () => {
        const { alertErrors } = mapInquiryErrors(
            {
                'projects.0.name': 'Give this project a name.',
                'projects.1.name': 'Give this project a name.',
                'projects.1.location': 'Choose where the site is.',
            },
            projects,
        );

        assert.deepEqual(alertErrors, [
            'Give this project a name.',
            'Choose where the site is.',
        ]);
    });

    it('maps nothing from no errors', () => {
        assert.deepEqual(mapInquiryErrors({}, projects), {
            projectMessages: {},
            sectionMessages: {},
            alertErrors: [],
        });
    });
});

describe('readInquiryFlash', () => {
    it('reads a success with its title and reference', () => {
        assert.deepEqual(
            readInquiryFlash({
                inquiry: {
                    status: 'success',
                    title: 'Kitchen counters',
                    reference: 'INQ-0012',
                },
            }),
            {
                status: 'success',
                title: 'Kitchen counters',
                reference: 'INQ-0012',
            },
        );
    });

    it('reads an error with its message', () => {
        assert.deepEqual(
            readInquiryFlash({
                inquiry: { status: 'error', message: 'Try again.' },
            }),
            { status: 'error', message: 'Try again.' },
        );
    });

    it('reads an error with no message', () => {
        assert.deepEqual(readInquiryFlash({ inquiry: { status: 'error' } }), {
            status: 'error',
        });
    });

    it('reads nothing from a page with no inquiry flash', () => {
        assert.equal(readInquiryFlash({}), null);
        assert.equal(readInquiryFlash(undefined), null);
        assert.equal(readInquiryFlash({ inquiry: 'nope' }), null);
        assert.equal(readInquiryFlash({ inquiry: { status: 'other' } }), null);
    });
});

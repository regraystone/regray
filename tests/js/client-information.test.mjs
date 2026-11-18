/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    CLIENT_INFORMATION_FIELDS,
    appendClientInformationFormData,
    clientInformationDiffers,
    clientInformationForStorage,
    clientInformationFormValues,
    clientInformationSchema,
    clientInformationZodErrors,
    mapClientInformationErrors,
    projectAnswersForNewProject,
} from '../../resources/js/lib/client-information.ts';
import {
    buildInquiryFormData,
    mapInquiryErrors,
} from '../../resources/js/lib/inquiry-flow.ts';

const values = {
    deliveryMethod: 'delivery',
    location: 'Makati',
};
const locations = ['Makati', 'Taguig'];
const formValues = () => clientInformationFormValues(values);
const parsed = (overrides = {}) =>
    clientInformationSchema(locations).safeParse({
        ...formValues(),
        ...overrides,
    });

describe('the two Review answers', () => {
    it('are only how the client gets it and, for a delivery, where', () => {
        assert.deepEqual(CLIENT_INFORMATION_FIELDS, [
            'deliveryMethod',
            'location',
        ]);
    });

    it('hold an unanswered site as an empty string', () => {
        assert.deepEqual(
            clientInformationFormValues({
                deliveryMethod: 'delivery',
                location: null,
            }),
            { deliveryMethod: 'delivery', location: '' },
        );
    });

    it('normalize to the saved shape, where a pickup holds no site', () => {
        assert.deepEqual(
            clientInformationForStorage({
                deliveryMethod: ' delivery ',
                location: ' Makati ',
            }),
            values,
        );
        assert.deepEqual(
            clientInformationForStorage({
                deliveryMethod: 'pickup',
                location: 'Makati',
            }),
            { deliveryMethod: 'pickup', location: null },
        );
        assert.deepEqual(
            clientInformationForStorage({
                deliveryMethod: 'delivery',
                location: '   ',
            }),
            { deliveryMethod: 'delivery', location: null },
        );
    });

    it('treats casing, space and an absent pickup site as the same answer', () => {
        assert.equal(
            clientInformationDiffers(values, {
                ...formValues(),
                location: ' Makati ',
            }),
            false,
        );
        assert.equal(
            clientInformationDiffers(
                { deliveryMethod: 'delivery', location: null },
                { deliveryMethod: 'delivery', location: '' },
            ),
            false,
        );
        assert.equal(
            clientInformationDiffers(
                { deliveryMethod: 'pickup', location: 'Makati' },
                { deliveryMethod: 'pickup', location: '' },
            ),
            false,
        );

        for (const field of CLIENT_INFORMATION_FIELDS) {
            assert.equal(
                clientInformationDiffers(values, {
                    ...formValues(),
                    [field]: 'Something else',
                }),
                true,
                `changing ${field} should offer the save choice`,
            );
        }
    });
});

describe('the starting answer for a new project', () => {
    it('comes from the saved row when there is no draft', () => {
        assert.deepEqual(
            projectAnswersForNewProject(values, locations),
            values,
        );
        assert.deepEqual(
            projectAnswersForNewProject(values, locations, null, null),
            values,
        );
    });

    it('lets a restored draft win, including a deliberately blank site', () => {
        assert.deepEqual(
            projectAnswersForNewProject(
                values,
                locations,
                'delivery',
                'Taguig',
            ),
            { deliveryMethod: 'delivery', location: 'Taguig' },
        );
        assert.deepEqual(
            projectAnswersForNewProject(values, locations, 'delivery', ''),
            { deliveryMethod: 'delivery', location: '' },
        );
        assert.deepEqual(
            projectAnswersForNewProject(values, locations, 'pickup', 'Taguig'),
            { deliveryMethod: 'pickup', location: '' },
        );
    });

    it('falls through silently when the draft or saved row is stale', () => {
        assert.deepEqual(
            projectAnswersForNewProject(
                values,
                locations,
                'delivery',
                'Nowhere',
            ),
            values,
        );
        assert.deepEqual(
            projectAnswersForNewProject(
                { deliveryMethod: 'delivery', location: 'Nowhere' },
                locations,
            ),
            { deliveryMethod: 'delivery', location: '' },
        );
        assert.deepEqual(
            projectAnswersForNewProject(
                { deliveryMethod: 'courier', location: 'Makati' },
                locations,
            ),
            { deliveryMethod: 'delivery', location: 'Makati' },
        );
    });
});

describe('the client information schema', () => {
    it('accepts a delivery only at a listed site', () => {
        assert.equal(parsed().success, true);
        assert.equal(parsed({ location: ' Taguig ' }).success, true);
        assert.equal(parsed({ location: '' }).success, false);
        assert.equal(parsed({ location: 'Nowhere' }).success, false);
        assert.equal(parsed({ deliveryMethod: 'courier' }).success, false);
    });

    it('accepts a pickup with no site, even if one was typed before', () => {
        assert.equal(
            parsed({ deliveryMethod: 'pickup', location: '' }).success,
            true,
        );
        assert.equal(
            parsed({ deliveryMethod: 'pickup', location: 'Nowhere' }).success,
            true,
        );
    });

    it('keeps the first complaint per field, in the Review block only', () => {
        assert.deepEqual(
            clientInformationZodErrors([
                { path: ['location'], message: 'first' },
                { path: ['location'], message: 'second' },
                { path: ['projects', 0, 'name'], message: 'not ours' },
            ]),
            { location: 'first' },
        );
    });

    it('reads the server’s nested complaint keys back onto the right field', () => {
        assert.deepEqual(
            mapClientInformationErrors({
                'clientInformation.location': 'Choose where the site is.',
                'clientInformation.deliveryMethod':
                    'Choose how you would like to get it.',
                'projects.0.name': 'Give this project a name.',
            }),
            {
                deliveryMethod: 'Choose how you would like to get it.',
                location: 'Choose where the site is.',
            },
        );
    });
});

describe('the request body', () => {
    it('appends the two answers and the save choice, normalized', () => {
        const body = new FormData();

        appendClientInformationFormData(
            body,
            { deliveryMethod: ' delivery ', location: ' Makati ' },
            true,
        );

        assert.equal(body.get('clientInformation[deliveryMethod]'), 'delivery');
        assert.equal(body.get('clientInformation[location]'), 'Makati');
        assert.equal(body.get('clientInformation[saveToProfile]'), '1');
        assert.equal([...body.keys()].length, 3);
    });

    it('clears the site for a pickup and sends an unchecked choice as 0', () => {
        const body = new FormData();

        appendClientInformationFormData(
            body,
            { deliveryMethod: 'pickup', location: 'Makati' },
            false,
        );

        assert.equal(body.get('clientInformation[deliveryMethod]'), 'pickup');
        assert.equal(body.get('clientInformation[location]'), '');
        assert.equal(body.get('clientInformation[saveToProfile]'), '0');
    });

    it('travels with a client inquiry only when one was given', () => {
        const without = buildInquiryFormData(
            { projects: [] },
            { serviceFor: () => undefined },
        );
        const withInformation = buildInquiryFormData(
            { projects: [] },
            {
                serviceFor: () => undefined,
                clientInformation: { ...formValues(), saveToProfile: false },
            },
        );

        assert.equal(
            [...without.keys()].some((key) =>
                key.startsWith('clientInformation['),
            ),
            false,
        );
        assert.equal(
            withInformation.get('clientInformation[deliveryMethod]'),
            'delivery',
        );
        assert.equal(
            withInformation.get('clientInformation[location]'),
            'Makati',
        );
    });

    it('maps the server’s client complaints away from the projects', () => {
        const { projectMessages, clientInformationMessages, alertErrors } =
            mapInquiryErrors(
                {
                    'clientInformation.location': 'Choose where the site is.',
                    'projects.0.name': 'Give this project a name.',
                },
                [{ id: 'a' }],
            );

        assert.deepEqual(projectMessages, { a: 'Give this project a name.' });
        assert.deepEqual(clientInformationMessages, {
            location: 'Choose where the site is.',
        });
        assert.deepEqual(alertErrors, [
            'Choose where the site is.',
            'Give this project a name.',
        ]);
    });
});
